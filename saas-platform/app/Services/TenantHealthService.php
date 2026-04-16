<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Database;
use Saas\Core\ErrorLogger;
use Saas\Core\StructuredLogger;

/**
 * Global tenant health check service (SaaS platform).
 *
 * Runs against ANY tenant by accepting the tenant's DB prefix.
 * The SaaS platform DB has access to all tenant-prefixed tables
 * in the same database (shared-DB multi-tenancy pattern).
 *
 * Checks per tenant:
 *  - DB connection alive
 *  - {prefix}settings table exists
 *  - Required cron/system setting keys present and non-empty
 *  - Tenant storage directory present (auto-creates if missing)
 *
 * Feature #2: Global Tenant Health Check System
 */
class TenantHealthService
{
    private const REQUIRED_KEYS = [
        'cron_dispatcher_token',
        'birthday_cron_token',
        'calendar_cron_secret',
        'tcp_cron_token',
        'cron_secret',
    ];

    public function __construct(private readonly Database $db) {}

    /**
     * Run a full health check for one tenant.
     *
     * @param  string $prefix  Table prefix, e.g. "t_therapano_2eff77_"
     * @param  string $tid     Human-readable tenant ID for logs
     * @return array{tid: string, status: string, checks: array, issues: list<string>}
     */
    public function check(string $prefix, string $tid = ''): array
    {
        $result = ['tid' => $tid, 'status' => 'ok', 'checks' => [], 'issues' => []];

        // 1. DB connection
        $dbCheck = $this->checkDbConnection();
        $result['checks']['db_connection'] = $dbCheck;
        if (!$dbCheck['ok']) {
            $result['status'] = 'critical';
            $result['issues'][] = 'DB connection failed';
        }

        // 2. Settings table
        $tableCheck = $this->checkSettingsTable($prefix);
        $result['checks']['settings_table'] = $tableCheck;
        if (!$tableCheck['ok']) {
            $result['status'] = 'critical';
            $result['issues'][] = 'Settings table missing: ' . ($prefix . 'settings');
        }

        // 3. Required keys (only if table exists)
        if ($tableCheck['ok']) {
            $keysCheck = $this->checkRequiredKeys($prefix);
            $result['checks']['required_keys'] = $keysCheck;
            if (!$keysCheck['ok']) {
                if ($result['status'] === 'ok') {
                    $result['status'] = 'warning';
                }
                foreach ($keysCheck['missing'] as $k) {
                    $result['issues'][] = "Missing setting: {$k}";
                }
            }
        }

        // 4. Storage directory
        $storageCheck = $this->checkStorage($prefix);
        $result['checks']['storage'] = $storageCheck;
        if (!$storageCheck['ok'] && $result['status'] === 'ok') {
            $result['status'] = 'warning';
            $result['issues'][] = 'Storage directory unavailable';
        }

        StructuredLogger::health('tenant_health_check', $result['status'], $tid, [
            'prefix' => $prefix,
            'issues' => count($result['issues']),
        ]);

        if ($result['status'] !== 'ok') {
            ErrorLogger::warn(sprintf(
                '[HEALTH] tenant=%s status=%s issues=%s',
                $tid, $result['status'], implode('; ', $result['issues'])
            ), $tid);
        }

        return $result;
    }

    private function checkDbConnection(): array
    {
        try {
            $this->db->fetch("SELECT 1", []);
            return ['ok' => true, 'message' => 'Connection OK'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkSettingsTable(string $prefix): array
    {
        $table = $prefix . 'settings';
        try {
            $exists = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            );
            return [
                'ok'      => $exists > 0,
                'table'   => $table,
                'message' => $exists > 0 ? 'Table exists' : 'Table not found',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'table' => $table, 'message' => $e->getMessage()];
        }
    }

    private function checkRequiredKeys(string $prefix): array
    {
        $missing = [];
        $table   = $prefix . 'settings';
        try {
            foreach (self::REQUIRED_KEYS as $key) {
                $row = $this->db->fetch(
                    "SELECT `value` FROM `{$table}` WHERE `key` = ?",
                    [$key]
                );
                if ($row === false || $row['value'] === null || $row['value'] === '') {
                    $missing[] = $key;
                }
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'missing' => [], 'message' => $e->getMessage()];
        }

        return [
            'ok'      => empty($missing),
            'missing' => $missing,
            'message' => empty($missing) ? 'All required keys present' : 'Missing: ' . implode(', ', $missing),
        ];
    }

    private function checkStorage(string $prefix): array
    {
        try {
            $base = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 3) . '/storage';
            $slug = rtrim($prefix, '_');
            $path = $base . '/tenants/' . $slug;

            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }

            return [
                'ok'      => is_dir($path),
                'path'    => $path,
                'message' => is_dir($path) ? 'Storage OK' : 'Could not create storage directory',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'path' => '', 'message' => $e->getMessage()];
        }
    }
}
