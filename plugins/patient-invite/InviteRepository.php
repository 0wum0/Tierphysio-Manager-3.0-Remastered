<?php

declare(strict_types=1);

namespace Plugins\PatientInvite;

use App\Core\Database;

class InviteRepository
{
    public function __construct(private readonly Database $db) {}

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    public function create(array $data): int
    {
        $cols         = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->db->execute(
            "INSERT INTO `{$this->t('patient_invite_tokens')}` ($cols) VALUES ($placeholders)",
            array_values($data)
        );
        return (int)$this->db->lastInsertId();
    }

    public function findByToken(string $token): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM `{$this->t('patient_invite_tokens')}` WHERE token = ? LIMIT 1",
            [$token]
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM `{$this->t('patient_invite_tokens')}` WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    public function getPaginated(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('patient_invite_tokens')}` WHERE hidden_at IS NULL"
        );
        $items = $this->db->fetchAll(
            "SELECT t.*, u.name AS created_by_name
             FROM `{$this->t('patient_invite_tokens')}` t
             LEFT JOIN `{$this->t('users')}` u ON t.created_by = u.id
             WHERE t.hidden_at IS NULL
             ORDER BY t.created_at DESC
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
        return [
            'items'        => $items,
            'total'        => $total,
            'current_page' => $page,
            'last_page'    => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * Blendet eine Einladung aus der Liste aus (Soft-Delete).
     * Der Datensatz bleibt in der DB erhalten — der übernommene Patient/Hund
     * und Besitzer/Tierhalter werden NICHT gelöscht.
     */
    public function hide(int $id): void
    {
        $this->db->execute(
            "UPDATE `{$this->t('patient_invite_tokens')}` SET hidden_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function accept(string $token, int $patientId, int $ownerId): void
    {
        $this->db->execute(
            "UPDATE `{$this->t('patient_invite_tokens')}`
             SET status = 'angenommen',
                 accepted_at = NOW(),
                 accepted_patient_id = ?,
                 accepted_owner_id = ?
             WHERE token = ?",
            [$patientId, $ownerId, $token]
        );
    }

    public function expireOld(): void
    {
        $this->db->execute(
            "UPDATE `{$this->t('patient_invite_tokens')}`
             SET status = 'abgelaufen'
             WHERE status = 'offen' AND expires_at < NOW()"
        );
    }

    public function countByStatus(string $status): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('patient_invite_tokens')}` WHERE status = ? AND hidden_at IS NULL",
            [$status]
        );
    }

    public function isTokenValid(string $token): bool
    {
        $row = $this->findByToken($token);
        if (!$row) return false;
        if ($row['status'] !== 'offen') return false;
        if (strtotime($row['expires_at']) < time()) return false;
        return true;
    }
}
