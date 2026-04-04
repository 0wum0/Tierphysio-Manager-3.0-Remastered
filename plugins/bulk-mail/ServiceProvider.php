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
        require_once __DIR__ . '/HolidayMailService.php';
        require_once __DIR__ . '/HolidayController.php';

        $this->runMigrations();

        $view = Application::getInstance()->getContainer()->get(View::class);
        $view->addTemplatePath(__DIR__ . '/templates', 'bulk-mail');

        $pluginManager->hook('registerRoutes', [$this, 'registerRoutes']);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get( '/bulk-mail',                                [BulkMailController::class,  'index'],      ['auth']);
        $router->post('/bulk-mail/vorschau',                       [BulkMailController::class,  'preview'],    ['auth']);
        $router->post('/bulk-mail/senden-email',                   [BulkMailController::class,  'sendEmail'],  ['auth']);
        $router->post('/bulk-mail/senden-portal',                  [BulkMailController::class,  'sendPortal'], ['auth']);

        $router->get( '/bulk-mail/feiertagsgruesse',               [HolidayController::class,   'index'],      ['auth']);
        $router->post('/bulk-mail/feiertagsgruesse/speichern',     [HolidayController::class,   'save'],       ['auth']);
        $router->post('/bulk-mail/feiertagsgruesse/vorschau',      [HolidayController::class,   'preview'],    ['auth']);
        $router->post('/bulk-mail/feiertagsgruesse/jetzt-senden',  [HolidayController::class,   'sendNow'],    ['auth']);
        $router->get( '/api/holiday-cron',                         [HolidayController::class,   'cron'],       []);
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
