<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Database;
use Saas\Core\ErrorLogger;
use Saas\Core\StructuredLogger;
use Saas\Services\MigrationService;

/**
 * Self-healing orchestrator (SaaS platform).
 *
 * Detects and auto-repairs common tenant data problems.
 * Operates on ANY tenant by accepting the table prefix.
 *
 * Repairs:
 *  - Missing settings  → insert defaults (NEVER overwrites existing)
 *  - Missing storage directories → recreate under STORAGE_PATH/tenants/
 *
 * Feature #13: Self-Healing Missing Data
 */
class SelfHealingService
{
    private const MAX_MIGRATION_VERSION = 48;

    private const DEFAULT_SETTINGS = [
        'timezone'          => 'Europe/Berlin',
        'language'          => 'de',
        'currency'          => 'EUR',
        'date_format'       => 'd.m.Y',
        'time_format'       => 'H:i',
        'invoice_prefix'    => 'RE-',
        'invoice_start'     => '1000',
        'reminder_days'     => '3',
        'mail_from_name'    => '',
        'mail_from_address' => '',
    ];

    private const STORAGE_DIRS = [
        'patients',
        'uploads',
        'vet-reports',
        'intake',
        'invoices',
        'exports',
    ];

    private const CRON_TOKEN_KEYS = [
        'cron_dispatcher_token',
        'birthday_cron_token',
        'calendar_cron_secret',
        'google_sync_cron_secret',
        'tcp_cron_token',
        'cron_secret',
    ];

    public function __construct(
        private readonly Database         $db,
        private readonly ?MigrationService $migrationService = null
    ) {}

    /**
     * Run all self-healing for one tenant.
     *
     * @param  string $prefix  Table prefix, e.g. "t_therapano_2eff77_"
     * @param  string $tid     Human-readable ID for logging
     * @return array{tid: string, healed: list<string>, failed: list<string>}
     */
    public function healAll(string $prefix, string $tid = ''): array
    {
        $report = ['tid' => $tid, 'healed' => [], 'failed' => []];

        $parts = [
            $this->healSettings($prefix, $tid),
            $this->healCronTokens($prefix, $tid),
            $this->healStorageDirs($prefix, $tid),
            $this->healMigrations($prefix, $tid),
        ];

        foreach ($parts as $part) {
            $report['healed'] = array_merge($report['healed'], $part['healed']);
            $report['failed'] = array_merge($report['failed'], $part['failed']);
        }

        if (!empty($report['healed'])) {
            StructuredLogger::system('self_heal.completed', 'ok', $tid, [
                'healed' => count($report['healed']),
                'items'  => implode(', ', $report['healed']),
            ]);
        }
        if (!empty($report['failed'])) {
            StructuredLogger::system('self_heal.partial_failure', 'warning', $tid, [
                'failed' => count($report['failed']),
                'items'  => implode(', ', $report['failed']),
            ]);
        }

        return $report;
    }

    /**
     * Ensure all default settings exist. NEVER overwrites existing non-empty values.
     *
     * @return array{healed: list<string>, failed: list<string>}
     */
    public function healSettings(string $prefix, string $tid = ''): array
    {
        $healed = [];
        $failed = [];
        $table  = $prefix . 'settings';

        foreach (self::DEFAULT_SETTINGS as $key => $default) {
            try {
                $row = $this->db->fetch(
                    "SELECT `value` FROM `{$table}` WHERE `key` = ?",
                    [$key]
                );
                $existing = ($row !== false) ? $row['value'] : null;

                if ($existing === null || $existing === '') {
                    $this->db->execute(
                        "INSERT INTO `{$table}` (`key`, `value`) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE `value` = IF(`value` = '' OR `value` IS NULL, VALUES(`value`), `value`)",
                        [$key, (string)$default]
                    );
                    $healed[] = "setting:{$key}";
                    StructuredLogger::system('setting.healed', 'ok', $tid, ['key' => $key]);
                }
            } catch (\Throwable $e) {
                $failed[] = "setting:{$key}";
                ErrorLogger::log("SelfHeal: setting '{$key}' failed: " . $e->getMessage(), '', 0, $tid);
            }
        }

        return compact('healed', 'failed');
    }

