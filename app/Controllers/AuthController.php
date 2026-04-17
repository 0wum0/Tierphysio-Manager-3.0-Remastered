<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Repositories\UserRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\PasswordResetRepository;
use App\Services\MailService;

class AuthController extends Controller
{
    private const RESET_TOKEN_TTL_SECONDS = 3600;
    private const PASSWORD_MIN_LENGTH     = 8;

    private function normalizeTenantPrefix(string $raw): string
    {
        $prefix = trim($raw);
        if ($prefix === '') {
            return '';
        }
        if (!str_starts_with($prefix, 't_')) {
            $prefix = 't_' . $prefix;
        }
        $prefix = preg_replace('/_+/', '_', $prefix) ?? $prefix;
        if (!str_ends_with($prefix, '_')) {
            $prefix .= '_';
        }
        return $prefix;
    }

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly UserRepository $userRepository,
        private readonly SettingsRepository $settingsRepository,
        private readonly Database $db,
        private readonly PasswordResetRepository $passwordResetRepository,
        private readonly MailService $mailService
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    /**
     * Ensures the Database connection targets the correct tenant by resolving
     * a prefix for the given email first, then falling back to schema auto-detect.
     * Returns true if a prefix could be applied.
     */
    private function applyTenantPrefixForEmail(string $email): bool
    {
        $prefix = $this->resolvePrefixForEmail($email);
        if ($prefix === '') {
            $prefix = $this->detectPrefixFromSchema();
        }
        if ($prefix !== '') {
            $this->db->setPrefix($prefix);
            return true;
        }
        return false;
    }

    /* ══════════════════════════════════════════════════════════
       PASSWORD RESET
       Token flow:
         1. User enters email on /passwort-vergessen
         2. Random 32-byte token is generated, SHA-256 hashed and stored
         3. User clicks mailed link /passwort-zuruecksetzen/{raw_token}
         4. Token is hashed and looked up; on match the user sets a new password
         5. Token is marked as used, any other open tokens are invalidated
       Security:
         - Raw token never stored, only SHA-256 hash
         - Generic success message (no email enumeration)
         - 60 minute TTL, single-use
         - Session regenerated on successful password change
    ══════════════════════════════════════════════════════════ */

    public function showForgotPassword(array $params = []): void
    {
        $this->render('auth/forgot_password.twig', [
            'page_title' => 'Passwort zurücksetzen',
        ]);
    }

    public function requestPasswordReset(array $params = []): void
    {
        $this->validateCsrf();

        $email = trim($this->post('email', ''));
        $genericSuccess = 'Wenn ein Konto mit dieser E-Mail-Adresse existiert, wurde ein Link zum Zurücksetzen versendet.';

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->session->flash('error', 'Bitte eine gültige E-Mail-Adresse eingeben.');
            $this->redirect('/passwort-vergessen');
            return;
        }

        // Resolve tenant prefix so we query the correct users table.
        if (!$this->applyTenantPrefixForEmail($email)) {
            // No tenant context available — answer generically to avoid enumeration.
            $this->session->flash('success', $genericSuccess);
            $this->redirect('/passwort-vergessen');
            return;
        }

        try {
            $user = $this->userRepository->findByEmail($email);
        } catch (\Throwable) {
            $user = false;
        }

        if ($user && (int)($user['is_active'] ?? $user['active'] ?? 1) === 1) {
            try {
                $rawToken  = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $ip        = $_SERVER['REMOTE_ADDR'] ?? null;

                $this->passwordResetRepository->createForUser(
                    (int)$user['id'],
                    $tokenHash,
                    self::RESET_TOKEN_TTL_SECONDS,
                    is_string($ip) ? substr($ip, 0, 45) : null
                );

                $resetUrl = $this->buildAbsoluteUrl('/passwort-zuruecksetzen/' . $rawToken);
                $this->mailService->sendPasswordReset($user, $resetUrl);
            } catch (\Throwable $e) {
                error_log('[AuthController::requestPasswordReset] ' . $e->getMessage());
                // Still show the generic success to avoid leaking internals.
            }
        }

