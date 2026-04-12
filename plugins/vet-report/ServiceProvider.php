<?php

declare(strict_types=1);

namespace Plugins\VetReport;

use App\Core\PluginManager;
use App\Core\Router;

class ServiceProvider
{
    public function register(PluginManager $pluginManager): void
    {
        require_once __DIR__ . '/VetReportService.php';
        require_once __DIR__ . '/VetReportController.php';

        $this->runMigrations();

        $pluginManager->hook('registerRoutes',        [$this, 'registerRoutes']);
        $pluginManager->hook('patientHeaderActions',  [$this, 'patientHeaderAction']);
    }

    private function runMigrations(): void
    {
        try {
            $db    = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $table = $db->prefix('vet_reports');

            $columns = [
                "ALTER TABLE `{$table}` ADD COLUMN `type`      ENUM('auto','custom') NOT NULL DEFAULT 'auto' AFTER `created_by`",
                "ALTER TABLE `{$table}` ADD COLUMN `title`     VARCHAR(255) NULL AFTER `type`",
                "ALTER TABLE `{$table}` ADD COLUMN `content`   TEXT NULL AFTER `title`",
                "ALTER TABLE `{$table}` ADD COLUMN `recipient` VARCHAR(500) NULL",
            ];

            foreach ($columns as $sql) {
                try {
                    $db->execute($sql);
                } catch (\Throwable $e) {
                    /* 1060 = column already exists, 1146 = table missing → both are fine */
                    $errno = ($e instanceof \PDOException && isset($e->errorInfo[1])) ? (int)$e->errorInfo[1] : 0;
                    if (!in_array($errno, [1060, 1146], true)) {
                        error_log('[VetReport runMigrations] ' . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[VetReport runMigrations] ' . $e->getMessage());
        }
    }

    public function registerRoutes(Router $router): void
    {
        $router->get(   '/patienten/{id}/tierarztbericht',                          [VetReportController::class, 'generate'], ['auth']);
        $router->post(  '/patienten/{id}/tierarztbericht/custom',                   [VetReportController::class, 'createCustom'], ['auth']);
        $router->get(   '/patienten/{id}/tierarztbericht/verlauf',                  [VetReportController::class, 'history'],  ['auth']);
        $router->get(   '/patienten/{id}/tierarztbericht/{reportId}/download',      [VetReportController::class, 'download'], ['auth']);
        $router->delete('/patienten/{id}/tierarztbericht/{reportId}',               [VetReportController::class, 'delete'],   ['auth']);
        $router->post(  '/patienten/{id}/tierarztbericht/{reportId}/email',         [VetReportController::class, 'sendEmail'], ['auth']);
    }

    public function patientHeaderAction(array $context): string
    {
        $patientId = $context['patient']['id'] ?? 0;
        if (!$patientId) return '';

        /* Paw print SVG — 4 toe pads + main pad */
        $paw = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;">'
             . '<ellipse cx="6"  cy="5.5" rx="2"   ry="2.5"/>'
             . '<ellipse cx="12" cy="4"   rx="2"   ry="2.5"/>'
             . '<ellipse cx="18" cy="5.5" rx="2"   ry="2.5"/>'
             . '<ellipse cx="3.5" cy="11" rx="1.7" ry="2.2"/>'
             . '<path d="M12 9c-3.5 0-7 2.5-7 5.5 0 2 1.2 3.5 3 4 .8.2 1.6.5 2.5.5h3c.9 0 1.7-.3 2.5-.5 1.8-.5 3-2 3-4C19 11.5 15.5 9 12 9z"/>'
             . '</svg>';

        return '<button type="button" class="btn btn-secondary btn-sm" onclick="openVetReportChoiceModal(' . $patientId . ')">'
             . $paw
             . '<span class="d-none d-md-inline ms-1">Tierarztbericht</span>'
             . '</button>';
    }
}
