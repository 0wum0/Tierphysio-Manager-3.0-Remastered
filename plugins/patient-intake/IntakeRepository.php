<?php

declare(strict_types=1);

namespace Plugins\PatientIntake;

use App\Core\Database;

class IntakeRepository
{
    private const TABLE = 'patient_intake_submissions';

    public function __construct(private readonly Database $db) {}

    /** Liefert den tenant-präfixierten Tabellennamen (z. B. `t_therapano_2eff77_patient_intake_submissions`). */
    private function t(): string
    {
        return $this->db->prefix(self::TABLE);
    }

    public function create(array $data): int
    {
        $cols         = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->db->execute(
            "INSERT INTO `" . $this->t() . "` ($cols) VALUES ($placeholders)",
            array_values($data)
        );
        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM `" . $this->t() . "` WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    public function findAll(string $status = '', int $limit = 100, int $offset = 0): array
    {
        if ($status !== '') {
            return $this->db->fetchAll(
                "SELECT * FROM `" . $this->t() . "` WHERE status = ? AND hidden_at IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?",
                [$status, $limit, $offset]
            );
        }
        return $this->db->fetchAll(
            "SELECT * FROM `" . $this->t() . "` WHERE hidden_at IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function countByStatus(string $status): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `" . $this->t() . "` WHERE status = ? AND hidden_at IS NULL",
            [$status]
        );
    }

    public function countUnread(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `" . $this->t() . "` WHERE status IN ('neu','in_bearbeitung') AND hidden_at IS NULL",
            []
        );
    }

    public function getLatestUnread(int $limit = 5): array
    {
        return $this->db->fetchAll(
            "SELECT id, patient_name, owner_first_name, owner_last_name, created_at
             FROM `" . $this->t() . "`
             WHERE status IN ('neu','in_bearbeitung') AND hidden_at IS NULL
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
            "UPDATE `" . $this->t() . "` SET $set WHERE id = ?",
            [...array_values($extra), $id]
        );
    }

    /**
     * Blendet einen Eintrag aus der Inbox aus (Soft-Delete).
     * Der Datensatz bleibt in der DB erhalten — Besitzer/Patient werden NICHT gelöscht.
     */
    public function hide(int $id): void
    {
        $this->db->execute(
            "UPDATE `" . $this->t() . "` SET hidden_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function getPaginated(int $page, int $perPage, string $status = ''): array
    {
        $offset = ($page - 1) * $perPage;

        if ($status !== '') {
            $total = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `" . $this->t() . "` WHERE status = ? AND hidden_at IS NULL",
                [$status]
            );
            $items = $this->db->fetchAll(
                "SELECT * FROM `" . $this->t() . "` WHERE status = ? AND hidden_at IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?",
                [$status, $perPage, $offset]
            );
        } else {
            $total = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `" . $this->t() . "` WHERE hidden_at IS NULL",
                []
            );
            $items = $this->db->fetchAll(
                "SELECT * FROM `" . $this->t() . "` WHERE hidden_at IS NULL ORDER BY created_at DESC LIMIT ? OFFSET ?",
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
