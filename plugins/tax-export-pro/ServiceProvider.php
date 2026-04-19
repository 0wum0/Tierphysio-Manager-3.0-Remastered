<?php

declare(strict_types=1);

namespace Plugins\TaxExportPro;

use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Core\Application;

class ServiceProvider
{
    public function register(PluginManager $pluginManager): void
    {
        require_once __DIR__ . '/TaxExportRepository.php';
        require_once __DIR__ . '/TaxExportService.php';
        require_once __DIR__ . '/TaxExportController.php';
        require_once __DIR__ . '/GobdAuditService.php';

        $this->runMigrations();

        /* Register plugin template path under @tax-export-pro namespace */
        $view = Application::getInstance()->getContainer()->get(View::class);
        $view->addTemplatePath(__DIR__ . '/templates', 'tax-export-pro');

        /* Register routes */
        $pluginManager->hook('registerRoutes', [$this, 'registerRoutes']);

        /* Nav item */
        $navItems = $view->getTwig()->getGlobals()['plugin_nav_items'] ?? [];
        $navItems[] = [
            'label'   => 'Steuerexport',
            'href'    => '/steuerexport',
            'feature' => 'tax_export',
            'icon'    => '<svg width="18" height="18" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="16" y1="17" x2="8" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="10 9 9 9 8 9"/></svg>',
        ];
        $view->addGlobal('plugin_nav_items', $navItems);

        /* invoiceTabs hook — adds a "Steuerexport" tab link to the invoice index */
        $pluginManager->hook('invoiceTabs', [$this, 'invoiceTab']);

        /* GoBD: Audit-Log hooks — listen to invoice mutations fired from InvoiceController */
        $pluginManager->hook('invoice.updated', function(array $payload): void {
            try {
                $db      = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
                $audit   = new GobdAuditService($db);
                $audit->log(
                    (int)($payload['invoice_id']     ?? 0),
                    (string)($payload['invoice_number'] ?? ''),
                    'updated',
                    $payload['old_values'] ?? null,
                    $payload['new_values'] ?? null,
                    (int)($payload['user_id'] ?? 0) ?: null
                );
            } catch (\Throwable) {}
        });

        $pluginManager->hook('invoice.deleted', function(array $payload): void {
            try {
                $db    = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
                $audit = new GobdAuditService($db);
                $audit->log(
                    (int)($payload['invoice_id']     ?? 0),
                    (string)($payload['invoice_number'] ?? ''),
                    'deleted',
                    $payload['old_values'] ?? null,
                    null,
                    (int)($payload['user_id'] ?? 0) ?: null
                );
            } catch (\Throwable) {}
        });
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/steuerexport',                          [TaxExportController::class, 'index'],        ['admin']);
        $router->get('/steuerexport/export-csv',               [TaxExportController::class, 'exportCsv'],    ['admin']);
        $router->get('/steuerexport/export-zip',               [TaxExportController::class, 'exportZip'],    ['admin']);
        $router->get('/steuerexport/export-pdf',               [TaxExportController::class, 'exportPdf'],    ['admin']);
        $router->get('/steuerexport/export-datev',             [TaxExportController::class, 'exportDatev'],  ['admin']);
        $router->post('/steuerexport/settings',                [TaxExportController::class, 'saveSettings'], ['admin']);
        $router->post('/steuerexport/{id}/stornieren',         [TaxExportController::class, 'cancel'],       ['admin']);
        $router->post('/steuerexport/{id}/finalisieren',       [TaxExportController::class, 'finalize'],     ['admin']);
        $router->get('/steuerexport/audit-log',                [TaxExportController::class, 'auditLog'],     ['admin']);
        $router->get('/steuerexport/audit-log/{id}',           [TaxExportController::class, 'auditLogInvoice'], ['admin']);
    }

    public function invoiceTab(): array
    {
        return [
            'label' => 'Steuerexport',
            'href'  => '/steuerexport',
            'icon'  => '<svg width="14" height="14" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline stroke="currentColor" stroke-width="2" points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="16" y1="17" x2="8" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        ];
    }

    private function runMigrations(): void
    {
        try {
            $db = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
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
                            /* Table/column already exists — skip */
                        }
                    }
                }
            }
        } catch (\Throwable) {
            /* DB not available yet (installer phase) */
        }
    }
}
