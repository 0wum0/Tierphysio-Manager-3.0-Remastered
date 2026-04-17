<?php

declare(strict_types=1);

namespace Plugins\TherapyCarePro;

use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Core\Application;

class ServiceProvider
{
    public function register(PluginManager $pluginManager): void
    {
        require_once __DIR__ . '/TherapyCareRepository.php';
        require_once __DIR__ . '/TherapyCareController.php';
        require_once __DIR__ . '/TherapyCarePortalController.php';
        require_once __DIR__ . '/TherapyCareReportService.php';

        $this->runMigrations();
        $this->publishAssets();

        // Register template namespace @therapy-care-pro
        $view = Application::getInstance()->getContainer()->get(View::class);
        $view->addTemplatePath(__DIR__ . '/templates', 'therapy-care-pro');

        // Register routes
        $pluginManager->hook('registerRoutes', [$this, 'registerRoutes']);

        // Patient detail tabs (adds Fortschritt + Naturheilkunde tabs)
        $pluginManager->hook('patientDetailTabs', [$this, 'addPatientDetailTabs']);

        // Patient header action buttons
        $pluginManager->hook('patientHeaderActions', [$this, 'patientHeaderActions']);

        // Dashboard widget
        $pluginManager->hook('dashboardWidgets', [$this, 'dashboardWidget']);

        // Nav items (admin area links)
        $navItems   = $view->getTwig()->getGlobals()['plugin_nav_items'] ?? [];
        $navItems[] = [
            'label' => 'TherapyCare Pro',
            'href'  => '/tcp/admin/einstellungen',
            'icon'  => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>',
        ];
        $view->addGlobal('plugin_nav_items', $navItems);
    }

