<?php

declare(strict_types=1);

namespace Plugins\Homework;

use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Core\Application;
use App\Controllers\HomeworkController;
use App\Repositories\HomeworkRepository;

class ServiceProvider
{
    public function register(PluginManager $pluginManager): void
    {
        // Dependencies registrieren
        $container = Application::getInstance()->getContainer();
        $container->singleton(HomeworkRepository::class, fn() => new HomeworkRepository($container->get('App\Core\Database')));
        $container->singleton(HomeworkController::class, fn() => new HomeworkController(
            $container->get(HomeworkRepository::class),
            $container->get('App\Repositories\PatientRepository')
        ));

        // Template-Pfad hinzufügen
        $view = Application::getInstance()->getContainer()->get(View::class);
        $view->addTemplatePath(__DIR__ . '/templates', 'homework');

        // Hooks registrieren
        $pluginManager->hook('registerRoutes', [$this, 'registerRoutes']);
        $pluginManager->hook('patientDetailTabs', [$this, 'addPatientDetailTab']);
    }

    public function registerRoutes(Router $router): void
    {
        // Hausaufgaben-API-Routes
        $router->get('/api/homework/templates', [HomeworkController::class, 'getTemplates'], ['auth']);
        $router->get('/api/patients/{patient_id}/homework', [HomeworkController::class, 'getPatientHomework'], ['auth']);
        $router->post('/api/patients/{patient_id}/homework', [HomeworkController::class, 'createPatientHomework'], ['auth']);
        $router->delete('/api/patients/{patient_id}/homework/{homework_id}', [HomeworkController::class, 'deletePatientHomework'], ['auth']);
    }

    public function addPatientDetailTab(array $context): array
    {
        $context['tabs'][] = [
            'id' => 'hausaufgaben',
            'title' => 'Hausaufgaben',
            'icon' => '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" style="margin-right:4px;vertical-align:text-bottom;"><path stroke="currentColor" stroke-width="2" d="M9 11l3 3L22 4"/><path stroke="currentColor" stroke-width="2" d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
            'content' => $this->renderHomeworkTab($context['patient'] ?? null),
            'active' => false
        ];

        return $context;
    }

    private function renderHomeworkTab($patient): string
    {
        if (!$patient) {
            return '<div class="text-center py-4" style="color:var(--text-muted);">Patient nicht gefunden</div>';
        }

        $view = Application::getInstance()->getContainer()->get(View::class);
        return $view->render('@homework/patient-tab.twig', [
            'patient' => $patient,
            'csrf_token' => $_SESSION['csrf_token'] ?? ''
        ]);
    }
}
