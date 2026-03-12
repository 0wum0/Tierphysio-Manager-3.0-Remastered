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

class OwnerPortalController extends Controller
{
    private OwnerPortalRepository $repo;
    private PdfService $pdfService;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db,
        PdfService $pdfService
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo       = new OwnerPortalRepository($db);
        $this->pdfService = $pdfService;
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
            'page_title'  => $pet['name'],
            'portal_user' => $user,
            'pet'         => $pet,
            'treatments'  => array_values($treatments),
            'notes'       => array_values($notes),
            'photos'      => array_values($photos),
            'documents'   => array_values($documents),
            'exercises'   => $exercises,
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

        /* Delegate to the main invoice PDF endpoint — just redirect */
        $this->redirect('/rechnungen/' . $invoiceId . '/pdf');
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