    public function registerRoutes(Router $router): void
    {
        /* ── MODULE 1: Progress Tracking ── */
        $router->get( '/patienten/{id}/fortschritt',                          [TherapyCareController::class, 'progressIndex'],       ['auth']);
        $router->post('/patienten/{id}/fortschritt',                          [TherapyCareController::class, 'progressStore'],       ['auth']);
        $router->post('/patienten/{id}/fortschritt/{entry_id}/loeschen',      [TherapyCareController::class, 'progressDeleteEntry'], ['auth']);

        /* ── MODULE 2: Feedback (Practice view) ── */
        $router->get( '/patienten/{id}/feedback',                             [TherapyCareController::class, 'feedbackIndex'],       ['auth']);

        /* ── MODULE 3: Reminders ── */
        $router->get( '/patienten/{id}/erinnerungen',                         [TherapyCareController::class, 'reminderQueue'],       ['auth']);
        $router->post('/patienten/{id}/erinnerungen',                         [TherapyCareController::class, 'reminderQueueStore'],  ['auth']);
        $router->get( '/tcp/admin/erinnerungen',                              [TherapyCareController::class, 'remindersAdmin'],      ['auth']);
        $router->post('/tcp/admin/erinnerungen',                              [TherapyCareController::class, 'reminderTemplateStore'],   ['auth']);
        $router->post('/tcp/admin/erinnerungen/{id}/bearbeiten',              [TherapyCareController::class, 'reminderTemplateUpdate'],  ['auth']);
        $router->post('/tcp/admin/erinnerungen/{id}/loeschen',                [TherapyCareController::class, 'reminderTemplateDelete'],  ['auth']);

        /* Cron — no auth middleware, secured by token */
        $router->get('/tcp/cron/erinnerungen',                                [TherapyCareController::class, 'cronReminders'],       []);

        /* ── MODULE 4: Therapy Reports ── */
        $router->get( '/patienten/{id}/berichte',                             [TherapyCareController::class, 'reportIndex'],         ['auth']);
        $router->get( '/patienten/{id}/berichte/neu',                         [TherapyCareController::class, 'reportCreate'],        ['auth']);
        $router->post('/patienten/{id}/berichte/neu',                         [TherapyCareController::class, 'reportStore'],         ['auth']);
        $router->get( '/patienten/{id}/berichte/{report_id}/download',        [TherapyCareController::class, 'reportDownload'],      ['auth']);
        $router->post('/patienten/{id}/berichte/{report_id}/senden',          [TherapyCareController::class, 'reportSend'],          ['auth']);
        $router->post('/patienten/{id}/berichte/{report_id}/loeschen',        [TherapyCareController::class, 'reportDelete'],        ['auth']);

        /* ── MODULE 5: Exercise Library ── */
        $router->get( '/tcp/bibliothek',                                      [TherapyCareController::class, 'exerciseLibraryIndex'],     ['auth']);
        $router->get( '/tcp/bibliothek/neu',                                  [TherapyCareController::class, 'exerciseLibraryCreate'],    ['auth']);
        $router->post('/tcp/bibliothek/neu',                                  [TherapyCareController::class, 'exerciseLibraryStore'],     ['auth']);
        $router->get( '/tcp/bibliothek/{id}/bearbeiten',                      [TherapyCareController::class, 'exerciseLibraryEdit'],      ['auth']);
        $router->post('/tcp/bibliothek/{id}/bearbeiten',                      [TherapyCareController::class, 'exerciseLibraryUpdate'],    ['auth']);
        $router->post('/tcp/bibliothek/{id}/loeschen',                        [TherapyCareController::class, 'exerciseLibraryDelete'],    ['auth']);
        $router->post('/tcp/bibliothek/{id}/duplizieren',                     [TherapyCareController::class, 'exerciseLibraryDuplicate'], ['auth']);
        $router->get( '/api/tcp/bibliothek',                                  [TherapyCareController::class, 'apiExerciseLibrary'],       ['auth']);

        /* ── MODULE 6: Natural Therapy ── */
        $router->get( '/patienten/{id}/naturheilkunde',                       [TherapyCareController::class, 'naturalIndex'],        ['auth']);
        $router->post('/patienten/{id}/naturheilkunde',                       [TherapyCareController::class, 'naturalStore'],        ['auth']);
        $router->post('/patienten/{id}/naturheilkunde/{entry_id}/bearbeiten', [TherapyCareController::class, 'naturalUpdate'],       ['auth']);
        $router->post('/patienten/{id}/naturheilkunde/{entry_id}/loeschen',   [TherapyCareController::class, 'naturalDelete'],       ['auth']);

        /* ── MODULE 7: Enhanced Timeline ── */
        $router->get( '/patienten/{id}/timeline',                             [TherapyCareController::class, 'timelineIndex'],       ['auth']);

        /* ── MODULE 8: Portal Visibility ── */
        $router->post('/patienten/{id}/portal-freigaben',                     [TherapyCareController::class, 'portalVisibilityUpdate'], ['auth']);

        /* ── ADMIN SETTINGS ── */
        $router->get( '/tcp/admin/einstellungen',                             [TherapyCareController::class, 'adminSettings'],      ['auth']);
        $router->post('/tcp/admin/fortschritt/kategorien',                    [TherapyCareController::class, 'adminProgressCategoryStore'],  ['auth']);
        $router->post('/tcp/admin/fortschritt/kategorien/{id}/bearbeiten',    [TherapyCareController::class, 'adminProgressCategoryUpdate'], ['auth']);
        $router->post('/tcp/admin/fortschritt/kategorien/{id}/loeschen',      [TherapyCareController::class, 'adminProgressCategoryDelete'], ['auth']);
        $router->post('/tcp/admin/naturheilkunde/typen',                      [TherapyCareController::class, 'adminNaturalTypeStore'],  ['auth']);
        $router->post('/tcp/admin/naturheilkunde/typen/{id}/bearbeiten',      [TherapyCareController::class, 'adminNaturalTypeUpdate'], ['auth']);
        $router->post('/tcp/admin/naturheilkunde/typen/{id}/loeschen',        [TherapyCareController::class, 'adminNaturalTypeDelete'], ['auth']);

        /* ── API ── */
        $router->get( '/api/tcp/patienten/{id}/fortschritt',                  [TherapyCareController::class, 'apiProgressData'],     ['auth']);
        $router->get( '/api/tcp/patienten/{id}/portal-visibility',            [TherapyCareController::class, 'apiPortalVisibility'], ['auth']);
        $router->get( '/api/tcp/patienten/{id}/modal-data',                   [TherapyCareController::class, 'apiModalData'],        ['auth']);

        /* ── OWNER PORTAL EXTENSIONS ── */
        $router->get( '/portal/tcp/tiere/{id}/fortschritt',                   [TherapyCarePortalController::class, 'progress'],        []);
        $router->get( '/portal/tcp/tiere/{id}/feedback',                      [TherapyCarePortalController::class, 'feedbackList'],    []);
        $router->post('/portal/tcp/tiere/{id}/feedback',                      [TherapyCarePortalController::class, 'feedbackStore'],   []);
        $router->get( '/portal/tcp/tiere/{id}/naturheilkunde',                [TherapyCarePortalController::class, 'naturalTherapy'],  []);
        $router->get( '/portal/tcp/tiere/{id}/berichte',                      [TherapyCarePortalController::class, 'reports'],         []);
        $router->get( '/portal/tcp/tiere/{id}/berichte/{report_id}/download', [TherapyCarePortalController::class, 'reportDownload'],  []);
    }

