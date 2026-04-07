<?php

declare(strict_types=1);

use Saas\Controllers\TenantAuthController;
use Saas\Controllers\RegistrationController;
use Saas\Controllers\LegalController;
use Saas\Controllers\DemoController;

// ── Landing Page ────────────────────────────────────────────────────────────
$router->get('/', [TenantAuthController::class, 'landing']);

// ── Demo ─────────────────────────────────────────────────────────────────────
$router->get('/demo', [DemoController::class, 'index']);

// ── Tenant Auth ─────────────────────────────────────────────────────────────
$router->get('/login',  [TenantAuthController::class, 'loginForm']);
$router->post('/login', [TenantAuthController::class, 'login']);
$router->get('/logout', [TenantAuthController::class, 'logout']);

// ── Password Reset ───────────────────────────────────────────────────────────
$router->get('/forgot-password',  [TenantAuthController::class, 'forgotForm']);
$router->post('/forgot-password', [TenantAuthController::class, 'forgotSubmit']);
$router->get('/reset-password',   [TenantAuthController::class, 'resetForm']);
$router->post('/reset-password',  [TenantAuthController::class, 'resetSubmit']);

// ── TID Availability Check (AJAX) ────────────────────────────────────────────
$router->get('/check-tid',  [TenantAuthController::class, 'checkTid']);
$router->post('/check-tid', [TenantAuthController::class, 'checkTid']);

// ── Public Registration ──────────────────────────────────────────────────────
$router->get('/register',        [RegistrationController::class, 'index']);
$router->get('/register/{plan}', [RegistrationController::class, 'form']);
$router->post('/register',       [RegistrationController::class, 'submit']);

// ── Legal / Impressum ────────────────────────────────────────────────────────
$router->get('/legal/{slug}', [LegalController::class, 'view']);
$router->get('/impressum',    [LegalController::class, 'impressum']);
$router->get('/datenschutz',  [LegalController::class, 'datenschutz']);