    /**
     * Ensure all 6 cron tokens exist. NEVER overwrites existing non-empty values.
     *
     * @return array{healed: list<string>, failed: list<string>}
     */
    public function healCronTokens(string $prefix, string $tid = ''): array
    {
        $healed = [];
        $failed = [];
        $table  = $prefix . 'settings';

        foreach (self::CRON_TOKEN_KEYS as $key) {
            try {
                $row = $this->db->fetch(
                    "SELECT `value` FROM `{$table}` WHERE `key` = ?",
                    [$key]
                );
                $existing = ($row !== false) ? $row['value'] : null;

                if ($existing === null || $existing === '') {
                    $token = bin2hex(random_bytes(32));
                    $this->db->execute(
                        "INSERT INTO `{$table}` (`key`, `value`) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE `value` = IF(`value` = '' OR `value` IS NULL, VALUES(`value`), `value`)",
                        [$key, $token]
                    );
                    $healed[] = "cron_token:{$key}";
                    StructuredLogger::system('cron_token.healed', 'ok', $tid, ['key' => $key]);
                }
            } catch (\Throwable $e) {
                $failed[] = "cron_token:{$key}";
                ErrorLogger::log("SelfHeal: cron token '{$key}' failed: " . $e->getMessage(), '', 0, $tid);
            }
        }

        return compact('healed', 'failed');
    }

    /**
     * Run any pending tenant migrations (db version < MAX_MIGRATION_VERSION).
     *
     * @return array{healed: list<string>, failed: list<string>}
     */
    public function healMigrations(string $prefix, string $tid = ''): array
    {
        $healed = [];
        $failed = [];

        if ($this->migrationService === null) {
            return compact('healed', 'failed');
        }

        try {
            $currentVersion = $this->migrationService->getTenantVersion($prefix);
            $latestVersion  = $this->migrationService->getLatestVersion();

            if ($currentVersion < $latestVersion) {
                $result = $this->migrationService->migrateTenant($prefix);
                if ($result['ran_count'] > 0) {
                    $healed[] = "migrations:v{$result['from']}→v{$result['to']} ({$result['ran_count']} ran)";
                    StructuredLogger::system('migrations.healed', 'ok', $tid, [
                        'from'      => $result['from'],
                        'to'        => $result['to'],
                        'ran_count' => $result['ran_count'],
                    ]);
                }
                if (!$result['success']) {
                    $failed[] = "migrations:v{$result['from']}→v{$result['to']} (errors)";
                }
            }
        } catch (\Throwable $e) {
            $failed[] = 'migrations:' . $e->getMessage();
            ErrorLogger::log('SelfHeal: migrations failed: ' . $e->getMessage(), '', 0, $tid);
        }

        return compact('healed', 'failed');
    }

    /**
     * Ensure all required tenant storage directories exist.
     *
     * @return array{healed: list<string>, failed: list<string>}
     */
    public function healStorageDirs(string $prefix, string $tid = ''): array
    {
        $healed = [];
        $failed = [];

        $base = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 3) . '/storage';
        $slug = rtrim($prefix, '_');          // "t_abc123_" → "t_abc123"
        $root = $base . '/tenants/' . $slug;

        // Ensure root first
        if (!is_dir($root)) {
            @mkdir($root, 0755, true);
        }

        foreach (self::STORAGE_DIRS as $dir) {
            try {
                $path = $root . '/' . $dir;
                if (is_dir($path)) {
                    continue;
                }
                if (@mkdir($path, 0755, true) || is_dir($path)) {
                    $healed[] = "storage:{$dir}";
                    StructuredLogger::system('storage.healed', 'ok', $tid, ['dir' => $dir]);
                } else {
                    $failed[] = "storage:{$dir}";
                    ErrorLogger::log("SelfHeal: mkdir failed for '{$path}'", '', 0, $tid);
                }
            } catch (\Throwable $e) {
                $failed[] = "storage:{$dir}";
                ErrorLogger::log("SelfHeal: storage error '{$dir}': " . $e->getMessage(), '', 0, $tid);
            }
        }

        return compact('healed', 'failed');
    }
}
