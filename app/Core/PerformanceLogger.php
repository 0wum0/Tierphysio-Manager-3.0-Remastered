<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight performance logger.
 * Writes structured timing entries to logs/performance.log.
 * All methods are static — zero instantiation overhead.
 * All writes are non-blocking (FILE_APPEND | LOCK_EX with error suppression).
 */
class PerformanceLogger
{
    private static array  $timers    = [];
    private static array  $checkpoints = [];
    private static string $context   = '';
    private static float  $requestStart = 0.0;

    public static function startRequest(string $context): void
    {
        self::$context      = $context;
        self::$requestStart = microtime(true);
        self::$timers       = [];
        self::$checkpoints  = [];
    }

    public static function mark(string $label): void
    {
        self::$checkpoints[] = [
            'label' => $label,
            'ms'    => self::elapsedMs(),
        ];
    }

    public static function startTimer(string $name): void
    {
        self::$timers[$name] = microtime(true);
    }

    public static function stopTimer(string $name): float
    {
        if (!isset(self::$timers[$name])) return 0.0;
        $elapsed = round((microtime(true) - self::$timers[$name]) * 1000, 2);
        self::$checkpoints[] = [
            'label' => $name,
            'ms'    => $elapsed,
        ];
        unset(self::$timers[$name]);
        return $elapsed;
    }

    public static function finish(?string $error = null): void
    {
        if (self::$requestStart === 0.0) return;

        $total   = self::elapsedMs();
        $context = self::$context ?: 'unknown';
        $ts      = date('Y-m-d H:i:s');
        $user    = $_SESSION['user_id'] ?? $_SESSION['saas_user'] ?? '-';
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '-';
        $uri     = $_SERVER['REQUEST_URI'] ?? '-';

        $parts = ["[{$ts}] [{$context}] total={$total}ms ip={$ip} user={$user} uri={$uri}"];

        foreach (self::$checkpoints as $cp) {
            $parts[] = "  {$cp['label']}={$cp['ms']}ms";
        }

        if ($error !== null) {
            $parts[] = "  ERROR=" . str_replace(["\n", "\r"], ' ', $error);
        }

        $line = implode("\n", $parts) . "\n\n";

        $logDir = defined('ROOT_PATH') ? ROOT_PATH . '/logs' : dirname(__DIR__, 2) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        @file_put_contents($logDir . '/performance.log', $line, FILE_APPEND | LOCK_EX);

        // Reset
        self::$timers       = [];
        self::$checkpoints  = [];
        self::$context      = '';
        self::$requestStart = 0.0;
    }

    public static function elapsedMs(): float
    {
        if (self::$requestStart === 0.0) return 0.0;
        return round((microtime(true) - self::$requestStart) * 1000, 2);
    }
}
