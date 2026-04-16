<?php

declare(strict_types=1);

namespace Saas\Core;

/**
 * Smart structured logger for the SaaS platform.
 *
 * Format: [DATE] [TYPE] tenant=xyz action=... status=... key=value
 *
 * Log files (project-root /logs/):
 *   app.log · cron.log · health.log · activity.log · system.log · error.log
 *
 * Static, fire-and-forget. NEVER throws.
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

    public static function info(string $action, string $status = 'ok', string $tenant = '', array $context = []): void
    {
        self::write('APP', $tenant, $action, $status, $context);
    }

    public static function error(string $action, string $message, string $tenant = '', array $context = []): void
    {
        self::write('ERROR', $tenant, $action, 'error', array_merge(['message' => $message], $context));
    }

    public static function cron(string $action, string $status, string $tenant = '', array $context = []): void
    {
        self::write('CRON', $tenant, $action, $status, $context);
    }

    public static function health(string $action, string $status, string $tenant = '', array $context = []): void
    {
        self::write('HEALTH', $tenant, $action, $status, $context);
    }

    public static function activity(string $action, string $status, string $tenant = '', array $context = []): void
    {
        self::write('ACTIVITY', $tenant, $action, $status, $context);
    }

    public static function system(string $action, string $status, string $tenant = '', array $context = []): void
    {
        self::write('SYSTEM', $tenant, $action, $status, $context);
    }

    private static function write(string $type, string $tenant, string $action, string $status, array $context = []): void
    {
        try {
            $dir = defined('ROOT_PATH')
                ? ROOT_PATH . '/logs'
                : dirname(__DIR__, 3) . '/logs';

            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $file        = $dir . '/' . (self::LOG_FILES[$type] ?? 'app.log');
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
            @error_log("[SaasStructuredLogger] {$type} tenant={$tenant} action={$action} status={$status}");
        }
    }
}
