<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * PushNotificationService — fires events to the push-thera Node.js server.
 *
 * Usage:
 *   $push = new PushNotificationService($config, $db);
 *   $push->dispatch($tenantId, 'new_invoice', 'invoice', $invoiceId, $recipients);
 *
 * The service is non-throwing by design: all errors are logged and silently
 * swallowed so a notification failure never breaks the main business flow.
 */
class PushNotificationService
{
    private string $serverUrl;
    private string $internalSecret;
    private bool $enabled;

    /** @var array<string,string> Notification type → safe title templates */
    private const TITLE_MAP = [
        // Owner
        'new_invoice'                  => 'Neue Rechnung verfügbar',
        'invoice_paid'                 => 'Rechnung bezahlt',
        'invoice_status_changed'       => 'Rechnungsstatus geändert',
        'new_homework'                 => 'Neue Hausaufgabe',
        'homework_updated'             => 'Hausaufgabe aktualisiert',
        'new_message'                  => 'Neue Nachricht erhalten',
        'appointment_booked'           => 'Neuer Termin erstellt',
        'appointment_changed'          => 'Termin wurde geändert',
        'appointment_cancelled'        => 'Termin abgesagt',
        'document_available'           => 'Dokument verfügbar',
        // Therapeut / Trainer
        'new_owner_registered'         => 'Neuer Besitzer registriert',
        'new_patient'                  => 'Neuer Patient angelegt',
        'new_message_staff'            => 'Neue Nachricht',
        'appointment_booked_staff'     => 'Neuer Termin',
        'appointment_cancelled_staff'  => 'Termin storniert',
        'appointment_changed_staff'    => 'Termin geändert',
        'invoice_paid_staff'           => 'Rechnung bezahlt',
        'homework_completed'           => 'Hausaufgabe erledigt',
        'owner_upload'                 => 'Datei hochgeladen',
        'system_warning'               => 'Systemwarnung',
        // SaaS Admin
        'saas_new_practice'            => 'Neue Praxis registriert',
        'saas_subscription_changed'    => 'Abo geändert',
        'saas_trial_expiring'          => 'Trial läuft bald ab',
        'saas_payment_failed'          => 'Zahlung fehlgeschlagen',
        'saas_system_error'            => 'Systemfehler',
        'saas_migration_error'         => 'Migration fehlgeschlagen',
    ];

    public function __construct(
        private readonly \App\Core\Config $config,
        private readonly Database $db
    ) {
        $this->serverUrl      = rtrim((string)($config->get('push.server_url') ?? ''), '/');
        $this->internalSecret = (string)($config->get('push.internal_secret') ?? '');
        $this->enabled        = $this->serverUrl !== '' && $this->internalSecret !== '';
    }

    /**
     * Dispatch a notification event to the push server.
     *
     * @param int    $tenantId
     * @param string $event          Notification type key (see TITLE_MAP)
     * @param string $sourceType     Entity type: 'invoice', 'homework', 'appointment', …
     * @param int|null $sourceId     Entity primary key
     * @param array  $recipients     [['userId' => int, 'role' => string, 'body' => string, 'dataJson' => array], …]
     * @param string $priority       'low' | 'normal' | 'high'
     */
    public function dispatch(
        int $tenantId,
        string $event,
        string $sourceType,
        ?int $sourceId,
        array $recipients,
        string $priority = 'normal'
    ): void {
        if (!$this->enabled || empty($recipients)) {
            return;
        }

        $title = self::TITLE_MAP[$event] ?? 'TheraPano Benachrichtigung';

        $payload = [
            'tenantId'   => $tenantId,
            'event'      => $event,
            'sourceType' => $sourceType,
            'sourceId'   => $sourceId,
            'recipients' => array_map(function (array $r) use ($title, $priority): array {
                return [
                    'userId'   => (int)$r['userId'],
                    'role'     => (string)$r['role'],
                    'title'    => $title,
                    'body'     => (string)($r['body'] ?? 'Eine Aktualisierung ist verfügbar.'),
                    'dataJson' => (array)($r['dataJson'] ?? []),
                    'priority' => (string)($r['priority'] ?? $priority),
                ];
            }, $recipients),
        ];

        $this->postAsync($this->serverUrl . '/internal/notify', $payload);
    }

