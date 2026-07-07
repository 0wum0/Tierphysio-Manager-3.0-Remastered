<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Config;

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
        'saas_subscription_changed' => 'Abo geändert',
        'saas_trial_expiring'       => 'Trial läuft bald ab',
        'saas_payment_failed'       => 'Zahlung fehlgeschlagen',
        'saas_system_error'         => 'Systemfehler',
        'saas_migration_error'      => 'Migration fehlgeschlagen',
        'saas_tenant_suspended'     => 'Praxis gesperrt',
        'saas_tenant_activated'     => 'Praxis aktiviert',
    ];

    public function __construct(Config $config)
    {
        $this->serverUrl      = rtrim((string)($config->get('push.server_url') ?? ''), '/');
        $this->internalSecret = (string)($config->get('push.internal_secret') ?? '');
        $this->enabled        = $this->serverUrl !== '' && $this->internalSecret !== '';
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
