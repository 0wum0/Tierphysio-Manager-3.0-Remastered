<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Config;
use App\Core\Database;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Core\PluginManager;
use App\Core\Translator;
use App\Core\Auth;
use Dotenv\Dotenv;

class Application
{
    private static Application $instance;
    private Container $container;
    private Router $router;
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
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
        date_default_timezone_set('Europe/Berlin');

        $config = new Config($this->rootPath);
        $this->container->singleton(Config::class, fn() => $config);

        $session = new Session($config);
        $session->start();
        $this->container->singleton(Session::class, fn() => $session);
        
        // Initialize Auth with Session
        Auth::init($session);

        $translator = new Translator($config->get('app.locale', 'de'), $this->rootPath . '/lang');
        $this->container->singleton(Translator::class, fn() => $translator);

        if ($config->get('app.installed', false)) {
            try {
                $db = new Database($config);

                // Resolve tenant table prefix.
                // Priority: 1) session cache (staff), 2) portal session (owner login), 3) SaaS-DB lookup, 4) INFORMATION_SCHEMA auto-detect
                $prefix = $session->get('tenant_table_prefix', '');

                /* Sanity-check: a valid prefix matches t_{slug}_ exactly once.
                   A corrupted prefix (e.g. from old buggy regex) contains the pattern more than once.
                   Detect by counting occurrences of 't_' — valid prefix has exactly one. */
                if ($prefix !== '' && substr_count($prefix, 't_') !== 1) {
                    $session->remove('tenant_table_prefix');
                    $session->remove('portal_tenant_prefix');
                    $prefix = '';
                }

                if ($prefix === '') {
                    $prefix = $session->get('portal_tenant_prefix', '');
                    if ($prefix !== '' && substr_count($prefix, 't_') !== 1) {
                        $session->remove('portal_tenant_prefix');
                        $prefix = '';
                    }
                }
                if ($prefix === '') {
                    $prefix = $this->resolveTenantPrefix($config, $session);
                }
                if ($prefix === '') {
                    $prefix = $this->detectPrefixFromSchema($db);
                }
                if ($prefix !== '') {
                    $db->setPrefix($prefix);
                }

                $this->container->singleton(Database::class, fn() => $db);

                /* Auto-Migration: führt ausstehende Migrationen tenant-sicher aus.
                 * - nur wenn Prefix aufgelöst
                 * - nur einmal pro Session je Migrations-Stand (filesystem version)
                 * - schluckt alle Fehler → darf App NIE blockieren
                 * - Einzel-Statement-Fehler sind bereits in MigrationService::runMigration() abgefangen
                 */
                if ($prefix !== '') {
                    $this->runAutoMigrations($db, $session);
                }
            } catch (\Throwable $dbEx) {
                /* Log and rethrow so handleException can render a proper error page */
                $logDir  = $this->rootPath . '/storage/logs';
                if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
                @file_put_contents(
                    $logDir . '/error.log',
                    '[' . date('Y-m-d H:i:s') . '] DB bootstrap: ' . $dbEx->getMessage() . "\n\n",
                    FILE_APPEND
                );
                throw $dbEx;
            }
        }

        $view = new View($this->rootPath . '/templates', $config, $session, $translator);
        $this->container->singleton(View::class, fn() => $view);

        $pluginManager = new PluginManager($this->rootPath . '/plugins', $this->container);
        $this->container->singleton(PluginManager::class, fn() => $pluginManager);

