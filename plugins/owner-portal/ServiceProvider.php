<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Core\Application;

class ServiceProvider
{
    public function register(PluginManager $pluginManager): void
    {
        require_once __DIR__ . '/OwnerPortalRepository.php';
        require_once __DIR__ . '/OwnerPortalMailService.php';
        require_once __DIR__ . '/OwnerAuthController.php';
        require_once __DIR__ . '/OwnerPortalController.php';
        require_once __DIR__ . '/OwnerPortalAdminController.php';
        require_once __DIR__ . '/MessagingRepository.php';
        require_once __DIR__ . '/MessagingMailService.php';
        require_once __DIR__ . '/MessagingAdminController.php';
        require_once __DIR__ . '/MessagingOwnerController.php';
        require_once __DIR__ . '/OwnerPortalBookingController.php';

        $this->runMigrations();

        $view = Application::getInstance()->getContainer()->get(View::class);
        $view->addTemplatePath(__DIR__ . '/templates', 'owner-portal');

        $pluginManager->hook('registerRoutes', [$this, 'registerRoutes']);
        $pluginManager->hook('dashboardWidgets', [$this, 'dashboardWidget']);

        /* Admin nav item with unread badge */
        try {
            $db      = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $msgRepo = new MessagingRepository($db);
            $unreadCount = $msgRepo->countUnreadForAdmin();
        } catch (\Throwable) {
            $unreadCount = 0;
        }
        $badgeHtml = $unreadCount > 0
            ? ' <span style="display:inline-block;background:#ef4444;color:#fff;border-radius:20px;font-size:.6rem;font-weight:700;padding:0 5px;line-height:1.5;vertical-align:1px;">' . $unreadCount . '</span>'
            : '';

        $navItems   = $view->getTwig()->getGlobals()['plugin_nav_items'] ?? [];
        $navItems[] = [
            'label'         => 'Besitzerportal',
            'href'          => '/portal-admin',
            'feature'       => 'patient_portal',
            'practice_only' => true, // Tierhalter-Portal — Hundeschule hat ihr eigenes Online-Portal
            'icon'    => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="9 22 9 12 15 12 15 22"/></svg>',
            'badge'   => $badgeHtml,
        ];
        $view->addGlobal('plugin_nav_items', $navItems);
        $view->addGlobal('portal_msg_unread_count', $unreadCount);

        $pluginManager->hook('navItems', [$this, 'navItem']);
    }

