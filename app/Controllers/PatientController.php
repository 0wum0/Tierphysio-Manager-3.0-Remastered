<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Services\PatientService;
use App\Services\OwnerService;
use App\Services\InvoiceService;
use App\Services\PdfService;
use App\Services\MailService;
use App\Repositories\TreatmentTypeRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\HomeworkRepository;
use App\Core\Database;

class PatientController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly PatientService $patientService,
        private readonly OwnerService $ownerService,
        private readonly TreatmentTypeRepository $treatmentTypeRepository,
        private readonly InvoiceService $invoiceService,
        private readonly SettingsRepository $settingsRepository,
        private readonly PdfService $pdfService,
        private readonly MailService $mailService,
        private readonly HomeworkRepository $homeworkRepository,
        private readonly Database $db
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    public function index(array $params = []): void
    {
        $search  = $this->get('search', '');
        $filter  = $this->get('filter', '');
        $page    = (int)$this->get('page', 1);
        $result  = $this->patientService->getPaginated($page, 12, $search, $filter);

        $treatmentTypes = [];
        try { $treatmentTypes = $this->treatmentTypeRepository->findActive(); } catch (\Throwable) {}
        $settings = $this->settingsRepository->all();
        $owners   = $this->ownerService->findAll();

        $patientIds      = array_column($result['items'], 'id');
        $invoiceStatsMap = $this->invoiceService->getInvoiceStatsForPatients($patientIds);

        $this->render('patients/index.twig', [
            'page_title'        => $this->translator->trans('nav.patients'),
            'patients'          => $result['items'],
            'pagination'        => $result,
            'search'            => $search,
            'filter'            => $filter,
            'owners'            => $owners,
            'treatment_types'   => $treatmentTypes,
            'next_number'       => $this->invoiceService->generateInvoiceNumber(),
            'kleinunternehmer'  => ($settings['kleinunternehmer'] ?? '0') === '1',
            'default_tax_rate'  => $settings['default_tax_rate'] ?? '19',
            'invoice_stats_map' => $invoiceStatsMap,
        ]);
    }

    public function show(array $params = []): void
    {
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            $this->abort(404);
        }

        $owner    = $this->ownerService->findById((int)$patient['owner_id']);
        $timeline = $this->patientService->getTimeline((int)$params['id']);
        $owners   = $this->ownerService->findAll();

        $treatmentTypes = [];
        try { $treatmentTypes = $this->treatmentTypeRepository->findActive(); } catch (\Throwable) {}

        $settings = $this->settingsRepository->all();

        $invoiceStats = $this->invoiceService->getInvoiceStatsByPatientId((int)$params['id']);

        /* Fire patientHeaderActions hook — collect HTML buttons from plugins */
        $headerActions = '';
        try {
            $pm = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\PluginManager::class);
            $context = ['patient' => $patient, 'owner' => $owner];
            foreach ($pm->getHookCallbacks('patientHeaderActions') as $cb) {
                $html = $cb($context);
                if (is_string($html) && $html !== '') {
                    $headerActions .= $html;
                }
            }
        } catch (\Throwable) {}

        /* ── Portal check-notifications for this patient ── */
        $portalCheckNotifications = [];
        try {
            $db         = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $portalRepo = new \Plugins\OwnerPortal\OwnerPortalRepository($db);
            $portalCheckNotifications = $portalRepo->getCheckNotificationsForPatient((int)$params['id']);
        } catch (\Throwable) {}

        /* ── Next appointment for this patient ── */
        $nextAppointment = null;
        try {
            $db = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $nextAppointment = $db->fetch(
                "SELECT a.id, a.title, a.start_at, a.end_at, a.status, a.notes,
                        tt.name AS treatment_type_name, tt.color AS treatment_color,
                        CONCAT(o.first_name, ' ', o.last_name) AS owner_name
                 FROM `{$db->prefix('appointments')}` a
                 LEFT JOIN `{$db->prefix('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 LEFT JOIN `{$db->prefix('owners')}` o ON o.id = a.owner_id
                 WHERE a.patient_id = ? AND a.start_at >= NOW() AND a.status NOT IN ('cancelled','noshow')
                 ORDER BY a.start_at ASC
                 LIMIT 1",
                [(int)$params['id']]
            ) ?: null;
        } catch (\Throwable) {}

        /* ── TherapyCare Pro data for patient detail tabs ── */
        $tcpProgress   = null;
        $tcpNatural    = null;
        $tcpReports    = null;
        $tcpFeedback   = null;
        $tcpCategories = null;
        $tcpVisibility = null;
        try {
            if (class_exists('\Plugins\TherapyCarePro\TherapyCareRepository')) {
                $db         = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
                $tcpRepo    = new \Plugins\TherapyCarePro\TherapyCareRepository($db);
                $patId      = (int)$params['id'];
                $tcpProgress   = $tcpRepo->getLatestProgressForPatient($patId);
                $tcpCategories = $tcpRepo->getActiveProgressCategories();
                $tcpNatural    = $tcpRepo->getNaturalEntriesForPatient($patId);
                $tcpReports    = $tcpRepo->getTherapyReportsForPatient($patId);
                $tcpFeedback   = $tcpRepo->getFeedbackForPatient($patId, 30);
                $tcpVisibility = $tcpRepo->getPortalVisibility($patId);
            }
        } catch (\Throwable) {}

        $this->render('patients/show.twig', [
            'page_title'               => $patient['name'],
            'patient'                  => $patient,
            'owner'                    => $owner,
            'timeline'                 => $timeline,
            'owners'                   => $owners,
            'treatment_types'          => $treatmentTypes,
            'next_number'              => $this->invoiceService->generateInvoiceNumber(),
            'kleinunternehmer'         => ($settings['kleinunternehmer'] ?? '0') === '1',
            'default_tax_rate'         => $settings['default_tax_rate'] ?? '19',
            'invoice_stats'            => $invoiceStats,
            'plugin_header_actions'    => $headerActions,
            'portal_check_notifications' => $portalCheckNotifications,
            'next_appointment'         => $nextAppointment,
            'tcp_progress'             => $tcpProgress,
            'tcp_categories'           => $tcpCategories,
            'tcp_natural'              => $tcpNatural,
            'tcp_reports'              => $tcpReports,
            'tcp_feedback'             => $tcpFeedback,
            'tcp_visibility'           => $tcpVisibility,
        ]);
    }

    public function showJson(array $params = []): void
    {
        $patient  = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'not found']);
            exit;
        }

        $owner          = $this->ownerService->findById((int)$patient['owner_id']);
        $timeline       = $this->patientService->getTimeline((int)$params['id']);
        $treatmentTypes = [];
        try { $treatmentTypes = $this->treatmentTypeRepository->findActive(); } catch (\Throwable) {}

        $invoiceStats = $this->invoiceService->getInvoiceStatsByPatientId((int)$params['id']);

        $appointments = [];
        try {
            $pid = (int)$params['id'];
            $stmt = $this->db->query(
                "SELECT a.id, a.title, a.start_at, a.end_at, a.status, a.color, a.all_day,
                        tt.name AS treatment_type_name
                 FROM `{$this->t('appointments')}` a
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 WHERE a.patient_id = ? AND a.start_at >= NOW()
                   AND a.status NOT IN ('cancelled','noshow')
                 ORDER BY a.start_at ASC
                 LIMIT 5",
                [$pid]
            );
            $appointments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($appointments)) {
                $stmt2 = $this->db->query(
                    "SELECT a.id, a.title, a.start_at, a.end_at, a.status, a.color, a.all_day,
                            tt.name AS treatment_type_name
                     FROM `{$this->t('appointments')}` a
                     LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                     WHERE a.patient_id = ?
                     ORDER BY a.start_at DESC
                     LIMIT 5",
                    [$pid]
                );
                $appointments = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable) {}

        header('Content-Type: application/json');
        echo json_encode([
            'patient'         => $patient,
            'owner'           => $owner,
            'timeline'        => $timeline,
            'treatment_types' => $treatmentTypes,
            'invoice_stats'   => $invoiceStats,
            'appointments'    => $appointments,
        ]);
        exit;
    }

    public function store(array $params = []): void
    {
        $this->validateCsrf();

        $data = [
            'name'          => $this->sanitize($this->post('name', '')),
            'species'       => $this->sanitize($this->post('species', '')),
            'breed'         => $this->sanitize($this->post('breed', '')),
            'birth_date'    => $this->post('birth_date', null),
            'gender'        => $this->sanitize($this->post('gender', '')),
            'color'         => $this->sanitize($this->post('color', '')),
            'chip_number'   => $this->sanitize($this->post('chip_number', '')),
            'owner_id'      => (int)$this->post('owner_id', 0),
            'notes'         => $this->post('notes', ''),
            'status'        => $this->sanitize($this->post('status', 'aktiv')),
            'deceased_date' => $this->post('deceased_date', null) ?: null,
        ];

        if (empty($data['name']) || empty($data['owner_id'])) {
            $this->session->flash('error', $this->translator->trans('patients.fill_required'));
            $this->redirect('/patienten');
            return;
        }

        $id = $this->patientService->create($data);
        $this->session->flash('success', $this->translator->trans('patients.created'));
        $this->redirect("/patienten/{$id}");
    }

    public function update(array $params = []): void
    {
        $this->validateCsrf();

        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            $this->abort(404);
        }

        $data = [
            'name'          => $this->sanitize($this->post('name', '')),
            'species'       => $this->sanitize($this->post('species', '')),
            'breed'         => $this->sanitize($this->post('breed', '')),
            'birth_date'    => $this->post('birth_date', null),
            'gender'        => $this->sanitize($this->post('gender', '')),
            'color'         => $this->sanitize($this->post('color', '')),
            'chip_number'   => $this->sanitize($this->post('chip_number', '')),
            'owner_id'      => (int)$this->post('owner_id', 0),
            'notes'         => $this->post('notes', ''),
            'status'        => $this->sanitize($this->post('status', 'aktiv')),
            'deceased_date' => $this->post('deceased_date', null) ?: null,
        ];

        $this->patientService->update((int)$params['id'], $data);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            $this->json(['ok' => true, 'patient' => $this->patientService->findById((int)$params['id'])]);
            return;
        }

        $this->session->flash('success', $this->translator->trans('patients.updated'));
        $this->redirect('/patienten');
    }

    public function delete(array $params = []): void
    {
        $this->validateCsrf();
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            $this->abort(404);
        }

        $this->patientService->delete((int)$params['id']);
        $this->session->flash('success', $this->translator->trans('patients.deleted'));
        $this->redirect('/patienten');
    }

    public function uploadPhoto(array $params = []): void
    {
        $this->validateCsrf();
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            $this->abort(404);
        }

        $wantsJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        $destination = STORAGE_PATH . '/patients/' . $params['id'];
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        $filename = $this->uploadFile('photo', $destination, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp'
        ]);

        if ($filename === false) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'error' => 'photo_upload_failed', 'message' => $this->translator->trans('patients.photo_upload_failed')], 400);
                return;
            }

            $this->session->flash('error', $this->translator->trans('patients.photo_upload_failed'));
            $this->redirect("/patienten/{$params['id']}");
            return;
        }

        $this->patientService->update((int)$params['id'], ['photo' => $filename]);

        if ($wantsJson) {
            $this->json(['ok' => true, 'photo' => $filename]);
            return;
        }

        $this->session->flash('success', $this->translator->trans('patients.photo_updated'));
        $this->redirect("/patienten/{$params['id']}");
    }

    public function addTimelineEntry(array $params = []): void
    {
        $this->validateCsrf();
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            $this->abort(404);
        }

        $data = [
            'patient_id'  => (int)$params['id'],
            'type'        => $this->sanitize($this->post('type', 'note')),
            'title'       => $this->sanitize($this->post('title', '')),
            'content'     => $this->post('content', ''),
            'status_badge'=> $this->sanitize($this->post('status_badge', '')),
            'entry_date'  => $this->post('entry_date') ?: date('Y-m-d H:i:s'),
            'user_id'     => (int)$this->session->get('user_id'),
        ];

        $file = null;
        if (!empty($_FILES['attachment']['name'])) {
            $destination = STORAGE_PATH . '/patients/' . $params['id'] . '/timeline';
            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }
            $file = $this->uploadFile('attachment', $destination, [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
                'video/x-msvideo', 'video/x-matroska', 'video/x-m4v',
            ]);
            if ($file) {
                $data['attachment'] = $file;
            }
        }

        $this->patientService->addTimelineEntry($data);

        /* ── Email an Besitzer bei neuem Behandlungseintrag ── */
        if (($data['type'] === 'treatment') && !empty($patient['owner_id'])) {
            try {
                $db         = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
                $owner      = $this->ownerService->findById((int)$patient['owner_id']);
                $portalUser = (new \Plugins\OwnerPortal\OwnerPortalRepository($db))
                    ->findUserByOwnerId((int)$patient['owner_id']);
                if ($owner && $portalUser && !empty($owner['email'])) {
                    $settings = \App\Core\Application::getInstance()->getContainer()
                        ->get(\App\Repositories\SettingsRepository::class);
                    $mailer   = \App\Core\Application::getInstance()->getContainer()
                        ->get(\App\Services\MailService::class);
                    $svc = new \Plugins\OwnerPortal\OwnerPortalMailService($settings, $mailer);
                    $svc->sendNewTreatment(
                        $owner['email'],
                        trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? '')),
                        $patient['name'] ?? 'Ihr Tier',
                        $data['title'] ?: 'Behandlung',
                        date('d.m.Y', strtotime($data['entry_date'])),
                        (int)$params['id']
                    );
                }
            } catch (\Throwable) { /* optional */ }
        }

        $this->session->flash('success', $this->translator->trans('patients.timeline_added'));
        $this->redirect("/patienten/{$params['id']}");
    }

    public function addTimelineEntryJson(array $params = []): void
    {
        $this->validateCsrf();
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'not found']);
            exit;
        }

        $ttId = $this->post('treatment_type_id', '');
        $data = [
            'patient_id'        => (int)$params['id'],
            'type'              => $this->sanitize($this->post('type', 'note')),
            'treatment_type_id' => $ttId !== '' ? (int)$ttId : null,
            'title'             => $this->sanitize($this->post('title', '')),
            'content'           => $this->post('content', ''),
            'status_badge'      => $this->sanitize($this->post('status_badge', '')),
            'entry_date'        => $this->post('entry_date') ?: date('Y-m-d H:i:s'),
            'user_id'           => (int)$this->session->get('user_id'),
        ];

        // Bereits hochgeladener Dateiname (vom uploadAttachment-Endpoint)
        $preUploaded = $this->post('uploaded_filename', '');
        if ($preUploaded !== '') {
            // Sicherheitscheck: nur Dateiname, kein Pfad-Traversal
            $safeFilename = basename($preUploaded);
            $uploadedPath = STORAGE_PATH . '/patients/' . (int)$params['id'] . '/timeline/' . $safeFilename;
            if (file_exists($uploadedPath)) {
                $data['attachment'] = $safeFilename;
            }
        } elseif (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            // Fallback: direkter Upload (kleine Dateien / Bilder)
            $destination = STORAGE_PATH . '/patients/' . $params['id'] . '/timeline';
            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }
            $file = $this->uploadFile('attachment', $destination, [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
                'video/x-msvideo', 'video/x-matroska', 'video/x-m4v',
            ]);
            if ($file) {
                $data['attachment'] = $file;
            }
        }

        $this->patientService->addTimelineEntry($data);
        $timeline = $this->patientService->getTimeline((int)$params['id'], 100);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'timeline' => $timeline]);
        exit;
    }
    public function uploadAttachment(array $params = []): void
    {
        $this->validateCsrf();
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'not found']);
            exit;
        }

        // Kein Timeout für große Uploads
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        if (empty($_FILES['attachment']['name']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['attachment']['error'] ?? -1;
            $errMsg  = match ($errCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei zu groß. Bitte php.ini upload_max_filesize prüfen.',
                UPLOAD_ERR_PARTIAL  => 'Upload wurde unterbrochen.',
                UPLOAD_ERR_NO_FILE  => 'Keine Datei ausgewählt.',
                default             => 'Upload-Fehler (Code ' . $errCode . ').',
            };
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $errMsg]);
            exit;
        }

        $destination = STORAGE_PATH . '/patients/' . (int)$params['id'] . '/timeline';
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $finfo      = new \finfo(FILEINFO_MIME_TYPE);
        $uploadMime = $finfo->file($_FILES['attachment']['tmp_name']);

        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
            'video/x-msvideo', 'video/x-matroska', 'video/x-m4v', 'video/mpeg',
        ];

        if (!in_array($uploadMime, $allowedMimes, true)) {
            http_response_code(415);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Dateityp nicht erlaubt: ' . $uploadMime]);
            exit;
        }

        $mimeExtMap = [
            'image/jpeg'      => 'jpg',  'image/png'  => 'png',
            'image/gif'       => 'gif',  'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'video/mp4'       => 'mp4',  'video/webm'  => 'webm',
            'video/ogg'       => 'ogv',  'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',  'video/x-matroska' => 'mkv',
            'video/x-m4v'     => 'm4v',  'video/mpeg'  => 'mpeg',
        ];
        $ext      = $mimeExtMap[$uploadMime] ?? 'bin';
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $fullPath = $destination . '/' . $filename;

        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $fullPath)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Datei konnte nicht gespeichert werden.']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'filename' => $filename, 'mime' => $uploadMime]);
        exit;
    }

    public function deleteTimelineEntry(array $params = []): void
    {
        $this->validateCsrf();
        $this->patientService->deleteTimelineEntry((int)$params['entryId']);
        $this->session->flash('success', $this->translator->trans('patients.timeline_deleted'));
        $this->redirect("/patienten/{$params['id']}");
    }

    public function updateTimelineEntryJson(array $params = []): void
    {
        $this->validateCsrf();
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) { http_response_code(404); header('Content-Type: application/json'); echo json_encode(['error' => 'not found']); exit; }

        $ttId = $this->post('treatment_type_id', '');
        $data = [
            'type'              => $this->sanitize($this->post('type', 'note')),
            'treatment_type_id' => $ttId !== '' ? (int)$ttId : null,
            'title'             => $this->sanitize($this->post('title', '')),
            'content'           => $this->post('content', ''),
            'status_badge'      => $this->sanitize($this->post('status_badge', '')),
            'entry_date'        => $this->post('entry_date') ?: date('Y-m-d H:i:s'),
        ];

        $this->patientService->updateTimelineEntry((int)$params['entryId'], $data);
        $timeline = $this->patientService->getTimeline((int)$params['id'], 100);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'timeline' => $timeline]);
        exit;
    }
    
    public function deleteTimelineEntryJson(array $params = []): void
    {
        $this->validateCsrf();
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) { http_response_code(404); header('Content-Type: application/json'); echo json_encode(['error' => 'not found']); exit; }

        $this->patientService->deleteTimelineEntry((int)$params['entryId']);
        $timeline = $this->patientService->getTimeline((int)$params['id'], 100);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'timeline' => $timeline]);
        exit;
    }

    public function wizard(array $params = []): void
    {
        $this->render('patients/wizard.twig', [
            'page_title' => 'Neuer Patient',
        ]);
    }

    public function globalSearch(array $params = []): void
    {
        $q       = trim($this->get('q', ''));
        $results = [];

        if (strlen($q) >= 2) {
            $qLower = strtolower($q);

            // Patients
            $patients = $this->patientService->getPaginated(1, 50, $q)['items'] ?? [];
            foreach (array_slice($patients, 0, 6) as $p) {
                $owner = $this->ownerService->findById((int)$p['owner_id']);
                $results[] = [
                    'type'       => 'patient',
                    'id'         => (int)$p['id'],
                    'name'       => $p['name'],
                    'sub'        => trim(($p['species'] ?? '') . ($p['breed'] ? ' · ' . $p['breed'] : ''))
                                    . ($owner ? ' — ' . $owner['first_name'] . ' ' . $owner['last_name'] : ''),
                    'status'     => $p['status'] ?? '',
                    'photo'      => $p['photo'] ?? null,
                ];
            }

            // Owners
            $owners = $this->ownerService->findAll();
            $ownerCount = 0;
            foreach ($owners as $o) {
                if ($ownerCount >= 4) break;
                $haystack = strtolower(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '') . ' ' . ($o['email'] ?? '') . ' ' . ($o['phone'] ?? ''));
                if (!str_contains($haystack, $qLower)) continue;
                $animals = $this->patientService->findByOwner((int)$o['id']);
                $results[] = [
                    'type'     => 'owner',
                    'id'       => (int)$o['id'],
                    'name'     => $o['first_name'] . ' ' . $o['last_name'],
                    'sub'      => $o['email'] ?? ($o['phone'] ?? ''),
                    'animals'  => array_map(fn($a) => ['id' => (int)$a['id'], 'name' => $a['name']], $animals),
                ];
                $ownerCount++;
            }
        }

        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }

    public function ownerSearch(array $params = []): void
    {
        $q      = trim($this->get('q', ''));
        $owners = [];

        $allOwners = $this->ownerService->findAll();
        $needle    = strtolower($q);

        foreach ($allOwners as $o) {
            $matchesSearch = $needle === ''
                ? true
                : str_contains(strtolower(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '') . ' ' . ($o['email'] ?? '')), $needle);

            if (!$matchesSearch && strlen($needle) >= 2) {
                continue;
            }
            if (!$matchesSearch && $needle !== '') {
                continue;
            }

            $animals = $this->patientService->findByOwner((int)$o['id']);
            $o['animal_count'] = count($animals);
            $o['animals']      = array_map(fn($a) => [
                'id'      => $a['id'],
                'name'    => $a['name'],
                'species' => $a['species'] ?? '',
            ], $animals);
            $owners[] = $o;

            if ($needle === '' && count($owners) >= 25) {
                break;
            }
            if ($needle !== '' && strlen($needle) >= 2 && count($owners) >= 8) {
                break;
            }
        }

        header('Content-Type: application/json');
        echo json_encode($owners);
        exit;
    }

    public function wizardStore(array $params = []): void
    {
        $this->validateCsrf();

        $ownerMode = $this->post('owner_mode', 'existing'); // 'existing' | 'new'
        $ownerId   = 0;

        if ($ownerMode === 'new') {
            $ownerData = [
                'first_name' => $this->sanitize($this->post('owner_first_name', '')),
                'last_name'  => $this->sanitize($this->post('owner_last_name', '')),
                'email'      => $this->sanitize($this->post('owner_email', '')),
                'phone'      => $this->sanitize($this->post('owner_phone', '')),
                'street'     => $this->sanitize($this->post('owner_street', '')),
                'zip'        => $this->sanitize($this->post('owner_zip', '')),
                'city'       => $this->sanitize($this->post('owner_city', '')),
                'notes'      => $this->post('owner_notes', ''),
            ];
            if (empty($ownerData['first_name']) || empty($ownerData['last_name'])) {
                $this->session->flash('error', 'Bitte Vor- und Nachname des Tierhalters angeben.');
                $this->redirect('/patienten/neu');
                return;
            }
            $ownerId = (int)$this->ownerService->create($ownerData);
        } else {
            $ownerId = (int)$this->post('owner_id', 0);
        }

        if (!$ownerId) {
            $this->session->flash('error', 'Bitte einen Tierhalter auswählen oder neu anlegen.');
            $this->redirect('/patienten/neu');
            return;
        }

        $patientData = [
            'name'        => $this->sanitize($this->post('name', '')),
            'species'     => $this->sanitize($this->post('species', '')),
            'breed'       => $this->sanitize($this->post('breed', '')),
            'birth_date'  => $this->post('birth_date', null) ?: null,
            'gender'      => $this->sanitize($this->post('gender', '')),
            'color'       => $this->sanitize($this->post('color', '')),
            'chip_number' => $this->sanitize($this->post('chip_number', '')),
            'notes'       => $this->post('notes', ''),
            'status'      => 'aktiv',
            'owner_id'    => $ownerId,
        ];

        if (empty($patientData['name'])) {
            $this->session->flash('error', 'Bitte einen Namen für den Patienten angeben.');
            $this->redirect('/patienten/neu');
            return;
        }

        $patientId = (int)$this->patientService->create($patientData);
        $this->session->flash('success', 'Patient "' . $patientData['name'] . '" wurde erfolgreich angelegt.');
        $this->redirect("/patienten/{$patientId}");
    }

    public function downloadPatientPdf(array $params = []): void
    {
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            $this->abort(404);
        }

        $owner    = $this->ownerService->findById((int)$patient['owner_id']);
        $timeline = array_filter(
            $this->patientService->getTimeline((int)$params['id']),
            fn($e) => ($e['type'] ?? '') !== 'payment'
        );

        $pdfBytes = $this->pdfService->generatePatientPdf($patient, $owner, array_values($timeline));

        $filename = 'Patientenakte_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $patient['name'] ?? 'Patient') . '_' . date('Y-m-d') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBytes));
        header('Cache-Control: private, max-age=0');
        echo $pdfBytes;
        exit;
    }

    public function uploadDocument(array $params = []): void
    {
        $this->validateCsrf();
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            $this->abort(404);
        }

        $destination = STORAGE_PATH . '/patients/' . $params['id'] . '/docs';
        $filename = $this->uploadFile('document', $destination);

        if ($filename === false) {
            $this->session->flash('error', $this->translator->trans('patients.upload_failed'));
        } else {
            $this->session->flash('success', $this->translator->trans('patients.uploaded'));
        }
        $this->redirect("/patienten/{$params['id']}");
    }

    public function downloadDocument(array $params = []): void
    {
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            $this->abort(404);
        }

        $file = basename($this->sanitize($params['file']));
        $path = STORAGE_PATH . '/patients/' . (int)$params['id'] . '/timeline/' . $file;

        if (!file_exists($path) || !is_file($path)) {
            $this->abort(404);
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        $isInline = str_starts_with($mimeType, 'image/')
            || str_starts_with($mimeType, 'video/')
            || $mimeType === 'application/pdf';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    public function servePhoto(array $params = []): void
    {
        $file = basename($this->sanitize($params['file']));

        /* Check multiple locations: per-patient folder, flat patients dir, intake dir */
        $candidates = [
            STORAGE_PATH . '/patients/' . (int)$params['id'] . '/' . $file,
            STORAGE_PATH . '/patients/' . $file,
            STORAGE_PATH . '/intake/' . $file,
        ];

        $path = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === null) {
            $this->abort(404);
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        if (!str_starts_with($mimeType, 'image/')) {
            $this->abort(403);
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }

    public function downloadHomeworkPdf(array $params = []): void
    {
        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            $this->abort(404);
        }

        $owner    = $patient['owner_id'] ? $this->ownerService->findById((int)$patient['owner_id']) : null;
        $tasks    = $this->homeworkRepository->findPatientHomework((int)$params['id']);
        $meta     = $this->homeworkRepository->getPatientPlanMeta((int)$params['id']) ?? [];

        $plan = [
            'plan_date'          => date('Y-m-d'),
            'physio_principles'  => $meta['physiotherapeutische_grundsaetze'] ?? '',
            'short_term_goals'   => $meta['kurzfristige_ziele'] ?? '',
            'long_term_goals'    => $meta['langfristige_ziele'] ?? '',
            'therapy_means'      => $meta['therapiemittel'] ?? '',
            'general_notes'      => $meta['beachte_hinweise'] ?? '',
            'next_appointment'   => !empty($meta['wiedervorstellung_date'])
                ? date('d.m.Y', strtotime($meta['wiedervorstellung_date']))
                : '',
            'therapist_name'     => $meta['therapist_name'] ?? ($this->settingsRepository->get('company_name', '') ?: ''),
        ];

        $pdfBytes = $this->pdfService->generateHomeworkPdf($plan, $tasks, $owner, $patient);
        $filename = 'Hausaufgaben_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $patient['name'] ?? 'Patient') . '_' . date('Y-m-d') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBytes));
        header('Cache-Control: private, max-age=0');
        echo $pdfBytes;
        exit;
    }

    public function sendHomeworkEmail(array $params = []): void
    {
        $this->validateCsrf();

        $patient = $this->patientService->findById((int)$params['id']);
        if (!$patient) {
            $this->abort(404);
        }

        $owner = $patient['owner_id'] ? $this->ownerService->findById((int)$patient['owner_id']) : null;
        if (!$owner || empty($owner['email'])) {
            $this->session->flash('error', 'Kein E-Mail-Adresse beim Tierhalter hinterlegt.');
            $this->redirect("/patienten/{$params['id']}?tab=hausaufgaben");
            return;
        }

        $tasks = $this->homeworkRepository->findPatientHomework((int)$params['id']);
        $meta  = $this->homeworkRepository->getPatientPlanMeta((int)$params['id']) ?? [];

        $plan = [
            'plan_date'          => date('Y-m-d'),
            'physio_principles'  => $meta['physiotherapeutische_grundsaetze'] ?? '',
            'short_term_goals'   => $meta['kurzfristige_ziele'] ?? '',
            'long_term_goals'    => $meta['langfristige_ziele'] ?? '',
            'therapy_means'      => $meta['therapiemittel'] ?? '',
            'general_notes'      => $meta['beachte_hinweise'] ?? '',
            'next_appointment'   => !empty($meta['wiedervorstellung_date'])
                ? date('d.m.Y', strtotime($meta['wiedervorstellung_date']))
                : '',
            'therapist_name'     => $meta['therapist_name'] ?? ($this->settingsRepository->get('company_name', '') ?: ''),
        ];

        $pdfBytes = $this->pdfService->generateHomeworkPdf($plan, $tasks, $owner, $patient);
        $sent     = $this->mailService->sendHomework($patient, $owner, $pdfBytes);

        if ($sent) {
            $this->session->flash('success', 'Hausaufgaben erfolgreich per E-Mail gesendet.');
        } else {
            $err = $this->mailService->getLastError();
            $this->session->flash('error', 'E-Mail konnte nicht gesendet werden.' . ($err ? ': ' . $err : ''));
        }

        $this->redirect("/patienten/{$params['id']}?tab=hausaufgaben");
    }
}
