<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Config;
use Saas\Core\Database;

/**
 * PushAdminNotificationService — SaaS-seitige Push-Anbindung.
 *
 * Sendet Admin-Notifications an den push-thera Node.js Server.
 * Gilt für globale SaaS-Ereignisse: neue Praxis, Abo-Änderung, Trial-Ablauf, etc.
 * Nicht werfend — Fehler werden ins error_log geschrieben.
 */
class PushAdminNotificationService
{
    private string $serverUrl;
    private string $internalSecret;
    private bool $enabled;

    private const TITLE_MAP = [
        'saas_new_practice'         => 'Neue Praxis registriert',
        'saas_new_registration'     => 'Neue Registrierung',
        'saas_subscription_changed' => 'Abo geändert',
        'saas_trial_expiring'       => 'Trial läuft bald ab',
        'saas_payment_failed'       => 'Zahlung fehlgeschlagen',
        'saas_new_invoice'          => 'Neue Rechnung',
        'saas_system_error'         => 'Systemfehler',
        'saas_migration_error'      => 'Migration fehlgeschlagen',
        'saas_tenant_suspended'     => 'Praxis gesperrt',
        'saas_tenant_activated'     => 'Praxis aktiviert',
        'saas_feedback'             => 'Neues Feedback',
        'saas_update_available'     => 'Update verfügbar',
    ];

    public function __construct(Config $config, Database $db)
    {
        $this->serverUrl      = rtrim((string)($config->get('push.server_url') ?? ''), '/');
        $this->internalSecret = (string)($config->get('push.internal_secret') ?? '');

        // Fallback: nach dem Pairing-Wizard stehen die Verbindungsdaten in
        // saas_settings — .env-Einträge sind dann nicht mehr nötig
        if ($this->serverUrl === '' || $this->internalSecret === '') {
            try {
                $rows = $db->fetchAll(
                    "SELECT `key`, `value` FROM saas_settings
                     WHERE `key` IN ('push_server_url','push_internal_secret_plain','push_enabled')"
                );
                $ps = [];
                foreach ($rows as $row) {
                    $ps[$row['key']] = $row['value'];
                }
                if (($ps['push_enabled'] ?? '0') === '1') {
                    if ($this->serverUrl === '') {
                        $this->serverUrl = rtrim((string)($ps['push_server_url'] ?? ''), '/');
                    }
                    if ($this->internalSecret === '') {
                        $this->internalSecret = (string)($ps['push_internal_secret_plain'] ?? '');
                    }
                }
            } catch (\Throwable) {
                // saas_settings nicht verfügbar — Push bleibt deaktiviert
            }
        }

        $this->enabled = $this->serverUrl !== '' && $this->internalSecret !== '';
    }

    /**
     * Send notification to all SaaS admins.
     *
     * @param string $event     One of the keys in TITLE_MAP
     * @param string $body      Safe notification body text
     * @param array  $dataJson  Navigation data for deep links
     * @param string $priority  'low' | 'normal' | 'high'
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

    /**
     * Send a notification to a single tenant user (e.g. feedback reply
     * to the practice user who submitted it).
     *
     * @param string $tenantPrefix Tabellen-Prefix des Tenants (z.B. "t_abc123_") —
     *                             wird zur push-Tenant-ID (crc32) aufgelöst,
     *                             konsistent mit den Browser-JWTs der Praxis-App.
     */
    public function notifyTenantUser(
        string $tenantPrefix,
        int $userId,
        string $event,
        string $title,
        string $body,
        array $dataJson = [],
        string $priority = 'normal',
        string $role = 'therapeut'
    ): void {
        if (!$this->enabled || $tenantPrefix === '' || $userId <= 0) {
            return;
        }

        $payload = [
            'tenantId'   => abs(crc32($tenantPrefix)),
            'event'      => $event,
            'sourceType' => 'feedback',
            'sourceId'   => null,
            'recipients' => [[
                'userId'   => $userId,
                'role'     => $role,
                'title'    => $title,
                'body'     => $body,
                'dataJson' => $dataJson,
                'priority' => $priority,
            ]],
        ];

        $this->postAsync($this->serverUrl . '/internal/notify', $payload);
    }

    private function postAsync(string $url, array $payload): void
    {
        $body   = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $secret = $this->internalSecret;

        register_shutdown_function(function () use ($url, $body, $secret): void {
            try {
                $this->httpPost($url, $body, $secret);
            } catch (\Throwable $e) {
                error_log('[PushAdminNotificationService] postAsync failed: ' . $e->getMessage());
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

        @file_get_contents($url, false, $context);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
