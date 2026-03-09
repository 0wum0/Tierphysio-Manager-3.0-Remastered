<?php

declare(strict_types=1);

namespace Saas\Repositories;

use Saas\Core\Database;

class AdminRepository
{
    public function __construct(private Database $db) {}

    public function findByEmail(string $email): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM saas_admins WHERE email = ? AND is_active = 1",
            [$email]
        );
    }

    public function find(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM saas_admins WHERE id = ?",
            [$id]
        );
    }

    public function all(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, email, role, is_active, last_login, created_at FROM saas_admins ORDER BY created_at ASC"
        );
    }

    public function create(array $data): int
    {
        return (int)$this->db->insert(
            "INSERT INTO saas_admins (name, email, password, role) VALUES (:name, :email, :password, :role)",
            $data
        );
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->execute("UPDATE saas_admins SET last_login = NOW() WHERE id = ?", [$id]);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->db->execute("UPDATE saas_admins SET password = ? WHERE id = ?", [$hash, $id]);
    }

    public function count(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM saas_admins");
    }
}