    public function registerRoutes(Router $router): void
    {
        /* ── Public portal routes (no admin auth) ── */
        $router->get('/portal/login',                    [OwnerAuthController::class,        'showLogin'],      []);
        $router->post('/portal/login',                   [OwnerAuthController::class,        'login'],          []);
        $router->post('/portal/logout',                  [OwnerAuthController::class,        'logout'],         []);
        $router->get('/portal/einladung/{token}',        [OwnerAuthController::class,        'showSetPassword'],[]);
        $router->post('/portal/einladung/{token}',       [OwnerAuthController::class,        'setPassword'],    []);

        /* ── Portal pages (owner session handled in controller) ── */
        $router->get('/portal/dashboard',                [OwnerPortalController::class,      'dashboard'],      []);
        $router->get('/portal/tiere',                    [OwnerPortalController::class,      'petList'],        []);
        $router->get('/portal/tiere/{id}',               [OwnerPortalController::class,      'petDetail'],      []);
        $router->get('/portal/tiere/{id}/foto/{file}',     [OwnerPortalController::class,      'petPhoto'],       []);
        $router->get('/portal/tiere/{id}/bearbeiten',     [OwnerPortalController::class,      'petEdit'],        []);
        $router->post('/portal/tiere/{id}/bearbeiten',    [OwnerPortalController::class,      'petEditSave'],    []);
        $router->get('/portal/tiere/{id}/uebungen',       [OwnerPortalController::class,      'exercises'],      []);
        $router->get('/portal/rechnungen',               [OwnerPortalController::class,      'invoices'],       []);
        $router->get('/portal/rechnungen/{id}/pdf',      [OwnerPortalController::class,      'invoicePdf'],     []);
        $router->get('/portal/termine',                  [OwnerPortalController::class,      'appointments'],   []);

        /* ── Hundeschul-Portal: Kurse + Pakete buchen (nur für Trainer-Tenants) ──
         * Der Controller prüft in jeder Methode selbständig, dass es sich um einen
         * Trainer-Tenant handelt (sonst 404) — Routen sind immer registriert, damit
         * kein Tenant-Switch die URL-Konfiguration ändert. */
        $router->get('/portal/kurse',                            [OwnerPortalBookingController::class, 'coursesIndex'],     []);
        $router->get('/portal/kurse/{id}',                       [OwnerPortalBookingController::class, 'courseDetail'],    []);
        $router->post('/portal/kurse/{id}/einschreiben',         [OwnerPortalBookingController::class, 'courseEnroll'],    []);
        $router->get('/portal/pakete',                           [OwnerPortalBookingController::class, 'packagesIndex'],   []);
        $router->post('/portal/pakete/{id}/kaufen',              [OwnerPortalBookingController::class, 'packagePurchase'], []);

        /* ── Admin portal management (requires staff auth) ── */
        $router->post('/portal-admin/einstellungen',             [OwnerPortalAdminController::class, 'saveSettings'],   ['auth']);
        $router->get('/portal-admin',                            [OwnerPortalAdminController::class, 'index'],          ['auth']);
        $router->get('/portal-admin/einladen',                   [OwnerPortalAdminController::class, 'showInvite'],     ['auth']);
        $router->post('/portal-admin/einladen',                  [OwnerPortalAdminController::class, 'sendInvite'],     ['auth']);
        $router->post('/portal-admin/{id}/deaktivieren',         [OwnerPortalAdminController::class, 'deactivate'],     ['auth']);
        $router->post('/portal-admin/{id}/aktivieren',           [OwnerPortalAdminController::class, 'activate'],       ['auth']);
        $router->post('/portal-admin/{id}/neu-einladen',         [OwnerPortalAdminController::class, 'resendInvite'],   ['auth']);
        $router->get('/portal-admin/tiere/{owner_id}/uebungen', [OwnerPortalAdminController::class, 'exerciseIndex'],  ['auth']);
        $router->post('/portal-admin/tiere/{owner_id}/uebungen', [OwnerPortalAdminController::class, 'exerciseStore'],  ['auth']);
        $router->post('/portal-admin/uebungen/{id}/loeschen',    [OwnerPortalAdminController::class, 'exerciseDelete'], ['auth']);
        $router->post('/portal-admin/uebungen/{id}/bearbeiten',  [OwnerPortalAdminController::class, 'exerciseUpdate'], ['auth']);

        /* ── Befundbögen (admin view) ── */
        $router->get('/portal-admin/tiere/{owner_id}/befunde',       [OwnerPortalAdminController::class, 'befundeIndex'],       ['auth']);

        /* ── Homework plans ── */
        $router->get('/portal-admin/tiere/{owner_id}/hausaufgaben',  [OwnerPortalAdminController::class, 'homeworkPlanIndex'],  ['auth']);
        $router->post('/portal-admin/tiere/{owner_id}/hausaufgaben', [OwnerPortalAdminController::class, 'homeworkPlanStore'],  ['auth']);
        $router->get('/portal-admin/hausaufgaben/{id}/bearbeiten',   [OwnerPortalAdminController::class, 'homeworkPlanEdit'],   ['auth']);
        $router->post('/portal-admin/hausaufgaben/{id}/bearbeiten',  [OwnerPortalAdminController::class, 'homeworkPlanUpdate'], ['auth']);
        $router->post('/portal-admin/hausaufgaben/{id}/loeschen',    [OwnerPortalAdminController::class, 'homeworkPlanDelete'], ['auth']);
        $router->get('/portal-admin/hausaufgaben/{id}/pdf',          [OwnerPortalAdminController::class, 'homeworkPlanPdf'],    ['auth']);
        $router->post('/portal-admin/hausaufgaben/{id}/senden',      [OwnerPortalAdminController::class, 'homeworkPlanSend'],   ['auth']);

        /* ── Owner portal homework view + checklist ── */
        $router->get('/portal/hausaufgaben',                                          [OwnerPortalController::class, 'homeworkOverview'],   []);
        $router->get('/portal/tiere/{id}/hausaufgaben',                              [OwnerPortalController::class, 'homework'],          []);
        $router->get('/portal/tiere/{id}/hausaufgaben/{plan_id}/pdf',                [OwnerPortalController::class, 'homeworkPdf'],        []);
        $router->post('/api/portal/hausaufgaben/{plan_id}/aufgabe/{task_id}/abhaken',[OwnerPortalController::class, 'homeworkTaskToggle'], []);

        /* ── Check-Notification API (Besitzer hat Aufgabe abgehakt) ── */
        $router->get('/api/portal-admin/check-notifications',           [OwnerPortalAdminController::class, 'checkNotifications'],       ['auth']);
        $router->post('/api/portal-admin/check-notifications/gelesen',  [OwnerPortalAdminController::class, 'checkNotificationsMarkRead'],['auth']);

        /* ── Messaging: Admin routes ── */
        $router->get('/portal-admin/nachrichten',                                    [MessagingAdminController::class, 'index'],     ['auth']);
        $router->get('/portal-admin/nachrichten/{id}',                               [MessagingAdminController::class, 'thread'],    ['auth']);
        $router->post('/api/portal-admin/nachrichten/{id}/antworten',                [MessagingAdminController::class, 'reply'],     ['auth']);
        $router->post('/api/portal-admin/nachrichten/{id}/status',                   [MessagingAdminController::class, 'setStatus'], ['auth']);
        $router->post('/api/portal-admin/nachrichten/neu',                           [MessagingAdminController::class, 'newThread'],   ['auth']);
        $router->get('/api/portal-admin/nachrichten-drawer',                         [MessagingAdminController::class, 'drawerData'],  ['auth']);
        $router->get('/api/portal-admin/nachrichten/{id}/messages',                   [MessagingAdminController::class, 'messages'],     ['auth']);
        $router->get('/api/portal-admin/portal-users',                               [MessagingAdminController::class, 'portalUsers'], ['auth']);
        $router->post('/api/portal-admin/nachrichten/{id}/loeschen',                 [MessagingAdminController::class, 'delete'],    ['auth']);

        /* ── Messaging: Owner (portal) routes ── */
        $router->get('/portal/nachrichten',                                          [MessagingOwnerController::class, 'index'],       []);
        $router->get('/portal/nachrichten/{id}',                                     [MessagingOwnerController::class, 'thread'],      []);
        $router->get('/api/portal/nachrichten/ungelesen',                            [MessagingOwnerController::class, 'unreadCount'], []);
        $router->post('/api/portal/nachrichten/{id}/antworten',                      [MessagingOwnerController::class, 'reply'],       []);
        $router->post('/api/portal/nachrichten/neu',                                 [MessagingOwnerController::class, 'newThread'],   []);
    }

