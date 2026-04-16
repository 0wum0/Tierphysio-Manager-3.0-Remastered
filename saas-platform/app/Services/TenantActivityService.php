<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Database;
use Saas\Core\ErrorLogger;
use Saas\Core\StructuredLogger;

/**
 * Tenant activity log service (SaaS platform).
 *
 * Tracks logins, data changes, and cron runs in
 * {prefix}activity_log for any tenant.
 *
 * Self-healing: table auto-created on first access.
 * All public methods non-throwing.
 *
 * Feature #8: Tenant Activity Log
 */
class TenantActivityService
{
    private bool $tableEnsured = false;

    /**
     * @param Database $db      SaaS platform DB
     * @param string   $prefix  Tenant table prefix, e.g. "t_therapano_2eff77_"
     */
    public function __construct(
        private readonly Database $db,
        private readonly string   $prefix
    ) {}

    /* ── Write ─────────────────────────────────────────────── */

    public function log(
        string $action,
        string $details  = '',
        ?int   $userId   = null,
        string $category = 'general'
    ): bool {
        try {
            $this->ensureTable();
            $this->db->execute(
                "INSERT INTO `{$this->prefix}activity_log`
                 (`category`, `action`, `details`, `user_id`, `ip_address`, `created_at`)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$category, $action, $details, $userId, $this->clientIp()]
            );
            StructuredLogger::activity($action, 'logged', '', ['category' => $category]);
            return true;
        } catch (\Throwable $e) {
            ErrorLogger::log('TenantActivityService::log failed: ' . $e->getMessage());
            return false;
        }
    }

    public function logLogin(int $userId, string $username = ''): bool
    {
        return $this->log("user.login", "User {$username} (#{$userId}) logged in", $userId, 'auth');
    }

    public function logLogout(int $userId, string $username = ''): bool
    {
        return $this->log("user.logout", "User {$username} (#{$userId}) logged out", $userId, 'auth');
    }

    public function logDataChange(string $entity, int $entityId, string $action, ?int $userId = null): bool
    {
        return $this->log("data.{$action}", "{$entity} #{$entityId} {$action}", $userId, 'data');
    }

    public function logCronRun(string $jobKey, string $status, string $details = ''): bool
    {
        $desc = "Cron '{$jobKey}' finished: {$status}";
        if ($details !== '') {
            $desc .= " – {$details}";
        }
        return $this->log("cron.{$jobKey}", $desc, null, 'cron');
    }

    public function logSystem(string $action, string $details = ''): bool
    {
        return $this->log("system.{$action}", $details, null, 'system');
    }

    /* ── Read ──────────────────────────────────────────────── */

    public function getRecent(int $limit = 50, string $category = ''): array
    {
        try {
            $this->ensureTable();
            if ($category !== '') {
                return $this->db->fetchAll(
                    "SELECT * FROM `{$this->prefix}activity_log`
                     WHERE `category` = ? ORDER BY `created_at` DESC LIMIT ?",
                    [$category, $limit]
                );
            }
            return $this->db->fetchAll(
                "SELECT * FROM `{$this->prefix}activity_log` ORDER BY `created_at` DESC LIMIT ?",
                [$limit]
            );
        } catch (\Throwable $e) {
            ErrorLogger::log('TenantActivityService::getRecent failed: ' . $e->getMessage());
            return [];
        }
    }

    public function countSince(string $since, string $category = ''): int
    {
        try {
            $this->ensureTable();
            if ($category !== '') {
                return (int)$this->db->fetchColumn(
                    "SELECT COUNT(*) FROM `{$this->prefix}activity_log`
                     WHERE `created_at` >= ? AND `category` = ?",
                    [$since, $category]
                );
            }
            return (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->prefix}activity_log` WHERE `created_at` >= ?",
                [$since]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /* ── Self-healing table bootstrap ─────────────────────── */

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }
        try {
            $table = $this->prefix . 'activity_log';
            $this->db->execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `category`   VARCHAR(50)  NOT NULL DEFAULT 'general',
                    `action`     VARCHAR(100) NOT NULL,
                    `details`    TEXT         NULL,
                    `user_id`    INT UNSIGNED NULL DEFAULT NULL,
                    `ip_address` VARCHAR(45)  NULL DEFAULT NULL,
                    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_category`   (`category`),
                    KEY `idx_action`     (`action`),
                    KEY `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                []
            );
            $this->tableEnsured = true;
        } catch (\Throwable $e) {
            ErrorLogger::log('TenantActivityService::ensureTable failed: ' . $e->getMessage());
        }
    }

    private function clientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
    }
}
