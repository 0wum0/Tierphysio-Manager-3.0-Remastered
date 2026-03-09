<?php

declare(strict_types=1);

namespace Saas\Repositories;

use Saas\Core\Database;

class PlanRepository
{
    public function __construct(private Database $db) {}

    public function all(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM plans ORDER BY sort_order ASC"
        );
    }

    public function allActive(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC"
        );
    }

    public function find(int $id): array|false
    {
        return $this->db->fetch("SELECT * FROM plans WHERE id = ?", [$id]);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetch("SELECT * FROM plans WHERE slug = ?", [$slug]);
    }

    public function update(int $id, array $data): void
    {
        $sets   = [];
        $params = [];
        foreach ($data as $key => $value) {
            $sets[]       = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }
        $params['id'] = $id;
        $this->db->execute("UPDATE plans SET " . implode(', ', $sets) . " WHERE id = :id", $params);
    }

    public function getFeatures(int $planId): array
    {
        $plan = $this->find($planId);
        if (!$plan) return [];
        $features = json_decode($plan['features'] ?? '[]', true);
        return is_array($features) ? $features : [];
    }
}