        $this->session->flash('success', $genericSuccess);
        $this->redirect('/passwort-vergessen');
    }

    public function showResetPassword(array $params = []): void
    {
        $token = (string)($params['token'] ?? '');
        if (!$this->looksLikeToken($token)) {
            $this->session->flash('error', 'Der Zurücksetz-Link ist ungültig oder abgelaufen.');
            $this->redirect('/login');
            return;
        }

        // At this stage we cannot yet validate the token without a tenant context.
        // We attempt schema auto-detect — if there is exactly one tenant in the DB
        // this succeeds; otherwise the user will be asked to enter their email first.
        $needsEmail = true;
        if ($this->detectPrefixFromSchema() !== '') {
            $this->db->setPrefix($this->detectPrefixFromSchema());
            $row = $this->passwordResetRepository->findValidByTokenHash(hash('sha256', $token));
            $needsEmail = ($row === false);
        }

        $this->render('auth/reset_password.twig', [
            'page_title'   => 'Neues Passwort setzen',
            'token'        => $token,
            'needs_email'  => $needsEmail,
            'min_length'   => self::PASSWORD_MIN_LENGTH,
        ]);
    }

    public function resetPassword(array $params = []): void
    {
        $this->validateCsrf();

        $token           = (string)($params['token'] ?? '');
        $email           = trim($this->post('email', ''));
        $password        = (string)$this->post('password', '');
        $passwordConfirm = (string)$this->post('password_confirm', '');

        if (!$this->looksLikeToken($token)) {
            $this->session->flash('error', 'Der Zurücksetz-Link ist ungültig oder abgelaufen.');
            $this->redirect('/login');
            return;
        }

        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $this->session->flash('error', 'Das Passwort muss mindestens ' . self::PASSWORD_MIN_LENGTH . ' Zeichen lang sein.');
            $this->redirect('/passwort-zuruecksetzen/' . $token);
            return;
        }
        if ($password !== $passwordConfirm) {
            $this->session->flash('error', 'Die Passwörter stimmen nicht überein.');
            $this->redirect('/passwort-zuruecksetzen/' . $token);
            return;
        }

        // Resolve tenant: prefer email-based lookup, fall back to schema auto-detect.
        if ($email !== '') {
            $this->applyTenantPrefixForEmail($email);
        } elseif ($this->detectPrefixFromSchema() !== '') {
            $this->db->setPrefix($this->detectPrefixFromSchema());
        }

        if ($this->db->getPrefix() === '') {
            $this->session->flash('error', 'Tenant-Kontext konnte nicht ermittelt werden. Bitte erneut anfordern.');
            $this->redirect('/passwort-vergessen');
            return;
        }

        $tokenHash = hash('sha256', $token);
        $row       = $this->passwordResetRepository->findValidByTokenHash($tokenHash);

        if (!$row) {
            $this->session->flash('error', 'Der Zurücksetz-Link ist ungültig oder abgelaufen.');
            $this->redirect('/passwort-vergessen');
            return;
        }

        $userId = (int)($row['user_id'] ?? 0);
        $user   = $userId > 0 ? $this->userRepository->findById($userId) : false;

        if (!$user) {
            $this->session->flash('error', 'Benutzer nicht gefunden.');
            $this->redirect('/passwort-vergessen');
            return;
        }

        try {
            $this->userRepository->updatePassword($userId, password_hash($password, PASSWORD_DEFAULT));
            $this->passwordResetRepository->markUsed((int)$row['id']);
            $this->passwordResetRepository->invalidateAllForUser($userId);
        } catch (\Throwable $e) {
            error_log('[AuthController::resetPassword] ' . $e->getMessage());
            $this->session->flash('error', 'Das Passwort konnte nicht gespeichert werden. Bitte erneut versuchen.');
            $this->redirect('/passwort-zuruecksetzen/' . $token);
            return;
        }

        $this->session->flash('success', 'Dein Passwort wurde erfolgreich geändert. Du kannst dich jetzt anmelden.');
        $this->redirect('/login');
    }

    private function looksLikeToken(string $token): bool
    {
        return (bool)preg_match('/^[a-f0-9]{64}$/', $token);
    }

    private function buildAbsoluteUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? $this->config->get('app.host', 'localhost'));
        return $scheme . '://' . $host . $path;
    }

    public function showLogin(array $params = []): void
    {
        try {
            $settings = $this->settingsRepository->all();
        } catch (\Throwable) {
            $settings = [];
        }
        $this->render('auth/login.twig', [
            'page_title'   => $this->translator->trans('auth.login_title'),
            'company_name' => $settings['company_name'] ?? '',
            'company_logo' => $settings['company_logo'] ?? '',
        ]);
    }

    public function login(array $params = []): void
    {
        $this->validateCsrf();

        $email    = trim($this->post('email', ''));
        $password = $this->post('password', '');

        if (empty($email) || empty($password)) {
            $this->session->flash('error', $this->translator->trans('auth.fill_all_fields'));
            $this->redirect('/login');
            return;
        }

        // Resolve tenant table prefix from SaaS DB BEFORE querying the users table.
        // Without the correct prefix the query would target a non-existent bare table.
        $prefix = $this->resolvePrefixForEmail($email);
        if ($prefix === '') {
            // SaaS DB not configured — auto-detect prefix from INFORMATION_SCHEMA.
            $prefix = $this->detectPrefixFromSchema();
        }
        if ($prefix !== '') {
            $this->db->setPrefix($prefix);
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->session->flash('error', $this->translator->trans('auth.invalid_credentials'));
            $this->redirect('/login');
            return;
        }

        if ((int)($user['is_active'] ?? $user['active'] ?? 0) !== 1) {
            $this->session->flash('error', $this->translator->trans('auth.account_inactive'));
            $this->redirect('/login');
            return;
        }

        $this->session->setUser($user);
        $this->session->set('user_last_login', $user['last_login'] ?? null);
        if ($prefix !== '') {
            $this->session->set('tenant_table_prefix', $prefix);
        }
        $this->userRepository->updateLastLogin($user['id']);
        $this->session->flash('success', $this->translator->trans('auth.welcome', ['name' => $user['name']]));
        $this->redirect('/dashboard');
    }

    public function logout(array $params = []): void
    {
        $this->validateCsrf();
        $this->session->destroy();
        $this->redirect('/login');
    }

    /**
     * Auto-detect the tenant table prefix by looking for a prefixed `users` table
     * in INFORMATION_SCHEMA. Works when only one tenant exists in the database.
     */
    private function detectPrefixFromSchema(): string
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT table_name FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name LIKE 't\_%\_users'
                  ORDER BY table_name ASC
                  LIMIT 1"
            );
            if (!empty($rows)) {
                $tableName = $rows[0]['table_name'] ?? $rows[0]['TABLE_NAME'] ?? '';
                // Strip the trailing 'users' to get the prefix
                if (str_ends_with($tableName, '_users')) {
                    return substr($tableName, 0, -strlen('users'));
                }
            }
        } catch (\Throwable) {}
        return '';
    }

    private function resolvePrefixForEmail(string $email): string
    {
        $saasDb = $this->config->get('saas_db.database', '');
        if ($saasDb === '') {
            return '';
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config->get('saas_db.host', 'localhost'),
                (int)$this->config->get('saas_db.port', 3306),
                $saasDb
            );
            $pdo = new \PDO($dsn, $this->config->get('saas_db.username'), $this->config->get('saas_db.password'), [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $stmt = $pdo->prepare("SELECT db_name FROM tenants WHERE email = ? AND status IN ('active','trial') LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            return ($row && !empty($row['db_name']))
                ? $this->normalizeTenantPrefix((string)$row['db_name'])
                : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
