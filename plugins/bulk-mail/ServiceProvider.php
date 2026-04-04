<?php
declare(strict_types=1);
namespace Plugins\BulkMail;

use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Core\Application;

class ServiceProvider
{
    public function register(PluginManager $pluginManager): void
    {
        require_once __DIR__ . '/BulkMailController.php';

        $this->runMigrations();

        $view = Application::getInstance()->getContainer()->get(View::class);
        $view->addTemplatePath(__DIR__ . '/templates', 'bulk-mail');

        $pluginManager->hook('registerRoutes', [$this, 'registerRoutes']);
        $pluginManager->hook('navItems',       [$this, 'navItem']);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get( '/bulk-mail',               [BulkMailController::class, 'index'],       ['auth']);
        $router->post('/bulk-mail/vorschau',       [BulkMailController::class, 'preview'],     ['auth']);
        $router->post('/bulk-mail/senden-email',   [BulkMailController::class, 'sendEmail'],   ['auth']);
        $router->post('/bulk-mail/senden-portal',  [BulkMailController::class, 'sendPortal'],  ['auth']);
    }

    public function navItem(array $context): array
    {
        return [
            'label' => 'Massen-Kommunikation',
            'href'  => '/bulk-mail',
            'icon'  => '<svg width="16" height="16" fill="none" viewBox="0 0 24 24"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><polyline points="22,6 12,13 2,6" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
        ];
    }

    private function runMigrations(): void
    {
        try {
            $db  = Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $sql = "CREATE TABLE IF NOT EXISTS bulk_mail_log (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type            ENUM('email','portal') NOT NULL DEFAULT 'email',
                subject         VARCHAR(255) NOT NULL,
                recipient_group VARCHAR(50)  NOT NULL DEFAULT 'all',
                sent_count      INT UNSIGNED NOT NULL DEFAULT 0,
                failed_count    INT UNSIGNED NOT NULL DEFAULT 0,
                sent_by         INT UNSIGNED NULL,
                created_at      DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $db->execute($sql);
        } catch (\Throwable) {}
    }
}
