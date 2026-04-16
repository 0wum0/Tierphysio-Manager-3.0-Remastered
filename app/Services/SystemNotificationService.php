<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ErrorLogger;
use App\Core\StructuredLogger;

/**
 * System-level notification service.
 *
 * Stores structured system notifications per tenant in
 * {prefix}system_notifications. Each notification has a type, title,
 * message, and read/unread state.
 *
 * Self-healing:
 *  - The table is created automatically (CREATE TABLE IF NOT EXISTS)
 *    on the first write or read attempt.
 *  - All methods are non-throwing; failures are logged and a safe
 *    default is returned.
 *
 * Notification types: cron_failed | missing_data | tenant_issue | info | warning
 *
 * Feature #6: Notification System (System Level)
 */
class SystemNotificationService
{
    /** Set to true once the table has been successfully ensured this request. */
    private bool $tableEnsured = false;

    public function __construct(private readonly Database $db) {}

    /* ──────────────────────────────────────────────────────────
       Write API
    ────────────────────────────────────────────────────────── */

    /**
     * Create a system notification for the current tenant.
     */
    public function create(string $type, string $title, string $message): bool
    {
        try {
            $this->ensureTable();
            $this->db->execute(
                "INSERT INTO `{$this->db->prefix('system_notifications')}`
                 (`type`, `title`, `message`, `created_at`)
                 VALUES (?, ?, ?, NOW())",
                [$type, $title, $message]
            );
            StructuredLogger::system("notification.created.{$type}", 'ok');
            return true;
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotification::create failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Notify that a cron job failed.
     */
    public function notifyCronFailed(string $jobKey, string $error): bool
    {
        return $this->create(
            'cron_failed',
            "Cron-Job fehlgeschlagen: {$jobKey}",
            "Der Cron-Job '{$jobKey}' ist mit folgendem Fehler fehlgeschlagen: {$error}"
        );
    }

    /**
     * Notify that required data is missing (system attempted auto-repair).
     */
    public function notifyMissingData(string $dataKey): bool
    {
        return $this->create(
            'missing_data',
            "Fehlende Daten: {$dataKey}",
            "Erforderliche Daten fehlen: '{$dataKey}'. Das System hat versucht, diese automatisch zu erstellen."
        );
    }

    /**
     * Notify about a general tenant-level issue.
     */
    public function notifyTenantIssue(string $issue): bool
    {
        return $this->create('tenant_issue', 'Tenant-Problem erkannt', $issue);
    }

    /**
     * Create an informational notification.
     */
    public function info(string $title, string $message): bool
    {
        return $this->create('info', $title, $message);
    }

    /**
     * Create a warning notification.
     */
    public function warning(string $title, string $message): bool
    {
        return $this->create('warning', $title, $message);
    }

    /* ──────────────────────────────────────────────────────────
       Read API
    ────────────────────────────────────────────────────────── */

    /**
     * Get all unread notifications, newest first.
     */
    public function getUnread(int $limit = 20): array
    {
        try {
            $this->ensureTable();
            return $this->db->fetchAll(
                "SELECT * FROM `{$this->db->prefix('system_notifications')}`
                 WHERE `read_at` IS NULL
                 ORDER BY `created_at` DESC
                 LIMIT ?",
                [$limit]
            );
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotification::getUnread failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all notifications (read and unread), newest first.
     */
    public function getAll(int $limit = 50): array
    {
        try {
            $this->ensureTable();
            return $this->db->fetchAll(
                "SELECT * FROM `{$this->db->prefix('system_notifications')}`
                 ORDER BY `created_at` DESC
                 LIMIT ?",
                [$limit]
            );
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotification::getAll failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count unread notifications (safe – returns 0 on error).
     */
    public function countUnread(): int
    {
        try {
            $this->ensureTable();
            return (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->db->prefix('system_notifications')}`
                 WHERE `read_at` IS NULL",
                []
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /* ──────────────────────────────────────────────────────────
       State management
    ────────────────────────────────────────────────────────── */

    /**
     * Mark a single notification as read.
     */
    public function markRead(int $id): bool
    {
        try {
            $this->ensureTable();
            $this->db->execute(
                "UPDATE `{$this->db->prefix('system_notifications')}`
                 SET `read_at` = NOW()
                 WHERE `id` = ? AND `read_at` IS NULL",
                [$id]
            );
            return true;
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotification::markRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllRead(): bool
    {
        try {
            $this->ensureTable();
            $this->db->execute(
                "UPDATE `{$this->db->prefix('system_notifications')}`
                 SET `read_at` = NOW()
                 WHERE `read_at` IS NULL",
                []
            );
            return true;
        } catch (\Throwable $e) {
            ErrorLogger::log('SystemNotification::markAllRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /* ──────────────────────────────────────────────────────────
       Self-healing table bootstrap
    ────────────────────────────────────────────────────────── */

    /**
     * Create {prefix}system_notifications if it does not yet exist.
     * Called lazily on the first read or write.
     */
    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        try {
            $table = $this->db->prefix('system_notifications');
            $this->db->execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                    `type`       VARCHAR(50)     NOT NULL DEFAULT 'info',
                    `title`      VARCHAR(255)    NOT NULL,
                    `message`    TEXT            NOT NULL,
                    `read_at`    DATETIME        NULL DEFAULT NULL,
                    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_read_at`  (`read_at`),
                    KEY `idx_type`     (`type`),
                    KEY `idx_created`  (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                []
            );
            $this->tableEnsured = true;
        } catch (\Throwable $e) {
            // Table creation failed – log but do not block; the next call will retry.
            ErrorLogger::log('SystemNotification::ensureTable failed: ' . $e->getMessage());
        }
    }
}
