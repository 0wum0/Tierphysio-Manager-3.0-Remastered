<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\ConsentRepository;

class ConsentController extends Controller
{
    private ConsentRepository $consents;

    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        ConsentRepository $consents,
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->consents = $consents;
    }

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_consents');
        $this->render('dogschool/consents/index.twig', [
            'page_title' => 'Einwilligungen',
            'active_nav' => 'consents',
            'consents'   => $this->consents->listAll(),
        ]);
    }

    public function create(array $params = []): void
    {
        $this->requireFeature('dogschool_consents');
        $this->render('dogschool/consents/form.twig', [
            'page_title' => 'Neue Einwilligung',
            'active_nav' => 'consents',
            'consent'    => [
                'id' => 0, 'name' => '', 'content' => '', 'type' => 'participation',
                'version' => '1.0', 'is_required' => 1, 'is_active' => 1,
            ],
            'is_new' => true,
        ]);
    }

    public function show(array $params = []): void
    {
        $this->requireFeature('dogschool_consents');
        $id = (int)($params['id'] ?? 0);
        $c  = $this->consents->findById($id);
        if (!$c) {
            $this->flash('error', 'Einwilligung nicht gefunden.');
            $this->redirect('/einwilligungen');
            return;
        }
        $this->render('dogschool/consents/form.twig', [
            'page_title' => $c['name'] ?? '',
            'active_nav' => 'consents',
            'consent'    => $c,
            'signatures' => $this->consents->signaturesForConsent($id),
            'is_new'     => false,
        ]);
    }

    public function store(array $params = []): void
    {
        $this->requireFeature('dogschool_consents');
        $this->validateCsrf();
        $data = $this->collectData();
        if (!empty($params['id'])) {
            $this->consents->update((int)$params['id'], $data);
            $this->flash('success', 'Aktualisiert.');
            $this->redirect('/einwilligungen/' . (int)$params['id']);
            return;
        }
        $this->consents->create($data);
        $this->flash('success', 'Einwilligung angelegt.');
        $this->redirect('/einwilligungen');
    }

    public function sign(array $params = []): void
    {
        $this->requireFeature('dogschool_consents');
        $this->validateCsrf();

        $signatureId = (int)$this->post('signature_id', 0);
        $name        = trim((string)$this->post('signature_name', ''));
        $signature   = (string)$this->post('signature_data', '') ?: null;
        $ip          = (string)($_SERVER['REMOTE_ADDR']     ?? '');
        $ua          = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

        if ($signatureId === 0 || $name === '') {
            $this->flash('error', 'Signatur oder Name fehlt.');
            $this->redirect('/einwilligungen/' . (int)($params['id'] ?? 0));
            return;
        }

        $this->consents->sign($signatureId, $name, $signature, $ip, $ua);
        $this->flash('success', 'Signatur gespeichert.');
        $this->redirect('/einwilligungen/' . (int)($params['id'] ?? 0));
    }

    public function delete(array $params = []): void
    {
        $this->requireFeature('dogschool_consents');
        $this->validateCsrf();

        $this->consents->delete((int)($params['id'] ?? 0));
        $this->flash('success', 'Einwilligung gelöscht.');
        $this->redirect('/einwilligungen');
    }

    /**
     * AJAX-Endpunkt: erstellt eine pending Signature-Row und liefert die ID
     * zurück. Wird vom Signatur-Pad-Canvas aufgerufen, bevor die
     * Zeichnung als PNG ans Signier-Formular angehängt wird.
     */
    public function apiCreateSignatureRow(array $params = []): void
    {
        $this->requireFeature('dogschool_consents');
        $this->validateCsrf();

        $consentId = (int)($params['id'] ?? 0);
        $ownerId   = (int)$this->post('owner_id', 0);
        $patientId = ((int)$this->post('patient_id', 0)) ?: null;

        if ($consentId === 0 || $ownerId === 0) {
            $this->json(['error' => 'consent_id und owner_id erforderlich'], 400);
            return;
        }

        $sigId = (int)$this->consents->createSignature($consentId, $ownerId, $patientId);
        $this->json(['signature_id' => $sigId]);
    }

    private function collectData(): array
    {
        return [
            'name'        => trim((string)$this->post('name', '')),
            'content'     => trim((string)$this->post('content', '')),
            'version'     => trim((string)$this->post('version', '1.0')),
            'type'        => (string)$this->post('type', 'participation'),
            'is_required' => (int)(bool)$this->post('is_required', 0),
            'is_active'   => (int)(bool)$this->post('is_active', 0),
        ];
    }
}
