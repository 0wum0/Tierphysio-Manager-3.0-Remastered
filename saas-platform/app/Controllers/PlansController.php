<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Repositories\PlanRepository;

class PlansController extends Controller
{
    public function __construct(
        View                   $view,
        Session                $session,
        private PlanRepository $planRepo
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $plans = $this->planRepo->all();
        $this->render('admin/plans/index.twig', [
            'plans'      => $plans,
            'page_title' => 'Abo-Pläne verwalten',
        ]);
    }

    public function edit(array $params = []): void
    {
        $this->requireAuth();

        $plan = $this->planRepo->find((int)($params['id'] ?? 0));
        if (!$plan) {
            $this->notFound();
        }

        $features = json_decode($plan['features'] ?? '[]', true);
        $this->render('admin/plans/edit.twig', [
            'plan'       => $plan,
            'features'   => is_array($features) ? $features : [],
            'page_title' => 'Plan bearbeiten: ' . $plan['name'],
        ]);
    }

    public function update(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id   = (int)($params['id'] ?? 0);
        $plan = $this->planRepo->find($id);
        if (!$plan) {
            $this->notFound();
        }

        $featuresRaw = $this->post('features', []);
        if (!is_array($featuresRaw)) {
            $featuresRaw = array_filter(array_map('trim', explode(',', $featuresRaw)));
        }

        $stripeId       = trim($this->post('stripe_price_id', ''));
        $stripeIdYearly = trim($this->post('stripe_price_id_yearly', ''));

        $this->planRepo->update($id, [
            'name'                 => trim($this->post('name', $plan['name'])),
            'description'          => trim($this->post('description', '')),
            'price_month'          => (float)str_replace(',', '.', $this->post('price_month', $plan['price_month'])),
            'price_year'           => (float)str_replace(',', '.', $this->post('price_year', $plan['price_year'])),
            'max_users'            => (int)$this->post('max_users', $plan['max_users']),
            'features'             => json_encode(array_values($featuresRaw)),
            'is_active'            => (int)(bool)$this->post('is_active', 1),
            'is_public'            => (int)(bool)$this->post('is_public', 1),
            'trial_days'           => max(0, (int)$this->post('trial_days', $plan['trial_days'] ?? 14)),
            'stripe_price_id'      => $stripeId !== '' ? $stripeId : null,
            'stripe_price_id_yearly' => $stripeIdYearly !== '' ? $stripeIdYearly : null,
        ]);

        $this->session->flash('success', 'Plan aktualisiert.');
        $this->redirect('/admin/plans');
    }

    public function createForm(array $params = []): void
    {
        $this->requireRole('superadmin', 'admin');

        $this->render('admin/plans/create.twig', [
            'page_title' => 'Neuen Plan erstellen',
        ]);
    }

    public function store(array $params = []): void
    {
        $this->requireRole('superadmin', 'admin');
        $this->verifyCsrf();

        $slug = strtolower(preg_replace('/[^a-z0-9-]/', '-', trim($this->post('slug', ''))));
        if ($slug === '') {
            $this->session->flash('error', 'Slug ist erforderlich.');
            $this->redirect('/admin/plans/create');
        }

        if ($this->planRepo->findBySlug($slug)) {
            $this->session->flash('error', "Ein Plan mit dem Slug '{$slug}' existiert bereits.");
            $this->redirect('/admin/plans/create');
        }

        $featuresRaw = $this->post('features', []);
        if (!is_array($featuresRaw)) {
            $featuresRaw = array_filter(array_map('trim', explode(',', $featuresRaw)));
        }

        $stripeId       = trim($this->post('stripe_price_id', ''));
        $stripeIdYearly = trim($this->post('stripe_price_id_yearly', ''));

        $newId = $this->planRepo->create([
            'slug'                 => $slug,
            'name'                 => trim($this->post('name', 'Neuer Plan')),
            'description'          => trim($this->post('description', '')),
            'price_month'          => (float)str_replace(',', '.', $this->post('price_month', '0')),
            'price_year'           => (float)str_replace(',', '.', $this->post('price_year', '0')),
            'max_users'            => (int)$this->post('max_users', 1),
            'features'             => json_encode(array_values($featuresRaw)),
            'is_active'            => (int)(bool)$this->post('is_active', 1),
            'is_public'            => (int)(bool)$this->post('is_public', 1),
            'trial_days'           => max(0, (int)$this->post('trial_days', 14)),
            'stripe_price_id'      => $stripeId !== '' ? $stripeId : null,
            'stripe_price_id_yearly' => $stripeIdYearly !== '' ? $stripeIdYearly : null,
        ]);

        $this->session->flash('success', 'Neuer Plan erstellt.');
        $this->redirect('/admin/plans/' . $newId . '/edit');
    }

    public function toggleActive(array $params = []): void
    {
        $this->requireRole('superadmin', 'admin');
        $this->verifyCsrf();

        $id   = (int)($params['id'] ?? 0);
        $plan = $this->planRepo->find($id);
        if (!$plan) {
            $this->notFound();
        }

        $this->planRepo->toggleActive($id);
        $state = $plan['is_active'] ? 'deaktiviert' : 'aktiviert';
        $this->session->flash('success', "Plan '{$plan['name']}' {$state}.");
        $this->redirect('/admin/plans');
    }
}
