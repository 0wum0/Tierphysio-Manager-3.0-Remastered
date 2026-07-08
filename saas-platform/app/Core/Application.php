<?php

declare(strict_types=1);

namespace Saas\Core;

use Dotenv\Dotenv;

class Application
{
    private static Application $instance;
    private Container $container;
    private Router    $router;

    public function __construct(private string $rootPath)
    {
        self::$instance = $this;
        $this->loadEnvironment();
        $this->container = new Container();
        $this->bootstrap();
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    private function loadEnvironment(): void
    {
        $envFile = $this->rootPath . '/.env';
        if (file_exists($envFile)) {
            $dotenv = Dotenv::createImmutable($this->rootPath);
            $dotenv->load();
        }
    }

    private function bootstrap(): void
    {
        $config = new Config($this->rootPath);
        $this->container->singleton(Config::class, fn() => $config);

        $session = new Session($config);
        $session->start();
        $this->container->singleton(Session::class, fn() => $session);

        $view = new View($this->rootPath . '/templates', $config, $session);
        $this->container->singleton(View::class, fn() => $view);

        if ($config->get('app.installed', false)) {
            $db = new Database($config);
            $this->container->singleton(Database::class, fn() => $db);

            // Expose logged-in user to view
            $view->addGlobal('auth_user', $session->get('saas_user'));
            $view->addGlobal('auth_role', $session->get('saas_role'));

            // Expose tenant session data for app-domain views
            $view->addGlobal('tenant_name',  $session->get('platform_name'));
            $view->addGlobal('tenant_email', $session->get('platform_email'));

            // ── Push notification config für eingeloggte SaaS-Admins ─────────
            // Liest die push-thera Verbindungsdaten aus saas_settings (Pairing)
            // bzw. .env und signiert ein kurzlebiges JWT für den Browser.
            // SaaS-Admins laufen unter tenant_id 0 / role saas_admin.
            try {
                $adminId = (int)($session->get('saas_user_id') ?? 0);
                if ($adminId > 0) {
                    $pushUrl    = rtrim((string)($config->get('push.server_url') ?? ''), '/');
                    $pushSecret = (string)($config->get('push.internal_secret') ?? '');

                    $rows = $db->fetchAll(
                        "SELECT `key`, `value` FROM saas_settings WHERE `key` LIKE 'push_%'"
                    );
                    $ps = [];
                    foreach ($rows as $row) { $ps[$row['key']] = $row['value']; }

                    if (($ps['push_enabled'] ?? '0') === '1') {
                        if ($pushUrl === '')    { $pushUrl    = rtrim((string)($ps['push_server_url'] ?? ''), '/'); }
                        if ($pushSecret === '') { $pushSecret = (string)($ps['push_internal_secret_plain'] ?? ''); }
                    }
                    $vapidKey = (string)($ps['push_vapid_public_key'] ?? '');

                    if ($pushUrl !== '' && $pushSecret !== '') {
                        $b64url = static fn(string $s): string
                            => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
                        $now = time();
                        $hdr = $b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
                        $pay = $b64url(json_encode([
                            'tenant_id' => 0,
                            'user_id'   => $adminId,
                            'role'      => 'saas_admin',
                            'app_type'  => 'saas_admin',
                            'iat'       => $now,
                            'exp'       => $now + 86400,
                            'iss'       => 'therapano',
                        ]));
                        $sig = $b64url(hash_hmac('sha256', "$hdr.$pay", $pushSecret, true));

                        $view->addGlobal('push_config', [
                            'server_url'       => $pushUrl,
                            'token'            => "$hdr.$pay.$sig",
                            'vapid_public_key' => $vapidKey,
                            'user_id'          => $adminId,
                            'tenant_id'        => 0,
                            'role'             => 'saas_admin',
                            'app_type'         => 'saas_admin',
                            'enable_web_push'  => $vapidKey !== '',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                error_log('[saas push bootstrap] ' . $e->getMessage());
            }
        }

        // Expose domain config to all views
        $view->addGlobal('platform_url', $config->get('platform.url', ''));
        $view->addGlobal('app_url',      $config->get('platform.app_url', ''));

        $this->router = new Router($this->container);
        $this->registerRoutes($config, $session);
    }

    private function registerRoutes(Config $config, Session $session): void
    {
        if (!$config->get('app.installed', false)) {
            $this->router->loadRoutes($this->rootPath . '/app/Routes/installer.php');
            return;
        }

        $host       = $_SERVER['HTTP_HOST'] ?? '';
        $isAppDomain = str_starts_with($host, 'app.');

        if ($isAppDomain) {
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $isAdminPath = str_starts_with($uri, '/admin') || str_starts_with($uri, '/api/');

            // Guard: no tenant session on non-admin paths → redirect to platform login
            if (!$isAdminPath && !$session->has('platform_tid')) {
                $platformUrl = $config->get('platform.url', '');
                $loginUrl    = $platformUrl !== '' ? $platformUrl . '/login' : '/login';
                header('Location: ' . $loginUrl);
                exit;
            }
            $this->router->loadRoutes($this->rootPath . '/app/Routes/web.php');
        } else {
            // therapano.de: public platform routes (landing, register, login, legal)
            $this->router->loadRoutes($this->rootPath . '/app/Routes/platform.php');
            // Admin routes also available on platform domain
            $this->router->loadRoutes($this->rootPath . '/app/Routes/web.php');
        }
    }

    public function run(): void
    {
        try {
            $this->router->dispatch();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    private function handleException(\Throwable $e): void
    {
        $config = $this->container->get(Config::class);
        $debug  = $config->get('app.debug', false);

        $logDir  = $this->rootPath . '/storage/logs';
        $logFile = $logDir . '/error.log';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(
            $logFile,
            '[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n"
            . $e->getTraceAsString() . "\n\n",
            FILE_APPEND
        );

        http_response_code(500);

        $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
               || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }

        if ($debug) {
            echo '<pre style="background:#1a1a2e;color:#e94560;padding:20px;font-family:monospace;">';
            echo '<strong>Exception:</strong> ' . htmlspecialchars($e->getMessage()) . "\n\n";
            echo '<strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . "\n\n";
            echo '<strong>Trace:</strong>' . "\n" . htmlspecialchars($e->getTraceAsString());
            echo '</pre>';
        } else {
            echo '<!DOCTYPE html><html><body style="background:#0f172a;color:#fff;font-family:sans-serif;text-align:center;padding:100px;">';
            echo '<h1>500 - Interner Serverfehler</h1><p>Bitte versuchen Sie es später erneut.</p>';
            echo '</body></html>';
        }
    }
}
