<?php
require_once 'vendor/autoload.php';
$app = require_once 'app/bootstrap.php';
$container = $app->getContainer();

try {
    $repo = $container->get(\App\Repositories\HomeworkRepository::class);
    $templates = $repo->findAllTemplates();
    echo 'Templates found: ' . count($templates) . PHP_EOL;
    
    if (empty($templates)) {
        echo 'No templates found. Checking database...' . PHP_EOL;
        
        $db = $container->get(\App\Core\Database::class);
        $result = $db->fetch("SHOW TABLES LIKE 'homework_templates'");
        if (!$result) {
            echo 'homework_templates table does not exist!' . PHP_EOL;
        } else {
            $count = $db->fetch("SELECT COUNT(*) as count FROM homework_templates");
            echo 'Rows in homework_templates: ' . $count['count'] . PHP_EOL;
        }
    } else {
        echo 'First template: ' . print_r($templates[0], true) . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    echo 'Trace: ' . $e->getTraceAsString() . PHP_EOL;
}
