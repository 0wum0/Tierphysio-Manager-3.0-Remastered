<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Config;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\SubscriptionRepository;
use Saas\Repositories\PlanRepository;
use Saas\Services\TenantFeatureCacheInvalidator;

class TenantAccountController extends Controller
{
    public function __construct(
        View                          $view,
        Session                       $session,
        private Config                $config,
        private TenantRepository      $tenantRepo,
        private SubscriptionRepository $subRepo,
        private PlanRepository        $planRepo,
        private TenantFeatureCacheInvalidator $cacheInvalidator,
    ) {
        parent::__construct($view, $session);
    }

    private function requireTenantAuth(): array
    {
        $tenantId = $this->session->get('platform_tenant_id');
        if (!$tenantId) {
            $this->redirect('/login');
        }
        $tenant = $this->tenantRepo->find((int)$tenantId);
        if (!$tenant) {
            $this->session->destroy();
            $this->redirect('/login');
        }
        return $tenant;
    }

    // ── GET /account ────────────────────────────────────────────────────────

    public function index(array $params = []): void
    {
        $tenant       = $this->requireTenantAuth();
        $subscription = $this->subRepo->findByTenant((int)$tenant['id']);
        $plans        = $this->planRepo->allActive();
        $appUrl       = rtrim($this->config->get('practice.url', '') ?: $this->config->get('platform.app_url', ''), '/');

        $this->render('account/index.twig', [
            'page_title'   => 'Mein Konto',
            'tenant'       => $tenant,
            'subscription' => $subscription ?: [],
            'plans'        => $plans,
            'app_url'      => $appUrl,
        ]);
    }

    // ── POST /account/update ─────────────────────────────────────────────────

    public function update(array $params = []): void
    {
        $this->verifyCsrf();
        $tenant = $this->requireTenantAuth();

        $data = [
            'practice_name' => trim($this->post('practice_name', '')),
            'owner_name'    => trim($this->post('owner_name', '')),
            'phone'         => trim($this->post('phone', '')),
            'address'       => trim($this->post('address', '')),
            'city'          => trim($this->post('city', '')),
            'zip'           => trim($this->post('zip', '')),
        ];

        if (empty($data['practice_name']) || empty($data['owner_name'])) {
            $this->session->flash('error', 'Praxisname und Name sind Pflichtfelder.');
            $this->redirect('/account');
        }

        $this->tenantRepo->update((int)$tenant['id'], $data);

        /* Session-Name aktualisieren */
        $this->session->set('platform_name', $data['owner_name']);

        $this->session->flash('success', 'Kontodaten erfolgreich gespeichert.');
        $this->redirect('/account');
    }

    // ── POST /account/password ───────────────────────────────────────────────

    public function changePassword(array $params = []): void
    {
        $this->verifyCsrf();
        $tenant = $this->requireTenantAuth();

        $current  = $this->post('current_password', '');
        $new      = $this->post('new_password', '');
        $confirm  = $this->post('confirm_password', '');

        $passwordHash = $tenant['password_hash'] ?? '';

        if (!$current || !password_verify($current, $passwordHash)) {
            $this->session->flash('error', 'Das aktuelle Passwort ist falsch.');
            $this->redirect('/account');
        }

        if (strlen($new) < 8) {
            $this->session->flash('error', 'Das neue Passwort muss mindestens 8 Zeichen lang sein.');
            $this->redirect('/account');
        }

        if ($new !== $confirm) {
            $this->session->flash('error', 'Die neuen Passwörter stimmen nicht überein.');
            $this->redirect('/account');
        }

        $this->tenantRepo->update((int)$tenant['id'], [
            'password_hash' => password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);

        $this->session->flash('success', 'Passwort erfolgreich geändert.');
        $this->redirect('/account');
    }

    // ── POST /account/plan ───────────────────────────────────────────────────

    public function changePlan(array $params = []): void
    {
        $this->verifyCsrf();
        $tenant = $this->requireTenantAuth();

        $planSlug = $this->post('plan_slug', '');
        $plan     = $this->planRepo->findBySlug($planSlug);

        if (!$plan) {
            $this->session->flash('error', 'Ungültiger Plan.');
            $this->redirect('/account');
        }

        $this->tenantRepo->update((int)$tenant['id'], ['plan_id' => (int)$plan['id']]);

        $subscription = $this->subRepo->findByTenant((int)$tenant['id']);
        if ($subscription) {
            $billingCycle = $subscription['billing_cycle'] ?? 'monthly';
            $amount       = $billingCycle === 'yearly' ? $plan['price_year'] : $plan['price_month'];
            $this->subRepo->update((int)$subscription['id'], [
                'plan_id' => (int)$plan['id'],
                'amount'  => $amount,
            ]);
        }

        /* Plan wurde geändert — Feature-Cache des Tenants in der Praxis-DB
         * sofort invalidieren, damit Downgrades ohne Verzögerung greifen und
         * keine alten (höheren) Feature-Flags weiter genutzt werden können. */
        $this->cacheInvalidator->invalidateForTenant((int)$tenant['id']);

        $this->session->flash('success', 'Plan wurde auf „' . $plan['name'] . '" geändert.');
        $this->redirect('/account');
    }
}
