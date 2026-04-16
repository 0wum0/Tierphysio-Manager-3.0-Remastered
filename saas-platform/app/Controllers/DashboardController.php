<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Database;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\SubscriptionRepository;
use Saas\Services\SaasPlatformMigrationService;

class DashboardController extends Controller
{
    public function __construct(
        View                               $view,
        Session                            $session,
        private TenantRepository            $tenantRepo,
        private SubscriptionRepository      $subRepo,
        private Database                    $db,
        private SaasPlatformMigrationService $saasMigrations
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $stats = $this->tenantRepo->getStats();
        $stats['active_subscriptions'] = $this->subRepo->countActive();
        $stats['monthly_revenue']      = $this->subRepo->revenueThisMonth();
        $stats['yearly_revenue']       = $this->subRepo->revenueThisYear();
        $stats['trial_expiring_soon']  = $this->tenantRepo->countTrialExpiringSoon(7);
        $stats['new_this_month']       = $this->tenantRepo->countNewThisMonth();
        $stats['churn_this_month']     = $this->tenantRepo->countChurnThisMonth();
        $stats['unread_feedback']      = $this->countUnreadFeedback();

        $recentTenants  = $this->tenantRepo->all(8, 0);
        $recentFeedback = $this->getRecentFeedback(5);

        // Monthly revenue for last 12 months (chart data)
        $revenueChart  = $this->buildRevenueChart();

        // Registrations per month last 12 months
        $regChart = $this->buildRegistrationsChart();

        // Plan distribution (donut)
        $planDistribution = $this->buildPlanDistribution();

        // Status distribution (donut)
        $statusDistribution = $this->buildStatusDistribution();

        // Annual forecast
        $forecast = $this->buildForecast($stats['monthly_revenue']);

        // Top tenants by subscription amount
        $topTenants = $this->getTopTenants(5);

        // Recent payments
        $recentPayments = $this->getRecentPayments(8);

        $trialExpiring   = $this->getTrialExpiringSoonList(7);
        $recentActivity  = $this->getRecentActivity(10);
        $lifecycleStats  = $this->getLifecycleStats();
        $pendingMig      = $this->saasMigrations->status()['pending'] ?? 0;
        $revenueThisMonth = $this->getRevenueThisMonth();

        $this->render('admin/dashboard.twig', [
            'stats'              => $stats,
            'recent_tenants'     => $recentTenants,
            'recent_feedback'    => $recentFeedback,
            'revenue_chart'      => $revenueChart,
            'reg_chart'          => $regChart,
            'plan_distribution'  => $planDistribution,
            'status_distribution'=> $statusDistribution,
            'forecast'           => $forecast,
            'top_tenants'        => $topTenants,
            'recent_payments'    => $recentPayments,
            'trial_expiring'     => $trialExpiring,
            'recent_activity'    => $recentActivity,
            'lifecycle_stats'    => $lifecycleStats,
            'pending_migrations' => $pendingMig,
            'revenue_this_month' => $revenueThisMonth,
            'page_title'         => 'Dashboard',
        ]);
    }

    private function buildRevenueChart(): array
    {
        $labels = [];
        $data   = [];
        for ($i = 11; $i >= 0; $i--) {
            $ts     = strtotime("-{$i} months");
            $year   = (int)date('Y', $ts);
            $month  = (int)date('n', $ts);
            $labels[] = date('M Y', $ts);

            try {
                $snapshot = $this->db->fetch(
                    "SELECT amount FROM revenue_snapshots WHERE year = ? AND month = ?",
                    [$year, $month]
                );
            } catch (\Throwable) {
                $snapshot = null;
            }

            if ($snapshot) {
                $data[] = (float)$snapshot['amount'];
            } else {
                try {
                    $sum = $this->db->fetchColumn(
                        "SELECT COALESCE(SUM(s.amount),0) FROM subscriptions s
                         WHERE s.status = 'active'
                           AND YEAR(s.started_at) <= ? AND MONTH(s.started_at) <= ?
                           AND (s.ends_at IS NULL OR (YEAR(s.ends_at) >= ? AND MONTH(s.ends_at) >= ?))",
                        [$year, $month, $year, $month]
                    );
                } catch (\Throwable) {
                    $sum = 0;
                }
                $data[] = (float)($sum ?? 0);
            }
        }
        return ['labels' => $labels, 'data' => $data];
    }

