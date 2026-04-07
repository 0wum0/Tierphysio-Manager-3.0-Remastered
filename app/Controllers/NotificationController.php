<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;

class NotificationController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly Database $db
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    public function index(array $params = []): void
    {
        $notifications = [];

        /* ── Geburtstage heute ── */
        try {
            $birthdays = $this->db->fetchAll(
                "SELECT p.id, p.name, p.species, p.birth_date,
                        CONCAT(o.first_name, ' ', o.last_name) AS owner_name
                 FROM `{$this->t('patients')}` p
                 LEFT JOIN `{$this->t('owners')}` o ON o.id = p.owner_id
                 WHERE DATE_FORMAT(p.birth_date, '%m-%d') = DATE_FORMAT(NOW(), '%m-%d')
                   AND p.birth_date IS NOT NULL
                   AND p.status NOT IN ('verstorben','inaktiv')
                 ORDER BY p.name ASC
                 LIMIT 10"
            );
            foreach ($birthdays as $b) {
                $age  = (int)date('Y') - (int)date('Y', strtotime($b['birth_date']));
                $notifications[] = [
                    'id'      => 'bday_' . date('Ymd') . '_' . $b['id'],
                    'type'    => 'birthday',
                    'title'   => '🎂 Geburtstag heute!',
                    'message' => $b['name'] . ' wird ' . $age . ' Jahre alt' . ($b['owner_name'] ? ' · ' . $b['owner_name'] : ''),
                    'url'     => '/patienten/' . $b['id'],
                    'time'    => date('Y-m-d H:i:s'),
                    'color'   => 'birthday',
                ];
            }
        } catch (\Throwable) {}

        /* ── Neue Patientenanmeldungen (ungelesen, letzte 24h) ── */
        try {
            $intakes = $this->db->fetchAll(
                "SELECT id, patient_name, owner_first_name, owner_last_name, created_at
                 FROM `{$this->t('patient_intakes')}`
                 WHERE read_at IS NULL AND created_at >= NOW() - INTERVAL 24 HOUR
                 ORDER BY created_at DESC
                 LIMIT 5"
            );
            foreach ($intakes as $intake) {
                $notifications[] = [
                    'id'      => 'intake_' . $intake['id'],
                    'type'    => 'intake',
                    'title'   => 'Neue Anmeldung',
                    'message' => '🐾 ' . $intake['patient_name'] . ' — ' . $intake['owner_first_name'] . ' ' . $intake['owner_last_name'],
                    'url'     => '/eingangsmeldungen/' . $intake['id'],
                    'time'    => $intake['created_at'],
                    'color'   => 'primary',
                ];
            }
        } catch (\Throwable) {
            /* intake plugin not installed — skip */
        }

        /* ── Termine in den nächsten 60 Minuten ── */
        try {
            $appointments = $this->db->fetchAll(
                "SELECT a.id, a.title, a.start_at,
                        p.name AS patient_name,
                        CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                        tt.name AS treatment_name
                 FROM `{$this->t('appointments')}` a
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = a.patient_id
                 LEFT JOIN `{$this->t('owners')}` o ON o.id = a.owner_id
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 WHERE a.start_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 60 MINUTE)
                   AND a.status NOT IN ('cancelled','noshow')
                 ORDER BY a.start_at ASC
                 LIMIT 5"
            );
            foreach ($appointments as $appt) {
                $label   = $appt['patient_name'] ?? $appt['title'] ?? 'Termin';
                $sub     = trim(($appt['treatment_name'] ? $appt['treatment_name'] . ' · ' : '') . ($appt['owner_name'] ?? ''));
                $time    = date('H:i', strtotime($appt['start_at']));
                $notifications[] = [
                    'id'      => 'appt_' . $appt['id'],
                    'type'    => 'appointment',
                    'title'   => 'Termin in Kürze — ' . $time . ' Uhr',
                    'message' => '📅 ' . $label . ($sub ? ' · ' . $sub : ''),
                    'url'     => '/kalender',
                    'time'    => $appt['start_at'],
                    'color'   => 'warning',
                ];
            }
        } catch (\Throwable) {
            /* appointments table not available — skip */
        }

        $this->json($notifications);
    }
}
