<?php

declare(strict_types=1);

namespace Saas\Core;

/**
 * Centralized error logger for the SaaS platform.
 *
 * Writes structured entries to logs/error.log.
 * Format: [DATE] [LEVEL] tenant=xyz file=... line=N message=...
 *
 * Static, fire-and-forget. NEVER throws.
 *
 * Feature #7: Error Logging System
 */
class ErrorLogger
{
    private static string $resolvedPath = '';

    public static function log(
        string $message,
        string $file = '',
        int    $line = 0,
        string $tid  = ''
    ): void {
        self::write('ERROR', $message, $tid, $file, $line);
    }

    public static function warn(string $message, string $tid = ''): void
    {
        self::write('WARN', $message, $tid);
    }

    public static function info(string $message, string $tid = ''): void
    {
        self::write('INFO', $message, $tid);
    }

    public static function logThrowable(\Throwable $e, string $tid = ''): void
    {
        self::write('ERROR', $e->getMessage(), $tid, $e->getFile(), $e->getLine());
    }

    private static function write(
        string $level,
        string $message,
        string $tid  = '',
        string $file = '',
        int    $line = 0
    ): void {
        try {
            $tenantPart = $tid  !== '' ? " tenant={$tid}"  : '';
            $filePart   = $file !== '' ? " file={$file}"   : '';
            $linePart   = $line >  0   ? " line={$line}"   : '';

            $entry = sprintf(
                "[%s] [%s]%s%s%s message=%s\n",
                date('Y-m-d H:i:s'),
                $level,
                $tenantPart,
                $filePart,
                $linePart,
                $message
            );

            @file_put_contents(self::logFile(), $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            @error_log("[SaasErrorLogger] {$level}: {$message}");
        }
    }

    private static function logFile(): string
    {
        if (self::$resolvedPath !== '') {
            return self::$resolvedPath;
        }
        // saas-platform/app/Core/ → 3 levels up = project root
        $dir = defined('ROOT_PATH')
            ? ROOT_PATH . '/logs'
            : dirname(__DIR__, 3) . '/logs';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        self::$resolvedPath = $dir . '/error.log';
        return self::$resolvedPath;
    }
}
