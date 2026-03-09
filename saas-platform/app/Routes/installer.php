<?php

declare(strict_types=1);

use Saas\Controllers\InstallerController;

$router->get('/',           [InstallerController::class, 'index']);
$router->get('/install',    [InstallerController::class, 'index']);
$router->post('/install',   [InstallerController::class, 'run']);