    /* ── Hook: add tabs to patient detail page ── */
    public function addPatientDetailTabs(array $context): array
    {
        $patient = $context['patient'] ?? null;
        if (!$patient) return $context;

        $patientId = (int)$patient['id'];

        try {
            $container = Application::getInstance()->getContainer();
            $db        = $container->get(\App\Core\Database::class);
            $repo      = new TherapyCareRepository($db);

            // Progress tab
            $context['tabs'][] = [
                'id'      => 'tcp-fortschritt',
                'title'   => 'Fortschritt',
                'icon'    => '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" style="margin-right:4px;vertical-align:text-bottom;"><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
                'content' => $this->renderProgressTab($patient, $repo),
                'active'  => false,
            ];

            // Natural Therapy tab
            $context['tabs'][] = [
                'id'      => 'tcp-naturheilkunde',
                'title'   => 'Naturheilkunde',
                'icon'    => '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" style="margin-right:4px;vertical-align:text-bottom;"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
                'content' => $this->renderNaturalTab($patient, $repo),
                'active'  => false,
            ];

        } catch (\Throwable) {}

        return $context;
    }

    /* ── Hook: add buttons to patient header ── */
    public function patientHeaderActions(array $context): string
    {
        $patientId = $context['patient']['id'] ?? 0;
        if (!$patientId) return '';

        $html  = '<a href="/patienten/' . $patientId . '/fortschritt" class="btn btn-secondary btn-sm">';
        $html .= '<svg width="14" height="14" fill="none" viewBox="0 0 24 24"><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>';
        $html .= '<span class="d-none d-md-inline ms-1">Fortschritt</span></a>';

        $html .= '<a href="/patienten/' . $patientId . '/berichte/neu" class="btn btn-secondary btn-sm">';
        $html .= '<svg width="14" height="14" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="14 2 14 8 20 8"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="16" y1="13" x2="8" y2="13"/><line stroke="currentColor" stroke-width="2" stroke-linecap="round" x1="16" y1="17" x2="8" y2="17"/></svg>';
        $html .= '<span class="d-none d-md-inline ms-1">Therapiebericht</span></a>';

        return $html;
    }

