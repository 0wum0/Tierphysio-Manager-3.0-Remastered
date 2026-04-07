<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

class PatientRepository extends Repository
{
    protected string $table = 'patients';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function search(string $query): array
    {
        $p = $this->t('patients');
        $o = $this->t('owners');
        return $this->db->fetchAll(
            "SELECT p.*, CONCAT(o.first_name, ' ', o.last_name) AS owner_name
             FROM `{$p}` p
             LEFT JOIN `{$o}` o ON p.owner_id = o.id
             WHERE p.name LIKE ? OR p.species LIKE ? OR p.breed LIKE ? OR o.last_name LIKE ?
             ORDER BY p.name ASC",
            ["%{$query}%", "%{$query}%", "%{$query}%", "%{$query}%"]
        );
    }

    public function findByOwner(int $ownerId): array
    {
        $patients = $this->db->fetchAll(
            "SELECT * FROM `{$this->t('patients')}` WHERE owner_id = ? ORDER BY name ASC",
            [$ownerId]
        );
        return $patients;
    }

    public function findWithOwner(int $id): array|false
    {
        $p = $this->t('patients');
        $o = $this->t('owners');
        return $this->db->fetch(
            "SELECT p.*, o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                    o.email AS owner_email, o.phone AS owner_phone
             FROM `{$p}` p
             LEFT JOIN `{$o}` o ON p.owner_id = o.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function getPaginated(int $page, int $perPage, string $search = '', string $filter = ''): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($search)) {
            $conditions[] = "(p.name LIKE ? OR p.species LIKE ? OR p.breed LIKE ? OR CONCAT(o.first_name, ' ', o.last_name) LIKE ?)";
            $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
        }

        if (!empty($filter)) {
            $conditions[] = "p.status = ?";
            $params[]     = $filter;
        }

        $where  = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $offset = ($page - 1) * $perPage;

        $p = $this->t('patients');
        $o = $this->t('owners');
        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$p}` p LEFT JOIN `{$o}` o ON p.owner_id = o.id {$where}",
            $params
        );

        $items = $this->db->fetchAll(
            "SELECT p.*, CONCAT(o.first_name, ' ', o.last_name) AS owner_name
             FROM `{$p}` p
             LEFT JOIN `{$o}` o ON p.owner_id = o.id
             {$where}
             ORDER BY p.name ASC
             LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );

        return [
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
            'has_next'  => ($page * $perPage) < $total,
            'has_prev'  => $page > 1,
        ];
    }

    public function getTimeline(int $patientId, int $limit = 0): array
    {
        $limitSql = $limit > 0 ? ' LIMIT ' . $limit : '';
        try {
            $tl = $this->t('patient_timeline');
            $u  = $this->t('users');
            $tt = $this->t('treatment_types');
            return $this->db->fetchAll(
                "SELECT t.*, u.name AS user_name,
                        tt.name AS treatment_type_name, tt.color AS treatment_type_color
                 FROM `{$tl}` t
                 LEFT JOIN `{$u}` u ON t.user_id = u.id
                 LEFT JOIN `{$tt}` tt ON t.treatment_type_id = tt.id
                 WHERE t.patient_id = ?
                 ORDER BY t.entry_date DESC" . $limitSql,
                [$patientId]
            );
        } catch (\Throwable) {
            $tl = $this->t('patient_timeline');
            $u  = $this->t('users');
            return $this->db->fetchAll(
                "SELECT t.*, u.name AS user_name
                 FROM `{$tl}` t
                 LEFT JOIN `{$u}` u ON t.user_id = u.id
                 WHERE t.patient_id = ?
                 ORDER BY t.entry_date DESC" . $limitSql,
                [$patientId]
            );
        }
    }

    public function addTimelineEntry(array $data): string
    {
        try {
            return $this->db->insert(
                "INSERT INTO `{$this->t('patient_timeline')}` (patient_id, type, treatment_type_id, title, content, status_badge, attachment, entry_date, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $data['patient_id'],
                    $data['type'],
                    $data['treatment_type_id'] ?? null,
                    $data['title'],
                    $data['content'],
                    $data['status_badge'] ?? null,
                    $data['attachment'] ?? null,
                    $data['entry_date'],
                    $data['user_id'],
                ]
            );
        } catch (\Throwable) {
            return $this->db->insert(
                "INSERT INTO `{$this->t('patient_timeline')}` (patient_id, type, title, content, status_badge, attachment, entry_date, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $data['patient_id'],
                    $data['type'],
                    $data['title'],
                    $data['content'],
                    $data['status_badge'] ?? null,
                    $data['attachment'] ?? null,
                    $data['entry_date'],
                    $data['user_id'],
                ]
            );
        }
    }

    public function getTimelineEntry(int $entryId): ?array
    {
        $tl = $this->t('patient_timeline');
        $u  = $this->t('users');
        $tt = $this->t('treatment_types');
        $row = $this->db->fetchAll(
            "SELECT t.*, u.name AS user_name, tt.name AS treatment_type_name, tt.color AS treatment_type_color
             FROM `{$tl}` t
             LEFT JOIN `{$u}` u ON t.user_id = u.id
             LEFT JOIN `{$tt}` tt ON t.treatment_type_id = tt.id
             WHERE t.id = ?",
            [$entryId]
        );
        return $row[0] ?? null;
    }

    public function updateTimelineEntry(int $entryId, array $data): void
    {
        $this->db->execute(
            "UPDATE `{$this->t('patient_timeline')}` SET type=?, treatment_type_id=?, title=?, content=?, status_badge=?, entry_date=? WHERE id=?",
            [
                $data['type'],
                $data['treatment_type_id'] ?: null,
                $data['title'],
                $data['content'],
                $data['status_badge'],
                $data['entry_date'],
                $entryId,
            ]
        );
    }

    public function deleteTimelineEntry(int $entryId): void
    {
        $this->db->execute("DELETE FROM `{$this->t('patient_timeline')}` WHERE id = ?", [$entryId]);
    }

    public function countNew(string $since = '30 days ago'): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('patients')}` WHERE created_at >= ?",
            [date('Y-m-d H:i:s', strtotime($since))]
        );
    }
}
