<?php

declare(strict_types=1);

namespace Plugins\GoogleCalendarSync;

use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Core\Application;

class ServiceProvider
{
    public function register(PluginManager $pluginManager): void
    {
        require_once __DIR__ . '/GoogleCalendarRepository.php';
        require_once __DIR__ . '/GoogleApiService.php';
        require_once __DIR__ . '/GoogleSyncService.php';
        require_once __DIR__ . '/GoogleCalendarController.php';

        $this->runMigrations();
        $this->loadEnvCredentials();

        $view = Application::getInstance()->getContainer()->get(View::class);
        $view->addTemplatePath(__DIR__ . '/templates', 'google-calendar-sync');

        /* Expose sync JS bridge URL as Twig global so calendar templates can use it */
        $view->addGlobal('google_sync_bridge_url', '/google-kalender/sync/termin');

        /* Google Kalender is NOT added to the main sidebar nav.
           It is accessible via Einstellungen → Integrationen → Google Kalender (/google-kalender). */

        $pluginManager->hook('registerRoutes', [$this, 'registerRoutes']);
        $pluginManager->hook('dashboardWidgets', [$this, 'dashboardWidget']);
        $pluginManager->hook('navItems', [$this, 'navItem']);

        /* Hook into appointment lifecycle events fired by the calendar plugin */
        $pluginManager->hook('appointmentCreated', [$this, 'onAppointmentCreated']);
        $pluginManager->hook('appointmentUpdated', [$this, 'onAppointmentUpdated']);
        $pluginManager->hook('appointmentDeleted', [$this, 'onAppointmentDeleted']);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/google-kalender',                          [GoogleCalendarController::class, 'index'],           ['auth']);
        $router->get('/google-kalender/verbinden',                [GoogleCalendarController::class, 'connect'],         ['auth']);
        $router->get('/google-kalender/callback',                 [GoogleCalendarController::class, 'oauthCallback'],   []);
        $router->post('/google-kalender/trennen',                 [GoogleCalendarController::class, 'disconnect'],      ['auth']);
        $router->post('/google-kalender/einstellungen',           [GoogleCalendarController::class, 'saveSettings'],    ['auth']);
        $router->post('/google-kalender/test-sync',               [GoogleCalendarController::class, 'testSync'],        ['auth']);
        $router->post('/google-kalender/bulk-sync',               [GoogleCalendarController::class, 'bulkSync'],        ['auth']);
        $router->post('/google-kalender/pull',                    [GoogleCalendarController::class, 'pullFromGoogle'],  ['auth']);
        $router->post('/google-kalender/kalender-auswaehlen',     [GoogleCalendarController::class, 'selectCalendar'],  ['auth']);
        $router->post('/google-kalender/sync/termin/{id}',        [GoogleCalendarController::class, 'syncAppointment'], ['auth']);
        $router->get('/google-kalender/cron',                     [GoogleCalendarController::class, 'cron'],            []);
    }

    /* ── Called when calendar plugin fires appointmentCreated hook ── */
    public function onAppointmentCreated(mixed $payload): mixed
    {
        if (!is_array($payload) || empty($payload['id'])) return $payload;
        try {
            $db   = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $repo = new GoogleCalendarRepository($db);
            $api  = new GoogleApiService($repo);
            $sync = new GoogleSyncService($repo, $api, $db);
            $sync->syncCreated((int)$payload['id']);
        } catch (\Throwable) { /* never crash the app */ }
        return $payload;
    }

    /* ── Called when calendar plugin fires appointmentUpdated hook ── */
    public function onAppointmentUpdated(mixed $payload): mixed
    {
        if (!is_array($payload) || empty($payload['id'])) return $payload;
        try {
            $db   = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $repo = new GoogleCalendarRepository($db);
            $api  = new GoogleApiService($repo);
            $sync = new GoogleSyncService($repo, $api, $db);
            $sync->syncUpdated((int)$payload['id']);
        } catch (\Throwable) { /* never crash the app */ }
        return $payload;
    }

    /* ── Called when calendar plugin fires appointmentDeleted hook ── */
    public function onAppointmentDeleted(mixed $payload): mixed
    {
        if (!is_array($payload) || empty($payload['id'])) return $payload;
        try {
            $db   = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $repo = new GoogleCalendarRepository($db);
            $api  = new GoogleApiService($repo);
            $sync = new GoogleSyncService($repo, $api, $db);
            $sync->syncDeleted((int)$payload['id']);
        } catch (\Throwable) { /* never crash the app */ }
        return $payload;
    }

