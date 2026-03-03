<?php

declare(strict_types=1);

namespace Plugins\PatientIntake;

use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Core\Application;

class ServiceProvider
{
    public function register(PluginManager $pluginManager): void
    {
        require_once __DIR__ . '/IntakeRepository.php';
        require_once __DIR__ . '/IntakeMailService.php';
        require_once __DIR__ . '/IntakeController.php';

        $this->runMigrations();

        $view = Application::getInstance()->getContainer()->get(View::class);
        $view->addTemplatePath(__DIR__ . '/templates', 'patient-intake');

        /* Routes */
        $pluginManager->hook('registerRoutes', [$this, 'registerRoutes']);

        /* Nav item in sidebar */
        $navItems   = $view->getTwig()->getGlobals()['plugin_nav_items'] ?? [];
        $navItems[] = [
            'label' => 'Eingangsmeldungen',
            'href'  => '/eingangsmeldungen',
            'icon'  => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="22,6 12,13 2,6"/></svg>',
        ];
        $view->addGlobal('plugin_nav_items', $navItems);

        /* Inject unread count into every page for the notification bell */
        $this->injectNotificationCount($view);

        /* Dashboard widget */
        $pluginManager->hook('dashboardWidgets', [$this, 'dashboardWidget']);
    }

    public function registerRoutes(Router $router): void
    {
        /* Public — no auth */
        $router->get('/anmeldung',        [IntakeController::class, 'form'],     []);
        $router->post('/anmeldung',        [IntakeController::class, 'submit'],   []);
        $router->get('/anmeldung/danke',   [IntakeController::class, 'thankYou'], []);

        /* Admin — requires auth */
        $router->get('/eingangsmeldungen',              [IntakeController::class, 'inbox'],        ['auth']);
        $router->get('/eingangsmeldungen/{id}',         [IntakeController::class, 'show'],         ['auth']);
        $router->post('/eingangsmeldungen/{id}/akzeptieren', [IntakeController::class, 'accept'],  ['auth']);
        $router->post('/eingangsmeldungen/{id}/ablehnen',    [IntakeController::class, 'reject'],  ['auth']);
        $router->post('/eingangsmeldungen/{id}/status',      [IntakeController::class, 'updateStatus'], ['auth']);

        /* API — requires auth */
        $router->get('/api/intake/notifications', [IntakeController::class, 'apiNotifications'], ['auth']);

        /* Photo serving (intake photos) */
        $router->get('/intake/foto/{file}', [IntakeController::class, 'servePhoto'], ['auth']);
    }

    public function dashboardWidget(array $context): string
    {
        try {
            $db   = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $repo = new IntakeRepository($db);
            $neu  = $repo->countByStatus('neu');
            $ib   = $repo->countByStatus('in_bearbeitung');
            $latest = $repo->getLatestUnread(3);
        } catch (\Throwable) {
            return '';
        }

        $total = $neu + $ib;

        $html  = '<div style="padding:1rem;">';
        $html .= '<div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem;">';
        $html .= '<svg width="13" height="13" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="22,6 12,13 2,6"/></svg>';
        $html .= 'Eingangsmeldungen</div>';

        $html .= '<div style="display:flex;gap:1rem;margin-bottom:0.75rem;">';
        $html .= '<div style="text-align:center;flex:1;"><div style="font-size:1.4rem;font-weight:700;color:' . ($neu > 0 ? '#ef4444' : 'var(--accent)') . ';">' . $neu . '</div><div style="font-size:0.7rem;color:var(--text-muted);">Neu</div></div>';
        $html .= '<div style="text-align:center;flex:1;"><div style="font-size:1.4rem;font-weight:700;color:var(--accent);">' . $ib . '</div><div style="font-size:0.7rem;color:var(--text-muted);">In Bearb.</div></div>';
        $html .= '</div>';

        if (empty($latest)) {
            $html .= '<div style="font-size:0.8rem;color:var(--text-muted);">Keine offenen Meldungen.</div>';
        } else {
            foreach ($latest as $item) {
                $name    = htmlspecialchars($item['patient_name']);
                $owner   = htmlspecialchars($item['owner_first_name'] . ' ' . $item['owner_last_name']);
                $time    = date('d.m. H:i', strtotime($item['created_at']));
                $html .= '<div style="display:flex;align-items:center;gap:0.5rem;padding:0.35rem 0;border-bottom:1px solid var(--glass-border);">';
                $html .= '<span style="width:8px;height:8px;border-radius:50%;background:#ef4444;flex-shrink:0;display:inline-block;animation:pulse 2s infinite;"></span>';
                $html .= '<span style="font-size:0.78rem;color:var(--text-muted);flex-shrink:0;">' . $time . '</span>';
                $html .= '<span style="font-size:0.82rem;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $name . ' · ' . $owner . '</span>';
                $html .= '</div>';
            }
        }

        $html .= '<a href="/eingangsmeldungen" style="display:block;margin-top:0.75rem;text-align:center;font-size:0.78rem;color:var(--accent);text-decoration:none;">Alle anzeigen →</a>';
        $html .= '</div>';
        return $html;
    }

    private function injectNotificationCount(View $view): void
    {
        try {
            $db   = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $repo = new IntakeRepository($db);
            $view->addGlobal('intake_unread_count', $repo->countUnread());
            $view->addGlobal('intake_latest', $repo->getLatestUnread(5));
        } catch (\Throwable) {
            $view->addGlobal('intake_unread_count', 0);
            $view->addGlobal('intake_latest', []);
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
                        } catch (\Throwable) {
                            /* Table already exists — skip */
                        }
                    }
                }
            }
        } catch (\Throwable) {
            /* DB not available yet (installer phase) */
        }
    }
}
