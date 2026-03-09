<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\SubscriptionRepository;

class DashboardController extends Controller
{
    public function __construct(
        View                          $view,
        Session                       $session,
        private TenantRepository       $tenantRepo,
        private SubscriptionRepository $subRepo
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $stats = $this->tenantRepo->getStats();
        $stats['active_subscriptions'] = $this->subRepo->countActive();
        $stats['monthly_revenue']      = $this->subRepo->revenueThisMonth();

        $recentTenants = $this->tenantRepo->all(10, 0);

        $this->render('admin/dashboard.twig', [
            'stats'         => $stats,
            'recent_tenants'=> $recentTenants,
            'page_title'    => 'Dashboard',
        ]);
    }
}
