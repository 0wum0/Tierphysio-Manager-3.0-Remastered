<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Config;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\PlanRepository;
use Saas\Services\MailService;

class TenantAuthController extends Controller
{
    public function __construct(
        View                     $view,
        Session                  $session,
        private Config           $config,
        private TenantRepository $tenantRepo,
        private PlanRepository   $planRepo,
        private MailService      $mail
    ) {
        parent::__construct($view, $session);
    }

    // ── Landing Page ────────────────────────────────────────────────────────

    public function landing(array $params = []): void
    {
        if ($this->session->has('platform_tid')) {
            $appUrl = $this->config->get('platform.app_url', '');
            if ($appUrl) {
                $this->redirect($appUrl);
            }
        }

        $plans = $this->planRepo->allActive();
        $this->render('landing/index.twig', [
            'plans'      => $plans,
            'page_title' => 'TheraPano – Praxis-Software für Tierphysios',
        ]);
    }

    // ── Tenant Login ────────────────────────────────────────────────────────

    public function loginForm(array $params = []): void
    {
        if ($this->session->has('platform_tid')) {
            $appUrl = $this->config->get('platform.app_url', '');
            $this->redirect($appUrl ?: '/');
        }

        $this->render('auth/tenant-login.twig', [
            'page_title'  => 'Anmelden',
            'logged_out'  => isset($_GET['logged_out']),
        ]);
    }

    public function login(array $params = []): void
    {
        $this->verifyCsrf();

        $email    = strtolower(trim($this->post('email', '')));
        $password = $this->post('password', '');

        if (!$email || !$password) {
            $this->session->flash('error', 'Bitte E-Mail und Passwort eingeben.');
            $this->redirect('/login');
        }

        $tenant = $this->tenantRepo->findByEmailForAuth($email);

        if (!$tenant) {
            $this->session->flash('error', 'Ungültige Anmeldedaten oder Konto nicht aktiv.');
            $this->redirect('/login');
        }

        $passwordField = $tenant['password_hash'] ?? $tenant['admin_password_hash'] ?? '';
        if (!$passwordField || !password_verify($password, $passwordField)) {
            $this->session->flash('error', 'Ungültige Anmeldedaten.');
            $this->redirect('/login');
        }

        $this->session->regenerate();
        $this->session->set('platform_tid',   $tenant['tid'] ?? $tenant['uuid']);
        $this->session->set('platform_email', $tenant['email']);
        $this->session->set('platform_name',  $tenant['owner_name']);
        $this->session->set('platform_tenant_id', (int)$tenant['id']);

        $this->tenantRepo->updateLastLogin((int)$tenant['id']);

        $appUrl = $this->config->get('platform.app_url', '');
        $this->redirect($appUrl ?: '/');
    }

    public function logout(array $params = []): void
    {
        $this->session->destroy();

        $platformUrl = $this->config->get('platform.url', '');
        $this->redirect(($platformUrl ?: '') . '/login?logged_out=1');
    }

    // ── TID-Check AJAX ──────────────────────────────────────────────────────

    public function checkTid(array $params = []): void
    {
        $tid    = trim($this->post('tid', '') ?: $this->get('tid', ''));
        $exists = $tid !== '' && (bool)$this->tenantRepo->findByTid($tid);

        $this->json(['available' => !$exists, 'exists' => $exists]);
    }

    // ── Forgot Password ─────────────────────────────────────────────────────

    public function forgotForm(array $params = []): void
    {
        $this->render('auth/forgot-password.twig', [
            'page_title' => 'Passwort zurücksetzen',
        ]);
    }

    public function forgotSubmit(array $params = []): void
    {
        $this->verifyCsrf();

        $email  = strtolower(trim($this->post('email', '')));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Bitte eine gültige E-Mail-Adresse eingeben.');
            $this->redirect('/forgot-password');
        }

        $tenant = $this->tenantRepo->findByEmail($email);
        if ($tenant) {
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $this->tenantRepo->setPasswordResetToken((int)$tenant['id'], $token, $expiresAt);

            $platformUrl = $this->config->get('platform.url', '');
            $resetUrl    = $platformUrl . '/reset-password?token=' . $token;

            try {
                $this->mail->send(
                    $email,
                    $tenant['owner_name'],
                    'Passwort zurücksetzen – TheraPano',
                    $this->buildResetEmailHtml($tenant['owner_name'], $resetUrl),
                    strip_tags(str_replace('<br>', "\n", $this->buildResetEmailHtml($tenant['owner_name'], $resetUrl)))
                );
            } catch (\Throwable) {
                // Silent fail — don't reveal whether email exists
            }
        }

        // Always show success to prevent email enumeration
        $this->session->flash('success', 'Falls ein Konto mit dieser E-Mail existiert, wurde eine Anleitung zum Zurücksetzen versendet.');
        $this->redirect('/forgot-password');
    }

    public function resetForm(array $params = []): void
    {
        $token  = trim($this->get('token', ''));
        $tenant = $token ? $this->tenantRepo->findByResetToken($token) : null;

        if (!$tenant) {
            $this->session->flash('error', 'Ungültiger oder abgelaufener Link.');
            $this->redirect('/login');
        }

        $this->render('auth/reset-password.twig', [
            'page_title' => 'Neues Passwort setzen',
            'token'      => $token,
        ]);
    }

    public function resetSubmit(array $params = []): void
    {
        $this->verifyCsrf();

        $token    = trim($this->post('token', ''));
        $password = $this->post('password', '');
        $confirm  = $this->post('password_confirm', '');

        $tenant = $token ? $this->tenantRepo->findByResetToken($token) : null;
        if (!$tenant) {
            $this->session->flash('error', 'Ungültiger oder abgelaufener Link.');
            $this->redirect('/login');
        }

        if (strlen($password) < 8) {
            $this->session->flash('error', 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            $this->redirect('/reset-password?token=' . urlencode($token));
        }

        if ($password !== $confirm) {
            $this->session->flash('error', 'Die Passwörter stimmen nicht überein.');
            $this->redirect('/reset-password?token=' . urlencode($token));
        }

        $this->tenantRepo->clearResetToken((int)$tenant['id'], password_hash($password, PASSWORD_BCRYPT));

        $this->session->flash('success', 'Passwort erfolgreich geändert. Bitte jetzt einloggen.');
        $this->redirect('/login');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function buildResetEmailHtml(string $name, string $resetUrl): string
    {
        return <<<HTML
        <p>Hallo {$name},</p>
        <p>Sie haben eine Passwort-Zurücksetzen-Anfrage gestellt. Klicken Sie auf den folgenden Link, um ein neues Passwort zu setzen:</p>
        <p><a href="{$resetUrl}" style="background:#2563eb;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block;">Passwort zurücksetzen</a></p>
        <p>Dieser Link ist 1 Stunde gültig.</p>
        <p>Falls Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail.</p>
        <p>Mit freundlichen Grüßen,<br>Das TheraPano-Team</p>
        HTML;
    }
}
