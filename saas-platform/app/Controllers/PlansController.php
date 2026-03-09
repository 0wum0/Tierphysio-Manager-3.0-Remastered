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

        $this->planRepo->update($id, [
            'name'        => trim($this->post('name', $plan['name'])),
            'description' => trim($this->post('description', '')),
            'price_month' => (float)str_replace(',', '.', $this->post('price_month', $plan['price_month'])),
            'price_year'  => (float)str_replace(',', '.', $this->post('price_year', $plan['price_year'])),
            'max_users'   => (int)$this->post('max_users', $plan['max_users']),
            'features'    => json_encode(array_values($featuresRaw)),
            'is_active'   => (int)(bool)$this->post('is_active', 1),
        ]);

        $this->session->flash('success', 'Plan aktualisiert.');
        $this->redirect('/admin/plans');
    }
}
