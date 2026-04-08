<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Repositories\PlanRepository;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\LegalRepository;
use Saas\Services\TenantProvisioningService;

class RegistrationController extends Controller
{
    public function __construct(
        View                             $view,
        Session                          $session,
        private PlanRepository           $planRepo,
        private TenantRepository         $tenantRepo,
        private LegalRepository          $legalRepo,
        private TenantProvisioningService $provisioning
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $plans = $this->planRepo->allActive();
        $this->render('register/plans.twig', [
            'plans'      => $plans,
            'page_title' => 'Tarif wählen',
        ]);
    }

    public function form(array $params = []): void
    {
        $planSlug = $params['plan'] ?? $this->get('plan', 'basic');
        $plan     = $this->planRepo->findBySlug($planSlug);
        if (!$plan) {
            $this->session->flash('error', 'Ungültiger Tarif.');
            $this->redirect('/register');
        }

        $legalDocs = $this->legalRepo->allActive();

        $this->render('register/form.twig', [
            'plan'       => $plan,
            'legal_docs' => $legalDocs,
            'page_title' => 'Registrierung – ' . $plan['name'],
        ]);
    }

    public function submit(array $params = []): void
    {
        $this->verifyCsrf();

        $planSlug = $this->post('plan_slug', 'basic');
        $plan     = $this->planRepo->findBySlug($planSlug);
        if (!$plan) {
            $this->session->flash('error', 'Ungültiger Tarif.');
            $this->redirect('/register');
        }

        $practiceType = in_array($this->post('practice_type'), ['therapeut', 'trainer'], true)
            ? $this->post('practice_type') : 'therapeut';

        $data = [
            'practice_name' => trim($this->post('practice_name', '')),
            'owner_name'    => trim($this->post('owner_name', '')),
            'email'         => strtolower(trim($this->post('email', ''))),
            'phone'         => trim($this->post('phone', '')),
            'address'       => trim($this->post('address', '')),
            'city'          => trim($this->post('city', '')),
            'zip'           => trim($this->post('zip', '')),
            'country'       => $this->post('country', 'DE'),
            'plan_slug'     => $planSlug,
            'billing_cycle' => $this->post('billing_cycle', 'monthly'),
            'admin_password'=> $this->post('password', ''),
            'practice_type' => $practiceType,
        ];

        $errors = $this->validate($data);

        // Check legal acceptance
        $legalDocs = $this->legalRepo->allActive();
        foreach ($legalDocs as $doc) {
            if (!$this->post('legal_' . $doc['slug'])) {
                $errors[] = 'Bitte stimmen Sie der ' . $doc['title'] . ' zu.';
            }
        }

        if ($errors) {
            $this->session->flash('error', implode('<br>', $errors));
            $this->redirect('/register/' . $planSlug);
        }

        try {
            $result = $this->provisioning->provision($data);

            // Record legal acceptances
            foreach ($legalDocs as $doc) {
                $this->legalRepo->recordAcceptance(
                    $result['tenant_id'],
                    (int)$doc['id'],
                    $doc['version'],
                    $_SERVER['REMOTE_ADDR'] ?? ''
                );
            }

            // Show success page
            $this->render('register/success.twig', [
                'owner_name'   => $data['owner_name'],
                'email'        => $data['email'],
                'practice_name'=> $data['practice_name'],
                'plan_name'    => $plan['name'],
                'page_title'   => 'Registrierung erfolgreich',
            ]);
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Registrierung fehlgeschlagen: ' . $e->getMessage());
            $this->redirect('/register/' . $planSlug);
        }
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (empty($data['practice_name'])) $errors[] = 'Praxisname ist erforderlich.';
        if (empty($data['owner_name']))    $errors[] = 'Ihr Name ist erforderlich.';
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
        } elseif ($this->tenantRepo->findByEmail($data['email'])) {
            $errors[] = 'Diese E-Mail-Adresse ist bereits registriert.';
        }
        if (strlen($data['admin_password']) < 8) {
            $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
        }
        return $errors;
    }
}
