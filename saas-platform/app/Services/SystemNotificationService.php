<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Database;
use Saas\Core\ErrorLogger;
use Saas\Core\StructuredLogger;

/**
 * System notification service (SaaS platform).
 *
 * Stores and reads system-level notifications in a tenant's
 * {prefix}system_notifications table.
 *
 * The SaaS platform can write notifications for ANY tenant by
 * passing the appropriate prefix.
 *
 * Self-healing: table is auto-created (CREATE TABLE IF NOT EXISTS).
 * All methods non-throwing.
 *
 * Feature #6: Notification System (System Level)
 */
class SystemNotificationService
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

    public function create(string $type, string $title, string $message): bool
    {
        try {
            $this->ensureTable();
            $this->db->execute(
                "INSERT INTO `{$this->prefix}system_notifications`
                 (`type`, `title`, `message`, `created_at`) VALUES (?, ?, ?, NOW())",
                [$type, $title, $message]
            );
            StructuredLogger::system("notification.created.{$type}", 'ok');
            return true;
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotificationService::create failed: ' . $e->getMessage());
            return false;
        }
    }

    public function notifyCronFailed(string $jobKey, string $error): bool
    {
        return $this->create(
            'cron_failed',
            "Cron-Job fehlgeschlagen: {$jobKey}",
            "Der Cron-Job '{$jobKey}' ist fehlgeschlagen: {$error}"
        );
    }

    public function notifyMissingData(string $dataKey): bool
    {
        return $this->create(
            'missing_data',
            "Fehlende Daten: {$dataKey}",
            "Erforderliche Daten fehlen: '{$dataKey}'. Automatische Reparatur wurde versucht."
        );
    }

    public function notifyTenantIssue(string $issue): bool
    {
        return $this->create('tenant_issue', 'Tenant-Problem erkannt', $issue);
    }

    public function info(string $title, string $message): bool
    {
        return $this->create('info', $title, $message);
    }

    public function warning(string $title, string $message): bool
    {
        return $this->create('warning', $title, $message);
    }

    /* ── Read ──────────────────────────────────────────────── */

    public function getUnread(int $limit = 20): array
    {
        try {
            $this->ensureTable();
            return $this->db->fetchAll(
                "SELECT * FROM `{$this->prefix}system_notifications`
                 WHERE `read_at` IS NULL ORDER BY `created_at` DESC LIMIT ?",
                [$limit]
            );
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotificationService::getUnread failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getAll(int $limit = 50): array
    {
        try {
            $this->ensureTable();
            return $this->db->fetchAll(
                "SELECT * FROM `{$this->prefix}system_notifications`
                 ORDER BY `created_at` DESC LIMIT ?",
                [$limit]
            );
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotificationService::getAll failed: ' . $e->getMessage());
            return [];
        }
    }

    public function countUnread(): int
    {
        try {
            $this->ensureTable();
            return (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->prefix}system_notifications` WHERE `read_at` IS NULL",
                []
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    public function markRead(int $id): bool
    {
        try {
            $this->ensureTable();
            $this->db->execute(
                "UPDATE `{$this->prefix}system_notifications`
                 SET `read_at` = NOW() WHERE `id` = ? AND `read_at` IS NULL",
                [$id]
            );
            return true;
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotificationService::markRead failed: ' . $e->getMessage());
            return false;
        }
    }

    public function markAllRead(): bool
    {
        try {
            $this->ensureTable();
            $this->db->execute(
                "UPDATE `{$this->prefix}system_notifications` SET `read_at` = NOW() WHERE `read_at` IS NULL",
                []
            );
            return true;
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotificationService::markAllRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /* ── Self-healing table bootstrap ─────────────────────── */

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }
        try {
            $table = $this->prefix . 'system_notifications';
            $this->db->execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `type`       VARCHAR(50)  NOT NULL DEFAULT 'info',
                    `title`      VARCHAR(255) NOT NULL,
                    `message`    TEXT         NOT NULL,
                    `read_at`    DATETIME     NULL DEFAULT NULL,
                    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_read_at` (`read_at`),
                    KEY `idx_type`    (`type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                []
            );
            $this->tableEnsured = true;
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotificationService::ensureTable failed: ' . $e->getMessage());
        }
    }
}
