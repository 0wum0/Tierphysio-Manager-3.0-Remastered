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

class OwnerAuthController extends Controller
{
    private OwnerPortalRepository $repo;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo = new OwnerPortalRepository($db);
    }

    /* ── GET /portal/login ── */
    public function showLogin(array $params = []): void
    {
        if ($this->session->get('owner_portal_user_id')) {
            $this->redirect('/portal/dashboard');
            return;
        }
        $this->render('@owner-portal/owner_login.twig', [
            'page_title' => 'Besitzerportal — Login',
            'csrf_token' => $this->session->generateCsrfToken(),
            'error'      => $this->session->getFlash('error'),
            'success'    => $this->session->getFlash('success'),
        ]);
    }

    /* ── POST /portal/login ── */
    public function login(array $params = []): void
    {
        $token = $this->post('_csrf_token', '');
        if (!$this->session->validateCsrfToken($token)) {
            $this->session->flash('error', 'Ungültiges Sicherheitstoken. Bitte neu laden.');
            $this->redirect('/portal/login');
            return;
        }

        $email    = strtolower(trim($this->post('email', '')));
        $password = $this->post('password', '');
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $this->repo->cleanOldAttempts();

        if ($this->repo->countRecentAttempts($email, $ip, 15) >= 5) {
            $this->session->flash('error', 'Zu viele Anmeldeversuche. Bitte warte 15 Minuten.');
            $this->redirect('/portal/login');
            return;
        }

        $this->repo->logLoginAttempt($email, $ip);

        $user = $this->repo->findUserByEmail($email);

        if (!$user || !$user['is_active'] || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
            $this->session->flash('error', 'E-Mail oder Passwort ist falsch.');
            $this->redirect('/portal/login');
            return;
        }

        $this->repo->updateLastLogin((int)$user['id']);
        $this->session->set('owner_portal_user_id', (int)$user['id']);
        $this->session->set('owner_portal_owner_id', (int)$user['owner_id']);
        $this->redirect('/portal/dashboard');
    }

    /* ── POST /portal/logout ── */
    public function logout(array $params = []): void
    {
        $token = $this->post('_csrf_token', '');
        if (!$this->session->validateCsrfToken($token)) {
            $this->redirect('/portal/dashboard');
            return;
        }
        $this->session->remove('owner_portal_user_id');
        $this->session->remove('owner_portal_owner_id');
        $this->redirect('/portal/login');
    }

    /* ── GET /portal/einladung/{token} ── */
    public function showSetPassword(array $params = []): void
    {
        $token = $params['token'] ?? '';
        $user  = $this->repo->findUserByInviteToken($token);

        if (!$user || $user['invite_used_at'] || ($user['invite_expires'] && strtotime($user['invite_expires']) < time())) {
            $this->render('@owner-portal/owner_invite_invalid.twig', [
                'page_title' => 'Link ungültig',
            ]);
            return;
        }

        $this->render('@owner-portal/owner_set_password.twig', [
            'page_title' => 'Passwort festlegen',
            'token'      => $token,
            'csrf_token' => $this->session->generateCsrfToken(),
            'error'      => $this->session->getFlash('error'),
        ]);
    }

    /* ── POST /portal/einladung/{token} ── */
    public function setPassword(array $params = []): void
    {
        $token = $params['token'] ?? '';

        $csrfToken = $this->post('_csrf_token', '');
        if (!$this->session->validateCsrfToken($csrfToken)) {
            $this->session->flash('error', 'Ungültiges Sicherheitstoken.');
            $this->redirect('/portal/einladung/' . urlencode($token));
            return;
        }

        $user = $this->repo->findUserByInviteToken($token);
        if (!$user || $user['invite_used_at'] || ($user['invite_expires'] && strtotime($user['invite_expires']) < time())) {
            $this->session->flash('error', 'Dieser Einladungslink ist abgelaufen oder bereits verwendet.');
            $this->redirect('/portal/login');
            return;
        }

        $password = $this->post('password', '');
        $confirm  = $this->post('password_confirm', '');

        if (strlen($password) < 8) {
            $this->session->flash('error', 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            $this->redirect('/portal/einladung/' . urlencode($token));
            return;
        }
        if ($password !== $confirm) {
            $this->session->flash('error', 'Die Passwörter stimmen nicht überein.');
            $this->redirect('/portal/einladung/' . urlencode($token));
            return;
        }

        $this->repo->updateUser((int)$user['id'], [
            'password_hash'  => password_hash($password, PASSWORD_BCRYPT),
            'invite_used_at' => date('Y-m-d H:i:s'),
            'invite_token'   => null,
            'is_active'      => 1,
        ]);

        $this->session->flash('success', 'Passwort wurde gesetzt. Du kannst dich jetzt einloggen.');
        $this->redirect('/portal/login');
    }
}
