<?php

declare(strict_types=1);

namespace Plugins\LicenseGuard;

use App\Core\Application;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;

class ServiceProvider
{
    public function register(PluginManager $pluginManager): void
    {
        require_once __DIR__ . '/Plugin.php';
        require_once __DIR__ . '/LicenseSetupController.php';

        $container = Application::getInstance()->getContainer();

        // Register plugin template path
        $view = $container->get(View::class);
        $view->addTemplatePath(__DIR__ . '/templates');

        // Run the license check immediately at boot
        $plugin = new Plugin();
        $plugin->boot($container);

        // Store instance in container so controllers can call hasFeature()
        $container->instance(Plugin::class, $plugin);

        // Register routes (license setup page)
        $pluginManager->hook('registerRoutes', [$this, 'registerRoutes']);

        // Add nav item for settings area
        $navItems = $view->getTwig()->getGlobals()['plugin_nav_items'] ?? [];
        $navItems[] = [
            'label' => 'Lizenz',
            'href'  => '/license-setup',
            'icon'  => '<svg width="16" height="16" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];
        $view->addGlobal('plugin_nav_items', $navItems);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/license-setup',  [LicenseSetupController::class, 'index'],  ['auth']);
        $router->post('/license-setup', [LicenseSetupController::class, 'save'],   ['auth']);
    }
}
