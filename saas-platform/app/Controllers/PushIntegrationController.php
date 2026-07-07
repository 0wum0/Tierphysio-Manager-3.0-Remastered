<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Database;
use Saas\Core\Config;
use Saas\Repositories\AdminRepository;
use Saas\Repositories\SettingsRepository;

/**
 * Push Integration API — called exclusively by push-thera during pairing.
 *
 * Endpoints:
 *   POST /api/push/connect             — authenticate SaaS admin, return one-time integration token
 *   GET  /api/push/discovery           — return system discovery data (requires integration token)
 *   POST /api/push/register-integration — store back-registration data from push-thera
 *   GET  /api/push/status              — integration status check
 */
class PushIntegrationController extends Controller
{
    /** Token TTL in seconds (15 minutes) */
    private const TOKEN_TTL = 900;

    public function __construct(
        View                      $view,
        Session                   $session,
        private Config            $config,
        private Database          $db,
        private AdminRepository   $adminRepo,
        private SettingsRepository $settings
    ) {
        parent::__construct($view, $session);
    }

    /**
     * POST /api/push/connect
     * Body: { "email": "...", "password": "..." }
     *
     * Authenticates a SaaS admin and returns a short-lived integration token.
     * Credentials are NEVER stored — used only for this handshake.
     */
    public function connect(array $params = []): void
    {
        $input    = $this->getJsonInput();
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (!$email || !$password) {
            $this->json(['error' => 'E-Mail und Passwort erforderlich'], 400);
        }

        $admin = $this->adminRepo->findByEmail($email);

        if (!$admin || !password_verify($password, $admin['password'])) {
            // Consistent timing to prevent user enumeration
            password_verify('dummy', '$2y$12$invaliddummyhashfortimingequaliz');
            $this->json(['error' => 'Ungültige Anmeldedaten'], 401);
        }

        // Generate a short-lived integration token (never store the password)
        $token   = bin2hex(random_bytes(32));
        $expires = time() + self::TOKEN_TTL;

        // Store token in push_integration_tokens (transient, TTL-based)
        $this->db->execute(
            "INSERT INTO push_integration_tokens (token_hash, admin_id, admin_email, admin_role, expires_at)
             VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))
             ON DUPLICATE KEY UPDATE
               token_hash = VALUES(token_hash),
               admin_id   = VALUES(admin_id),
               expires_at = VALUES(expires_at)",
            [
                hash('sha256', $token),
                (int)$admin['id'],
                $admin['email'],
                $admin['role'],
                $expires,
            ]
        );

        $this->json([
            'token'      => $token,
            'expires_at' => $expires,
            'admin_name' => $admin['name'],
            'admin_role' => $admin['role'],
        ]);
    }

    /**
     * GET /api/push/discovery
     * Header: Authorization: Bearer <integration-token>
     *
     * Returns all configuration data push-thera needs to set itself up.
     */
    public function discovery(array $params = []): void
    {
        $admin = $this->requireIntegrationToken();

        $pushConfig = $this->config->get('push');
        $saasUrl    = $_SERVER['HTTP_HOST'] ?? 'therapano.de';

        $existing = $this->getExistingIntegration();

        // DB credentials — push-thera uses the same database
        $dbConfig = $this->config->get('db');

        // CORS origins — derive from platform URLs
        $appUrl      = rtrim($this->config->get('platform.app_url') ?? $this->config->get('app.url') ?? '', '/');
        $platformUrl = rtrim($this->config->get('platform.url') ?? '', '/');
        $corsOrigins = implode(',', array_filter(array_unique([$appUrl, $platformUrl])))
            ?: 'https://app.therapano.de,https://portal.therapano.de';

        $this->json([
            'saas_url'            => 'https://' . $saasUrl,
            'saas_name'           => 'TheraPano SaaS',
            'push_server_url'     => rtrim($pushConfig['server_url'] ?? '', '/'),
            'internal_secret_set' => !empty($pushConfig['internal_secret']),
            'existing_integration'=> $existing !== null,
            'existing_client_id'  => $existing['client_id'] ?? null,
            'admin_email'         => $admin['admin_email'],
            'admin_role'          => $admin['admin_role'],
            'cors_origins'        => $corsOrigins,
            'discovery_at'        => date('c'),
            // DB credentials for push-thera bootstrap (HTTPS + integration token protected)
            'db' => [
                'host'     => $dbConfig['host']     ?? 'localhost',
                'port'     => (int)($dbConfig['port'] ?? 3306),
                'database' => $dbConfig['database'] ?? '',
                'username' => $dbConfig['username'] ?? '',
                'password' => $dbConfig['password'] ?? '',
            ],
        ]);
    }

    /**
     * POST /api/push/register-integration
     * Header: Authorization: Bearer <integration-token>
     * Body: {
     *   "client_id": "...",
     *   "webhook_secret": "...",
     *   "internal_secret": "...",
     *   "vapid_public_key": "...",
     *   "push_server_url": "..."
     * }
     *
     * Called by push-thera after it has set itself up.
     * Stores integration reference data — never overwrites existing secrets.
     */
    public function registerIntegration(array $params = []): void
    {
        $admin = $this->requireIntegrationToken();
        $input = $this->getJsonInput();

        $clientId       = trim($input['client_id'] ?? '');
        $webhookSecret  = trim($input['webhook_secret'] ?? '');
        $internalSecret = trim($input['internal_secret'] ?? '');
        $vapidPublicKey = trim($input['vapid_public_key'] ?? '');
        $pushServerUrl  = trim($input['push_server_url'] ?? '');

        if (!$clientId || !$webhookSecret || !$internalSecret || !$pushServerUrl) {
            $this->json(['error' => 'Pflichtfelder fehlen: client_id, webhook_secret, internal_secret, push_server_url'], 400);
        }

        $existing = $this->getExistingIntegration();

        if ($existing !== null) {
            // Update only non-secret fields — never overwrite existing secrets
            $this->db->execute(
                "UPDATE push_integration
                 SET push_server_url  = ?,
                     vapid_public_key = COALESCE(NULLIF(?, ''), vapid_public_key),
                     integration_status = 'connected',
                     last_synced_at   = NOW()
                 WHERE id = ?",
                [$pushServerUrl, $vapidPublicKey, (int)$existing['id']]
            );
        } else {
            // First registration — store hashed secrets
            $this->db->execute(
                "INSERT INTO push_integration
                 (push_server_url, client_id, webhook_secret_hash, internal_secret, vapid_public_key, integration_status, connected_at)
                 VALUES (?, ?, ?, ?, ?, 'connected', NOW())",
                [
                    $pushServerUrl,
                    $clientId,
                    hash('sha256', $webhookSecret),
                    hash('sha256', $internalSecret),
                    $vapidPublicKey ?: null,
                ]
            );
        }

        // Write push settings to saas_settings so tenant app and portal can auto-discover
        $this->savePushSettings($pushServerUrl, $vapidPublicKey, $internalSecret);

        // Invalidate the used token
        $this->invalidateToken($admin['token_hash']);

        $this->json([
            'success'    => true,
            'message'    => 'Integration erfolgreich registriert',
            'client_id'  => $clientId,
            'synced_at'  => date('c'),
        ]);
    }

    /**
     * GET /api/push/status
     * Header: Authorization: Bearer <integration-token>  OR  no auth (returns minimal public status)
     */
    public function status(array $params = []): void
    {
        $existing = $this->getExistingIntegration();

        $this->json([
            'integrated'        => $existing !== null,
            'status'            => $existing['integration_status'] ?? 'disconnected',
            'push_server_url'   => $existing['push_server_url'] ?? null,
            'last_synced_at'    => $existing['last_synced_at'] ?? null,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function requireIntegrationToken(): array
    {
        $bearer = $this->getBearerToken();

        if (!$bearer) {
            $this->json(['error' => 'Integration token erforderlich'], 401);
        }

        $hash = hash('sha256', $bearer);

        try {
            $row = $this->db->fetch(
                "SELECT * FROM push_integration_tokens WHERE token_hash = ? AND expires_at > NOW()",
                [$hash]
            );
        } catch (\Throwable) {
            $this->json(['error' => 'Token-Validierung fehlgeschlagen'], 500);
        }

        if (!$row) {
            $this->json(['error' => 'Ungültiger oder abgelaufener Token'], 401);
        }

        $row['token_hash'] = $hash;
        return $row;
    }

    private function invalidateToken(string $tokenHash): void
    {
        try {
            $this->db->execute("DELETE FROM push_integration_tokens WHERE token_hash = ?", [$tokenHash]);
        } catch (\Throwable) {}
    }

    private function getExistingIntegration(): array|false
    {
        try {
            return $this->db->fetch(
                "SELECT * FROM push_integration ORDER BY connected_at DESC LIMIT 1"
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Write push configuration to saas_settings so the tenant app and owner portal
     * can discover push-thera automatically without manual .env setup.
     * The internal_secret is stored in plaintext here (server-side only) so PHP
     * can sign short-lived JWTs for browser clients.
     */
    private function savePushSettings(string $url, string $vapid, string $secret): void
    {
        $rows = [
            ['push_server_url',            rtrim($url, '/')],
            ['push_vapid_public_key',      $vapid],
            ['push_internal_secret_plain', $secret],
            ['push_enabled',               '1'],
        ];
        foreach ($rows as [$key, $value]) {
            if ($value === '') continue;
            try {
                $this->db->execute(
                    "INSERT INTO saas_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                    [$key, $value]
                );
            } catch (\Throwable) {}
        }
    }

    private function getBearerToken(): string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return '';
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
