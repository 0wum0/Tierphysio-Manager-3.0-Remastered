<?php

declare(strict_types=1);

namespace Plugins\PatientInvite;

use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Core\Application;

class ServiceProvider
{
    public function register(PluginManager $pluginManager): void
    {
        require_once __DIR__ . '/InviteRepository.php';
        require_once __DIR__ . '/InviteMailService.php';
        require_once __DIR__ . '/InviteController.php';

        $this->runMigrations();

        $view = Application::getInstance()->getContainer()->get(View::class);
        $view->addTemplatePath(__DIR__ . '/templates', 'patient-invite');

        $pluginManager->hook('registerRoutes', [$this, 'registerRoutes']);
        $pluginManager->hook('dashboardWidgets', [$this, 'dashboardWidget']);

        /* Sidebar nav item */
        $navItems   = $view->getTwig()->getGlobals()['plugin_nav_items'] ?? [];
        $navItems[] = [
            'label'         => 'Einladungslinks',
            'href'          => '/einladungen',
            'feature'       => 'patient_invite',
            'practice_only' => true, // Patienten-Einladungsprozess — nicht im Hundeschulmodus
            'icon'    => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
        ];
        $view->addGlobal('plugin_nav_items', $navItems);
    }

    public function registerRoutes(Router $router): void
    {
        /* Admin — requires auth */
        $router->get('/einladungen',              [InviteController::class, 'index'],      ['auth']);
        $router->post('/einladungen/senden',       [InviteController::class, 'send'],       ['auth']);
        $router->get('/einladungen/{id}/whatsapp', [InviteController::class, 'whatsappUrl'],['auth']);
        $router->get('/einladungen/{id}/link',     [InviteController::class, 'copyLink'],   ['auth']);
        $router->post('/einladungen/{id}/widerrufen',  [InviteController::class, 'revoke'],      ['auth']);
        $router->post('/einladungen/{id}/bearbeiten', [InviteController::class, 'update'],      ['auth']);
        $router->post('/einladungen/{id}/annehmen',   [InviteController::class, 'acceptAdmin'], ['auth']);
        $router->post('/einladungen/{id}/ablehnen',   [InviteController::class, 'rejectAdmin'], ['auth']);
        $router->post('/einladungen/{id}/ausblenden', [InviteController::class, 'hide'],        ['auth']);

        /* Admin — diagnose */
        $router->get('/einladungen/diagnose',      [InviteController::class, 'diagnose'],   ['auth']);
        $router->get('/einladung/{token}/test',    [InviteController::class, 'testSubmit'], ['auth']);

        /* Public — magic link (no auth) */
        $router->get('/einladung/{token}',        [InviteController::class, 'landing'],    []);
        $router->post('/einladung/{token}',        [InviteController::class, 'submit'],     []);
        $router->get('/einladung/{token}/danke',   [InviteController::class, 'thankYou'],   []);
    }

    public function dashboardWidget(array $context): array
    {
        try {
            $db   = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $repo = new InviteRepository($db);
            $offen      = $repo->countByStatus('offen');
            $angenommen = $repo->countByStatus('angenommen');
        } catch (\Throwable) {
            return [];
        }

        $html  = '<div class="d-flex gap-3 mb-3">';
        $html .= '<div class="text-center flex-fill"><div class="fs-3 fw-800" style="color:' . ($offen > 0 ? '#f59e0b' : 'var(--bs-primary)') . '">' . $offen . '</div><div class="fs-nano text-muted">Offen</div></div>';
        $html .= '<div class="text-center flex-fill"><div class="fs-3 fw-800" style="color:#22c55e">' . $angenommen . '</div><div class="fs-nano text-muted">Angenommen</div></div>';
        $html .= '</div>';
        $html .= '<a href="/einladungen" class="btn btn-sm btn-outline-primary w-100">Alle anzeigen →</a>';

        return [
            'id'      => 'panel-widget-invites',
            'title'   => 'Einladungslinks',
            'icon'    => '<svg width="14" height="14" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
            'content' => $html,
            'col'     => 'col-xl-4 col-lg-5 col-12',
        ];
    }

    /**
     * Self-Heal-Migrations-Runner für den patient-invite-Plugin.
     *
     * Delegiert an den zentralen PluginMigrationService, der pro Tenant die
     * aktuelle Migrations-Version in `settings.plugin_patient_invite_migration_version`
     * trackt und nur ausstehende Migrationen fährt. Läuft bei jedem
     * authentifizierten Request (session-gecached), sodass Altbestand-Tenants
     * sich beim nächsten Login automatisch auf den neuesten Schema-Stand heilen.
     */
    private function runMigrations(): void
    {
        try {
            $container = Application::getInstance()->getContainer();
            $db        = $container->get(\App\Core\Database::class);
            $session   = $container->has(\App\Core\Session::class)
                ? $container->get(\App\Core\Session::class)
                : null;

            $runner = new \App\Services\PluginMigrationService($db, $session);
            $runner->runPending('patient_invite', __DIR__ . '/migrations');
        } catch (\Throwable $e) {
            error_log('[patient-invite] migration bootstrap failed: ' . $e->getMessage());
        }
    }
}
