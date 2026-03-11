<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;
use App\Repositories\SettingsRepository;
use App\Services\MailService;
use App\Services\PdfService;

class OwnerPortalAdminController extends Controller
{
    private OwnerPortalRepository $repo;
    private OwnerPortalMailService $mailer;
    private PdfService $pdfService;
    private MailService $mailService;
    private SettingsRepository $settingsRepository;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db,
        SettingsRepository $settingsRepository,
        MailService $mailService,
        PdfService $pdfService
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo               = new OwnerPortalRepository($db);
        $this->mailer             = new OwnerPortalMailService($settingsRepository, $mailService);
        $this->pdfService         = $pdfService;
        $this->mailService        = $mailService;
        $this->settingsRepository = $settingsRepository;
    }

    /* ── GET /portal-admin ── */
    public function index(array $params = []): void
    {
        $users = $this->repo->getAllPortalUsers();

        $this->render('@owner-portal/admin_index.twig', [
            'page_title'   => 'Besitzerportal — Verwaltung',
            'portal_users' => $users,
            'csrf_token'   => $this->session->generateCsrfToken(),
            'success'      => $this->session->getFlash('success'),
            'error'        => $this->session->getFlash('error'),
        ]);
    }

    /* ── GET /portal-admin/einladen ── */
    public function showInvite(array $params = []): void
    {
        /* Load all owners that don't yet have a portal account */
        $allUsers    = $this->repo->getAllPortalUsers();
        $linkedOwnerIds = array_column($allUsers, 'owner_id');

        $db    = \App\Core\Application::getInstance()->getContainer()->get(Database::class);
        $stmt  = $db->query(
            'SELECT id, first_name, last_name, email FROM owners ORDER BY last_name ASC, first_name ASC'
        );
        $owners = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('@owner-portal/admin_invite.twig', [
            'page_title'      => 'Besitzer einladen',
            'owners'          => $owners,
            'linked_owner_ids'=> $linkedOwnerIds,
            'csrf_token'      => $this->session->generateCsrfToken(),
            'error'           => $this->session->getFlash('error'),
        ]);
    }

    /* ── POST /portal-admin/einladen ── */
    public function sendInvite(array $params = []): void
    {
        $this->validateCsrf();

        $ownerId = (int)$this->post('owner_id', 0);
        $email   = strtolower(trim($this->post('email', '')));

        if (!$ownerId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Bitte Besitzer und gültige E-Mail angeben.');
            $this->redirect('/portal-admin/einladen');
            return;
        }

        /* Check if already exists */
        $existing = $this->repo->findUserByEmail($email);
        if ($existing) {
            $this->session->flash('error', 'Diese E-Mail hat bereits einen Portal-Account.');
            $this->redirect('/portal-admin/einladen');
            return;
        }

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

        $this->repo->createUser([
            'owner_id'       => $ownerId,
            'email'          => $email,
            'password_hash'  => null,
            'is_active'      => 0,
            'invite_token'   => $token,
            'invite_expires' => $expires,
        ]);

        try {
            $this->mailer->sendInvite($email, $token);
        } catch (\Throwable $e) {
            /* Mail failed but user was created — show warning */
            $this->session->flash('error', 'Konto erstellt, aber E-Mail konnte nicht gesendet werden: ' . $e->getMessage());
            $this->redirect('/portal-admin');
            return;
        }

        $this->session->flash('success', 'Einladung wurde gesendet an ' . htmlspecialchars($email));
        $this->redirect('/portal-admin');
    }

    /* ── POST /portal-admin/{id}/deaktivieren ── */
    public function deactivate(array $params = []): void
    {
        $this->validateCsrf();
        $id = (int)($params['id'] ?? 0);
        $this->repo->updateUser($id, ['is_active' => 0]);
        $this->session->flash('success', 'Portal-Zugang deaktiviert.');
        $this->redirect('/portal-admin');
    }

    /* ── POST /portal-admin/{id}/aktivieren ── */
    public function activate(array $params = []): void
    {
        $this->validateCsrf();
        $id = (int)($params['id'] ?? 0);
        $this->repo->updateUser($id, ['is_active' => 1]);
        $this->session->flash('success', 'Portal-Zugang aktiviert.');
        $this->redirect('/portal-admin');
    }

    /* ── POST /portal-admin/{id}/neu-einladen ── */
    public function resendInvite(array $params = []): void
    {
        $this->validateCsrf();
        $id   = (int)($params['id'] ?? 0);
        $user = $this->repo->findUserById($id);
        if (!$user) {
            $this->session->flash('error', 'Benutzer nicht gefunden.');
            $this->redirect('/portal-admin');
            return;
        }

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

        $this->repo->updateUser($id, [
            'invite_token'   => $token,
            'invite_expires' => $expires,
            'invite_used_at' => null,
            'is_active'      => 0,
        ]);

        try {
            $this->mailer->sendInvite($user['email'], $token);
            $this->session->flash('success', 'Neue Einladung gesendet an ' . htmlspecialchars($user['email']));
        } catch (\Throwable $e) {
            $this->session->flash('error', 'E-Mail konnte nicht gesendet werden: ' . $e->getMessage());
        }

        $this->redirect('/portal-admin');
    }

    /* ── GET /portal-admin/tiere/{owner_id}/uebungen ── */
    public function exerciseIndex(array $params = []): void
    {
        $ownerId = (int)($params['owner_id'] ?? 0);
        $db      = \App\Core\Application::getInstance()->getContainer()->get(Database::class);

        $ownerStmt = $db->query(
            'SELECT o.*, u.email AS portal_email FROM owners o LEFT JOIN owner_portal_users u ON u.owner_id = o.id WHERE o.id = ? LIMIT 1',
            [$ownerId]
        );
        $owner = $ownerStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$owner) { $this->abort(404); return; }

        $patients = $this->repo->getPetsByOwnerId($ownerId);

        /* Build exercises map keyed by patient_id */
        $exercisesByPatient = [];
        foreach ($patients as $p) {
            $exercisesByPatient[$p['id']] = $this->repo->getExercisesByPatient((int)$p['id']);
        }

        $this->render('@owner-portal/admin_exercises.twig', [
            'page_title'          => 'Übungen — ' . trim($owner['first_name'] . ' ' . $owner['last_name']),
            'owner'               => $owner,
            'patients'            => $patients,
            'exercises_by_patient'=> $exercisesByPatient,
            'csrf_token'          => $this->session->generateCsrfToken(),
            'success'             => $this->session->getFlash('success'),
            'error'               => $this->session->getFlash('error'),
        ]);
    }

    /* ── POST /portal-admin/tiere/{owner_id}/uebungen ── */
    public function exerciseStore(array $params = []): void
    {
        $this->validateCsrf();
        $ownerId   = (int)($params['owner_id'] ?? 0);
        $patientId = (int)$this->post('patient_id', 0);
        $userId    = $this->session->getUser()['id'] ?? null;

        if (!$patientId) {
            $this->session->flash('error', 'Bitte einen Patienten auswählen.');
            $this->redirect('/portal-admin/tiere/' . $ownerId . '/uebungen');
            return;
        }

        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $image = $this->uploadFile('image', STORAGE_PATH . '/uploads/exercises', [
                'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            ]);
        }

        $this->repo->createExercise([
            'patient_id'  => $patientId,
            'title'       => $this->sanitize($this->post('title', '')),
            'description' => $this->post('description', ''),
            'video_url'   => $this->sanitize($this->post('video_url', '')),
            'image'       => $image ?: null,
            'sort_order'  => (int)$this->post('sort_order', 0),
            'created_by'  => $userId,
        ]);

        $this->session->flash('success', 'Übung hinzugefügt.');
        $this->redirect('/portal-admin/tiere/' . $ownerId . '/uebungen');
    }

    /* ── POST /portal-admin/uebungen/{id}/loeschen ── */
    public function exerciseDelete(array $params = []): void
    {
        $this->validateCsrf();
        $id       = (int)($params['id'] ?? 0);
        $exercise = $this->repo->getExerciseById($id);
        if (!$exercise) { $this->abort(404); return; }

        $this->repo->deleteExercise($id);
        $this->session->flash('success', 'Übung gelöscht.');
        $this->redirect('/portal-admin/tiere/' . $exercise['patient_id'] . '/uebungen');
    }

    /* ── POST /portal-admin/uebungen/{id}/bearbeiten ── */
    public function exerciseUpdate(array $params = []): void
    {
        $this->validateCsrf();
        $id       = (int)($params['id'] ?? 0);
        $exercise = $this->repo->getExerciseById($id);
        if (!$exercise) { $this->abort(404); return; }

        $data = [
            'title'       => $this->sanitize($this->post('title', '')),
            'description' => $this->post('description', ''),
            'video_url'   => $this->sanitize($this->post('video_url', '')),
            'sort_order'  => (int)$this->post('sort_order', 0),
            'is_active'   => (int)$this->post('is_active', 1),
        ];

        if (!empty($_FILES['image']['name'])) {
            $image = $this->uploadFile('image', STORAGE_PATH . '/uploads/exercises', [
                'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            ]);
            if ($image) $data['image'] = $image;
        }

        $this->repo->updateExercise($id, $data);
        $this->session->flash('success', 'Übung aktualisiert.');
        $this->redirect('/portal-admin/tiere/' . $exercise['patient_id'] . '/uebungen');
    }

    /* ════════════════════════════════════════════════════════════════
       HAUSAUFGABEN-PLÄNE
    ════════════════════════════════════════════════════════════════ */

    /* ── GET /portal-admin/tiere/{owner_id}/hausaufgaben ── */
    public function homeworkPlanIndex(array $params = []): void
    {
        $ownerId = (int)($params['owner_id'] ?? 0);
        $db      = \App\Core\Application::getInstance()->getContainer()->get(Database::class);

        $ownerStmt = $db->query(
            'SELECT o.*, u.email AS portal_email FROM owners o LEFT JOIN owner_portal_users u ON u.owner_id = o.id WHERE o.id = ? LIMIT 1',
            [$ownerId]
        );
        $owner = $ownerStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$owner) { $this->abort(404); return; }

        $patients = $this->repo->getPetsByOwnerId($ownerId);

        $plansByPatient = [];
        foreach ($patients as $p) {
            $plansByPatient[$p['id']] = $this->repo->getHomeworkPlansByPatient((int)$p['id']);
        }

        $templates = $this->repo->getAllHomeworkTemplates();

        $this->render('@owner-portal/admin_homework.twig', [
            'page_title'       => 'Hausaufgaben — ' . trim($owner['first_name'] . ' ' . $owner['last_name']),
            'owner'            => $owner,
            'patients'         => $patients,
            'plans_by_patient' => $plansByPatient,
            'templates'        => $templates,
            'csrf_token'       => $this->session->generateCsrfToken(),
            'success'          => $this->session->getFlash('success'),
            'error'            => $this->session->getFlash('error'),
        ]);
    }

    /* ── POST /portal-admin/tiere/{owner_id}/hausaufgaben ── */
    public function homeworkPlanStore(array $params = []): void
    {
        $this->validateCsrf();
        $ownerId   = (int)($params['owner_id'] ?? 0);
        $patientId = (int)$this->post('patient_id', 0);
        $userId    = $this->session->getUser()['id'] ?? null;

        if (!$patientId) {
            $this->session->flash('error', 'Bitte einen Patienten auswählen.');
            $this->redirect('/portal-admin/tiere/' . $ownerId . '/hausaufgaben');
            return;
        }

        $planId = $this->repo->createHomeworkPlan([
            'patient_id'        => $patientId,
            'owner_id'          => $ownerId,
            'plan_date'         => $this->post('plan_date', date('Y-m-d')),
            'physio_principles' => $this->post('physio_principles', ''),
            'short_term_goals'  => $this->post('short_term_goals', ''),
            'long_term_goals'   => $this->post('long_term_goals', ''),
            'therapy_means'     => $this->post('therapy_means', ''),
            'general_notes'     => $this->post('general_notes', ''),
            'next_appointment'  => $this->post('next_appointment', ''),
            'therapist_name'    => $this->post('therapist_name', ''),
            'status'            => 'active',
            'created_by'        => $userId,
        ]);

        $this->repo->saveTasksForPlan($planId, $this->parseTasksFromPost());

        $this->session->flash('success', 'Hausaufgabenplan erstellt.');
        $this->redirect('/portal-admin/tiere/' . $ownerId . '/hausaufgaben');
    }

    /* ── GET /portal-admin/hausaufgaben/{id}/bearbeiten ── */
    public function homeworkPlanEdit(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $plan = $this->repo->getHomeworkPlanById($id);
        if (!$plan) { $this->abort(404); return; }

        $tasks    = $this->repo->getTasksByPlan($id);
        $owner    = $this->getOwnerById((int)$plan['owner_id']);
        $patients = $this->repo->getPetsByOwnerId((int)$plan['owner_id']);
        $templates = $this->repo->getAllHomeworkTemplates();

        $this->render('@owner-portal/admin_homework_edit.twig', [
            'page_title' => 'Hausaufgabenplan bearbeiten',
            'plan'       => $plan,
            'tasks'      => $tasks,
            'owner'      => $owner,
            'patients'   => $patients,
            'templates'  => $templates,
            'csrf_token' => $this->session->generateCsrfToken(),
            'success'    => $this->session->getFlash('success'),
            'error'      => $this->session->getFlash('error'),
        ]);
    }

    /* ── POST /portal-admin/hausaufgaben/{id}/bearbeiten ── */
    public function homeworkPlanUpdate(array $params = []): void
    {
        $this->validateCsrf();
        $id   = (int)($params['id'] ?? 0);
        $plan = $this->repo->getHomeworkPlanById($id);
        if (!$plan) { $this->abort(404); return; }

        $this->repo->updateHomeworkPlan($id, [
            'plan_date'         => $this->post('plan_date', date('Y-m-d')),
            'physio_principles' => $this->post('physio_principles', ''),
            'short_term_goals'  => $this->post('short_term_goals', ''),
            'long_term_goals'   => $this->post('long_term_goals', ''),
            'therapy_means'     => $this->post('therapy_means', ''),
            'general_notes'     => $this->post('general_notes', ''),
            'next_appointment'  => $this->post('next_appointment', ''),
            'therapist_name'    => $this->post('therapist_name', ''),
            'status'            => $this->post('status', 'active'),
        ]);

        $this->repo->saveTasksForPlan($id, $this->parseTasksFromPost());

        $this->session->flash('success', 'Hausaufgabenplan aktualisiert.');
        $this->redirect('/portal-admin/tiere/' . $plan['owner_id'] . '/hausaufgaben');
    }

    /* ── POST /portal-admin/hausaufgaben/{id}/loeschen ── */
    public function homeworkPlanDelete(array $params = []): void
    {
        $this->validateCsrf();
        $id   = (int)($params['id'] ?? 0);
        $plan = $this->repo->getHomeworkPlanById($id);
        if (!$plan) { $this->abort(404); return; }

        $ownerId = $plan['owner_id'];
        $this->repo->deleteHomeworkPlan($id);
        $this->session->flash('success', 'Hausaufgabenplan gelöscht.');
        $this->redirect('/portal-admin/tiere/' . $ownerId . '/hausaufgaben');
    }

    /* ── GET /portal-admin/hausaufgaben/{id}/pdf ── */
    public function homeworkPlanPdf(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $plan = $this->repo->getHomeworkPlanById($id);
        if (!$plan) { $this->abort(404); return; }

        $tasks   = $this->repo->getTasksByPlan($id);
        $owner   = $this->getOwnerById((int)$plan['owner_id']);
        $patient = $this->getPatientById((int)$plan['patient_id']);

        $pdfContent = $this->pdfService->generateHomeworkPdf($plan, $tasks, $owner, $patient);

        $filename = 'Hausaufgaben-' . ($patient['name'] ?? 'Plan') . '-' . date('Y-m-d', strtotime($plan['plan_date'])) . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        echo $pdfContent;
        exit;
    }

    /* ── POST /portal-admin/hausaufgaben/{id}/senden ── */
    public function homeworkPlanSend(array $params = []): void
    {
        $this->validateCsrf();
        $id   = (int)($params['id'] ?? 0);
        $plan = $this->repo->getHomeworkPlanById($id);
        if (!$plan) { $this->abort(404); return; }

        $tasks   = $this->repo->getTasksByPlan($id);
        $owner   = $this->getOwnerById((int)$plan['owner_id']);
        $patient = $this->getPatientById((int)$plan['patient_id']);

        if (!$owner || empty($owner['email'])) {
            $this->session->flash('error', 'Kein E-Mail-Adresse für diesen Besitzer hinterlegt.');
            $this->redirect('/portal-admin/tiere/' . $plan['owner_id'] . '/hausaufgaben');
            return;
        }

        $pdfContent  = $this->pdfService->generateHomeworkPdf($plan, $tasks, $owner, $patient);
        $companyName = $this->settingsRepository->get('company_name', 'Tierphysio Praxis');
        $petName     = $patient['name'] ?? 'Ihr Tier';
        $ownerName   = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
        $planDate    = !empty($plan['plan_date']) ? date('d.m.Y', strtotime($plan['plan_date'])) : date('d.m.Y');
        $filename    = 'Hausaufgaben-' . ($patient['name'] ?? 'Plan') . '-' . date('Y-m-d', strtotime($plan['plan_date'])) . '.pdf';

        $subject = 'Hausaufgaben für ' . $petName . ' — ' . $companyName;
        $body    = "Guten Tag " . $ownerName . ",\n\n"
                 . "anbei finden Sie die Hausaufgaben für " . $petName . " vom " . $planDate . ".\n\n"
                 . "Bitte führen Sie die Übungen wie beschrieben durch.\n"
                 . "Bei Fragen stehen wir Ihnen gerne zur Verfügung.\n\n"
                 . "Mit freundlichen Grüßen\n"
                 . $companyName;

        $sent = $this->mailService->sendRaw(
            $owner['email'],
            $ownerName,
            $subject,
            nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
            [['content' => $pdfContent, 'name' => $filename, 'mime' => 'application/pdf']]
        );

        if ($sent) {
            $this->repo->updateHomeworkPlan($id, [
                'pdf_sent_at' => date('Y-m-d H:i:s'),
                'pdf_sent_to' => $owner['email'],
            ]);
            $this->session->flash('success', 'Hausaufgaben per E-Mail gesendet an ' . htmlspecialchars($owner['email']));
        } else {
            $this->session->flash('error', 'E-Mail konnte nicht gesendet werden: ' . $this->mailService->getLastError());
        }

        $this->redirect('/portal-admin/tiere/' . $plan['owner_id'] . '/hausaufgaben');
    }

    /* ── Helpers ── */

    private function parseTasksFromPost(): array
    {
        $titles       = $_POST['task_title']       ?? [];
        $descriptions = $_POST['task_description'] ?? [];
        $frequencies  = $_POST['task_frequency']   ?? [];
        $durations    = $_POST['task_duration']    ?? [];
        $notes        = $_POST['task_notes']       ?? [];
        $templateIds  = $_POST['task_template_id'] ?? [];

        $tasks = [];
        foreach ($titles as $i => $title) {
            $tasks[] = [
                'title'           => $title,
                'description'     => $descriptions[$i] ?? '',
                'frequency'       => $frequencies[$i]  ?? '',
                'duration'        => $durations[$i]    ?? '',
                'therapist_notes' => $notes[$i]        ?? '',
                'template_id'     => !empty($templateIds[$i]) ? (int)$templateIds[$i] : null,
            ];
        }
        return $tasks;
    }

    private function getOwnerById(int $id): ?array
    {
        $db   = \App\Core\Application::getInstance()->getContainer()->get(Database::class);
        $stmt = $db->query('SELECT * FROM owners WHERE id = ? LIMIT 1', [$id]);
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getPatientById(int $id): ?array
    {
        $db   = \App\Core\Application::getInstance()->getContainer()->get(Database::class);
        $stmt = $db->query('SELECT * FROM patients WHERE id = ? LIMIT 1', [$id]);
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