    public function dashboardWidget(array $context): array
    {
        try {
            $db   = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $repo = new GoogleCalendarRepository($db);
            $conn = $repo->getConnection();
            if (!$conn) return [];
            $synced    = $repo->getRecentSyncedCount(24);
            $lastError = $repo->getLastError();
            $lastOk    = $repo->getLastSuccessfulSync();
        } catch (\Throwable) {
            return [];
        }

        $statusColor = $conn['sync_enabled'] ? '#22c55e' : '#f59e0b';
        $statusLabel = $conn['sync_enabled'] ? 'Aktiv' : 'Deaktiviert';

        $html  = '<div class="d-flex gap-3 mb-3">';
        $html .= '<div class="text-center flex-fill"><div class="fs-3 fw-800" style="color:#a78bfa">' . $synced . '</div><div class="fs-nano text-muted">Heute synchronisiert</div></div>';
        $html .= '<div class="text-center flex-fill"><div class="fs-sm fw-600" style="color:' . $statusColor . '">' . $statusLabel . '</div><div class="fs-nano text-muted">Status</div></div>';
        $html .= '</div>';
        if ($lastError) {
            $html .= '<div class="alert alert-warning p-2 fs-nano mb-2">⚠ ' . htmlspecialchars(mb_substr($lastError['message'] ?? '', 0, 80)) . '</div>';
        }
        $html .= '<a href="/google-kalender" class="btn btn-sm btn-outline-primary w-100">Google Kalender →</a>';

        return [
            'id'      => 'panel-widget-google-cal',
            'title'   => 'Google Kalender Sync',
            'icon'    => '<svg width="14" height="14" fill="none" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/></svg>',
            'content' => $html,
            'col'     => 'col-xl-4 col-lg-5 col-12',
        ];
    }

    public function navItem(array $context): array
    {
        return [
            'label' => 'Google Kalender',
            'href'  => '/google-kalender',
            'icon'  => '<svg width="16" height="16" fill="none" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/></svg>',
        ];
    }

    private function loadEnvCredentials(): void
    {
        /* Load from .env or storage/config/google.php if not already defined */
        $configFile = defined('ROOT_PATH') ? ROOT_PATH . '/storage/config/google.php' : null;
        if ($configFile && file_exists($configFile)) {
            $cfg = require $configFile;
            if (!defined('GOOGLE_CLIENT_ID')     && !empty($cfg['client_id']))     define('GOOGLE_CLIENT_ID',     $cfg['client_id']);
            if (!defined('GOOGLE_CLIENT_SECRET') && !empty($cfg['client_secret'])) define('GOOGLE_CLIENT_SECRET', $cfg['client_secret']);
            if (!defined('GOOGLE_REDIRECT_URI')  && !empty($cfg['redirect_uri']))  define('GOOGLE_REDIRECT_URI',  $cfg['redirect_uri']);
            if (!defined('GOOGLE_SYNC_CRON_SECRET') && !empty($cfg['cron_secret'])) define('GOOGLE_SYNC_CRON_SECRET', $cfg['cron_secret']);
        }

        /* Fallback to environment variables */
        if (!defined('GOOGLE_CLIENT_ID')     && getenv('GOOGLE_CLIENT_ID'))     define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID'));
        if (!defined('GOOGLE_CLIENT_SECRET') && getenv('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET'));
        if (!defined('GOOGLE_REDIRECT_URI')  && getenv('GOOGLE_REDIRECT_URI'))  define('GOOGLE_REDIRECT_URI',  getenv('GOOGLE_REDIRECT_URI'));
        if (!defined('GOOGLE_SYNC_CRON_SECRET') && getenv('GOOGLE_SYNC_CRON_SECRET')) define('GOOGLE_SYNC_CRON_SECRET', getenv('GOOGLE_SYNC_CRON_SECRET'));

        /* Build redirect URI from current host if still missing */
        if (!defined('GOOGLE_REDIRECT_URI')) {
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            define('GOOGLE_REDIRECT_URI', $scheme . '://' . $host . '/google-kalender/callback');
        }
    }

    private function runMigrations(): void
    {
        try {
            $db           = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $migrationDir = __DIR__ . '/migrations';
            if (!is_dir($migrationDir)) return;

            $files = glob($migrationDir . '/*.sql');
            if (!$files) return;
            sort($files);

            foreach ($files as $file) {
                $sql        = file_get_contents($file);
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        try {
                            $db->execute($stmt);
                        } catch (\Throwable) { /* table already exists */ }
                    }
                }
            }
        } catch (\Throwable) { /* DB not ready */ }
    }
}