    /* ── Hook: dashboard widget ── */
    public function dashboardWidget(array $context): array
    {
        try {
            $db    = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $repo  = new TherapyCareRepository($db);
            $stats = $repo->getProgressDashboardStats();
        } catch (\Throwable) {
            return [];
        }

        $html  = '<div class="d-flex gap-3 mb-3 flex-wrap">';
        $html .= '<div class="text-center flex-fill"><div class="fs-3 fw-800" style="color:var(--bs-primary)">' . $stats['totalEntries'] . '</div><div class="fs-nano text-muted">Fortschrittswerte</div></div>';
        $html .= '<div class="text-center flex-fill"><div class="fs-3 fw-800" style="color:var(--bs-success)">' . $stats['totalFeedback'] . '</div><div class="fs-nano text-muted">Feedbacks</div></div>';
        $html .= '<div class="text-center flex-fill"><div class="fs-3 fw-800" style="color:' . ($stats['painFeedback'] > 0 ? 'var(--bs-danger)' : 'var(--bs-success)') . '">' . $stats['painFeedback'] . '</div><div class="fs-nano text-muted">Schmerz-Meldungen (7T)</div></div>';
        $html .= '<div class="text-center flex-fill"><div class="fs-3 fw-800" style="color:var(--bs-warning)">' . $stats['pendingReminders'] . '</div><div class="fs-nano text-muted">Erinnerungen offen</div></div>';
        $html .= '</div>';
        $html .= '<div class="d-flex gap-2 mt-1 flex-wrap">';
        $html .= '<a href="/tcp/admin/einstellungen" class="btn btn-sm btn-outline-primary flex-fill">Einstellungen</a>';
        $html .= '<a href="/tcp/bibliothek" class="btn btn-sm btn-outline-secondary flex-fill">Übungsbibliothek</a>';
        $html .= '</div>';

        return [
            'id'      => 'panel-widget-therapy-care-pro',
            'title'   => 'TherapyCare Pro',
            'icon'    => '<svg width="14" height="14" fill="none" viewBox="0 0 24 24"><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
            'content' => $html,
            'col'     => 'col-xl-4 col-lg-5 col-12',
        ];
    }

