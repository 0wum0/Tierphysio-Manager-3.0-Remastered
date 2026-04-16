<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Repositories\RevenueRepository;

class RevenueController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly RevenueRepository $revenue
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $mrr        = $this->revenue->mrr();
        $arr        = $this->revenue->arr();
        $active     = $this->revenue->activeCount();
        $trials     = $this->revenue->trialCount();
        $churned    = $this->revenue->churnedThisMonth();
        $conversion = $this->revenue->trialConversionRate();
        $arpu       = $this->revenue->arpu();
        $totalRev   = $this->revenue->totalRevenue();
        $monthRev   = $this->revenue->revenueThisMonth();

        $mrrTrend    = $this->revenue->mrrTrend(12);
        $newTenants  = $this->revenue->newTenantsPerMonth(12);
        $churnData   = $this->revenue->churnPerMonth(12);
        $byPlan      = $this->revenue->revenueByPlan();
        $recentPaid  = $this->revenue->recentRevenue(8);

        $this->render('admin/revenue/index.twig', [
            'page_title'  => 'Revenue Dashboard',
            'active_nav'  => 'revenue',
            'mrr'         => $mrr,
            'arr'         => $arr,
            'active'      => $active,
            'trials'      => $trials,
            'churned'     => $churned,
            'conversion'  => $conversion,
            'arpu'        => $arpu,
            'total_rev'   => $totalRev,
            'month_rev'   => $monthRev,
            'mrr_trend'   => $mrrTrend,
            'new_tenants' => $newTenants,
            'churn_data'  => $churnData,
            'by_plan'     => $byPlan,
            'recent_paid' => $recentPaid,
        ]);
    }

    /**
     * GET /admin/revenue/api – JSON für Live-Refresh im Dashboard
     */
    public function api(array $params = []): void
    {
        $this->requireAuth();
        $this->json([
            'mrr'        => $this->revenue->mrr(),
            'arr'        => $this->revenue->arr(),
            'active'     => $this->revenue->activeCount(),
            'trials'     => $this->revenue->trialCount(),
            'churned'    => $this->revenue->churnedThisMonth(),
            'conversion' => $this->revenue->trialConversionRate(),
            'arpu'       => $this->revenue->arpu(),
            'mrr_trend'  => $this->revenue->mrrTrend(12),
        ]);
    }
}
