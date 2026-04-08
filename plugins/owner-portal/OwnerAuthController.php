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

        /* ── Multi-tenant login: find user first, THEN use tenant-prefixed tables ── */
        [$user, $tenantPrefix] = $this->findUserAcrossAllTenants($email);

        /* Apply prefix to repo BEFORE rate-limit checks so table names are correct */
        if ($tenantPrefix !== '') {
            $this->session->set('portal_tenant_prefix', $tenantPrefix);
            $this->session->set('tenant_table_prefix', $tenantPrefix);
            $db = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $db->setPrefix($tenantPrefix);
        }

        try { $this->repo->cleanOldAttempts(); } catch (\Throwable) {}

        try {
            if ($this->repo->countRecentAttempts($email, $ip, 15) >= 5) {
                $this->session->flash('error', 'Zu viele Anmeldeversuche. Bitte warte 15 Minuten.');
                $this->redirect('/portal/login');
                return;
            }
        } catch (\Throwable) {}

        try { $this->repo->logLoginAttempt($email, $ip); } catch (\Throwable) {}

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

    /**
     * Search every tenant's owner_portal_users table for the given invite token.
     * Returns [userRow, tenantPrefix] or [null, ''] if not found.
     */
    private function findTokenAcrossAllTenants(string $token): array
    {
        try {
            $db  = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $pdo = $db->getPdo();

            $stmt = $pdo->prepare(
                "SELECT table_name FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name LIKE '%_owner_portal_users'
                    AND table_name NOT LIKE '%owner_portal_owner_portal%'
                  ORDER BY table_name ASC"
            );
            $stmt->execute();
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $prefix = substr($table, 0, -strlen('owner_portal_users'));
                $s = $pdo->prepare("SELECT * FROM `{$table}` WHERE invite_token = ? LIMIT 1");
                $s->execute([$token]);
                $row = $s->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    return [$row, $prefix];
                }
            }
        } catch (\Throwable) {}

        return [$this->repo->findUserByInviteToken($token), ''];
    }

    /**
     * Search every tenant's owner_portal_users table for the given e-mail.
     * Returns [userRow, tenantPrefix] or [null, ''] if not found.
     */
    private function findUserAcrossAllTenants(string $email): array
    {
        try {
            $db  = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $pdo = $db->getPdo();

            /* Find all owner_portal_users tables in this database */
            $stmt = $pdo->prepare(
                "SELECT table_name FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name LIKE '%_owner_portal_users'
                  ORDER BY table_name ASC"
            );
            $stmt->execute();
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                /* Derive prefix: strip trailing "owner_portal_users" */
                $prefix = substr($table, 0, -strlen('owner_portal_users'));

                $s = $pdo->prepare("SELECT * FROM `{$table}` WHERE email = ? LIMIT 1");
                $s->execute([$email]);
                $row = $s->fetch(\PDO::FETCH_ASSOC);

                if ($row) {
                    return [$row, $prefix];
                }
            }
        } catch (\Throwable) {
            /* Fall through to single-tenant lookup */
        }

        /* Fallback: use the already-configured repo (single-tenant / dev) */
        return [$this->repo->findUserByEmail($email), ''];
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
        $this->session->remove('portal_tenant_prefix');
        $this->session->remove('tenant_table_prefix');
        $this->redirect('/portal/login');
    }

    /* ── GET /portal/einladung/{token} ── */
    public function showSetPassword(array $params = []): void
    {
        $token = $params['token'] ?? '';
        [$user, $tenantPrefix] = $this->findTokenAcrossAllTenants($token);

        if ($tenantPrefix !== '') {
            $db = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $db->setPrefix($tenantPrefix);
        }

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

        [$user, $tenantPrefix] = $this->findTokenAcrossAllTenants($token);
        if ($tenantPrefix !== '') {
            $db = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $db->setPrefix($tenantPrefix);
            $this->session->set('portal_tenant_prefix', $tenantPrefix);
            $this->session->set('tenant_table_prefix', $tenantPrefix);
        }

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
