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
use App\Services\MailService;

class AuthController extends Controller
{
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
        private readonly MailService $mailService,
    ) {
        parent::__construct($view, $session, $config, $translator);
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

    public function showForgotPassword(array $params = []): void
    {
        $this->render('auth/forgot-password.twig', ['page_title' => 'Passwort vergessen']);
    }

    public function forgotPasswordSubmit(array $params = []): void
    {
        $this->validateCsrf();
        $email = trim($this->post('email', ''));

        if (empty($email)) {
            $this->session->flash('error', 'Bitte gib deine E-Mail-Adresse ein.');
            $this->redirect('/forgot-password');
            return;
        }

        $prefix = $this->resolvePrefixForEmail($email);
        if ($prefix === '') {
            $prefix = $this->detectPrefixFromSchema();
        }
        if ($prefix !== '') {
            $this->db->setPrefix($prefix);
        }

        // Always show success message to prevent user enumeration
        $user = $this->userRepository->findByEmail($email);
        if ($user) {
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 7200);
            $this->userRepository->setPasswordResetToken((int)$user['id'], $token, $expiresAt);

            $appUrl   = rtrim($this->config->get('app.url', ''), '/');
            $resetUrl = $appUrl . '/reset-password/' . $token;
            $this->mailService->sendPasswordReset($email, $user['name'] ?? '', $resetUrl);
        }

        $this->session->flash('success', 'Falls ein Konto mit dieser E-Mail-Adresse existiert, wurde eine E-Mail mit weiteren Anweisungen gesendet.');
        $this->redirect('/forgot-password');
    }

    public function showResetPassword(array $params = []): void
    {
        $token = $params['token'] ?? '';
        $prefix = $this->detectPrefixFromSchema();
        if ($prefix !== '') {
            $this->db->setPrefix($prefix);
        }

        $user = $this->userRepository->findByResetToken($token);
        if (!$user) {
            $this->session->flash('error', 'Dieser Link ist ungültig oder abgelaufen.');
            $this->redirect('/forgot-password');
            return;
        }

        $this->render('auth/reset-password.twig', ['token' => $token, 'page_title' => 'Neues Passwort setzen']);
    }

    public function resetPasswordSubmit(array $params = []): void
    {
        $this->validateCsrf();
        $token    = $params['token'] ?? '';
        $password = $this->post('password', '');
        $confirm  = $this->post('password_confirm', '');

        if (strlen($password) < 8) {
            $this->session->flash('error', 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            $this->redirect("/reset-password/{$token}");
            return;
        }

        if ($password !== $confirm) {
            $this->session->flash('error', 'Die Passwörter stimmen nicht überein.');
            $this->redirect("/reset-password/{$token}");
            return;
        }

        $prefix = $this->detectPrefixFromSchema();
        if ($prefix !== '') {
            $this->db->setPrefix($prefix);
        }

        $user = $this->userRepository->findByResetToken($token);
        if (!$user) {
            $this->session->flash('error', 'Dieser Link ist ungültig oder abgelaufen.');
            $this->redirect('/forgot-password');
            return;
        }

        $this->userRepository->updatePasswordAndClearToken((int)$user['id'], password_hash($password, PASSWORD_BCRYPT));
        $this->session->flash('success', 'Dein Passwort wurde erfolgreich geändert. Du kannst dich jetzt anmelden.');
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
