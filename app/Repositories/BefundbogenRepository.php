<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

class BefundbogenRepository extends Repository
{
    protected string $table = 'befundboegen';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function findByPatient(int $patientId): array
    {
        $b = $this->t('befundboegen'); $u = $this->t('users');
        return $this->db->fetchAll(
            "SELECT b.*, u.name AS ersteller_name
             FROM `{$b}` b
             LEFT JOIN `{$u}` u ON u.id = b.created_by
             WHERE b.patient_id = ?
             ORDER BY b.datum DESC, b.created_at DESC",
            [$patientId]
        );
    }

    public function findByOwner(int $ownerId): array
    {
        $b = $this->t('befundboegen'); $p = $this->t('patients'); $u = $this->t('users');
        return $this->db->fetchAll(
            "SELECT b.*, p.name AS patient_name, p.species AS patient_species,
                    u.name AS ersteller_name
             FROM `{$b}` b
             LEFT JOIN `{$p}` p ON p.id = b.patient_id
             LEFT JOIN `{$u}` u ON u.id = b.created_by
             WHERE b.owner_id = ? AND b.status != 'entwurf'
             ORDER BY b.datum DESC, b.created_at DESC",
            [$ownerId]
        );
    }

    public function findAllWithDetails(string $search = '', string $statusFilter = ''): array
    {
        $conditions = [];
        $params     = [];

        if ($statusFilter !== '') {
            $conditions[] = 'b.status = ?';
            $params[]     = $statusFilter;
        }

        if ($search !== '') {
            $conditions[] = '(p.name LIKE ? OR CONCAT(o.first_name,\' \',o.last_name) LIKE ?)';
            $params[]     = "%{$search}%";
            $params[]     = "%{$search}%";
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $b = $this->t('befundboegen'); $p = $this->t('patients'); $o = $this->t('owners'); $u = $this->t('users');
        return $this->db->fetchAll(
            "SELECT b.*, p.name AS patient_name, p.species AS patient_species,
                    CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                    u.name AS ersteller_name
             FROM `{$b}` b
             LEFT JOIN `{$p}` p ON p.id = b.patient_id
             LEFT JOIN `{$o}` o ON o.id = b.owner_id
             LEFT JOIN `{$u}` u ON u.id = b.created_by
             {$where}
             ORDER BY b.datum DESC, b.created_at DESC",
            $params
        );
    }

    public function createBefund(array $data): int
    {
        $this->db->execute(
            "INSERT INTO `{$this->t('befundboegen')}`
                (patient_id, owner_id, created_by, status, datum, naechster_termin, notizen)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['patient_id'],
                $data['owner_id']         ?? null,
                $data['created_by']       ?? null,
                $data['status']           ?? 'entwurf',
                $data['datum'],
                $data['naechster_termin'] ?? null,
                $data['notizen']          ?? null,
            ]
        );
        return (int)$this->db->getPdo()->lastInsertId();
    }

    public function updateBefund(int $id, array $data): void
    {
        $allowed = ['status', 'datum', 'naechster_termin', 'notizen', 'pdf_path', 'pdf_sent_at', 'pdf_sent_to'];
        $sets    = [];
        $params  = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]  = "`{$col}` = ?";
                $params[] = $data[$col];
            }
        }

        if (empty($sets)) return;

        $params[] = $id;
        $this->db->execute(
            "UPDATE `{$this->t('befundboegen')}` SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );
    }

    public function deleteBefund(int $id): void
    {
        $this->db->execute("DELETE FROM `{$this->t('befundboegen')}` WHERE id = ?", [$id]);
    }

    public function saveFelder(int $befundbogenId, array $felder): void
    {
        $this->db->execute(
            "DELETE FROM `{$this->t('befundbogen_felder')}` WHERE befundbogen_id = ?",
            [$befundbogenId]
        );

        foreach ($felder as $feldname => $feldwert) {
            $wert = is_array($feldwert) ? json_encode($feldwert, JSON_UNESCAPED_UNICODE) : (string)$feldwert;
            if ($wert === '' || $wert === '[]' || $wert === 'null') continue;
            $this->db->execute(
                "INSERT INTO `{$this->t('befundbogen_felder')}` (befundbogen_id, feldname, feldwert) VALUES (?, ?, ?)",
                [$befundbogenId, $feldname, $wert]
            );
        }
    }

    public function getFelder(int $befundbogenId): array
    {
        $rows   = $this->db->fetchAll(
            "SELECT feldname, feldwert FROM `{$this->t('befundbogen_felder')}` WHERE befundbogen_id = ?",
            [$befundbogenId]
        );
        $result = [];
        foreach ($rows as $row) {
            $decoded = json_decode($row['feldwert'], true);
            $result[$row['feldname']] = ($decoded !== null && json_last_error() === JSON_ERROR_NONE)
                ? $decoded
                : $row['feldwert'];
        }
        return $result;
    }

    public function findWithFelder(int $id): ?array
    {
        $b = $this->t('befundboegen'); $u = $this->t('users'); $p = $this->t('patients'); $o = $this->t('owners');
        $row = $this->db->fetch(
            "SELECT b.*, u.name AS ersteller_name,
                    p.name AS patient_name, p.species AS patient_species,
                    CONCAT(o.first_name,' ',o.last_name) AS owner_name
             FROM `{$b}` b
             LEFT JOIN `{$u}` u ON u.id = b.created_by
             LEFT JOIN `{$p}` p ON p.id = b.patient_id
             LEFT JOIN `{$o}` o ON o.id = b.owner_id
             WHERE b.id = ? LIMIT 1",
            [$id]
        );

        if (!$row) return null;

        $row['felder'] = $this->getFelder($id);
        return $row;
    }

    public function markVersendet(int $id, string $email, string $pdfPath): void
    {
        $this->db->execute(
            "UPDATE `{$this->t('befundboegen')}`
             SET status = 'versendet', pdf_path = ?, pdf_sent_at = NOW(), pdf_sent_to = ?
             WHERE id = ?",
            [$pdfPath, $email, $id]
        );
    }
}
