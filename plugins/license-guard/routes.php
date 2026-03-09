<?php

declare(strict_types=1);

use Plugins\LicenseGuard\LicenseSetupController;

$router->get('/license-setup',  [LicenseSetupController::class, 'index']);
$router->post('/license-setup', [LicenseSetupController::class, 'save']);
