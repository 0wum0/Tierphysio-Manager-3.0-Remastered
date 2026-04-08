<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class UserPreferencesRepository
{
    public function __construct(private readonly Database $db) {}

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    public function get(int $userId, string $key, mixed $default = null): mixed
    {
        $row = $this->db->fetch(
            "SELECT `value` FROM `{$this->t('user_preferences')}` WHERE user_id = ? AND `key` = ?",
            [$userId, $key]
        );
        return $row ? $row['value'] : $default;
    }

    public function set(int $userId, string $key, string $value): void
    {
        $this->db->execute(
            "INSERT INTO `{$this->t('user_preferences')}` (user_id, `key`, `value`)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$userId, $key, $value]
        );
    }

    public function getAll(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT `key`, `value` FROM `{$this->t('user_preferences')}` WHERE user_id = ?",
            [$userId]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }
}
