<?php

declare(strict_types=1);

namespace Saas\Repositories;

use Saas\Core\Database;

class ActivityLogRepository
{
    public function __construct(private Database $db) {}

    public function log(string $action, string $actor = 'admin', ?string $subject = null, ?int $subjectId = null, ?string $detail = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->db->execute(
            "INSERT INTO saas_activity_log (actor, action, subject, subject_id, detail, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$actor, $action, $subject, $subjectId, $detail, $ip]
        );
    }

    public function getPaginated(int $page = 1, int $perPage = 50, string $action = '', string $actor = ''): array
    {
        $conditions = [];
        $params     = [];

        if ($action !== '') { $conditions[] = "action LIKE ?"; $params[] = "%{$action}%"; }
        if ($actor  !== '') { $conditions[] = "actor LIKE ?";  $params[] = "%{$actor}%"; }

        $where  = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $offset = ($page - 1) * $perPage;

        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM saas_activity_log {$where}", $params);
        $items = $this->db->fetchAll(
            "SELECT * FROM saas_activity_log {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );

        return [
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'last_page' => max(1, (int)ceil($total / $perPage)),
            'has_next'  => ($page * $perPage) < $total,
            'has_prev'  => $page > 1,
        ];
    }

    public function getRecent(int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM saas_activity_log ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }

    public function purgeOlderThan(int $days): int
    {
        $this->db->execute(
            "DELETE FROM saas_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        return 0;
    }
}
