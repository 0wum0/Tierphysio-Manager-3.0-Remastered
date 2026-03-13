<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;
use App\Services\PdfService;
use App\Repositories\SettingsRepository;

class OwnerPortalController extends Controller
{
    private OwnerPortalRepository $repo;
    private PdfService $pdfService;
    private SettingsRepository $settingsRepository;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db,
        PdfService $pdfService,
        SettingsRepository $settingsRepository
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo               = new OwnerPortalRepository($db);
        $this->pdfService         = $pdfService;
        $this->settingsRepository = $settingsRepository;
    }

    private function isHomeworkEnabled(): bool
    {
        $settings = $this->settingsRepository->all();
        return ($settings['portal_show_homework'] ?? '1') === '1';
    }

    /* ── Auth guard helper ── */
    private function requireOwnerAuth(): array
    {
        $userId = $this->session->get('owner_portal_user_id');
        if (!$userId) {
            $this->redirect('/portal/login');
            exit;
        }
        $user = $this->repo->findUserById((int)$userId);
        if (!$user || !$user['is_active']) {
            $this->session->remove('owner_portal_user_id');
            $this->session->remove('owner_portal_owner_id');
            $this->redirect('/portal/login');
            exit;
        }
        return $user;
    }

    /* ── GET /portal/dashboard ── */
    public function dashboard(array $params = []): void
    {
        $user    = $this->requireOwnerAuth();
        $ownerId = (int)$user['owner_id'];

        $pets         = $this->repo->getPetsByOwnerId($ownerId);
        $invoices     = $this->repo->getInvoicesByOwnerId($ownerId);
        $appointments = $this->repo->getAppointmentsByOwnerId($ownerId);

        $upcomingAppointments = array_filter($appointments, fn($a) => strtotime($a['start_at']) >= time());
        $openInvoices         = array_filter($invoices, fn($i) => in_array($i['status'], ['open', 'overdue'], true));

        $allExercises = [];
        if (!empty($pets)) {
            $petIds       = array_column($pets, 'id');
            $allExercises = $this->repo->getAllExercisesForPatients($petIds);
        }

        $this->render('@owner-portal/owner_dashboard.twig', [
            'page_title'           => 'Mein Tierportal',
            'portal_user'          => $user,
            'pets'                 => $pets,
            'upcoming_appointments'=> array_values($upcomingAppointments),
            'open_invoices'        => array_values($openInvoices),
            'exercises'            => $allExercises,
            'csrf_token'           => $this->session->generateCsrfToken(),
        ]);
    }

    /* ── GET /portal/tiere ── */
    public function petList(array $params = []): void
    {
        $user    = $this->requireOwnerAuth();
        $ownerId = (int)$user['owner_id'];
        $pets    = $this->repo->getPetsByOwnerId($ownerId);

        $this->render('@owner-portal/owner_pet_list.twig', [
            'page_title'  => 'Meine Tiere',
            'portal_user' => $user,
            'pets'        => $pets,
        ]);
    }

    /* ── GET /portal/tiere/{id} ── */
    public function petDetail(array $params = []): void
    {
        $user    = $this->requireOwnerAuth();
        $ownerId = (int)$user['owner_id'];
        $petId   = (int)($params['id'] ?? 0);

        $pet = $this->repo->getPetByIdAndOwner($petId, $ownerId);
        if (!$pet) {
            $this->abort(404);
            return;
        }

        $timeline  = $this->repo->getPetTimeline($petId);
        $exercises = $this->repo->getExercisesByPatient($petId);

        $treatments = array_filter($timeline, fn($e) => $e['type'] === 'treatment');
        $notes      = array_filter($timeline, fn($e) => $e['type'] === 'note');
        $photos     = array_filter($timeline, fn($e) => $e['type'] === 'photo');
        $documents  = array_filter($timeline, fn($e) => $e['type'] === 'document');

        $this->render('@owner-portal/owner_pet_detail.twig', [
            'page_title'          => $pet['name'],
            'portal_user'         => $user,
            'pet'                 => $pet,
            'treatments'          => array_values($treatments),
            'notes'               => array_values($notes),
            'photos'              => array_values($photos),
            'documents'           => array_values($documents),
            'exercises'           => $exercises,
            'show_homework'       => $this->isHomeworkEnabled(),
        ]);
    }

    /* ── GET /portal/rechnungen ── */
    public function invoices(array $params = []): void
    {
        $user     = $this->requireOwnerAuth();
        $ownerId  = (int)$user['owner_id'];
        $invoices = $this->repo->getInvoicesByOwnerId($ownerId);

        $this->render('@owner-portal/owner_invoices.twig', [
            'page_title'  => 'Meine Rechnungen',
            'portal_user' => $user,
            'invoices'    => $invoices,
        ]);
    }

    /* ── GET /portal/rechnungen/{id}/pdf ── */
    public function invoicePdf(array $params = []): void
    {
        $user      = $this->requireOwnerAuth();
        $ownerId   = (int)$user['owner_id'];
        $invoiceId = (int)($params['id'] ?? 0);

        $invoice = $this->repo->getInvoiceByIdAndOwner($invoiceId, $ownerId);
        if (!$invoice) {
            $this->abort(403);
            return;
        }

        /* Generate PDF directly — do NOT redirect to the admin route */
        $db        = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
        $posStmt   = $db->query('SELECT * FROM invoice_positions WHERE invoice_id = ? ORDER BY sort_order ASC', [$invoiceId]);
        $positions = $posStmt->fetchAll(\PDO::FETCH_ASSOC);

        $ownerStmt = $db->query('SELECT * FROM owners WHERE id = ? LIMIT 1', [$ownerId]);
        $owner     = $ownerStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        $patient = null;
        if (!empty($invoice['patient_id'])) {
            $patStmt = $db->query('SELECT * FROM patients WHERE id = ? LIMIT 1', [(int)$invoice['patient_id']]);
            $patient = $patStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        $pdf = $this->pdfService->generateInvoicePdf($invoice, $positions, $owner, $patient);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Rechnung-' . htmlspecialchars($invoice['invoice_number'], ENT_QUOTES, 'UTF-8') . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /* ── GET /portal/termine ── */
    public function appointments(array $params = []): void
    {
        $user         = $this->requireOwnerAuth();
        $ownerId      = (int)$user['owner_id'];
        $appointments = $this->repo->getAppointmentsByOwnerId($ownerId);

        $upcoming = array_values(array_filter($appointments, fn($a) => strtotime($a['start_at']) >= time()));
        $past     = array_values(array_filter($appointments, fn($a) => strtotime($a['start_at']) < time()));

        $this->render('@owner-portal/owner_appointments.twig', [
            'page_title'  => 'Meine Termine',
            'portal_user' => $user,
            'upcoming'    => $upcoming,
            'past'        => $past,
        ]);
    }

    /* ── GET /portal/tiere/{id}/foto/{file} ── */
    public function petPhoto(array $params = []): void
    {
        $user    = $this->requireOwnerAuth();
        $ownerId = (int)$user['owner_id'];
        $petId   = (int)($params['id'] ?? 0);

        /* Security: verify this pet belongs to this owner */
        $pet = $this->repo->getPetByIdAndOwner($petId, $ownerId);
        if (!$pet) { $this->abort(403); return; }

        $file = basename($params['file'] ?? '');
        if (!$file) { $this->abort(404); return; }

        $candidates = [
            STORAGE_PATH . '/patients/' . $petId . '/' . $file,
            STORAGE_PATH . '/patients/' . $file,
        ];

        $path = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === null) { $this->abort(404); return; }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);
        if (!str_starts_with($mimeType, 'image/')) { $this->abort(403); return; }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }

    /* ── GET /portal/tiere/{id}/bearbeiten ── */
    public function petEdit(array $params = []): void
    {
        $user    = $this->requireOwnerAuth();
        $ownerId = (int)$user['owner_id'];
        $petId   = (int)($params['id'] ?? 0);

        $pet = $this->repo->getPetByIdAndOwner($petId, $ownerId);
        if (!$pet) { $this->abort(404); return; }

        $this->render('@owner-portal/owner_pet_edit.twig', [
            'page_title'  => $pet['name'] . ' bearbeiten',
            'portal_user' => $user,
            'pet'         => $pet,
            'csrf_token'  => $this->session->generateCsrfToken(),
            'success'     => $this->session->getFlash('success'),
            'error'       => $this->session->getFlash('error'),
        ]);
    }

    /* ── POST /portal/tiere/{id}/bearbeiten ── */
    public function petEditSave(array $params = []): void
    {
        $this->validateCsrf();
        $user    = $this->requireOwnerAuth();
        $ownerId = (int)$user['owner_id'];
        $petId   = (int)($params['id'] ?? 0);

        $pet = $this->repo->getPetByIdAndOwner($petId, $ownerId);
        if (!$pet) { $this->abort(404); return; }

        $data = [
            'name'        => $this->sanitize($this->post('name', '')),
            'species'     => $this->sanitize($this->post('species', '')),
            'breed'       => $this->sanitize($this->post('breed', '')),
            'birth_date'  => $this->post('birth_date', '') ?: null,
            'gender'      => $this->sanitize($this->post('gender', '')),
            'color'       => $this->sanitize($this->post('color', '')),
            'chip_number' => $this->sanitize($this->post('chip_number', '')),
        ];

        if (empty($data['name'])) {
            $this->session->flash('error', 'Name darf nicht leer sein.');
            $this->redirect('/portal/tiere/' . $petId . '/bearbeiten');
            return;
        }

        /* Photo upload */
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $destination = STORAGE_PATH . '/patients/' . $petId;
            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }
            $filename = $this->uploadFile('photo', $destination, [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            ]);
            if ($filename !== false) {
                $data['photo'] = $filename;
            }
        }

        $this->repo->updatePet($petId, $data);

        $this->session->flash('success', 'Änderungen gespeichert.');
        $this->redirect('/portal/tiere/' . $petId);
    }

    /* ── GET /portal/tiere/{id}/uebungen ── */
    public function exercises(array $params = []): void
    {
        $user    = $this->requireOwnerAuth();
        $ownerId = (int)$user['owner_id'];
        $petId   = (int)($params['id'] ?? 0);

        $pet = $this->repo->getPetByIdAndOwner($petId, $ownerId);
        if (!$pet) {
            $this->abort(404);
            return;
        }

        $exercises = $this->repo->getExercisesByPatient($petId);

        $this->render('@owner-portal/owner_exercises.twig', [
            'page_title'  => 'Übungen – ' . $pet['name'],
            'portal_user' => $user,
            'pet'         => $pet,
            'exercises'   => $exercises,
        ]);
    }

    /* ── GET /portal/tiere/{id}/hausaufgaben/{plan_id}/pdf ── */
    public function homeworkPdf(array $params = []): void
    {
        if (!$this->isHomeworkEnabled()) { $this->abort(403); return; }
        $user    = $this->requireOwnerAuth();
        $ownerId = (int)$user['owner_id'];
        $petId   = (int)($params['id'] ?? 0);
        $planId  = (int)($params['plan_id'] ?? 0);

        $pet  = $this->repo->getPetByIdAndOwner($petId, $ownerId);
        $plan = $this->repo->getHomeworkPlanById($planId);

        if (!$pet || !$plan || (int)$plan['owner_id'] !== $ownerId) {
            $this->abort(403);
            return;
        }

        $tasks   = $this->repo->getTasksByPlan($planId);
        $db      = \App\Core\Application::getInstance()->getContainer()->get(Database::class);
        $ownerStmt = $db->query('SELECT * FROM owners WHERE id = ? LIMIT 1', [$ownerId]);
        $owner   = $ownerStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        $patient = $pet;

        $pdfContent = $this->pdfService->generateHomeworkPdf($plan, $tasks, $owner, $patient);
        $filename   = 'Hausaufgaben-' . ($pet['name'] ?? 'Plan') . '-' . date('Y-m-d', strtotime($plan['plan_date'])) . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        echo $pdfContent;
        exit;
    }

    /* ── GET /portal/tiere/{id}/hausaufgaben ── */
    public function homework(array $params = []): void
    {
        if (!$this->isHomeworkEnabled()) { $this->abort(403); return; }
        $user    = $this->requireOwnerAuth();
        $ownerId = (int)$user['owner_id'];
        $petId   = (int)($params['id'] ?? 0);

        $pet = $this->repo->getPetByIdAndOwner($petId, $ownerId);
        if (!$pet) {
            $this->abort(404);
            return;
        }

        $plans = $this->repo->getHomeworkPlansByPatient($petId);

        // Load tasks for each plan
        $tasksByPlan = [];
        foreach ($plans as $p) {
            $tasksByPlan[$p['id']] = $this->repo->getTasksByPlan((int)$p['id']);
        }

        $this->render('@owner-portal/owner_homework.twig', [
            'page_title'   => 'Hausaufgaben – ' . $pet['name'],
            'portal_user'  => $user,
            'pet'          => $pet,
            'plans'        => $plans,
            'tasks_by_plan'=> $tasksByPlan,
        ]);
    }
}
