<?php

declare(strict_types=1);

use App\Core\Application;

// Bootstrap die Anwendung
$app = new Application(dirname(__DIR__));

// Container für Dependency Injection
$container = $app->getContainer();

// Für Migrationen direkt die Datenbank zurückgeben
return $container->get('App\Core\Database');
