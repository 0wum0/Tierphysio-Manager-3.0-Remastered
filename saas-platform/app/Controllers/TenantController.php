<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Config;
use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\SubscriptionRepository;
use Saas\Repositories\PlanRepository;
use Saas\Repositories\LicenseRepository;
use Saas\Services\TenantProvisioningService;
use Saas\Services\LicenseService;
use Saas\Services\SubscriptionService;

class TenantController extends Controller
{
    public function __construct(
        View                          $view,
        Session                       $session,
        private Config                $config,
        private TenantRepository       $tenantRepo,
        private SubscriptionRepository $subRepo,
        private PlanRepository         $planRepo,
        private LicenseRepository      $licenseRepo,
        private TenantProvisioningService $provisioningService,
        private LicenseService         $licenseService,
        private SubscriptionService    $subscriptionService
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
        $subEvents    = $this->subscriptionService->getEventsForTenant((int)$tenant['id'], 20);

        $this->render('admin/tenants/show.twig', [
            'tenant'       => $tenant,
            'subscription' => $subscription,
            'all_subs'     => $allSubs,
            'licenses'     => $licenses,
            'plans'        => $plans,
            'sub_events'   => $subEvents,
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

    public function activate(array $params = []): void
    {
        $this->requireRole('superadmin', 'admin');
        $this->verifyCsrf();

        $id = (int)($params['id'] ?? 0);
        $this->tenantRepo->setStatus($id, 'active');
        $this->session->flash('success', 'Praxis aktiviert.');
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

    public function setTrial(array $params = []): void
    {
        $this->requireRole('superadmin', 'admin');
        $this->verifyCsrf();

        $id    = (int)($params['id'] ?? 0);
        $days  = (int)$this->post('trial_days', 14);
        $type  = $this->post('trial_type', 'days');
        $actor = $this->session->get('saas_user') ?? 'admin';

        if ($type === 'lifetime') {
            $this->tenantRepo->update($id, [
                'trial_ends_at' => '2099-12-31 23:59:59',
                'status'        => 'active',
            ]);
            $sub = $this->subRepo->findByTenant($id);
            if ($sub) {
                $this->subRepo->update((int)$sub['id'], [
                    'ends_at'       => '2099-12-31 23:59:59',
                    'next_billing'  => '2099-12-31 23:59:59',
                    'trial_ends_at' => '2099-12-31 23:59:59',
                    'status'        => 'active',
                ]);
            }
            $this->subscriptionService->logEvent($id, 'trial_started', [
                'type' => 'lifetime', 'ends_at' => '2099-12-31 23:59:59',
            ], $actor);
            $this->session->flash('success', 'Lifetime-Lizenz vergeben.');
        } else {
            $days   = max(1, min(3650, $days));
            $endsAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            $this->tenantRepo->update($id, [
                'trial_ends_at' => $endsAt,
                'status'        => 'trial',
            ]);
            $sub = $this->subRepo->findByTenant($id);
            if ($sub) {
                $this->subRepo->update((int)$sub['id'], [
                    'ends_at'       => $endsAt,
                    'next_billing'  => $endsAt,
                    'trial_ends_at' => $endsAt,
                    'status'        => 'trial',
                ]);
            }
            $this->subscriptionService->logEvent($id, 'trial_started', [
                'type' => 'days', 'trial_days' => $days, 'ends_at' => $endsAt,
            ], $actor);
            $this->session->flash('success', "Kostenlose Nutzung für {$days} Tage vergeben (bis " . date('d.m.Y', strtotime("+{$days} days")) . ").");
        }

        $this->redirect('/admin/tenants/' . $id);
    }

    public function setGrandfatheredPrice(array $params = []): void
    {
        $this->requireRole('superadmin', 'admin');
        $this->verifyCsrf();

        $id     = (int)($params['id'] ?? 0);
        $tenant = $this->tenantRepo->find($id);
        if (!$tenant) {
            $this->notFound();
        }

        $price  = (float)str_replace(',', '.', trim($this->post('grandfathered_price', '0')));
        $reason = trim($this->post('grandfathered_reason', 'early-adopter'));
        $note   = trim($this->post('pricing_note', ''));
        $actor  = $this->session->get('saas_user') ?? 'admin';

        if ($price <= 0) {
            try {
                $this->subscriptionService->removeGrandfatheredPrice($id, $actor);
                $this->session->flash('success', 'Sonderpreis entfernt. Standardpreis wird wieder angewendet.');
            } catch (\Throwable $e) {
                $this->session->flash('error', 'Fehler: ' . $e->getMessage());
            }
        } else {
            try {
                $this->subscriptionService->setGrandfatheredPrice(
                    $id, $price, $reason, $note ?: null, $actor
                );
                $this->session->flash('success', sprintf(
                    "Sonderpreis von %.2f \u{20AC} f\u{FC}r '%s' gesetzt (%s).",
                    $price, $tenant['practice_name'], $reason
                ));
            } catch (\Throwable $e) {
                $this->session->flash('error', 'Fehler: ' . $e->getMessage());
            }
        }

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

        /* ── Storage-Schutz ──────────────────────────────────────────────────
         * Der Daten-Ordner des Mandanten wird NIEMALS vom PHP-Code gelöscht.
         * Stattdessen wird eine Marker-Datei erstellt, die anzeigt, dass der
         * Mandant gelöscht wurde. So können Hosting-Cleanup-Tools erkennen,
         * dass dieser Ordner intentional archiviert wird und nicht gelöscht
         * werden darf.  Die App erstellt den Basis-Ordner bei Bedarf automatisch
         * neu (Database::storagePath).                                       */
        $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_');
        if ($prefix !== '') {
            $practicePath = rtrim($this->config->get('practice.path', ''), '/');
            $storageRoot  = $practicePath !== ''
                ? $practicePath . '/storage/tenants'
                : dirname($this->config->getRootPath()) . '/storage/tenants';
            $tenantDir = $storageRoot . '/' . $prefix;
            if (is_dir($tenantDir)) {
                @file_put_contents(
                    $tenantDir . '/_MANDANT_GELOESCHT_' . date('Y-m-d') . '.txt',
                    "Praxis: {$tenant['practice_name']}\n" .
                    "Mandant-ID: {$id}\n" .
                    "Geloescht am: " . date('Y-m-d H:i:s') . "\n" .
                    "Dieser Ordner ist ein Archiv. Bitte NICHT ohne Absprache loeschen.\n"
                );
            }
        }

        $this->licenseService->revokeAllTokens($id);
        $this->tenantRepo->delete($id);

        $this->session->flash('success', "Praxis '{$tenant['practice_name']}' wurde gelöscht. Storage-Daten wurden archiviert und nicht gelöscht.");
        $this->redirect('/admin/tenants');
    }

    public function fixStorage(array $params = []): void
    {
        $this->requireRole('superadmin');
        $this->verifyCsrf();

        $tenants  = $this->tenantRepo->all();
        $created  = [];
        $skipped  = [];

        $practicePath = rtrim($this->config->get('practice.path', ''), '/');
        $storageRoot  = $practicePath !== ''
            ? $practicePath . '/storage/tenants'
            : dirname($this->config->getRootPath()) . '/storage/tenants';

        if (!is_dir($storageRoot)) {
            @mkdir($storageRoot, 0755, true);
        }

        foreach ($tenants as $tenant) {
            $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_');
            if ($prefix === '') {
                continue;
            }
            $tenantDir = $storageRoot . '/' . $prefix;
            if (is_dir($tenantDir)) {
                $skipped[] = $tenant['practice_name'];
                continue;
            }
            @mkdir($tenantDir, 0755, true);
            foreach (['patients', 'uploads', 'vet-reports', 'intake'] as $sub) {
                @mkdir($tenantDir . '/' . $sub, 0755, true);
            }
            $created[] = $tenant['practice_name'] . ' (' . $prefix . ')';
        }

        $msg = count($created) > 0
            ? 'Storage-Ordner erstellt für: ' . implode(', ', $created) . '. '
            : 'Keine neuen Ordner benötigt. ';
        $msg .= count($skipped) > 0 ? 'Bereits vorhanden: ' . implode(', ', $skipped) . '.' : '';

        $this->session->flash('success', $msg);
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
