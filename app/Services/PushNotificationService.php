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
        // Besitzer (Hundeschule / Pakete / Trainings)
        'new_package'                  => 'Neues Paket verfügbar',
        'new_training'                 => 'Neues Training',
        'training_updated'             => 'Training aktualisiert',
        // Feedback (Praxis ↔ SaaS)
        'new_feedback'                 => 'Neues Feedback',
        'feedback_reply'               => 'Antwort auf dein Feedback',
        // SaaS Admin
        'saas_new_practice'            => 'Neue Praxis registriert',
        'saas_new_registration'        => 'Neue Registrierung',
        'saas_new_invoice'             => 'Neue Rechnung',
        'saas_feedback'                => 'Neues Feedback',
        'saas_update_available'        => 'Update verfügbar',
        'saas_subscription_changed'    => 'Abo geändert',
        'saas_trial_expiring'          => 'Trial läuft bald ab',
        'saas_payment_failed'          => 'Zahlung fehlgeschlagen',
        'saas_system_error'            => 'Systemfehler',
        'saas_migration_error'         => 'Migration fehlgeschlagen',
        // Geburtstage
        'birthday_today'               => 'Geburtstag heute',
        // Überfällige Rechnungen (Besitzerseite)
        'invoice_overdue'              => 'Rechnung überfällig',
    ];

    /** @var array<string,string>|null Request-weiter Cache der push_* saas_settings */
    private static ?array $settingsCache = null;

    public function __construct(
        private readonly \App\Core\Config $config,
        private readonly Database $db
    ) {
        $this->serverUrl      = rtrim((string)($config->get('push.server_url') ?? ''), '/');
        $this->internalSecret = (string)($config->get('push.internal_secret') ?? '');

        // Fallback: wenn .env nicht gesetzt, aus saas_settings lesen (nach Pairing automatisch befüllt)
        if ($this->serverUrl === '' || $this->internalSecret === '') {
            $ps = self::loadPushSettings($config, $db);
            if (($ps['push_enabled'] ?? '0') === '1') {
                if ($this->serverUrl === '') {
                    $this->serverUrl = rtrim((string)($ps['push_server_url'] ?? ''), '/');
                }
                if ($this->internalSecret === '') {
                    $this->internalSecret = (string)($ps['push_internal_secret_plain'] ?? '');
                }
            }
        }

        $this->enabled = $this->serverUrl !== '' && $this->internalSecret !== '';
    }

    /**
     * Lädt alle push_* Einträge aus saas_settings.
     *
     * WICHTIG: saas_settings liegt in der SaaS-Datenbank (config saas_db.*),
     * NICHT zwingend in der Tenant-DB der Praxis-App. Bei getrennten
     * Datenbanken schlägt die Abfrage über die App-Verbindung fehl — dann
     * wird direkt die SaaS-DB angesprochen (gleiches Muster wie
     * FeedbackController::getSaasDb()). Ergebnis wird pro Request gecacht.
     *
     * @return array<string,string>
     */
    public static function loadPushSettings(\App\Core\Config $config, Database $db): array
    {
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }

        $ps = [];

        // 1) Shared-DB-Installation: saas_settings existiert in derselben DB
        try {
            $rows = $db->fetchAll(
                "SELECT `key`, `value` FROM `saas_settings` WHERE `key` LIKE 'push_%'"
            );
            foreach ($rows as $row) {
                $ps[$row['key']] = (string)$row['value'];
            }
        } catch (\Throwable) {
            // Tabelle nicht in der App-DB — unten über die SaaS-DB versuchen
        }

        // 2) Getrennte Datenbanken: direkt die SaaS-DB abfragen
        if ($ps === []) {
            $saasDbName = (string)($config->get('saas_db.database') ?? '');
            if ($saasDbName !== '') {
                try {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                        $config->get('saas_db.host', 'localhost'),
                        (int)$config->get('saas_db.port', 3306),
                        $saasDbName
                    );
                    $pdo = new \PDO($dsn, $config->get('saas_db.username'), $config->get('saas_db.password'), [
                        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_TIMEOUT            => 3,
                    ]);
                    $stmt = $pdo->query("SELECT `key`, `value` FROM `saas_settings` WHERE `key` LIKE 'push_%'");
                    foreach ($stmt->fetchAll() as $row) {
                        $ps[$row['key']] = (string)$row['value'];
                    }
                } catch (\Throwable $e) {
                    error_log('[PushNotificationService] SaaS-DB saas_settings nicht erreichbar: ' . $e->getMessage());
                }
            }
        }

        self::$settingsCache = $ps;
        return $ps;
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
        ?int $sourceId = null,
        string $priority = 'normal'
    ): void {
        $this->dispatch($tenantId, $event, $sourceType, $sourceId, [[
            'userId'   => $ownerUserId,
            'role'     => 'owner',
            'body'     => $body,
            'dataJson' => $dataJson,
            'priority' => $priority,
        ]]);
    }

    /**
     * Convenience: notify all therapist/trainer users for a tenant.
     * tenantId param is kept for backward compat; internally the DB prefix is used.
     */
    public function notifyAllTherapists(
        int $tenantId,
        string $event,
        string $body,
        array $dataJson = [],
        string $sourceType = '',
        ?int $sourceId = null,
        string $priority = 'normal'
    ): void {
        $realTenantId = $this->currentTenantId();
        $users = $this->loadTherapistUsers();
        if (empty($users)) {
            return;
        }

        $recipients = array_map(fn(array $u) => [
            'userId'   => (int)$u['id'],
            'role'     => 'therapeut',
            'body'     => $body,
            'dataJson' => $dataJson,
            'priority' => $priority,
        ], $users);

        $this->dispatch($realTenantId, $event, $sourceType, $sourceId, $recipients, $priority);
    }

    /**
     * Convenience: notify all owners with an active portal account
     * (e.g. neues kaufbares Paket, neue Trainings-Termine der Hundeschule).
     */
    public function notifyAllOwners(
        string $event,
        string $body,
        array $dataJson = [],
        string $sourceType = '',
        ?int $sourceId = null,
        string $priority = 'normal'
    ): void {
        if (!$this->enabled) {
            return;
        }

        try {
            $users = $this->db->fetchAll(
                "SELECT id FROM `{$this->db->prefix('owner_portal_users')}`
                 WHERE is_active = 1
                 ORDER BY id ASC
                 LIMIT 500"
            );
        } catch (\Throwable) {
            return;
        }
        if (empty($users)) {
            return;
        }

        $recipients = array_map(fn(array $u) => [
            'userId'   => (int)$u['id'],
            'role'     => 'owner',
            'body'     => $body,
            'dataJson' => $dataJson,
            'priority' => $priority,
        ], $users);

        $this->dispatch($this->currentTenantId(), $event, $sourceType, $sourceId, $recipients, $priority);
    }

    /**
     * Returns the tenant ID derived from the current DB prefix.
     * Consistent with what Application.php generates for push JWTs.
     */
    public function currentTenantId(): int
    {
        return abs(crc32($this->db->getPrefix()));
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
    private function loadTherapistUsers(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT id FROM `{$this->db->prefix('users')}`
                 WHERE is_active = 1
                 ORDER BY id ASC
                 LIMIT 100"
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve the portal user id for an owner (needed to send push to owner).
     *
     * Der Portal-Browser-JWT trägt owner_portal_users.id als user_id — Push an
     * Besitzer muss daher an genau diese ID adressiert werden. Der Tenant wird
     * über den AKTUELLEN DB-Prefix aufgelöst (der Parameter $tenantId bleibt
     * nur für Rückwärtskompatibilität der Signatur erhalten).
     */
    public function resolveOwnerUserId(int $tenantId, int $ownerId): ?int
    {
        try {
            $row = $this->db->fetchOne(
                "SELECT id FROM `{$this->db->prefix('owner_portal_users')}`
                 WHERE owner_id = ? AND is_active = 1
                 ORDER BY id ASC LIMIT 1",
                [$ownerId]
            );
            return $row ? (int)$row['id'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