    /**
     * Convenience: notify a single owner user.
     */
    public function notifyOwner(
        int $tenantId,
        int $ownerUserId,
        string $event,
        string $body,
        array $dataJson = [],
        string $sourceType = '',
        ?int $sourceId = null
    ): void {
        $this->dispatch($tenantId, $event, $sourceType, $sourceId, [[
            'userId'   => $ownerUserId,
            'role'     => 'owner',
            'body'     => $body,
            'dataJson' => $dataJson,
        ]]);
    }

    /**
     * Convenience: notify all therapist/trainer users for a tenant.
     */
    public function notifyAllTherapists(
        int $tenantId,
        string $event,
        string $body,
        array $dataJson = [],
        string $sourceType = '',
        ?int $sourceId = null
    ): void {
        $users = $this->loadTherapistUsers($tenantId);
        if (empty($users)) {
            return;
        }

        $recipients = array_map(fn(array $u) => [
            'userId'   => (int)$u['id'],
            'role'     => 'therapeut',
            'body'     => $body,
            'dataJson' => $dataJson,
        ], $users);

        $this->dispatch($tenantId, $event, $sourceType, $sourceId, $recipients);
    }

    /**
     * Notify all SaaS admins via the dedicated admin endpoint.
     */
    public function notifyAdmins(
        string $event,
        string $body,
        array $dataJson = [],
        string $priority = 'normal'
    ): void {
        if (!$this->enabled) {
            return;
        }

        $title = self::TITLE_MAP[$event] ?? 'TheraPano Admin';

        $payload = [
            'event'    => $event,
            'title'    => $title,
            'body'     => $body,
            'dataJson' => $dataJson,
            'priority' => $priority,
        ];

        $this->postAsync($this->serverUrl . '/internal/notify-admins', $payload);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Non-blocking HTTP POST — uses register_shutdown_function to send after
     * the main response is complete, so it never blocks the user.
     */
    private function postAsync(string $url, array $payload): void
    {
        $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $secret  = $this->internalSecret;

        register_shutdown_function(function () use ($url, $body, $secret): void {
            try {
                $this->httpPost($url, $body, $secret);
            } catch (\Throwable $e) {
                // Non-critical — log to error_log but never crash
                error_log('[PushNotificationService] postAsync failed: ' . $e->getMessage());
            }
        });
    }

    private function httpPost(string $url, string $body, string $secret): void
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($body),
                    'X-Internal-Secret: ' . $secret,
                    'Connection: close',
                ]),
                'content'       => $body,
                'timeout'       => 3,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            error_log('[PushNotificationService] HTTP call failed: ' . $url);
        }
    }

    /**
     * Load all active therapist/trainer users for a tenant.
     */
    private function loadTherapistUsers(int $tenantId): array
    {
        $prefix = "t_{$tenantId}_";
        try {
            return $this->db->fetchAll(
                "SELECT id FROM `{$prefix}users`
                 WHERE is_active = 1
                 ORDER BY id ASC
                 LIMIT 100"
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve the mobile_user_id for an owner (needed to send push to owner).
     */
    public function resolveOwnerUserId(int $tenantId, int $ownerId): ?int
    {
        $prefix = "t_{$tenantId}_";
        try {
            $row = $this->db->fetchOne(
                "SELECT mobile_user_id FROM `{$prefix}owners`
                 WHERE id = ? AND mobile_user_id IS NOT NULL",
                [$ownerId]
            );
            return $row ? (int)$row['mobile_user_id'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
