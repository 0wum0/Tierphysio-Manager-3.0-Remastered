<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Smart structured logger.
 *
 * Writes machine-readable log entries in a consistent format:
 *   [DATE] [TYPE] tenant=xyz action=... status=... key=value ...
 *
 * One log file per category:
 *   logs/app.log      – general application events
 *   logs/cron.log     – cron system events
 *   logs/health.log   – health check results
 *   logs/activity.log – tenant user activity
 *   logs/system.log   – self-healing and system operations
 *   logs/error.log    – shared with ErrorLogger
 *
 * All methods are static and fire-and-forget.
 * NEVER throws – logging must never crash the application.
 *
 * Feature #12: Smart Logging System
 */
class StructuredLogger
{
    private const LOG_FILES = [
        'APP'      => 'app.log',
        'CRON'     => 'cron.log',
        'HEALTH'   => 'health.log',
        'ACTIVITY' => 'activity.log',
        'SYSTEM'   => 'system.log',
        'ERROR'    => 'error.log',
    ];

    /* ──────────────────────────────────────────────────────────
       Public API
    ────────────────────────────────────────────────────────── */

    /**
     * General application event.
     * [DATE] [APP] tenant=xyz action=... status=... [context...]
     */
    public static function info(
        string $action,
        string $status  = 'ok',
        string $tenant  = '',
        array  $context = []
    ): void {
        self::write('APP', $tenant, $action, $status, $context);
    }

    /**
     * Error event (also written to error.log).
     */
    public static function error(
        string $action,
        string $message,
        string $tenant  = '',
        array  $context = []
    ): void {
        self::write('ERROR', $tenant, $action, 'error', array_merge(['message' => $message], $context));
    }

    /**
     * Cron system event.
     * [DATE] [CRON] tenant=xyz action=... status=...
     */
    public static function cron(
        string $action,
        string $status,
        string $tenant  = '',
        array  $context = []
    ): void {
        self::write('CRON', $tenant, $action, $status, $context);
    }

    /**
     * Health check result.
     * [DATE] [HEALTH] tenant=xyz action=... status=ok|warning|critical
     */
    public static function health(
        string $action,
        string $status,
        string $tenant  = '',
        array  $context = []
    ): void {
        self::write('HEALTH', $tenant, $action, $status, $context);
    }

    /**
     * Tenant user activity event.
     * [DATE] [ACTIVITY] tenant=xyz action=... status=...
     */
    public static function activity(
        string $action,
        string $status,
        string $tenant  = '',
        array  $context = []
    ): void {
        self::write('ACTIVITY', $tenant, $action, $status, $context);
    }

    /**
     * System / self-healing operation.
     * [DATE] [SYSTEM] tenant=xyz action=... status=...
     */
    public static function system(
        string $action,
        string $status,
        string $tenant  = '',
        array  $context = []
    ): void {
        self::write('SYSTEM', $tenant, $action, $status, $context);
    }

    /* ──────────────────────────────────────────────────────────
       Internal helpers
    ────────────────────────────────────────────────────────── */

    private static function write(
        string $type,
        string $tenant,
        string $action,
        string $status,
        array  $context = []
    ): void {
        try {
            $logDir = defined('ROOT_PATH')
                ? ROOT_PATH . '/logs'
                : dirname(__DIR__, 2) . '/logs';

            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $file        = $logDir . '/' . (self::LOG_FILES[$type] ?? 'app.log');
            $tenantPart  = $tenant !== '' ? " tenant={$tenant}" : '';
            $contextPart = '';

            foreach ($context as $k => $v) {
                $scalar      = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
                $contextPart .= " {$k}={$scalar}";
            }

            $entry = sprintf(
                "[%s] [%s]%s action=%s status=%s%s\n",
                date('Y-m-d H:i:s'),
                $type,
                $tenantPart,
                $action,
                $status,
                $contextPart
            );

            @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Absolute last-resort fallback
            @error_log("[StructuredLogger] {$type} tenant={$tenant} action={$action} status={$status}");
        }
    }
}
