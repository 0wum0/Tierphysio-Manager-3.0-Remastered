<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Core\Database;
use PDO;

class MessagingRepository
{
    public function __construct(private readonly Database $db) {}

    /* ─── Threads ─── */

    public function getAllThreads(): array
    {
        $stmt = $this->db->query(
            "SELECT t.*,
                    CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                    o.email AS owner_email,
                    (SELECT COUNT(*) FROM portal_messages m WHERE m.thread_id = t.id AND m.is_read = 0 AND m.sender_type = 'owner') AS unread_count,
                    (SELECT m2.body FROM portal_messages m2 WHERE m2.thread_id = t.id ORDER BY m2.created_at DESC LIMIT 1) AS last_body
             FROM portal_message_threads t
             JOIN owners o ON o.id = t.owner_id
             ORDER BY t.last_message_at DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getThreadsByOwner(int $ownerId): array
    {
        $stmt = $this->db->query(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM portal_messages m WHERE m.thread_id = t.id AND m.is_read = 0 AND m.sender_type = 'admin') AS unread_count,
                    (SELECT m2.body FROM portal_messages m2 WHERE m2.thread_id = t.id ORDER BY m2.created_at DESC LIMIT 1) AS last_body
             FROM portal_message_threads t
             WHERE t.owner_id = ?
             ORDER BY t.last_message_at DESC",
            [$ownerId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getThreadById(int $id): ?array
    {
        $stmt = $this->db->query(
            "SELECT t.*,
                    CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                    o.email AS owner_email
             FROM portal_message_threads t
             JOIN owners o ON o.id = t.owner_id
             WHERE t.id = ? LIMIT 1",
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createThread(int $ownerId, string $subject, string $createdBy): int
    {
        $this->db->execute(
            "INSERT INTO portal_message_threads (owner_id, subject, status, created_by, last_message_at, created_at)
             VALUES (?, ?, 'open', ?, NOW(), NOW())",
            [$ownerId, $subject, $createdBy]
        );
        return (int)$this->db->lastInsertId();
    }

    public function closeThread(int $id): void
    {
        $this->db->execute("UPDATE portal_message_threads SET status = 'closed' WHERE id = ?", [$id]);
    }

    public function reopenThread(int $id): void
    {
        $this->db->execute("UPDATE portal_message_threads SET status = 'open' WHERE id = ?", [$id]);
    }

    public function touchThread(int $id): void
    {
        $this->db->execute("UPDATE portal_message_threads SET last_message_at = NOW() WHERE id = ?", [$id]);
    }

    /* ─── Messages ─── */

    public function getMessages(int $threadId): array
    {
        $stmt = $this->db->query(
            "SELECT m.*,
                    CASE WHEN m.sender_type = 'admin' THEN COALESCE(u.name, 'Team') ELSE CONCAT(o.first_name, ' ', o.last_name) END AS sender_name
             FROM portal_messages m
             LEFT JOIN users u ON u.id = m.sender_id AND m.sender_type = 'admin'
             LEFT JOIN portal_message_threads t ON t.id = m.thread_id
             LEFT JOIN owners o ON o.id = t.owner_id AND m.sender_type = 'owner'
             WHERE m.thread_id = ?
             ORDER BY m.created_at ASC",
            [$threadId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addMessage(int $threadId, string $senderType, ?int $senderId, string $body): int
    {
        $this->db->execute(
            "INSERT INTO portal_messages (thread_id, sender_type, sender_id, body, is_read, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())",
            [$threadId, $senderType, $senderId, trim($body)]
        );
        $msgId = (int)$this->db->lastInsertId();
        $this->touchThread($threadId);
        return $msgId;
    }

    public function markThreadReadByAdmin(int $threadId): void
    {
        $this->db->execute(
            "UPDATE portal_messages SET is_read = 1 WHERE thread_id = ? AND sender_type = 'owner' AND is_read = 0",
            [$threadId]
        );
    }

    public function markThreadReadByOwner(int $threadId): void
    {
        $this->db->execute(
            "UPDATE portal_messages SET is_read = 1 WHERE thread_id = ? AND sender_type = 'admin' AND is_read = 0",
            [$threadId]
        );
    }

    public function countUnreadForAdmin(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM portal_messages WHERE sender_type = 'owner' AND is_read = 0"
        );
        return (int)$stmt->fetchColumn();
    }

    public function countUnreadForOwner(int $ownerId): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM portal_messages m
             JOIN portal_message_threads t ON t.id = m.thread_id
             WHERE t.owner_id = ? AND m.sender_type = 'admin' AND m.is_read = 0",
            [$ownerId]
        );
        return (int)$stmt->fetchColumn();
    }

    public function deleteThread(int $id): void
    {
        $this->db->execute("DELETE FROM portal_messages WHERE thread_id = ?", [$id]);
        $this->db->execute("DELETE FROM portal_message_threads WHERE id = ?", [$id]);
    }
}
