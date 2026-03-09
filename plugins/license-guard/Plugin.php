<?php

declare(strict_types=1);

namespace Plugins\LicenseGuard;

use App\Core\Container;
use App\Core\Database;
use App\Core\Session;

/**
 * LicenseGuard Plugin
 *
 * Non-invasive SaaS license checker for the Tierphysio Manager.
 * - Checks license online every 24 hours
 * - Stores token + last-check timestamp in DB settings
 * - Allows offline use up to 30 days before blocking
 * - Exposes active features to the app via session / global
 */
class Plugin
{
    private const CACHE_HOURS    = 24;
    private const OFFLINE_DAYS   = 30;
    private const SETTINGS_TOKEN = 'license_token';
    private const SETTINGS_UUID  = 'tenant_uuid';
    private const SETTINGS_CHECK = 'license_checked_at';
    private const SETTINGS_DATA  = 'license_data_cache';
    private const SETTINGS_URL   = 'saas_url';

    private Database $db;
    private array    $licenseData = [];
    private string   $status      = 'unknown';

    public function boot(Container $container): void
    {
        $this->db = $container->get(Database::class);
        $this->checkLicense($container);
    }

    private function checkLicense(Container $container): void
    {
        $token      = $this->getSetting(self::SETTINGS_TOKEN);
        $uuid       = $this->getSetting(self::SETTINGS_UUID);
        $saasUrl    = $this->getSetting(self::SETTINGS_URL);
        $checkedAt  = $this->getSetting(self::SETTINGS_CHECK);
        $cachedData = $this->getSetting(self::SETTINGS_DATA);

        // No license configured → run in unconfigured mode (demo/trial)
        if (!$uuid && !$token) {
            $this->status      = 'unconfigured';
            $this->licenseData = $this->defaultFeatures();
            $this->applyToContext($container);
            return;
        }

        $needsCheck = !$checkedAt
            || (time() - (int)$checkedAt) > (self::CACHE_HOURS * 3600);

        if (!$needsCheck && $cachedData) {
            // Use cached data
            $decoded = json_decode($cachedData, true);
            if (is_array($decoded)) {
                $this->licenseData = $decoded;
                $this->status      = $decoded['valid'] ? 'active' : 'invalid';
                $this->applyToContext($container);
                return;
            }
        }

        // Try online check
        if ($saasUrl && $uuid) {
            $result = $this->onlineCheck($saasUrl, $uuid, $token);
            if ($result !== null) {
                $this->licenseData = $result;
                $this->status      = $result['valid'] ? 'active' : 'invalid';
                $this->saveSetting(self::SETTINGS_CHECK, (string)time());
                $this->saveSetting(self::SETTINGS_DATA, json_encode($result));

                // Save fresh token if provided
                if (!empty($result['token'])) {
                    $this->saveSetting(self::SETTINGS_TOKEN, $result['token']);
                }

                $this->applyToContext($container);
                return;
            }
        }

        // Online check failed — use offline mode
        $offlineDays = self::OFFLINE_DAYS;
        if ($checkedAt) {
            $daysSince = (time() - (int)$checkedAt) / 86400;
            if ($daysSince > $offlineDays) {
                // Grace period expired
                $this->status      = 'expired_offline';
                $this->licenseData = ['valid' => false, 'features' => []];
                $this->applyToContext($container);
                $this->enforceRestrictions($container);
                return;
            }
        }

        // Within grace period — use cached data
        if ($cachedData) {
            $decoded = json_decode($cachedData, true);
            if (is_array($decoded) && $decoded['valid']) {
                $this->licenseData = $decoded;
                $this->status      = 'offline_grace';
                $this->applyToContext($container);
                return;
            }
        }

        // No usable data at all — restrict
        $this->status      = 'restricted';
        $this->licenseData = ['valid' => false, 'features' => []];
        $this->applyToContext($container);
        $this->enforceRestrictions($container);
    }

    private function onlineCheck(string $saasUrl, string $uuid, string $token): ?array
    {
        $url = rtrim($saasUrl, '/') . '/api/license/check?uuid=' . urlencode($uuid);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 5,
                'header'  => "Accept: application/json\r\nUser-Agent: TierphysioManager/3.0\r\n",
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $response = @file_get_contents($url, false, $ctx);
            if ($response === false) {
                return null;
            }
            $data = json_decode($response, true);
            if (!is_array($data)) {
                return null;
            }
            return $data;
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyToContext(Container $container): void
    {
        $view    = $container->get(\App\Core\View::class);
        $session = $container->get(Session::class);

        $features  = $this->licenseData['features']  ?? [];
        $plan      = $this->licenseData['plan']       ?? 'unknown';
        $maxUsers  = $this->licenseData['max_users']  ?? 1;

        $view->addGlobal('license_status',   $this->status);
        $view->addGlobal('license_plan',     $plan);
        $view->addGlobal('license_features', $features);
        $view->addGlobal('license_valid',    in_array($this->status, ['active', 'offline_grace', 'unconfigured'], true));
        $view->addGlobal('license_max_users',$maxUsers);

        $session->set('license_status',   $this->status);
        $session->set('license_features', $features);
        $session->set('license_plan',     $plan);
        $session->set('license_max_users',$maxUsers);
    }

    private function enforceRestrictions(Container $container): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Allow: login, logout, assets, license setup
        $allowed = ['/login', '/logout', '/install', '/license-setup'];
        foreach ($allowed as $path) {
            if (str_starts_with($uri, $path)) {
                return;
            }
        }

        // Allow static assets
        if (preg_match('/\.(css|js|png|jpg|gif|ico|woff|woff2|svg)$/i', $uri)) {
            return;
        }

        // Show restriction notice instead of blocking hard
        // (allows admin to fix the license key)
        $session = $container->get(Session::class);
        $session->flash('error',
            'Ihre Lizenz ist abgelaufen oder konnte nicht geprüft werden. ' .
            'Bitte stellen Sie eine Internetverbindung her oder kontaktieren Sie den Support. ' .
            'Offline-Nutzung war ' . self::OFFLINE_DAYS . ' Tage möglich.'
        );
    }

    private function defaultFeatures(): array
    {
        return [
            'valid'     => true,
            'plan'      => 'basic',
            'features'  => ['patients', 'owners', 'appointments', 'invoices'],
            'max_users' => 1,
        ];
    }

    private function getSetting(string $key): string
    {
        try {
            $result = $this->db->fetchColumn(
                "SELECT `value` FROM settings WHERE `key` = ?",
                [$key]
            );
            return $result !== false ? (string)$result : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function saveSetting(string $key, string $value): void
    {
        try {
            $this->db->execute(
                "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = ?",
                [$key, $value, $value]
            );
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Called by PluginManager to check if a feature is enabled.
     * Other plugins/controllers can call: $pluginManager->call('license-guard', 'hasFeature', 'reports')
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->licenseData['features'] ?? [];
        return in_array($feature, $features, true);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPlan(): string
    {
        return $this->licenseData['plan'] ?? 'unknown';
    }
}
