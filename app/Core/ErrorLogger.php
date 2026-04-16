<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Centralized error logger.
 *
 * Writes structured entries to logs/error.log.
 * Format: [DATE] [LEVEL] tenant=xyz file=... line=N message=...
 *
 * All methods are static and fire-and-forget.
 * This class NEVER throws – logging must never crash the application.
 *
 * Feature #7: Error Logging System
 */
class ErrorLogger
{
    private static string $resolvedPath = '';

    /* ──────────────────────────────────────────────────────────
       Public API
    ────────────────────────────────────────────────────────── */

    /**
     * Log an error with optional tenant, file, and line context.
     */
    public static function log(
        string $message,
        string $file   = '',
        int    $line   = 0,
        string $tid    = ''
    ): void {
        self::write('ERROR', $message, $tid, $file, $line);
    }

    /**
     * Log a warning (non-fatal).
     */
    public static function warn(string $message, string $tid = ''): void
    {
        self::write('WARN', $message, $tid);
    }

    /**
     * Log an informational message.
     */
    public static function info(string $message, string $tid = ''): void
    {
        self::write('INFO', $message, $tid);
    }

    /**
     * Log any Throwable with full context.
     */
    public static function logThrowable(\Throwable $e, string $tid = ''): void
    {
        self::write('ERROR', $e->getMessage(), $tid, $e->getFile(), $e->getLine());
    }

    /* ──────────────────────────────────────────────────────────
       Internal helpers
    ────────────────────────────────────────────────────────── */

    private static function write(
        string $level,
        string $message,
        string $tid  = '',
        string $file = '',
        int    $line = 0
    ): void {
        try {
            $tenantPart = $tid  !== '' ? " tenant={$tid}"          : '';
            $filePart   = $file !== '' ? " file={$file}"           : '';
            $linePart   = $line >  0   ? " line={$line}"           : '';

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
            // Absolute fallback – write to PHP error log so nothing is lost silently
            @error_log("[ErrorLogger] {$level}: {$message}");
        }
    }

    private static function logFile(): string
    {
        if (self::$resolvedPath !== '') {
            return self::$resolvedPath;
        }

        $dir = defined('ROOT_PATH')
            ? ROOT_PATH . '/logs'
            : dirname(__DIR__, 2) . '/logs';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        self::$resolvedPath = $dir . '/error.log';
        return self::$resolvedPath;
    }
}