    private function buildRegistrationsChart(): array
    {
        $labels = [];
        $data   = [];
        for ($i = 11; $i >= 0; $i--) {
            $ts     = strtotime("-{$i} months");
            $year   = (int)date('Y', $ts);
            $month  = (int)date('n', $ts);
            $labels[] = date('M Y', $ts);
            $count = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM tenants WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?",
                [$year, $month]
            );
            $data[] = (int)($count ?? 0);
        }
        return ['labels' => $labels, 'data' => $data];
    }

    private function buildPlanDistribution(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT p.name, COUNT(t.id) AS cnt
             FROM tenants t
             LEFT JOIN plans p ON p.id = t.plan_id
             WHERE t.status IN ('active','trial')
             GROUP BY p.id, p.name
             ORDER BY cnt DESC"
        );
        $labels = array_column($rows, 'name');
        $data   = array_map(fn($r) => (int)$r['cnt'], $rows);
        return ['labels' => $labels, 'data' => $data];
    }

    private function buildStatusDistribution(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM tenants GROUP BY status ORDER BY cnt DESC"
        );
        $labels = array_column($rows, 'status');
        $data   = array_map(fn($r) => (int)$r['cnt'], $rows);
        return ['labels' => $labels, 'data' => $data];
    }

    private function buildForecast(float $currentMonthlyRevenue): array
    {
        $yearlyIfSame    = round($currentMonthlyRevenue * 12, 2);
        $currentMonth    = (int)date('n');
        $remainingMonths = 12 - $currentMonth;

        try {
            $ytd = (float)($this->db->fetchColumn(
                "SELECT COALESCE(SUM(amount),0) FROM revenue_snapshots WHERE year = YEAR(NOW())"
            ) ?? 0);
        } catch (\Throwable) {
            $ytd = 0;
        }
        if ($ytd == 0) {
            $ytd = $currentMonthlyRevenue * $currentMonth;
        }

        $projected = round($ytd + ($currentMonthlyRevenue * $remainingMonths), 2);
        $growth    = 0;

        try {
            $lastYear = (float)($this->db->fetchColumn(
                "SELECT COALESCE(SUM(amount),0) FROM revenue_snapshots WHERE year = YEAR(NOW()) - 1"
            ) ?? 0);
        } catch (\Throwable) {
            $lastYear = 0;
        }
        if ($lastYear > 0 && $currentMonth > 0) {
            $growth = round((($ytd / ($currentMonth / 12 * $lastYear)) - 1) * 100, 1);
        }

        return [
            'yearly_if_same' => $yearlyIfSame,
            'projected'      => $projected,
            'ytd'            => $ytd,
            'growth_pct'     => $growth,
            'last_year'      => $lastYear,
        ];
    }

    private function countUnreadFeedback(): int
    {
        try {
            return (int)($this->db->fetchColumn(
                "SELECT COUNT(*) FROM feedback WHERE is_read = 0"
            ) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getRecentFeedback(int $limit): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT f.*, t.practice_name
                 FROM feedback f
                 LEFT JOIN tenants t ON t.id = f.tenant_id
                 ORDER BY f.created_at DESC
                 LIMIT ?",
                [$limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function getTopTenants(int $limit): array
    {
        return $this->db->fetchAll(
            "SELECT t.practice_name, t.owner_name, t.status,
                    p.name AS plan_name, s.amount, s.billing_cycle
             FROM tenants t
             LEFT JOIN plans p ON p.id = t.plan_id
             LEFT JOIN subscriptions s ON s.tenant_id = t.id AND s.status = 'active'
             WHERE t.status = 'active'
             ORDER BY s.amount DESC, t.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    private function getRecentPayments(int $limit): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT pay.*, t.practice_name
                 FROM payments pay
                 LEFT JOIN tenants t ON t.id = pay.tenant_id
                 ORDER BY pay.created_at DESC
                 LIMIT ?",
                [$limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function getTrialExpiringSoonList(int $days = 7): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT t.id, t.practice_name, t.owner_name, t.email, t.trial_ends_at,
                        DATEDIFF(t.trial_ends_at, NOW()) AS days_left,
                        p.name AS plan_name
                 FROM tenants t
                 LEFT JOIN plans p ON p.id = t.plan_id
                 WHERE t.status = 'trial'
                   AND t.trial_ends_at IS NOT NULL
                   AND t.trial_ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
                 ORDER BY t.trial_ends_at ASC",
                [$days]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function getRecentActivity(int $limit = 10): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ?",
                [$limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function getLifecycleStats(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT email_key,
                        COUNT(*) AS total,
                        SUM(status = 'sent')   AS sent,
                        SUM(status = 'failed') AS failed
                 FROM tenant_lifecycle_emails
                 GROUP BY email_key
                 ORDER BY FIELD(email_key,'welcome','trial_warning','trial_expired','activated')"
            );
            $map = [];
            foreach ($rows as $r) {
                $map[$r['email_key']] = $r;
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    private function getRevenueThisMonth(): float
    {
        try {
            return round((float)($this->db->fetchColumn(
                "SELECT COALESCE(SUM(total_amount),0) FROM saas_invoices
                 WHERE status='paid' AND paid_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
            ) ?? 0), 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
