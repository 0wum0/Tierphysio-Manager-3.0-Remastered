<?php

declare(strict_types=1);

namespace Plugins\PatientIntake;

use App\Core\Database;

class IntakeRepository
{
    private const TABLE = 'patient_intake_submissions';

    public function __construct(private readonly Database $db) {}

    public function create(array $data): int
    {
        $cols         = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->db->execute(
            "INSERT INTO `" . self::TABLE . "` ($cols) VALUES ($placeholders)",
            array_values($data)
        );
        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM `" . self::TABLE . "` WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    public function findAll(string $status = '', int $limit = 100, int $offset = 0): array
    {
        if ($status !== '') {
            return $this->db->fetchAll(
                "SELECT * FROM `" . self::TABLE . "` WHERE status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
                [$status, $limit, $offset]
            );
        }
        return $this->db->fetchAll(
            "SELECT * FROM `" . self::TABLE . "` ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function countByStatus(string $status): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `" . self::TABLE . "` WHERE status = ?",
            [$status]
        );
    }

    public function countUnread(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `" . self::TABLE . "` WHERE status IN ('neu','in_bearbeitung')",
            []
        );
    }

    public function getLatestUnread(int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT id, patient_name, owner_first_name, owner_last_name, created_at
             FROM `" . self::TABLE . "`
             WHERE status IN ('neu','in_bearbeitung')
             ORDER BY created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    public function updateStatus(int $id, string $status, array $extra = []): void
    {
        $extra['status']     = $status;
        $extra['updated_at'] = date('Y-m-d H:i:s');
        $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($extra)));
        $this->db->execute(
            "UPDATE `" . self::TABLE . "` SET $set WHERE id = ?",
            [...array_values($extra), $id]
        );
    }

    public function getPaginated(int $page, int $perPage, string $status = ''): array
    {
        $offset = ($page - 1) * $perPage;

        if ($status !== '') {
            $total = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `" . self::TABLE . "` WHERE status = ?",
                [$status]
            );
            $items = $this->db->fetchAll(
                "SELECT * FROM `" . self::TABLE . "` WHERE status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
                [$status, $perPage, $offset]
            );
        } else {
            $total = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `" . self::TABLE . "`",
                []
            );
            $items = $this->db->fetchAll(
                "SELECT * FROM `" . self::TABLE . "` ORDER BY created_at DESC LIMIT ? OFFSET ?",
                [$perPage, $offset]
            );
        }

        return [
            'items'        => $items,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int)ceil($total / $perPage),
        ];
    }
}