        if ($config->get('app.installed', false)) {
            $pluginManager->loadPlugins();

            // Only query DB globals when a prefix is resolved (i.e. tenant is known)
            $db     = $this->container->has(Database::class) ? $this->container->get(Database::class) : null;
            $hasPrefix = $db && $db->getPrefix() !== '';

            if ($hasPrefix) {
                /* ── Feature-Gate: zentrale Gating-Instanz registrieren + Twig-Global ── */
                try {
                    $gate = new \App\Services\FeatureGateService($db, $session, $config);
                    $this->container->singleton(
                        \App\Services\FeatureGateService::class,
                        fn() => $gate
                    );
                    $view->addGlobal('features', $gate->all());
                } catch (\Throwable $e) {
                    /* Niemals blockieren — Defaults laden (alles AUS außer Core) */
                    $view->addGlobal('features', array_fill_keys(
                        \App\Services\FeatureGateService::CORE_FEATURES,
                        true
                    ));
                    error_log('[FeatureGate bootstrap] ' . $e->getMessage());
                }

                // app_name is always the product name (TheraPano) — never overwritten by tenant data.
                // company_name is exposed separately as tenant_name for display in the UI.
                try {
                    $settingsRepo = new \App\Repositories\SettingsRepository($db);
                    $companyName  = $settingsRepo->get('company_name', '');
                    $view->addGlobal('tenant_name', $companyName);
                    // Also expose settings globally for layout templates
                    $view->addGlobal('global_settings', $settingsRepo->all());
                    // Practice type: 'therapeut' or 'trainer'
                    $practiceType = $settingsRepo->get('practice_type', 'therapeut');
                    $view->addGlobal('practice_type', $practiceType);
                    $view->addGlobal('is_trainer', $practiceType === 'trainer');
                } catch (\Throwable) {
                    $view->addGlobal('tenant_name', '');
                    $view->addGlobal('practice_type', 'therapeut');
                    $view->addGlobal('is_trainer', false);
                }

                // Load per-user UI layout settings (theme, fixed header, etc.)
                try {
                    $prefsRepo = new \App\Repositories\UserPreferencesRepository($db);
                    $userId    = (int)($session->get('user_id') ?? 0);
                    $uiRaw     = $userId ? $prefsRepo->get($userId, 'ui_layout_settings') : null;
                    $view->addGlobal('server_ui_settings', $uiRaw ?? 'null');
                } catch (\Throwable) {
                    $view->addGlobal('server_ui_settings', 'null');
                }
            } else {
                $view->addGlobal('server_ui_settings', 'null');
            }
        }

        $this->router = new Router($this->container);
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $config = $this->container->get(Config::class);

        if (!$config->get('app.installed', false)) {
            $this->router->loadRoutes($this->rootPath . '/app/Routes/installer.php');
            return;
        }

        $this->router->loadRoutes($this->rootPath . '/app/Routes/web.php');

        $pluginManager = $this->container->get(PluginManager::class);
        $pluginManager->registerRoutes($this->router);
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

    /**
     * Runs pending migrations for the current tenant automatically.
     *
     * Safety guarantees:
     * - Only runs when a tenant prefix is resolved (skips SaaS-admin-only requests).
     * - Caches the last-checked filesystem migration version in the session so we
     *   only hit the DB on a real change (fresh deploy or new migration file).
     * - Skips silently for installer/CLI requests and AJAX uploads where a full
     *   schema sync would be surprising.
     * - Logs failures to storage/logs/error.log but NEVER throws — the app must
     *   keep running even if a migration has a syntax error on one tenant.
     */
    private function runAutoMigrations(Database $db, Session $session): void
    {
        try {
            /* Skip for AJAX/API requests — a fresh HTML page load will run them */
            $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
            if ($isAjax) {
                return;
            }

            $svc    = new \App\Services\MigrationService($db);
            $latest = $svc->getLatestVersion();
            $cached = (int)($session->get('db_migration_checked', -1) ?? -1);

            if ($cached === $latest) {
                return;
            }

            $ran = $svc->runPending();
            $session->set('db_migration_checked', $latest);

            if (!empty($ran)) {
                $logDir = $this->rootPath . '/storage/logs';
                if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
                @file_put_contents(
                    $logDir . '/migrations.log',
                    '[' . date('Y-m-d H:i:s') . '] tenant=' . $db->getPrefix()
                        . ' applied=' . implode(',', $ran) . "\n",
                    FILE_APPEND
                );
            }
        } catch (\Throwable $e) {
            $logDir = $this->rootPath . '/storage/logs';
            if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
            @file_put_contents(
                $logDir . '/error.log',
                '[' . date('Y-m-d H:i:s') . '] auto-migration: ' . $e->getMessage() . "\n",
                FILE_APPEND
            );
            /* NEVER rethrow — app must stay up */
        }
    }

