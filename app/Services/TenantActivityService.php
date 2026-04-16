<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ErrorLogger;
use App\Core\StructuredLogger;

/**
 * Tenant activity log service.
 *
 * Tracks user logins, data mutations, and cron runs per tenant.
 * Entries are stored in {prefix}activity_log.
 *
 * Self-healing:
 *  - The table is created automatically (CREATE TABLE IF NOT EXISTS)
 *    on the first write or read.
 *  - All public methods are non-throwing; failures are logged and
 *    execution continues.
 *
 * Feature #8: Tenant Activity Log
 */
class TenantActivityService
{
    /** Set to true once the table has been ensured this request. */
    private bool $tableEnsured = false;

    public function __construct(private readonly Database $db) {}

    /* ──────────────────────────────────────────────────────────
       Write API
    ────────────────────────────────────────────────────────── */

    /**
     * Log a generic activity entry.
     *
     * @param string   $action    Dot-notation action, e.g. "user.login", "invoice.created"
     * @param string   $details   Human-readable description
     * @param int|null $userId    ID of the user who triggered the action
     * @param string   $category  Grouping category: auth | data | cron | system | general
     */
    public function log(
        string $action,
        string $details   = '',
        ?int   $userId    = null,
        string $category  = 'general'
    ): bool {
        try {
            $this->ensureTable();
            $this->db->execute(
                "INSERT INTO `{$this->db->prefix('activity_log')}`
                 (`category`, `action`, `details`, `user_id`, `ip_address`, `created_at`)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    $category,
                    $action,
                    $details,
                    $userId,
                    $this->clientIp(),
                ]
            );
            StructuredLogger::activity($action, 'logged', '', ['category' => $category]);
            return true;
        } catch (\Throwable $e) {
            ErrorLogger::log('ActivityLog::log failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log a user login event.
     */
    public function logLogin(int $userId, string $username = ''): bool
    {
        return $this->log(
            'user.login',
            "User {$username} (#{$userId}) logged in",
            $userId,
            'auth'
        );
    }

    /**
     * Log a user logout event.
     */
    public function logLogout(int $userId, string $username = ''): bool
    {
        return $this->log(
            'user.logout',
            "User {$username} (#{$userId}) logged out",
            $userId,
            'auth'
        );
    }

    /**
     * Log a data change (create / update / delete).
     *
     * @param string   $entity    Model name, e.g. "invoice", "patient"
     * @param int      $entityId  Primary key of the affected record
     * @param string   $action    "created" | "updated" | "deleted"
     * @param int|null $userId    Who made the change
     */
    public function logDataChange(
        string $entity,
        int    $entityId,
        string $action,
        ?int   $userId = null
    ): bool {
        return $this->log(
            "data.{$action}",
            "{$entity} #{$entityId} {$action}",
            $userId,
            'data'
        );
    }

    /**
     * Log a cron job run result.
     */
    public function logCronRun(string $jobKey, string $status, string $details = ''): bool
    {
        $desc = "Cron job '{$jobKey}' finished with status '{$status}'";
        if ($details !== '') {
            $desc .= ": {$details}";
        }
        return $this->log("cron.{$jobKey}", $desc, null, 'cron');
    }

    /**
     * Log a system-level event (self-healing, migrations, etc.).
     */
    public function logSystem(string $action, string $details = ''): bool
    {
        return $this->log("system.{$action}", $details, null, 'system');
    }

    /* ──────────────────────────────────────────────────────────
       Read API
    ────────────────────────────────────────────────────────── */

    /**
     * Get recent activity log entries, newest first.
     *
     * @param  int    $limit     Maximum number of rows to return
     * @param  string $category  Optional category filter (empty = all)
     * @return list<array>
     */
    public function getRecent(int $limit = 50, string $category = ''): array
    {
        try {
            $this->ensureTable();

            if ($category !== '') {
                return $this->db->fetchAll(
                    "SELECT * FROM `{$this->db->prefix('activity_log')}`
                     WHERE `category` = ?
                     ORDER BY `created_at` DESC
                     LIMIT ?",
                    [$category, $limit]
                );
            }

            return $this->db->fetchAll(
                "SELECT * FROM `{$this->db->prefix('activity_log')}`
                 ORDER BY `created_at` DESC
                 LIMIT ?",
                [$limit]
            );
        } catch (\Throwable $e) {
            ErrorLogger::log('ActivityLog::getRecent failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count entries in a given time window.
     *
     * @param  string $since  MySQL datetime string, e.g. "2024-01-01 00:00:00"
     * @param  string $category  Optional category filter
     */
    public function countSince(string $since, string $category = ''): int
    {
        try {
            $this->ensureTable();

            if ($category !== '') {
                return (int)$this->db->fetchColumn(
                    "SELECT COUNT(*) FROM `{$this->db->prefix('activity_log')}`
                     WHERE `created_at` >= ? AND `category` = ?",
                    [$since, $category]
                );
            }

            return (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->db->prefix('activity_log')}`
                 WHERE `created_at` >= ?",
                [$since]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /* ──────────────────────────────────────────────────────────
       Self-healing table bootstrap
    ────────────────────────────────────────────────────────── */

    /**
     * Create {prefix}activity_log if it does not yet exist.
     * Called lazily on first read or write.
     */
    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        try {
            $table = $this->db->prefix('activity_log');
            $this->db->execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                    `category`   VARCHAR(50)     NOT NULL DEFAULT 'general',
                    `action`     VARCHAR(100)    NOT NULL,
                    `details`    TEXT            NULL,
                    `user_id`    INT UNSIGNED    NULL DEFAULT NULL,
                    `ip_address` VARCHAR(45)     NULL DEFAULT NULL,
                    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_category`   (`category`),
                    KEY `idx_action`     (`action`),
                    KEY `idx_user`       (`user_id`),
                    KEY `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                []
            );
            $this->tableEnsured = true;
        } catch (\Throwable $e) {
            ErrorLogger::log('ActivityLog::ensureTable failed: ' . $e->getMessage());
        }
    }

    /* ──────────────────────────────────────────────────────────
       Helpers
    ────────────────────────────────────────────────────────── */

    private function clientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
    }
}