    /* ── Private: render progress tab HTML ── */
    private function renderProgressTab(array $patient, TherapyCareRepository $repo): string
    {
        try {
            $view      = Application::getInstance()->getContainer()->get(View::class);
            $patientId = (int)$patient['id'];
            $latest    = $repo->getLatestProgressForPatient($patientId);
            $cats      = $repo->getActiveProgressCategories();
            $visibility = $repo->getPortalVisibility($patientId);
            $natural    = $repo->getNaturalEntriesForPatient($patientId);
            $naturalTypes = $repo->getNaturalTherapyTypesByCategory();
            $reports    = $repo->getTherapyReportsForPatient($patientId);

            return $view->fetch('@therapy-care-pro/patient_tab_progress.twig', [
                'patient'    => $patient,
                'latest'     => $latest,
                'categories' => $cats,
                'visibility' => $visibility,
                'natural'    => $natural,
                'types'      => $naturalTypes,
                'reports'    => $reports,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return '<div class="p-3 text-muted">Fortschrittsdaten nicht verfügbar.</div>';
        }
    }

    /* ── Private: render natural therapy tab HTML ── */
    private function renderNaturalTab(array $patient, TherapyCareRepository $repo): string
    {
        try {
            $view      = Application::getInstance()->getContainer()->get(View::class);
            $patientId = (int)$patient['id'];
            $entries   = $repo->getNaturalEntriesForPatient($patientId);
            $types     = $repo->getNaturalTherapyTypesByCategory();

            return $view->fetch('@therapy-care-pro/patient_tab_natural.twig', [
                'patient'    => $patient,
                'entries'    => $entries,
                'types'      => $types,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return '<div class="p-3 text-muted">Naturheilkundliche Daten nicht verfügbar.</div>';
        }
    }

    /* ── Publish static assets to public/plugin-assets/ ── */
    private function publishAssets(): void
    {
        $src = __DIR__ . '/templates/therapy-care-pro.css';
        $dst = defined('PUBLIC_PATH') ? PUBLIC_PATH : (defined('ROOT_PATH') ? ROOT_PATH . '/public' : '');
        if (!$dst) return;
        $destDir  = $dst . '/plugin-assets/therapy-care-pro';
        $destFile = $destDir . '/therapy-care-pro.css';
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        if (file_exists($src) && (!file_exists($destFile) || filemtime($src) > filemtime($destFile))) {
            @copy($src, $destFile);
        }
    }

    /* ── Run plugin-own SQL migrations ── */
    private function runMigrations(): void
    {
        try {
            $db     = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $prefix = $db->getPrefix();

            /* Self-heal: check ALL expected tcp_* tables in a single information_schema query.
               If any is missing, run migrations (CREATE TABLE IF NOT EXISTS + INSERT IGNORE
               makes re-running idempotent and safe). This ensures older installations
               automatically receive new tables added in later plugin versions. */
            $expectedTables = [
                'tcp_progress_categories',
                'tcp_progress_entries',
                'tcp_exercise_feedback',
                'tcp_reminder_templates',
                'tcp_reminder_queue',
                'tcp_reminder_logs',
                'tcp_therapy_reports',
                'tcp_exercise_library',
                'tcp_natural_therapy_types',
                'tcp_natural_therapy_entries',
                'tcp_timeline_meta',
                'tcp_portal_visibility',
            ];

            try {
                $existing = $db->fetchAll(
                    "SELECT TABLE_NAME FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE ?",
                    [$prefix . 'tcp_%']
                );
                $existingSet = [];
                foreach ($existing as $row) {
                    $name = $row['TABLE_NAME'] ?? $row['table_name'] ?? null;
                    if ($name !== null) {
                        $existingSet[$name] = true;
                    }
                }

                $missing = false;
                foreach ($expectedTables as $t) {
                    if (!isset($existingSet[$prefix . $t])) {
                        $missing = true;
                        error_log('[TherapyCare Pro self-heal] missing table: ' . $prefix . $t);
                        break;
                    }
                }
                if (!$missing) {
                    return; /* All good — fast path */
                }
            } catch (\Throwable $e) {
                /* information_schema query failed for some reason — fall through to full migration run */
                error_log('[TherapyCare Pro self-heal check] ' . $e->getMessage());
            }

            $migrationDir = __DIR__ . '/migrations';
            if (!is_dir($migrationDir)) return;

            $files = glob($migrationDir . '/*.sql');
            if (!$files) return;
            sort($files);

            foreach ($files as $file) {
                $sql = file_get_contents($file);

                /* Replace {{PREFIX}} placeholder with tenant prefix.
                   Affects BOTH table names AND FK-constraint names
                   so multi-tenant installs never collide on constraint names. */
                $sql = str_replace('{{PREFIX}}', $prefix, $sql);

                $statements = $this->splitSql($sql);
                foreach ($statements as $stmt) {
                    try {
                        $db->getPdo()->exec($stmt);
                    } catch (\Throwable $e) {
                        $msg = $e->getMessage();
                        /* Suppress only "already exists" — duplicate/FK-name errors
                           must now be visible because constraint names are prefixed
                           per tenant and should never collide. */
                        if (stripos($msg, 'already exists') === false) {
                            error_log('[TherapyCare Pro migration] ' . $msg . ' — SQL: ' . substr($stmt, 0, 200));
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[TherapyCare Pro runMigrations] ' . $e->getMessage());
        }
    }

    /**
     * Split a SQL file into individual statements, correctly handling:
     * - Single-line comments (--)
     * - Multi-line comments (/* ... *\/)
     * - Single-quoted string literals (with escaped quotes)
     */
    private function splitSql(string $sql): array
    {
        $statements = [];
        $current    = '';
        $len        = strlen($sql);
        $i          = 0;

        while ($i < $len) {
            $ch = $sql[$i];

            /* Single-line comment: skip to end of line */
            if ($ch === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
                $end = strpos($sql, "\n", $i);
                $i   = $end === false ? $len : $end + 1;
                continue;
            }

            /* Multi-line comment: skip to closing */
            if ($ch === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i   = $end === false ? $len : $end + 2;
                continue;
            }

            /* Quoted string: copy verbatim including the closing quote */
            if ($ch === "'") {
                $current .= $ch;
                $i++;
                while ($i < $len) {
                    $qch = $sql[$i];
                    $current .= $qch;
                    $i++;
                    if ($qch === '\\') {
                        /* escaped character — copy next char too */
                        if ($i < $len) { $current .= $sql[$i]; $i++; }
                        continue;
                    }
                    if ($qch === "'") {
                        /* check for doubled quote (SQL escape) */
                        if (isset($sql[$i]) && $sql[$i] === "'") {
                            $current .= $sql[$i]; $i++;
                            continue;
                        }
                        break;
                    }
                }
                continue;
            }

            /* Statement terminator */
            if ($ch === ';') {
                $stmt = trim($current);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
                $i++;
                continue;
            }

            $current .= $ch;
            $i++;
        }

        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }
}
