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

    /**
     * Return only public plans (is_public = 1, is_active = 1) for registration.
     */
    public function allPublic(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM plans WHERE is_active = 1 AND is_public = 1 ORDER BY sort_order ASC"
        );
    }

    /**
     * Create a new plan record. Returns the new plan ID.
     */
    public function create(array $data): int
    {
        $data = array_merge([
            'trial_days'           => 14,
            'is_public'            => 1,
            'currency'             => 'EUR',
            'stripe_price_id'      => null,
            'stripe_price_id_yearly' => null,
            'is_active'            => 1,
            'sort_order'           => $this->nextSortOrder(),
            'description'          => null,
        ], $data);

        return (int)$this->db->insert(
            "INSERT INTO plans
             (slug, name, description, price_month, price_year, max_users, features,
              is_active, is_public, trial_days, currency, stripe_price_id, stripe_price_id_yearly, sort_order)
             VALUES
             (:slug, :name, :description, :price_month, :price_year, :max_users, :features,
              :is_active, :is_public, :trial_days, :currency, :stripe_price_id, :stripe_price_id_yearly, :sort_order)",
            $data
        );
    }

    /**
     * Toggle the is_active flag for a plan.
     */
    public function toggleActive(int $id): void
    {
        $this->db->execute(
            "UPDATE plans SET is_active = NOT is_active WHERE id = ?",
            [$id]
        );
    }

    /**
     * Return the next available sort_order value.
     */
    private function nextSortOrder(): int
    {
        $max = $this->db->fetchColumn("SELECT COALESCE(MAX(sort_order), 0) FROM plans");
        return (int)$max + 10;
    }
}
