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
        $data = array_merge([
            'trial_starts_at'      => null,
            'trial_ends_at'        => null,
            'billing_starts_at'    => null,
            'grandfathered_price'  => null,
            'grandfathered_reason' => null,
            'pricing_note'         => null,
            'stripe_price_id'      => null,
            'last_webhook_sync_at' => null,
        ], $data);

        return (int)$this->db->insert(
            "INSERT INTO subscriptions
             (tenant_id, plan_id, billing_cycle, status, started_at, ends_at, next_billing, amount, currency,
              payment_method, external_id, trial_starts_at, trial_ends_at, billing_starts_at,
              grandfathered_price, grandfathered_reason, pricing_note, stripe_price_id, last_webhook_sync_at)
             VALUES
             (:tenant_id, :plan_id, :billing_cycle, :status, :started_at, :ends_at, :next_billing, :amount, :currency,
              :payment_method, :external_id, :trial_starts_at, :trial_ends_at, :billing_starts_at,
              :grandfathered_price, :grandfathered_reason, :pricing_note, :stripe_price_id, :last_webhook_sync_at)",
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

    /**
     * Find the most recent active subscription for a tenant.
     * Returns false if none is found or if the status is non-active.
     */
    public function findActiveForTenant(int $tenantId): array|false
    {
        return $this->db->fetch(
            "SELECT s.*, p.name AS plan_name, p.slug AS plan_slug
             FROM subscriptions s
             LEFT JOIN plans p ON p.id = s.plan_id
             WHERE s.tenant_id = ?
               AND s.status IN ('active', 'trial', 'trialing')
             ORDER BY s.created_at DESC
             LIMIT 1",
            [$tenantId]
        );
    }

    /**
     * Update only the status field of a subscription.
     */
    public function setStatus(int $id, string $status): void
    {
        $this->db->execute(
            "UPDATE subscriptions SET status = ? WHERE id = ?",
            [$status, $id]
        );
    }

    /**
     * Count subscriptions by status.
     */
    public function countByStatus(string $status): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM subscriptions WHERE status = ?",
            [$status]
        );
    }

    /**
     * Count subscriptions with active grandfathered pricing.
     */
    public function countGrandfathered(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM subscriptions WHERE grandfathered_price IS NOT NULL AND status = 'active'"
        );
    }

    /**
     * Return all subscriptions that are in trial and have passed their trial_ends_at.
     */
    public function findExpiredTrials(): array
    {
        return $this->db->fetchAll(
            "SELECT s.id, s.tenant_id, s.trial_ends_at, t.practice_name
             FROM subscriptions s
             JOIN tenants t ON t.id = s.tenant_id
             WHERE s.status = 'trial'
               AND s.trial_ends_at IS NOT NULL
               AND s.trial_ends_at < NOW()",
            []
        );
    }
}
