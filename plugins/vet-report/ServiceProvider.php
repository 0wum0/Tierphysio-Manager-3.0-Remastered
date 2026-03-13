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

        $pluginManager->hook('registerRoutes',        [$this, 'registerRoutes']);
        $pluginManager->hook('patientHeaderActions',  [$this, 'patientHeaderAction']);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get(   '/patienten/{id}/tierarztbericht',                          [VetReportController::class, 'generate'], ['auth']);
        $router->get(   '/patienten/{id}/tierarztbericht/verlauf',                  [VetReportController::class, 'history'],  ['auth']);
        $router->get(   '/patienten/{id}/tierarztbericht/{reportId}/download',      [VetReportController::class, 'download'], ['auth']);
        $router->delete('/patienten/{id}/tierarztbericht/{reportId}',               [VetReportController::class, 'delete'],   ['auth']);
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

        return '<a href="/patienten/' . $patientId . '/tierarztbericht" target="_blank" class="btn btn-secondary btn-sm">'
             . $paw
             . '<span class="d-none d-md-inline ms-1">Tierarztbericht erstellen</span>'
             . '</a>';
    }
}
