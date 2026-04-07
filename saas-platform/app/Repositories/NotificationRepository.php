<?php

declare(strict_types=1);

namespace Saas\Repositories;

use Saas\Core\Database;

class NotificationRepository
{
    public function __construct(private Database $db) {}

    public function create(string $type, string $title, string $message, ?array $data = null): int
    {
        try {
            $this->db->execute(
                "INSERT INTO saas_notifications (type, title, message, data, is_read, created_at)
                 VALUES (?, ?, ?, ?, 0, NOW())",
                [$type, $title, $message, $data ? json_encode($data) : null]
            );
            return (int)$this->db->lastInsertId();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function getUnread(int $limit = 20): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM saas_notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT ?",
                [$limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getAll(int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        try {
            $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM saas_notifications");
            $items = $this->db->fetchAll(
                "SELECT * FROM saas_notifications ORDER BY created_at DESC LIMIT ? OFFSET ?",
                [$perPage, $offset]
            );
        } catch (\Throwable) {
            $total = 0;
            $items = [];
        }
        return [
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'last_page' => max(1, (int)ceil($total / $perPage)),
            'has_next'  => ($page * $perPage) < $total,
            'has_prev'  => $page > 1,
        ];
    }

    public function countUnread(): int
    {
        try {
            return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM saas_notifications WHERE is_read = 0");
        } catch (\Throwable) {
            return 0;
        }
    }

    public function markRead(int $id): void
    {
        try { $this->db->execute("UPDATE saas_notifications SET is_read = 1 WHERE id = ?", [$id]); } catch (\Throwable) {}
    }

    public function markAllRead(): void
    {
        try { $this->db->execute("UPDATE saas_notifications SET is_read = 1 WHERE is_read = 0"); } catch (\Throwable) {}
    }

    public function delete(int $id): void
    {
        try { $this->db->execute("DELETE FROM saas_notifications WHERE id = ?", [$id]); } catch (\Throwable) {}
    }

    public function deleteOlderThan(int $days): void
    {
        try {
            $this->db->execute(
                "DELETE FROM saas_notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND is_read = 1",
                [$days]
            );
        } catch (\Throwable) {}
    }

    public function getByType(string $type, int $limit = 10): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM saas_notifications WHERE type = ? ORDER BY created_at DESC LIMIT ?",
                [$type, $limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
