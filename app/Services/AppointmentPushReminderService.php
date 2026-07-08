<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Fires push notifications for upcoming appointments.
 *
 * Sends to ALL staff users AND to the appointment owner (if they have a portal account):
 *   • 24h reminder — once per appointment when it is 23–25 hours away
 *   •  1h reminder — once per appointment when it is 50–70 minutes away
 *
 * Idempotent: uses push_24h_sent / push_1h_sent flags to prevent duplicates.
 */
class AppointmentPushReminderService
{
    public function __construct(
        private readonly Database              $db,
        private readonly PushNotificationService $push
    ) {}

    public function sendPendingReminders(): array
    {
        $log = ['24h' => 0, '1h' => 0, 'errors' => []];

        try {
            $this->db->ensureColumn($this->db->prefix('appointments'), 'push_24h_sent', 'TINYINT(1) NOT NULL DEFAULT 0');
            $this->db->ensureColumn($this->db->prefix('appointments'), 'push_1h_sent',  'TINYINT(1) NOT NULL DEFAULT 0');
        } catch (\Throwable) {}

        $apt  = $this->db->prefix('appointments');
        $pat  = $this->db->prefix('patients');
        $own  = $this->db->prefix('owners');
        $usrs = $this->db->prefix('users');

        $pu = $this->db->prefix('owner_portal_users');

        // ── 24-hour reminder ─────────────────────────────────────────────────
        try {
            $rows24 = $this->db->fetchAll(
                "SELECT a.id, a.start_at, a.owner_id,
                        COALESCE(a.title, p.name, 'Termin') AS label,
                        CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                        pu.id AS portal_user_id
                 FROM `{$apt}` a
                 LEFT JOIN `{$pat}` p ON p.id = a.patient_id
                 LEFT JOIN `{$own}` o ON o.id = a.owner_id
                 LEFT JOIN `{$pu}` pu ON pu.owner_id = o.id AND pu.is_active = 1
                 WHERE a.push_24h_sent = 0
                   AND a.status NOT IN ('cancelled','noshow')
                   AND a.start_at BETWEEN DATE_ADD(NOW(), INTERVAL 23 HOUR)
                                      AND DATE_ADD(NOW(), INTERVAL 25 HOUR)"
            );

            foreach ($rows24 as $row) {
                try {
                    $time  = date('H:i', strtotime($row['start_at']));
                    $date  = date('d.m.Y', strtotime($row['start_at']));
                    $label = $row['label'];
                    $owner = $row['owner_name'] ? ' · ' . $row['owner_name'] : '';

                    $staffBody = "Morgen um {$time} Uhr: {$label}{$owner}";
                    $tenantId  = $this->push->currentTenantId();

                    $this->push->notifyAllTherapists(
                        $tenantId, 'appointment_booked_staff',
                        $staffBody,
                        ['screen' => 'calendar', 'appointment_id' => (int)$row['id'], 'date' => $date],
                        'appointment', (int)$row['id']
                    );

                    if (!empty($row['portal_user_id'])) {
                        $this->push->notifyOwner(
                            $tenantId, (int)$row['portal_user_id'],
                            'appointment_booked',
                            "Ihr Termin ist morgen um {$time} Uhr: {$label}",
                            ['screen' => 'calendar', 'appointment_id' => (int)$row['id']],
                            'appointment', (int)$row['id']
                        );
                    }

                    $this->db->execute(
                        "UPDATE `{$apt}` SET push_24h_sent = 1 WHERE id = ?",
                        [(int)$row['id']]
                    );
                    $log['24h']++;
                } catch (\Throwable $e) {
                    $log['errors'][] = '24h appt#' . $row['id'] . ': ' . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            $log['errors'][] = '24h query: ' . $e->getMessage();
        }

        // ── 1-hour reminder ──────────────────────────────────────────────────
        try {
            $rows1h = $this->db->fetchAll(
                "SELECT a.id, a.start_at, a.owner_id,
                        COALESCE(a.title, p.name, 'Termin') AS label,
                        CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                        pu.id AS portal_user_id
                 FROM `{$apt}` a
                 LEFT JOIN `{$pat}` p ON p.id = a.patient_id
                 LEFT JOIN `{$own}` o ON o.id = a.owner_id
                 LEFT JOIN `{$pu}` pu ON pu.owner_id = o.id AND pu.is_active = 1
                 WHERE a.push_1h_sent = 0
                   AND a.status NOT IN ('cancelled','noshow')
                   AND a.start_at BETWEEN DATE_ADD(NOW(), INTERVAL 50 MINUTE)
                                      AND DATE_ADD(NOW(), INTERVAL 70 MINUTE)"
            );

            foreach ($rows1h as $row) {
                try {
                    $time  = date('H:i', strtotime($row['start_at']));
                    $label = $row['label'];
                    $owner = $row['owner_name'] ? ' · ' . $row['owner_name'] : '';

                    $tenantId = $this->push->currentTenantId();

                    $this->push->notifyAllTherapists(
                        $tenantId, 'appointment_booked_staff',
                        "In 1 Stunde um {$time} Uhr: {$label}{$owner}",
                        ['screen' => 'calendar', 'appointment_id' => (int)$row['id']],
                        'appointment', (int)$row['id'],
                        'high'
                    );

                    if (!empty($row['portal_user_id'])) {
                        $this->push->notifyOwner(
                            $tenantId, (int)$row['portal_user_id'],
                            'appointment_booked',
                            "Ihr Termin beginnt in 1 Stunde um {$time} Uhr: {$label}",
                            ['screen' => 'calendar', 'appointment_id' => (int)$row['id']],
                            'appointment', (int)$row['id'],
                            'high'
                        );
                    }

                    $this->db->execute(
                        "UPDATE `{$apt}` SET push_1h_sent = 1 WHERE id = ?",
                        [(int)$row['id']]
                    );
                    $log['1h']++;
                } catch (\Throwable $e) {
                    $log['errors'][] = '1h appt#' . $row['id'] . ': ' . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            $log['errors'][] = '1h query: ' . $e->getMessage();
        }

        return $log;
    }
}
