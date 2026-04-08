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

class AuthController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly UserRepository $userRepository,
        private readonly SettingsRepository $settingsRepository,
        private readonly Database $db
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
        if ($prefix !== '') {
            $this->db->setPrefix($prefix);
        } elseif ($this->db->getPrefix() === '') {
            // No SaaS DB configured and no session prefix — cannot resolve tenant.
            // Fall back to DB_PREFIX env value if set.
            $envPrefix = $_ENV['DB_PREFIX'] ?? '';
            if ($envPrefix !== '') {
                $this->db->setPrefix($envPrefix);
            }
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->session->flash('error', $this->translator->trans('auth.invalid_credentials'));
            $this->redirect('/login');
            return;
        }

        if ((int)$user['active'] !== 1) {
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
            return ($row && !empty($row['db_name'])) ? (string)$row['db_name'] : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
