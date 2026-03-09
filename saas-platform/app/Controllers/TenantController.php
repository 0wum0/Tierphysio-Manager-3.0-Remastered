<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\SubscriptionRepository;
use Saas\Repositories\PlanRepository;
use Saas\Repositories\LicenseRepository;
use Saas\Services\TenantProvisioningService;
use Saas\Services\LicenseService;

class TenantController extends Controller
{
    public function __construct(
        View                          $view,
        Session                       $session,
        private TenantRepository       $tenantRepo,
        private SubscriptionRepository $subRepo,
        private PlanRepository         $planRepo,
        private LicenseRepository      $licenseRepo,
        private TenantProvisioningService $provisioningService,
        private LicenseService         $licenseService
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $search  = trim($this->get('q', ''));
        $page    = max(1, (int)$this->get('page', 1));
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;

        if ($search !== '') {
            $tenants = $this->tenantRepo->search($search);
            $total   = count($tenants);
        } else {
            $tenants = $this->tenantRepo->all($perPage, $offset);
            $total   = $this->tenantRepo->count();
        }

        $this->render('admin/tenants/index.twig', [
            'tenants'    => $tenants,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'pages'      => (int)ceil($total / $perPage),
            'search'     => $search,
            'page_title' => 'Praxen verwalten',
        ]);
    }

    public function show(array $params = []): void
    {
        $this->requireAuth();

        $tenant = $this->tenantRepo->find((int)($params['id'] ?? 0));
        if (!$tenant) {
            $this->notFound();
        }

        $subscription = $this->subRepo->findByTenant((int)$tenant['id']);
        $allSubs      = $this->subRepo->allByTenant((int)$tenant['id']);
        $licenses     = $this->licenseRepo->getActiveForTenant((int)$tenant['id']);
        $plans        = $this->planRepo->allActive();

        $this->render('admin/tenants/show.twig', [
            'tenant'       => $tenant,
            'subscription' => $subscription,
            'all_subs'     => $allSubs,
            'licenses'     => $licenses,
            'plans'        => $plans,
            'page_title'   => $tenant['practice_name'],
        ]);
    }

    public function createForm(array $params = []): void
    {
        $this->requireAuth();

        $plans = $this->planRepo->allActive();
        $this->render('admin/tenants/create.twig', [
            'plans'      => $plans,
            'page_title' => 'Neue Praxis erstellen',
        ]);
    }

    public function create(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $data = [
            'practice_name'       => trim($this->post('practice_name', '')),
            'owner_name'          => trim($this->post('owner_name', '')),
            'email'               => strtolower(trim($this->post('email', ''))),
            'phone'               => trim($this->post('phone', '')),
            'address'             => trim($this->post('address', '')),
            'city'                => trim($this->post('city', '')),
            'zip'                 => trim($this->post('zip', '')),
            'country'             => $this->post('country', 'DE'),
            'plan_slug'           => $this->post('plan_slug', 'basic'),
            'billing_cycle'       => $this->post('billing_cycle', 'monthly'),
            'admin_password'      => $this->post('admin_password', ''),
            'payment_method'      => $this->post('payment_method', 'manual'),
        ];

        $errors = $this->validateTenantData($data);
        if ($errors) {
            $this->session->flash('error', implode('<br>', $errors));
            $this->redirect('/admin/tenants/create');
        }

        // Auto-generate password if not provided
        if (empty($data['admin_password'])) {
            $data['admin_password'] = $this->generatePassword();
        }

        try {
            $result = $this->provisioningService->provision($data);
            $this->session->flash('success',
                "Praxis '{$data['practice_name']}' erfolgreich erstellt! " .
                "Admin-Passwort: {$result['admin_password']} (wurde per E-Mail gesendet)"
            );
            $this->redirect('/admin/tenants/' . $result['tenant_id']);
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Fehler beim Erstellen: ' . $e->getMessage());
            $this->redirect('/admin/tenants/create');
        }
    }

