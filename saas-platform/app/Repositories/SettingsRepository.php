<?php

declare(strict_types=1);

namespace Saas\Repositories;

use Saas\Core\Database;

class SettingsRepository
{
    private array $cache = [];

    public function __construct(private Database $db) {}

    public function all(): array
    {
        try {
            $rows = $this->db->fetchAll("SELECT * FROM saas_settings ORDER BY `group`, `key`");
        } catch (\Throwable) {
            return [];
        }
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['group']][$row['key']] = $row;
        }
        return $grouped;
    }

    public function allFlat(): array
    {
        try {
            $rows = $this->db->fetchAll("SELECT `key`, `value` FROM saas_settings");
        } catch (\Throwable) {
            return [];
        }
        $flat = [];
        foreach ($rows as $r) { $flat[$r['key']] = $r['value']; }
        return $flat;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->cache[$key])) return $this->cache[$key];
        try {
            $val = $this->db->fetchColumn("SELECT `value` FROM saas_settings WHERE `key` = ?", [$key]);
        } catch (\Throwable) {
            return $default;
        }
        $this->cache[$key] = ($val !== false) ? $val : $default;
        return $this->cache[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->cache[$key] = $value;
        try {
            $this->db->execute(
                "INSERT INTO saas_settings (`key`, `value`, `updated_at`)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()",
                [$key, (string)$value]
            );
        } catch (\Throwable) {}
    }

    public function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function getGroup(string $group): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT `key`, `value`, `type`, `label` FROM saas_settings WHERE `group` = ? ORDER BY `key`",
                [$group]
            );
        } catch (\Throwable) {
            return [];
        }
        $result = [];
        foreach ($rows as $r) { $result[$r['key']] = $r; }
        return $result;
    }
}
