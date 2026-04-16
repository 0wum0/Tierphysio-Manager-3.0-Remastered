<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

class SettingsRepository extends Repository
{
    protected string $table = 'settings';
    protected string $primaryKey = 'key';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->db->fetch("SELECT value FROM `{$this->t()}` WHERE `key` = ?", [$key]);
        return $row !== false ? $row['value'] : $default;
    }

    public function set(string $key, string $value): void
    {
        $this->db->execute(
            "INSERT INTO `{$this->t()}` (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$key, $value]
        );
    }

    public function all(): array
    {
        $rows   = $this->db->fetchAll("SELECT `key`, value FROM `{$this->t()}`");
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }
}