    public function editForm(array $params = []): void
    {
        $this->requireAuth();

        $tenant = $this->tenantRepo->find((int)($params['id'] ?? 0));
        if (!$tenant) {
            $this->notFound();
        }

        $plans = $this->planRepo->allActive();
        $this->render('admin/tenants/edit.twig', [
            'tenant'     => $tenant,
            'plans'      => $plans,
            'page_title' => 'Praxis bearbeiten: ' . $tenant['practice_name'],
        ]);
    }

    public function edit(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id     = (int)($params['id'] ?? 0);
        $tenant = $this->tenantRepo->find($id);
        if (!$tenant) {
            $this->notFound();
        }

        $planSlug = $this->post('plan_slug', '');
        $plan     = $this->planRepo->findBySlug($planSlug);

        $this->tenantRepo->update($id, [
            'practice_name' => trim($this->post('practice_name', $tenant['practice_name'])),
            'owner_name'    => trim($this->post('owner_name', $tenant['owner_name'])),
            'phone'         => trim($this->post('phone', '')),
            'address'       => trim($this->post('address', '')),
            'city'          => trim($this->post('city', '')),
            'zip'           => trim($this->post('zip', '')),
            'country'       => $this->post('country', 'DE'),
            'plan_id'       => $plan ? (int)$plan['id'] : (int)$tenant['plan_id'],
            'notes'         => trim($this->post('notes', '')),
        ]);

        $this->session->flash('success', 'Praxis-Daten aktualisiert.');
        $this->redirect('/admin/tenants/' . $id);
    }

    public function suspend(array $params = []): void
    {
        $this->requireRole('superadmin', 'admin');
        $this->verifyCsrf();

        $id = (int)($params['id'] ?? 0);
        $this->provisioningService->suspend($id);
        $this->session->flash('success', 'Praxis gesperrt.');
        $this->redirect('/admin/tenants/' . $id);
    }

    public function reactivate(array $params = []): void
    {
        $this->requireRole('superadmin', 'admin');
        $this->verifyCsrf();

        $id = (int)($params['id'] ?? 0);
        $this->provisioningService->reactivate($id);
        $this->session->flash('success', 'Praxis reaktiviert.');
        $this->redirect('/admin/tenants/' . $id);
    }

    public function cancel(array $params = []): void
    {
        $this->requireRole('superadmin', 'admin');
        $this->verifyCsrf();

        $id = (int)($params['id'] ?? 0);
        $this->provisioningService->cancel($id);
        $this->session->flash('success', 'Abo gekündigt.');
        $this->redirect('/admin/tenants/' . $id);
    }

    public function issueLicense(array $params = []): void
    {
        $this->requireRole('superadmin', 'admin');
        $this->verifyCsrf();

        $id = (int)($params['id'] ?? 0);
        try {
            $token = $this->licenseService->issueToken($id);
            $this->session->flash('success', 'Neuer Lizenz-Token ausgestellt.');
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Fehler: ' . $e->getMessage());
        }
        $this->redirect('/admin/tenants/' . $id);
    }

    public function delete(array $params = []): void
    {
        $this->requireRole('superadmin');
        $this->verifyCsrf();

        $id     = (int)($params['id'] ?? 0);
        $tenant = $this->tenantRepo->find($id);
        if (!$tenant) {
            $this->notFound();
        }

        $this->licenseService->revokeAllTokens($id);
        $this->tenantRepo->delete($id);

        $this->session->flash('success', "Praxis '{$tenant['practice_name']}' wurde gelöscht.");
        $this->redirect('/admin/tenants');
    }

    private function validateTenantData(array $data): array
    {
        $errors = [];
        if (empty($data['practice_name'])) $errors[] = 'Praxisname ist erforderlich.';
        if (empty($data['owner_name']))    $errors[] = 'Name des Therapeuten ist erforderlich.';
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Gültige E-Mail-Adresse erforderlich.';
        } elseif ($this->tenantRepo->findByEmail($data['email'])) {
            $errors[] = 'Diese E-Mail-Adresse ist bereits registriert.';
        }
        return $errors;
    }

    private function generatePassword(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$';
        $pass  = '';
        for ($i = 0; $i < 12; $i++) {
            $pass .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pass;
    }
}
