<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Repositories\BefundbogenRepository;
use App\Repositories\SettingsRepository;
use App\Services\BefundbogenPdfService;
use App\Services\MailService;

class BefundbogenController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly BefundbogenRepository $repo,
        private readonly SettingsRepository $settings,
        private readonly BefundbogenPdfService $pdfService,
        private readonly MailService $mailService
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    /* ══════════════════════════════════════════════════════
       ADMIN — per patient
    ══════════════════════════════════════════════════════ */

    /** GET /patienten/{patient_id}/befunde */
    public function index(array $params = []): void
    {
        $patientId = (int)$params['patient_id'];
        $patient   = $this->fetchPatient($patientId);
        $owner     = $patient ? $this->fetchOwner((int)$patient['owner_id']) : null;
        $befunde   = $this->repo->findByPatient($patientId);

        $this->render('befunde/index.twig', [
            'page_title' => 'Befundbögen — ' . ($patient['name'] ?? ''),
            'patient'    => $patient,
            'owner'      => $owner,
            'befunde'    => $befunde,
        ]);
    }

    /** GET /patienten/{patient_id}/befunde/neu */
    public function create(array $params = []): void
    {
        $patientId = (int)$params['patient_id'];
        $patient   = $this->fetchPatient($patientId);
        if (!$patient) { $this->abort(404); }
        $owner     = $this->fetchOwner((int)$patient['owner_id']);

        $this->render('befunde/form.twig', [
            'page_title' => 'Neuer Befundbogen — ' . $patient['name'],
            'patient'    => $patient,
            'owner'      => $owner,
            'befundbogen' => null,
            'felder'      => [],
        ]);
    }

    /** POST /patienten/{patient_id}/befunde/speichern */
    public function store(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)$params['patient_id'];
        $patient   = $this->fetchPatient($patientId);
        if (!$patient) { $this->abort(404); }

        $authUser = $this->session->getUser();

        $id = $this->repo->createBefund([
            'patient_id'       => $patientId,
            'owner_id'         => $patient['owner_id'] ?? null,
            'created_by'       => $authUser['id'] ?? null,
            'status'           => $this->post('status', 'entwurf'),
            'datum'            => $this->post('datum', date('Y-m-d')),
            'naechster_termin' => $this->post('naechster_termin') ?: null,
            'notizen'          => $this->post('notizen', ''),
        ]);

        $this->repo->saveFelder($id, $this->collectFelder());

        $this->flash('success', 'Befundbogen wurde gespeichert.');
        $this->redirect('/patienten/' . $patientId . '/befunde/' . $id);
    }

    /** GET /patienten/{patient_id}/befunde/{id} */
    public function show(array $params = []): void
    {
        $befundbogen = $this->repo->findWithFelder((int)$params['id']);
        if (!$befundbogen || (int)$befundbogen['patient_id'] !== (int)$params['patient_id']) {
            $this->abort(404);
        }

        $patient = $this->fetchPatient((int)$befundbogen['patient_id']);
        $owner   = $this->fetchOwner((int)$befundbogen['owner_id']);

        $this->render('befunde/show.twig', [
            'page_title'  => 'Befundbogen — ' . ($patient['name'] ?? ''),
            'befundbogen' => $befundbogen,
            'felder'      => $befundbogen['felder'],
            'patient'     => $patient,
            'owner'       => $owner,
        ]);
    }

    /** GET /patienten/{patient_id}/befunde/{id}/bearbeiten */
    public function edit(array $params = []): void
    {
        $befundbogen = $this->repo->findWithFelder((int)$params['id']);
        if (!$befundbogen || (int)$befundbogen['patient_id'] !== (int)$params['patient_id']) {
            $this->abort(404);
        }

        $patient = $this->fetchPatient((int)$befundbogen['patient_id']);
        $owner   = $this->fetchOwner((int)$befundbogen['owner_id']);

        $this->render('befunde/form.twig', [
            'page_title'  => 'Befundbogen bearbeiten — ' . ($patient['name'] ?? ''),
            'befundbogen' => $befundbogen,
            'felder'      => $befundbogen['felder'],
            'patient'     => $patient,
            'owner'       => $owner,
        ]);
    }

    /** POST /patienten/{patient_id}/befunde/{id}/aktualisieren */
    public function update(array $params = []): void
    {
        $this->validateCsrf();
        $befundbogen = $this->repo->findById((int)$params['id']);
        if (!$befundbogen || (int)$befundbogen['patient_id'] !== (int)$params['patient_id']) {
            $this->abort(404);
        }

        $this->repo->updateBefund((int)$params['id'], [
            'status'           => $this->post('status', $befundbogen['status']),
            'datum'            => $this->post('datum', $befundbogen['datum']),
            'naechster_termin' => $this->post('naechster_termin') ?: null,
            'notizen'          => $this->post('notizen', ''),
        ]);

        $this->repo->saveFelder((int)$params['id'], $this->collectFelder());

        $this->flash('success', 'Befundbogen wurde aktualisiert.');
        $this->redirect('/patienten/' . $params['patient_id'] . '/befunde/' . $params['id']);
    }

    /** GET /patienten/{patient_id}/befunde/{id}/pdf */
    public function pdf(array $params = []): void
    {
        $befundbogen = $this->repo->findWithFelder((int)$params['id']);
        if (!$befundbogen || (int)$befundbogen['patient_id'] !== (int)$params['patient_id']) {
            $this->abort(404);
        }

        $patient  = $this->fetchPatient((int)$befundbogen['patient_id']);
        $owner    = $this->fetchOwner((int)$befundbogen['owner_id']);
        $settings = $this->settings->all();

        $pdfContent = $this->pdfService->generate($befundbogen, $befundbogen['felder'], $patient, $owner ?? [], $settings);

        $filename = 'Befundbogen-BF-' . str_pad((string)$befundbogen['id'], 4, '0', STR_PAD_LEFT) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        echo $pdfContent;
        exit;
    }

    /** POST /patienten/{patient_id}/befunde/{id}/senden */
    public function senden(array $params = []): void
    {
        $this->validateCsrf();
        $befundbogen = $this->repo->findWithFelder((int)$params['id']);
        if (!$befundbogen || (int)$befundbogen['patient_id'] !== (int)$params['patient_id']) {
            $this->abort(404);
        }

        $patient = $this->fetchPatient((int)$befundbogen['patient_id']);
        $owner   = $this->fetchOwner((int)$befundbogen['owner_id']);

        if (!$owner || empty($owner['email'])) {
            $this->flash('error', 'Kein Tierhalter oder keine E-Mail-Adresse hinterlegt.');
            $this->redirect('/patienten/' . $params['patient_id'] . '/befunde/' . $params['id']);
        }

        $settings   = $this->settings->all();
        $pdfContent = $this->pdfService->generate($befundbogen, $befundbogen['felder'], $patient, $owner, $settings);

        // Save PDF to storage
        $storagePath = STORAGE_PATH . '/befunde';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        $filename = 'BF-' . str_pad((string)$befundbogen['id'], 4, '0', STR_PAD_LEFT) . '-' . date('Ymd') . '.pdf';
        file_put_contents($storagePath . '/' . $filename, $pdfContent);

        $sent = $this->mailService->sendBefundbogen(
            array_merge($befundbogen, ['patient_name' => $patient['name'] ?? '']),
            $owner,
            $pdfContent
        );

        if ($sent) {
            $this->repo->markVersendet((int)$params['id'], $owner['email'], 'befunde/' . $filename);
            $this->flash('success', 'Befundbogen wurde per E-Mail an ' . $owner['email'] . ' versendet.');
        } else {
            $this->flash('error', 'E-Mail konnte nicht gesendet werden: ' . $this->mailService->getLastError());
        }

        $this->redirect('/patienten/' . $params['patient_id'] . '/befunde/' . $params['id']);
    }

    /** POST /patienten/{patient_id}/befunde/{id}/loeschen */
    public function delete(array $params = []): void
    {
        $this->validateCsrf();
        $befundbogen = $this->repo->findById((int)$params['id']);
        if (!$befundbogen || (int)$befundbogen['patient_id'] !== (int)$params['patient_id']) {
            $this->abort(404);
        }

        $this->repo->deleteBefund((int)$params['id']);
        $this->flash('success', 'Befundbogen wurde gelöscht.');
        $this->redirect('/patienten/' . $params['patient_id'] . '/befunde');
    }

    /* ══════════════════════════════════════════════════════
       PORTAL ADMIN — manage befunde for all patients
    ══════════════════════════════════════════════════════ */

    /** GET /portal-admin/befunde */
    public function adminIndex(array $params = []): void
    {
        $search = $this->get('search', '');
        $status = $this->get('status', '');
        $befunde = $this->repo->findAllWithDetails($search, $status);

        $this->render('portal-admin/befunde/index.twig', [
            'page_title' => 'Befundbögen (Portal-Admin)',
            'befunde'    => $befunde,
            'search'     => $search,
            'status'     => $status,
        ]);
    }

    /** GET /portal-admin/befunde/{id} */
    public function adminShow(array $params = []): void
    {
        $befundbogen = $this->repo->findWithFelder((int)$params['id']);
        if (!$befundbogen) { $this->abort(404); }

        $patient = $this->fetchPatient((int)$befundbogen['patient_id']);
        $owner   = $this->fetchOwner((int)$befundbogen['owner_id']);

        $this->render('portal-admin/befunde/show.twig', [
            'page_title'  => 'Befundbogen — ' . ($patient['name'] ?? ''),
            'befundbogen' => $befundbogen,
            'felder'      => $befundbogen['felder'],
            'patient'     => $patient,
            'owner'       => $owner,
        ]);
    }

    /** POST /portal-admin/befunde/{id}/senden */
    public function adminSenden(array $params = []): void
    {
        $this->validateCsrf();
        $befundbogen = $this->repo->findWithFelder((int)$params['id']);
        if (!$befundbogen) { $this->abort(404); }

        $patient = $this->fetchPatient((int)$befundbogen['patient_id']);
        $owner   = $this->fetchOwner((int)$befundbogen['owner_id']);

        if (!$owner || empty($owner['email'])) {
            $this->flash('error', 'Kein Tierhalter oder keine E-Mail-Adresse hinterlegt.');
            $this->redirect('/portal-admin/befunde/' . $params['id']);
        }

        $settings   = $this->settings->all();
        $pdfContent = $this->pdfService->generate($befundbogen, $befundbogen['felder'], $patient, $owner, $settings);

        $storagePath = STORAGE_PATH . '/befunde';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        $filename = 'BF-' . str_pad((string)$befundbogen['id'], 4, '0', STR_PAD_LEFT) . '-' . date('Ymd') . '.pdf';
        file_put_contents($storagePath . '/' . $filename, $pdfContent);

        $sent = $this->mailService->sendBefundbogen(
            array_merge($befundbogen, ['patient_name' => $patient['name'] ?? '']),
            $owner,
            $pdfContent
        );

        if ($sent) {
            $this->repo->markVersendet((int)$params['id'], $owner['email'], 'befunde/' . $filename);
            $this->flash('success', 'Befundbogen versendet an ' . $owner['email'] . '.');
        } else {
            $this->flash('error', 'E-Mail-Versand fehlgeschlagen: ' . $this->mailService->getLastError());
        }

        $this->redirect('/portal-admin/befunde/' . $params['id']);
    }

    /* ══════════════════════════════════════════════════════
       OWNER PORTAL — read-only view
    ══════════════════════════════════════════════════════ */

    /** GET /portal/befunde — owner sees own befunde */
    public function portalIndex(array $params = []): void
    {
        $base    = $this->requirePortalAuth();
        $ownerId = (int)$base['portal_user']['owner_id'];
        $befunde = $this->repo->findByOwner($ownerId);

        $this->render('portal/befunde/index.twig', array_merge($base, [
            'page_title' => 'Meine Befundbögen',
            'active_nav' => 'befunde',
            'befunde'    => $befunde,
        ]));
    }

    /** GET /portal/befunde/{id} — owner reads single befund */
    public function portalShow(array $params = []): void
    {
        $base        = $this->requirePortalAuth();
        $ownerId     = (int)$base['portal_user']['owner_id'];
        $befundbogen = $this->repo->findWithFelder((int)$params['id']);

        if (!$befundbogen || (int)$befundbogen['owner_id'] !== $ownerId || $befundbogen['status'] === 'entwurf') {
            $this->abort(403);
        }

        $patient = $this->fetchPatient((int)$befundbogen['patient_id']);

        $this->render('portal/befunde/show.twig', array_merge($base, [
            'page_title'  => 'Befundbogen — ' . ($patient['name'] ?? ''),
            'active_nav'  => 'befunde',
            'befundbogen' => $befundbogen,
            'felder'      => $befundbogen['felder'],
            'patient'     => $patient,
        ]));
    }

    /** GET /portal/befunde/{id}/pdf */
    public function portalPdf(array $params = []): void
    {
        $base        = $this->requirePortalAuth();
        $ownerId     = (int)$base['portal_user']['owner_id'];
        $befundbogen = $this->repo->findWithFelder((int)$params['id']);

        if (!$befundbogen || (int)$befundbogen['owner_id'] !== $ownerId || $befundbogen['status'] === 'entwurf') {
            $this->abort(403);
        }

        $patient  = $this->fetchPatient((int)$befundbogen['patient_id']);
        $owner    = $this->fetchOwner($ownerId);
        $settings = $this->settings->all();

        $pdfContent = $this->pdfService->generate($befundbogen, $befundbogen['felder'], $patient, $owner ?? [], $settings);
        $filename   = 'Befundbogen-BF-' . str_pad((string)$befundbogen['id'], 4, '0', STR_PAD_LEFT) . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        echo $pdfContent;
        exit;
    }

    /* ══════════════════════════════════════════════════════
       PRIVATE HELPERS
    ══════════════════════════════════════════════════════ */

    private function collectFelder(): array
    {
        $knownFields = [
            'hauptbeschwerde', 'seit_wann', 'vorerkrankungen', 'medikamente', 'allergien',
            'ernaehrung', 'bewegung', 'haltung', 'bisherige_therapien',
            'allgemeinbefinden', 'ernaehrungszustand', 'temperament', 'koerpertemperatur',
            'koerperhaltung', 'gangbild', 'lahmheitsgrad',
            'betroffene_regionen', 'muskeltonus', 'triggerpunkte', 'schmerz_nrs',
            'gelenke_befund', 'neurologischer_status',
            'konstitutionstyp', 'energetischer_eindruck', 'bachblueten_emotionen',
            'bachblueten_auswahl', 'bachblueten_dosierung', 'homoeopathie_mittel',
            'homoeopathie_potenz', 'homoeopathie_dauer', 'phytotherapie',
            'schuesslersalze', 'weitere_naturheilmittel',
            'pt_methoden', 'therapieziele', 'hausaufgaben', 'therapiefrequenz',
            'therapiedauer', 'kontrolltermin', 'verlauf_notizen',
        ];

        $felder = [];
        foreach ($knownFields as $field) {
            $value = $_POST[$field] ?? null;
            if ($value === null) continue;
            if (is_array($value)) {
                $value = array_filter($value, fn($v) => $v !== '');
                if (!empty($value)) {
                    $felder[$field] = array_values($value);
                }
            } else {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    $felder[$field] = $trimmed;
                }
            }
        }
        return $felder;
    }

    private function fetchPatient(int $id): ?array
    {
        try {
            $db = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $row = $db->fetch("SELECT * FROM patients WHERE id = ? LIMIT 1", [$id]);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchOwner(?int $id): ?array
    {
        if (!$id) return null;
        try {
            $db = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $row = $db->fetch("SELECT * FROM owners WHERE id = ? LIMIT 1", [$id]);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolvePortalOwnerId(): ?int
    {
        $ownerId = $this->session->get('owner_portal_owner_id');
        return $ownerId ? (int)$ownerId : null;
    }

    /**
     * Require owner-portal session and return base template vars
     * (mirrors OwnerPortalController::requireOwnerAuth + portalBase).
     * Redirects to login if not authenticated.
     */
    private function requirePortalAuth(): array
    {
        $userId = $this->session->get('owner_portal_user_id');
        if (!$userId) {
            $this->redirect('/portal/login');
            exit;
        }

        try {
            $db       = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $userStmt = $db->query(
                'SELECT u.*, o.first_name, o.last_name FROM owner_portal_users u JOIN owners o ON o.id = u.owner_id WHERE u.id = ? LIMIT 1',
                [(int)$userId]
            );
            $user = $userStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable) {
            $user = null;
        }

        if (!$user || !$user['is_active']) {
            $this->session->remove('owner_portal_user_id');
            $this->session->remove('owner_portal_owner_id');
            $this->redirect('/portal/login');
            exit;
        }

        /* Unread message count */
        $unread = 0;
        try {
            $db2   = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $uStmt = $db2->query(
                "SELECT COUNT(*) FROM portal_messages WHERE thread_id IN (SELECT id FROM portal_threads WHERE owner_id = ?) AND sender = 'admin' AND is_read = 0",
                [(int)$user['owner_id']]
            );
            $unread = (int)($uStmt->fetchColumn() ?: 0);
        } catch (\Throwable) {}

        return [
            'portal_user'         => $user,
            'portal_unread_count' => $unread,
            'csrf_token'          => $this->session->generateCsrfToken(),
            'show_homework_nav'   => true,
        ];
    }
}
