<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

class PasswordResetRepository extends Repository
{
    protected string $table = 'password_resets';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Create a new password reset row for a user.
     * Expects an already-hashed token (SHA-256 hex).
     */
    public function createForUser(int $userId, string $tokenHash, int $ttlSeconds = 3600, ?string $ipAddress = null): int
    {
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttlSeconds);

        return (int)$this->db->insert(
            "INSERT INTO `{$this->t()}` (user_id, token_hash, expires_at, ip_address)
             VALUES (?, ?, ?, ?)",
            [$userId, $tokenHash, $expiresAt, $ipAddress]
        );
    }

    /**
     * Returns the reset row only if the token is not used and not expired.
     */
    public function findValidByTokenHash(string $tokenHash): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM `{$this->t()}`
              WHERE token_hash = ?
                AND used_at   IS NULL
                AND expires_at > UTC_TIMESTAMP()
              LIMIT 1",
            [$tokenHash]
        );
    }

    public function markUsed(int $id): void
    {
        $this->db->execute(
            "UPDATE `{$this->t()}` SET used_at = UTC_TIMESTAMP() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Invalidates every still-open reset token for the given user
     * (used directly after a successful password change).
     */
    public function invalidateAllForUser(int $userId): void
    {
        $this->db->execute(
            "UPDATE `{$this->t()}`
                SET used_at = UTC_TIMESTAMP()
              WHERE user_id = ?
                AND used_at IS NULL",
            [$userId]
        );
    }

    /**
     * Housekeeping: remove rows older than 7 days.
     * Safe to call from a cron job.
     */
    public function cleanupExpired(): int
    {
        return $this->db->execute(
            "DELETE FROM `{$this->t()}`
              WHERE expires_at < (UTC_TIMESTAMP() - INTERVAL 7 DAY)"
        );
    }
}
