<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ErrorLogger;
use App\Core\StructuredLogger;
use App\Repositories\SettingsRepository;

/**
 * Self-healing orchestrator.
 *
 * Detects and automatically repairs common tenant data problems:
 *  - Missing settings         → re-initialize from defaults
 *  - Missing storage dirs     → recreate directory tree
 *  - Issues are logged; nothing is thrown
 *
 * Self-healing design principle:
 *  - NEVER overwrites existing data.
 *  - Always logs what was healed and what failed.
 *  - All operations are idempotent and safe to call repeatedly.
 *
 * Feature #13: Self-Healing Missing Data
 *
 * Usage (call once per request after the tenant prefix is set):
 *   $healer = new SelfHealingService($db, $settings);
 *   $report = $healer->healAll($tid);
 */
class SelfHealingService
{
    /**
     * Default settings that every tenant must have.
     * Only missing or empty values are written – existing values are untouched.
     */
    private const DEFAULT_SETTINGS = [
        'timezone'           => 'Europe/Berlin',
        'language'           => 'de',
        'currency'           => 'EUR',
        'date_format'        => 'd.m.Y',
        'time_format'        => 'H:i',
        'invoice_prefix'     => 'RE-',
        'invoice_start'      => '1000',
        'reminder_days'      => '3',
        'mail_from_name'     => '',
        'mail_from_address'  => '',
    ];

    /**
     * Storage sub-directories that must exist under the tenant root.
     * storagePath() creates the root automatically; we create the children here.
     */
    private const STORAGE_DIRS = [
        'patients',
        'uploads',
        'vet-reports',
        'intake',
        'invoices',
        'exports',
    ];

    public function __construct(
        private readonly Database           $db,
        private readonly SettingsRepository $settings
    ) {}

    /* ──────────────────────────────────────────────────────────
       Public API
    ────────────────────────────────────────────────────────── */

    /**
     * Run all self-healing checks for the current tenant.
     *
     * @param  string $tid  Human-readable tenant ID for logging.
     * @return array{
     *   tid: string,
     *   healed: list<string>,
     *   failed: list<string>
     * }
     */
    public function healAll(string $tid = ''): array
    {
        $report = ['tid' => $tid, 'healed' => [], 'failed' => []];

        $parts = [
            $this->healSettings($tid),
            $this->healStorageDirs($tid),
        ];

        foreach ($parts as $part) {
            $report['healed'] = array_merge($report['healed'], $part['healed']);
            $report['failed'] = array_merge($report['failed'], $part['failed']);
        }

        if (!empty($report['healed'])) {
            StructuredLogger::system(
                'self_heal.completed',
                'ok',
                $tid,
                ['healed' => count($report['healed']), 'items' => implode(', ', $report['healed'])]
            );
        }

        if (!empty($report['failed'])) {
            StructuredLogger::system(
                'self_heal.partial_failure',
                'warning',
                $tid,
                ['failed' => count($report['failed']), 'items' => implode(', ', $report['failed'])]
            );
        }

        return $report;
    }

    /**
     * Ensure all default settings exist for the current tenant.
     * NEVER overwrites an existing non-empty value.
     *
     * @return array{healed: list<string>, failed: list<string>}
     */
    public function healSettings(string $tid = ''): array
    {
        $healed = [];
        $failed = [];

        foreach (self::DEFAULT_SETTINGS as $key => $default) {
            try {
                $existing = $this->settings->get($key);
                if ($existing === null || $existing === '') {
                    $this->settings->set($key, (string)$default);
                    $healed[] = "setting:{$key}";
                    StructuredLogger::system("setting.healed", 'ok', $tid, ['key' => $key]);
                }
            } catch (\Throwable $e) {
                $failed[] = "setting:{$key}";
                ErrorLogger::log(
                    "SelfHeal: could not initialize setting '{$key}': " . $e->getMessage(),
                    '',
                    0,
                    $tid
                );
            }
        }

        return compact('healed', 'failed');
    }

    /**
     * Ensure all required tenant storage directories exist.
     * Directories that are missing are created automatically.
     *
     * @return array{healed: list<string>, failed: list<string>}
     */
    public function healStorageDirs(string $tid = ''): array
    {
        $healed = [];
        $failed = [];

        foreach (self::STORAGE_DIRS as $dir) {
            try {
                // storagePath() already creates the base tenant dir.
                // Passing a sub-path triggers auto-creation of that sub-dir too (Database.php #4).
                $path = $this->db->storagePath($dir);

                if (is_dir($path)) {
                    continue; // Already exists, nothing to heal
                }

                // Fallback explicit mkdir in case storagePath() creation silently failed
                if (@mkdir($path, 0755, true) || is_dir($path)) {
                    $healed[] = "storage:{$dir}";
                    StructuredLogger::system("storage.healed", 'ok', $tid, ['dir' => $dir, 'path' => $path]);
                } else {
                    $failed[] = "storage:{$dir}";
                    ErrorLogger::log(
                        "SelfHeal: could not create storage dir '{$path}'",
                        '',
                        0,
                        $tid
                    );
                }
            } catch (\Throwable $e) {
                $failed[] = "storage:{$dir}";
                ErrorLogger::log(
                    "SelfHeal: storage error for '{$dir}': " . $e->getMessage(),
                    '',
                    0,
                    $tid
                );
            }
        }

        return compact('healed', 'failed');
    }

    /* ──────────────────────────────────────────────────────────
       Helpers
    ────────────────────────────────────────────────────────── */

    /**
     * Add or update an entry in DEFAULT_SETTINGS dynamically.
     * Useful for plugins that need to register their own defaults.
     *
     * @param array<string, string> $defaults
     */
    public static function getDefaultSettings(): array
    {
        return self::DEFAULT_SETTINGS;
    }
}
