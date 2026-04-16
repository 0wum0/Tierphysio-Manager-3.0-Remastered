<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ErrorLogger;
use App\Core\StructuredLogger;

/**
 * Global tenant health check service.
 *
 * Checks per tenant:
 *  - DB connection is alive
 *  - Settings table exists and is reachable
 *  - Required cron/system setting keys exist
 *  - Tenant storage directory is present (auto-creates if missing)
 *
 * Self-healing:
 *  - Missing storage directories are auto-created.
 *  - Issues are logged via StructuredLogger to logs/health.log.
 *
 * Feature #2: Global Tenant Health Check System
 */
class TenantHealthService
{
    /**
     * Setting keys that must be non-empty for the system to function correctly.
     */
    private const REQUIRED_KEYS = [
        'cron_dispatcher_token',
        'birthday_cron_token',
        'calendar_cron_secret',
        'tcp_cron_token',
        'cron_secret',
    ];

    public function __construct(private readonly Database $db) {}

    /* ──────────────────────────────────────────────────────────
       Public API
    ────────────────────────────────────────────────────────── */

    /**
     * Run a full health check for the current tenant.
     * The DB prefix must already be set before calling this method.
     *
     * @param  string $tid  Human-readable tenant ID for log output.
     * @return array{
     *   tid: string,
     *   status: 'ok'|'warning'|'critical',
     *   checks: array<string, array>,
     *   issues: list<string>
     * }
     */
    public function check(string $tid = ''): array
    {
        $result = [
            'tid'    => $tid,
            'status' => 'ok',
            'checks' => [],
            'issues' => [],
        ];

        // ── 1. DB connection ──────────────────────────────────
        $dbCheck = $this->checkDbConnection();
        $result['checks']['db_connection'] = $dbCheck;
        if (!$dbCheck['ok']) {
            $result['status'] = 'critical';
            $result['issues'][] = 'DB connection failed';
        }

        // ── 2. Settings table ─────────────────────────────────
        $tableCheck = $this->checkSettingsTable();
        $result['checks']['settings_table'] = $tableCheck;
        if (!$tableCheck['ok']) {
            $result['status'] = 'critical';
            $result['issues'][] = 'Settings table missing: ' . $tableCheck['table'];
        }

        // ── 3. Required setting keys ──────────────────────────
        if ($tableCheck['ok']) {
            $keysCheck = $this->checkRequiredKeys();
            $result['checks']['required_keys'] = $keysCheck;
            if (!$keysCheck['ok']) {
                if ($result['status'] === 'ok') {
                    $result['status'] = 'warning';
                }
                foreach ($keysCheck['missing'] as $missing) {
                    $result['issues'][] = "Missing setting key: {$missing}";
                }
            }
        }

        // ── 4. Storage directory ──────────────────────────────
        $storageCheck = $this->checkStorage();
        $result['checks']['storage'] = $storageCheck;
        if (!$storageCheck['ok'] && $result['status'] === 'ok') {
            $result['status'] = 'warning';
            $result['issues'][] = 'Storage directory unavailable: ' . ($storageCheck['path'] ?? '');
        }

        // ── Log result ────────────────────────────────────────
        StructuredLogger::health(
            'tenant_health_check',
            $result['status'],
            $tid,
            [
                'checks' => count($result['checks']),
                'issues' => count($result['issues']),
            ]
        );

        if ($result['status'] !== 'ok') {
            ErrorLogger::warn(
                sprintf(
                    '[HEALTH] tenant=%s status=%s issues=%s',
                    $tid,
                    $result['status'],
                    implode('; ', $result['issues'])
                ),
                $tid
            );
        }

        return $result;
    }

    /* ──────────────────────────────────────────────────────────
       Individual checks
    ────────────────────────────────────────────────────────── */

    private function checkDbConnection(): array
    {
        try {
            $this->db->fetch("SELECT 1", []);
            return ['ok' => true, 'message' => 'Connection OK'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkSettingsTable(): array
    {
        $table = $this->db->prefix('settings');
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

    private function checkRequiredKeys(): array
    {
        $missing = [];
        try {
            foreach (self::REQUIRED_KEYS as $key) {
                $row = $this->db->safeFetch(
                    "SELECT `value` FROM `{$this->db->prefix('settings')}` WHERE `key` = ?",
                    [$key]
                );
                if ($row === null || $row['value'] === null || $row['value'] === '') {
                    $missing[] = $key;
                }
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'missing' => [], 'message' => $e->getMessage()];
        }

        return [
            'ok'      => empty($missing),
            'missing' => $missing,
            'message' => empty($missing)
                ? 'All required keys present'
                : 'Missing: ' . implode(', ', $missing),
        ];
    }

    private function checkStorage(): array
    {
        try {
            // storagePath() already auto-creates the directory (Feature #4)
            $path   = $this->db->storagePath('');
            $exists = is_dir($path);

            return [
                'ok'      => $exists,
                'path'    => $path,
                'message' => $exists
                    ? 'Storage directory OK'
                    : 'Storage directory could not be created',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'path' => '', 'message' => $e->getMessage()];
        }
    }
}
