<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

class LeadRepository extends Repository
{
    protected string $table = 'dogschool_leads';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function listPaginated(int $page, int $perPage, string $status = '', string $search = ''): array
    {
        $conds  = [];
        $params = [];
        if ($status !== '' && $status !== 'all') {
            $conds[]  = 'status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $conds[]  = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR dog_name LIKE ?)';
            $s        = "%{$search}%";
            $params   = array_merge($params, [$s, $s, $s, $s, $s]);
        }
        $where  = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
        $offset = max(0, ($page - 1) * $perPage);

        $total = (int)$this->db->safeFetchColumn(
            "SELECT COUNT(*) FROM `{$this->t()}` {$where}",
            $params
        );
        $items = $this->db->safeFetchAll(
            "SELECT * FROM `{$this->t()}` {$where}
              ORDER BY FIELD(status,'new','contacted','trial_scheduled','trial_done','converted','lost','archived'),
                       next_followup_at IS NULL, next_followup_at ASC,
                       created_at DESC
              LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );
        return [
            'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage,
            'last_page' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    public function countByStatus(): array
    {
        $rows = $this->db->safeFetchAll(
            "SELECT status, COUNT(*) AS n FROM `{$this->t()}` GROUP BY status"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['n'];
        }
        return $out;
    }
}
