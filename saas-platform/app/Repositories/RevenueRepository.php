<?php

declare(strict_types=1);

namespace Saas\Repositories;

use Saas\Core\Database;

/**
 * RevenueRepository
 * ──────────────────
 * Alle Queries für das Revenue Dashboard.
 * Datenquellen: subscriptions, tenants, plans, saas_invoices, revenue_snapshots
 */
class RevenueRepository
{
    public function __construct(private readonly Database $db) {}

    // ── KPIs ─────────────────────────────────────────────────────────────────

    /**
     * Monthly Recurring Revenue – Summe der aktiven Monatsabonnements.
     */
    public function mrr(): float
    {
        $row = $this->db->fetch("
            SELECT COALESCE(SUM(
                CASE s.billing_cycle
                    WHEN 'yearly'  THEN p.price_year  / 12
                    ELSE p.price_month
                END
            ), 0) AS mrr
            FROM subscriptions s
            JOIN plans p ON p.id = s.plan_id
            WHERE s.status = 'active'
              AND s.plan_id IS NOT NULL
        ");
        return round((float)($row['mrr'] ?? 0), 2);
    }

    /**
     * Annual Recurring Revenue.
     */
    public function arr(): float
    {
        return round($this->mrr() * 12, 2);
    }

    /**
     * Active paying tenants (status = active).
     */
    public function activeCount(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM tenants WHERE status = 'active'"
        );
    }

    /**
     * Trial tenants.
     */
    public function trialCount(): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM tenants WHERE status = 'trial'"
        );
    }

    /**
     * Churned this month (status became cancelled/expired).
     */
    public function churnedThisMonth(): int
    {
        return (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM tenants
            WHERE status IN ('cancelled','expired','suspended')
              AND updated_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
    }

    /**
     * Trial-to-Paid conversion rate (last 90 days), as a percentage 0-100.
     */
    public function trialConversionRate(): float
    {
        $total = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM tenants
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        if ($total === 0) return 0.0;

        $converted = (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM tenants
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
              AND status = 'active'
        ");
        return round($converted / $total * 100, 1);
    }

    /**
     * Average Revenue Per User (MRR / active tenants).
     */
    public function arpu(): float
    {
        $active = $this->activeCount();
        return $active > 0 ? round($this->mrr() / $active, 2) : 0.0;
    }

    // ── Chart Data ────────────────────────────────────────────────────────────

    /**
     * MRR trend – last N months from revenue_snapshots.
     * Returns [ ['month'=>'2025-01', 'mrr'=>1234.50], … ]
     */
    public function mrrTrend(int $months = 12): array
    {
        try {
            return $this->db->fetchAll("
                SELECT DATE_FORMAT(snapshot_date, '%Y-%m') AS month,
                       MAX(mrr)                             AS mrr
                FROM revenue_snapshots
                WHERE snapshot_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY month
                ORDER BY month ASC
            ", [$months]);
        } catch (\Throwable) {
            return $this->mrrTrendFallback($months);
        }
    }

    /**
     * Fallback: compute MRR trend from subscriptions (less accurate but works without snapshots).
     */
    private function mrrTrendFallback(int $months): array
    {
        $rows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $label = date('Y-m', strtotime("-{$i} months"));
            $rows[] = ['month' => $label, 'mrr' => 0];
        }
        return $rows;
    }

    /**
     * New tenants per month – last N months.
     * Returns [ ['month'=>'2025-01', 'count'=>5], … ]
     */
    public function newTenantsPerMonth(int $months = 12): array
    {
        return $this->db->fetchAll("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                   COUNT(*)                          AS count
            FROM tenants
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY month
            ORDER BY month ASC
        ", [$months]);
    }

    /**
     * Churn per month – last N months.
     * Returns [ ['month'=>'2025-01', 'count'=>2], … ]
     */
    public function churnPerMonth(int $months = 12): array
    {
        return $this->db->fetchAll("
            SELECT DATE_FORMAT(updated_at, '%Y-%m') AS month,
                   COUNT(*)                          AS count
            FROM tenants
            WHERE status IN ('cancelled','expired')
              AND updated_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY month
            ORDER BY month ASC
        ", [$months]);
    }

    /**
     * Revenue by plan – current active subscriptions.
     * Returns [ ['plan_name'=>'Pro', 'count'=>10, 'mrr'=>990.0], … ]
     */
    public function revenueByPlan(): array
    {
        return $this->db->fetchAll("
            SELECT p.name  AS plan_name,
                   p.slug  AS plan_slug,
                   COUNT(s.id) AS sub_count,
                   COALESCE(SUM(
                       CASE s.billing_cycle
                           WHEN 'yearly' THEN p.price_year / 12
                           ELSE p.price_month
                       END
                   ), 0) AS mrr
            FROM subscriptions s
            JOIN plans p ON p.id = s.plan_id
            WHERE s.status = 'active'
            GROUP BY p.id, p.name, p.slug
            ORDER BY mrr DESC
        ");
    }

    /**
     * Recent invoices (paid), last 30 days.
     */
    public function recentRevenue(int $limit = 10): array
    {
        try {
            return $this->db->fetchAll("
                SELECT i.*, t.practice_name
                FROM saas_invoices i
                JOIN tenants t ON t.id = i.tenant_id
                WHERE i.status = 'paid'
                  AND i.paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY i.paid_at DESC
                LIMIT ?
            ", [$limit]);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Total revenue collected (all paid invoices).
     */
    public function totalRevenue(): float
    {
        try {
            $val = $this->db->fetchColumn(
                "SELECT COALESCE(SUM(total_amount), 0) FROM saas_invoices WHERE status = 'paid'"
            );
            return round((float)$val, 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Revenue this month.
     */
    public function revenueThisMonth(): float
    {
        try {
            $val = $this->db->fetchColumn("
                SELECT COALESCE(SUM(total_amount), 0) FROM saas_invoices
                WHERE status = 'paid'
                  AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            return round((float)$val, 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
