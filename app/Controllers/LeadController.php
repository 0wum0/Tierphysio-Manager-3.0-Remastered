<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\LeadRepository;
use App\Repositories\OwnerRepository;
use App\Repositories\PatientRepository;

/**
 * LeadController — Interessenten-/Probetraining-CRM.
 *
 * Konvertiert Leads in echte Owner/Patient-Datensätze wenn gewünscht.
 */
class LeadController extends Controller
{
    private LeadRepository $leads;
    private OwnerRepository $owners;
    private PatientRepository $patients;

    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        LeadRepository $leads,
        OwnerRepository $owners,
        PatientRepository $patients,
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->leads    = $leads;
        $this->owners   = $owners;
        $this->patients = $patients;
    }

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_leads');

        $page   = max(1, (int)$this->get('page', 1));
        $status = (string)$this->get('status', '');
        $search = trim((string)$this->get('q', ''));

        $pagination = $this->leads->listPaginated($page, 30, $status, $search);
        $counts     = $this->leads->countByStatus();

        $this->render('dogschool/leads/index.twig', [
            'page_title'  => 'Interessenten',
            'active_nav'  => 'leads',
            'pagination'  => $pagination,
            'counts'      => $counts,
            'filter_status' => $status,
            'filter_q'    => $search,
            'status_list' => [
                ''                => 'Alle',
                'new'             => 'Neu',
                'contacted'       => 'Kontaktiert',
                'trial_scheduled' => 'Probetraining geplant',
                'trial_done'      => 'Probetraining erledigt',
                'converted'       => 'Konvertiert',
                'lost'            => 'Verloren',
                'archived'        => 'Archiviert',
            ],
        ]);
    }

    public function create(array $params = []): void
    {
        $this->requireFeature('dogschool_leads');
        $this->render('dogschool/leads/form.twig', [
            'page_title' => 'Neuer Interessent',
            'active_nav' => 'leads',
            'lead'       => ['id' => 0, 'status' => 'new'],
            'is_new'     => true,
        ]);
    }

    public function show(array $params = []): void
    {
        $this->requireFeature('dogschool_leads');

        $id   = (int)($params['id'] ?? 0);
        $lead = $this->leads->findById($id);
        if (!$lead) {
            $this->flash('error', 'Interessent nicht gefunden.');
            $this->redirect('/interessenten');
            return;
        }
        $this->render('dogschool/leads/form.twig', [
            'page_title' => ($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''),
            'active_nav' => 'leads',
            'lead'       => $lead,
            'is_new'     => false,
        ]);
    }

    public function store(array $params = []): void
    {
        $this->requireFeature('dogschool_leads');
        $this->validateCsrf();

        $data = $this->collectData();
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->leads->create($data);
        $this->flash('success', 'Interessent angelegt.');
        $this->redirect('/interessenten');
    }

    public function update(array $params = []): void
    {
        $this->requireFeature('dogschool_leads');
        $this->validateCsrf();

        $id = (int)($params['id'] ?? 0);
        if (!$this->leads->findById($id)) {
            $this->flash('error', 'Nicht gefunden.');
            $this->redirect('/interessenten');
            return;
        }
        $this->leads->update($id, $this->collectData());
        $this->flash('success', 'Gespeichert.');
        $this->redirect('/interessenten/' . $id);
    }

    public function delete(array $params = []): void
    {
        $this->requireFeature('dogschool_leads');
        $this->validateCsrf();

        $this->leads->delete((int)($params['id'] ?? 0));
        $this->flash('success', 'Interessent gelöscht.');
        $this->redirect('/interessenten');
    }

    /**
     * Konvertiert Lead → Owner + (optional) Patient.
     * Der Lead wird als `converted` markiert und referenziert die neuen IDs.
     */
    public function convert(array $params = []): void
    {
        $this->requireFeature('dogschool_leads');
        $this->validateCsrf();

        $id   = (int)($params['id'] ?? 0);
        $lead = $this->leads->findById($id);
        if (!$lead) {
            $this->flash('error', 'Interessent nicht gefunden.');
            $this->redirect('/interessenten');
            return;
        }

        /* Owner anlegen */
        $ownerId = (int)$this->owners->create([
            'first_name' => (string)($lead['first_name'] ?? ''),
            'last_name'  => (string)($lead['last_name']  ?? ''),
            'email'      => (string)($lead['email']      ?? '') ?: null,
            'phone'      => (string)($lead['phone']      ?? '') ?: null,
            'notes'      => 'Aus Interessent #' . $id . ' konvertiert. '
                          . (string)($lead['notes'] ?? ''),
        ]);

        /* Optional: Patient (Hund) wenn Stammdaten vorhanden */
        $patientId = null;
        if (!empty($lead['dog_name'])) {
            try {
                $patientId = (int)$this->patients->create([
                    'owner_id'  => $ownerId,
                    'name'      => (string)$lead['dog_name'],
                    'breed'     => (string)($lead['dog_breed'] ?? ''),
                    'species'   => 'Hund',
                ]);
            } catch (\Throwable $e) {
                error_log('[LeadController convert] patient create: ' . $e->getMessage());
            }
        }

        $this->leads->update($id, [
            'status'               => 'converted',
            'converted_owner_id'   => $ownerId,
            'converted_patient_id' => $patientId,
        ]);

        $this->flash('success', 'Interessent wurde konvertiert.');
        $this->redirect('/tierhalter/' . $ownerId);
    }

    private function collectData(): array
    {
        $age = $this->post('dog_age_months', '');
        $fu  = trim((string)$this->post('next_followup_at', ''));
        return [
            'source'           => trim((string)$this->post('source', '')) ?: null,
            'first_name'       => trim((string)$this->post('first_name', '')),
            'last_name'        => trim((string)$this->post('last_name', '')),
            'email'            => trim((string)$this->post('email', '')) ?: null,
            'phone'            => trim((string)$this->post('phone', '')) ?: null,
            'dog_name'         => trim((string)$this->post('dog_name', '')) ?: null,
            'dog_breed'        => trim((string)$this->post('dog_breed', '')) ?: null,
            'dog_age_months'   => ($age === '' || $age === null) ? null : (int)$age,
            'interest'         => trim((string)$this->post('interest', '')) ?: null,
            'message'          => trim((string)$this->post('message', '')) ?: null,
            'status'           => (string)$this->post('status', 'new'),
            'next_followup_at' => $fu !== '' ? $fu : null,
            'notes'            => trim((string)$this->post('notes', '')) ?: null,
        ];
    }
}