    public function dashboardWidget(array $context): array
    {
        try {
            $db   = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $repo = new OwnerPortalRepository($db);
            $users = $repo->getAllPortalUsers();
            $active   = count(array_filter($users, fn($u) => $u['is_active'] && $u['password_hash']));
            $pending  = count(array_filter($users, fn($u) => !$u['invite_used_at'] && $u['invite_token']));
        } catch (\Throwable) {
            return [];
        }

        $html  = '<div class="d-flex gap-3 mb-3">';
        $html .= '<div class="text-center flex-fill"><div class="fs-3 fw-800" style="color:var(--bs-primary)">' . $active . '</div><div class="fs-nano text-muted">Aktiv</div></div>';
        $html .= '<div class="text-center flex-fill"><div class="fs-3 fw-800" style="color:#f59e0b">' . $pending . '</div><div class="fs-nano text-muted">Eingeladen</div></div>';
        $html .= '</div>';
        $html .= '<a href="/portal-admin" class="btn btn-sm btn-outline-primary w-100">Verwaltung öffnen →</a>';

        return [
            'id'      => 'panel-widget-owner-portal',
            'title'   => 'Besitzerportal',
            'icon'    => '<svg width="14" height="14" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>',
            'content' => $html,
            'col'     => 'col-xl-4 col-lg-5 col-12',
        ];
    }

    public function navItem(array $context): array
    {
        return [
            'label' => 'Besitzerportal',
            'href'  => '/portal-admin',
            'icon'  => '<svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>',
        ];
    }

    private function runMigrations(): void
    {
        try {
            $db           = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $prefix       = $db->getPrefix();
            $migrationDir = __DIR__ . '/migrations';
            if (!is_dir($migrationDir)) return;

            $files = glob($migrationDir . '/*.sql');
            if (!$files) return;
            sort($files);

            foreach ($files as $file) {
                $sql = file_get_contents($file);

                /* Replace {PREFIX} placeholder with actual tenant prefix */
                $sql = str_replace('{PREFIX}', $prefix, $sql);

                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        try {
                            $db->execute($stmt);
                        } catch (\Throwable) {
                            /* Table already exists or constraint duplicate — skip */
                        }
                    }
                }
            }
        } catch (\Throwable) {
            /* DB not available yet */
        }
    }
}