    /**
     * Auto-detect the tenant table prefix from INFORMATION_SCHEMA.
     * Looks for a table matching t_*_users in the current database.
     * Fallback when SAAS_DB is not configured and no session prefix exists.
     */
    private function detectPrefixFromSchema(Database $db): string
    {
        try {
            $rows = $db->fetchAll(
                "SELECT table_name FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name LIKE 't\_%\_users'
                  ORDER BY table_name ASC"
            );
            
            // If more than 1 tenant exists, auto-detection via schema is ambiguous and dangerous.
            // We only allow it if there is exactly one prefix found.
            $prefixes = [];
            foreach ($rows as $row) {
                $tableName = $row['table_name'] ?? $row['TABLE_NAME'] ?? '';
                if (str_contains($tableName, 'portal') || str_contains($tableName, 'attempt')) {
                    continue;
                }
                $prefixes[] = substr($tableName, 0, -strlen('users'));
            }

            $prefixes = array_unique($prefixes);
            if (count($prefixes) === 1) {
                return $prefixes[0];
            }
        } catch (\Throwable) {}
        return '';
    }

    /**
     * Look up the tenant table prefix from the SaaS tenants table.
     * Uses the user_email stored in session to identify the tenant.
     * The prefix is then cached in the session for subsequent requests.
     */
    private function resolveTenantPrefix(Config $config, Session $session): string
    {
        $saasDb   = $config->get('saas_db.database', '');
        $email    = $session->get('user_email', '');

        if ($saasDb === '' || $email === '') {
            return '';
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config->get('saas_db.host', 'localhost'),
                $config->get('saas_db.port', 3306),
                $saasDb
            );
            $pdo = new \PDO($dsn, $config->get('saas_db.username'), $config->get('saas_db.password'), [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            $stmt = $pdo->prepare("SELECT db_name FROM tenants WHERE email = ? AND status IN ('active','trial') LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && !empty($row['db_name'])) {
                $prefix = $this->normalizeTenantPrefix((string)$row['db_name']);
                $session->set('tenant_table_prefix', $prefix);
                return $prefix;
            }
        } catch (\Throwable) {
            // SaaS DB unreachable or tenant not found — fall through silently
        }

        return '';
    }

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

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    private function handleException(\Throwable $e): void
    {
        $config = $this->container->get(Config::class);
        $debug = $config->get('app.debug', false);

        /* Always log to file */
        $logDir  = $this->rootPath . '/storage/logs';
        $logFile = $logDir . '/error.log';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        @file_put_contents(
            $logFile,
            '[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n"
            . $e->getTraceAsString() . "\n\n",
            FILE_APPEND
        );

        http_response_code(500);

        /* For AJAX/API requests always return JSON so devtools shows the real error */
        $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
               || (($_SERVER['HTTP_ACCEPT'] ?? '') && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if ($debug) {
                echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
            } else {
                echo json_encode(['error' => 'Interner Serverfehler. Bitte versuchen Sie es später erneut.']);
            }
            exit;
        }

        if ($debug) {
            echo '<pre style="background:#1a1a2e;color:#e94560;padding:20px;font-family:monospace;">';
            echo '<strong>Exception:</strong> ' . htmlspecialchars($e->getMessage()) . "\n\n";
            echo '<strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . "\n\n";
            echo '<strong>Trace:</strong>' . "\n" . htmlspecialchars($e->getTraceAsString());
            echo '</pre>';
        } else {
            echo '<!DOCTYPE html><html><body style="background:#0f0f1a;color:#fff;font-family:sans-serif;text-align:center;padding:100px;">';
            echo '<h1>500 - Interner Serverfehler</h1><p>Bitte versuchen Sie es später erneut.</p>';
            echo '</body></html>';
        }
    }
}
