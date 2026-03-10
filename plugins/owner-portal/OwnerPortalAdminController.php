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

class OwnerPortalAdminController extends Controller
{
    private OwnerPortalRepository $repo;
    private OwnerPortalMailService $mailer;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db,
        SettingsRepository $settingsRepository,
        MailService $mailService
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo   = new OwnerPortalRepository($db);
        $this->mailer = new OwnerPortalMailService($settingsRepository, $mailService);
    }

    /* ── GET /portal-admin ── */
    public function index(array $params = []): void
    {
        $users = $this->repo->getAllPortalUsers();

        $this->render('@owner-portal/admin_index.twig', [
            'page_title'   => 'Besitzerportal — Verwaltung',
            'portal_users' => $users,
            'csrf_token'   => $this->session->getCsrfToken(),
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
            'csrf_token'      => $this->session->getCsrfToken(),
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

    /* ── GET /portal-admin/tiere/{patient_id}/uebungen ── */
    public function exerciseIndex(array $params = []): void
    {
        $patientId = (int)($params['patient_id'] ?? 0);
        $db        = \App\Core\Application::getInstance()->getContainer()->get(Database::class);
        $stmt      = $db->query('SELECT * FROM patients WHERE id = ? LIMIT 1', [$patientId]);
        $patient   = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$patient) { $this->abort(404); return; }

        $exercises = $this->repo->getExercisesByPatient($patientId);

        $this->render('@owner-portal/admin_exercises.twig', [
            'page_title'  => 'Übungen — ' . $patient['name'],
            'patient'     => $patient,
            'exercises'   => $exercises,
            'csrf_token'  => $this->session->getCsrfToken(),
            'success'     => $this->session->getFlash('success'),
            'error'       => $this->session->getFlash('error'),
        ]);
    }

    /* ── POST /portal-admin/tiere/{patient_id}/uebungen ── */
    public function exerciseStore(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)($params['patient_id'] ?? 0);
        $userId    = $this->session->getUser()['id'] ?? null;

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
        $this->redirect('/portal-admin/tiere/' . $patientId . '/uebungen');
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
}
