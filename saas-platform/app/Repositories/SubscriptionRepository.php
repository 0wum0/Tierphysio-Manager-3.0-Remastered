<?php

declare(strict_types=1);

namespace Saas\Repositories;

use Saas\Core\Database;

class SubscriptionRepository
{
    public function __construct(private Database $db) {}

    public function findByTenant(int $tenantId): array|false
    {
        return $this->db->fetch(
            "SELECT s.*, p.name AS plan_name, p.slug AS plan_slug
             FROM subscriptions s
             LEFT JOIN plans p ON p.id = s.plan_id
             WHERE s.tenant_id = ?
             ORDER BY s.created_at DESC
             LIMIT 1",
            [$tenantId]
        );
    }

    public function allByTenant(int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT s.*, p.name AS plan_name
             FROM subscriptions s
             LEFT JOIN plans p ON p.id = s.plan_id
             WHERE s.tenant_id = ?
             ORDER BY s.created_at DESC",
            [$tenantId]
        );
    }

    public function create(array $data): int
    {
        return (int)$this->db->insert(
            "INSERT INTO subscriptions
             (tenant_id, plan_id, billing_cycle, status, started_at, ends_at, next_billing, amount, currency, payment_method, external_id)
             VALUES
             (:tenant_id, :plan_id, :billing_cycle, :status, :started_at, :ends_at, :next_billing, :amount, :currency, :payment_method, :external_id)",
            $data
        );
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
        $this->db->execute("UPDATE subscriptions SET " . implode(', ', $sets) . " WHERE id = :id", $params);
    }

    public function cancel(int $id): void
    {
        $this->db->execute(
            "UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function countActive(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM subscriptions WHERE status = 'active'"
        );
    }

    public function revenueThisMonth(): float
    {
        $result = $this->db->fetchColumn(
            "SELECT SUM(amount) FROM subscriptions
             WHERE status = 'active' AND billing_cycle = 'monthly'"
        );
        return (float)($result ?? 0);
    }

    public function revenueThisYear(): float
    {
        $monthly = (float)($this->db->fetchColumn(
            "SELECT SUM(amount) FROM subscriptions WHERE status = 'active' AND billing_cycle = 'monthly'"
        ) ?? 0);
        $yearly = (float)($this->db->fetchColumn(
            "SELECT SUM(amount) FROM subscriptions WHERE status = 'active' AND billing_cycle = 'yearly'"
        ) ?? 0);
        return round($monthly * 12 + $yearly, 2);
    }
}
