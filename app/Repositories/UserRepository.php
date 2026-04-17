<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

class UserRepository extends Repository
{
    protected string $table = 'users';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function findByEmail(string $email): array|false
    {
        return $this->findOneBy('email', $email);
    }

    public function updateLastLogin(int|string $id): void
    {
        $this->db->execute(
            "UPDATE `{$this->t()}` SET last_login = NOW() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Set a new password hash for a user.
     * The hash MUST already be produced via password_hash().
     */
    public function updatePassword(int|string $id, string $passwordHash): void
    {
        $this->db->execute(
            "UPDATE `{$this->t()}` SET password = ? WHERE id = ?",
            [$passwordHash, $id]
        );
    }

    public function findAll(string $orderBy = 'name', string $direction = 'ASC'): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, email, role, active, last_login, created_at FROM `{$this->t()}` ORDER BY `{$orderBy}` {$direction}"
        );
    }

    public function setPasswordResetToken(int $id, string $token, string $expiresAt): void
    {
        $this->db->execute(
            "UPDATE `{$this->t()}` SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?",
            [$token, $expiresAt, $id]
        );
    }

    public function findByResetToken(string $token): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM `{$this->t()}` WHERE reset_token = ? AND reset_token_expires_at > NOW() LIMIT 1",
            [$token]
        );
    }

    public function updatePasswordAndClearToken(int $id, string $passwordHash): void
    {
        $this->db->execute(
            "UPDATE `{$this->t()}` SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?",
            [$passwordHash, $id]
        );
    }
}
