<?php

declare(strict_types=1);

use Saas\Controllers\AuthController;
use Saas\Controllers\DashboardController;
use Saas\Controllers\TenantController;
use Saas\Controllers\PlansController;
use Saas\Controllers\LegalController;
use Saas\Controllers\LicenseApiController;
use Saas\Controllers\SaasInvoiceController;
use Saas\Controllers\SettingsController;
use Saas\Controllers\NotificationController;
use Saas\Controllers\UpdateController;
use Saas\Controllers\DataMigrationController;
use Saas\Controllers\FeedbackController;
use Saas\Controllers\PaymentSettingsController;

// ── License API (called by Praxissoftware) ─────────────────────────────────
$router->post('/api/license/verify',  [LicenseApiController::class, 'verify']);
$router->get('/api/license/check',    [LicenseApiController::class, 'check']);
$router->post('/api/license/token',   [LicenseApiController::class, 'token']);

// ── Auth ───────────────────────────────────────────────────────────────────
$router->get('/admin/login',  [AuthController::class, 'loginForm']);
$router->post('/admin/login', [AuthController::class, 'login']);
$router->get('/admin/logout', [AuthController::class, 'logout']);

// ── Admin Dashboard ────────────────────────────────────────────────────────
$router->get('/admin',        [DashboardController::class, 'index']);
$router->get('/admin/',       [DashboardController::class, 'index']);

// ── Tenant Management ──────────────────────────────────────────────────────
$router->get('/admin/tenants',                  [TenantController::class, 'index']);
$router->get('/admin/tenants/create',           [TenantController::class, 'createForm']);
$router->post('/admin/tenants/create',          [TenantController::class, 'create']);
$router->get('/admin/tenants/{id}',             [TenantController::class, 'show']);
$router->get('/admin/tenants/{id}/edit',        [TenantController::class, 'editForm']);
$router->post('/admin/tenants/{id}/edit',       [TenantController::class, 'edit']);
$router->post('/admin/tenants/{id}/suspend',    [TenantController::class, 'suspend']);
$router->post('/admin/tenants/{id}/activate',   [TenantController::class, 'activate']);
$router->post('/admin/tenants/{id}/reactivate', [TenantController::class, 'reactivate']);
$router->post('/admin/tenants/{id}/cancel',     [TenantController::class, 'cancel']);
$router->post('/admin/tenants/{id}/license',    [TenantController::class, 'issueLicense']);
$router->post('/admin/tenants/{id}/set-trial',  [TenantController::class, 'setTrial']);
$router->post('/admin/tenants/{id}/delete',     [TenantController::class, 'delete']);

// ── Plans Management ───────────────────────────────────────────────────────
$router->get('/admin/plans',            [PlansController::class, 'index']);
$router->get('/admin/plans/{id}/edit',  [PlansController::class, 'edit']);
$router->post('/admin/plans/{id}/edit', [PlansController::class, 'update']);

// ── Legal Documents Management ─────────────────────────────────────────────
$router->get('/admin/legal',            [LegalController::class, 'index']);
$router->get('/admin/legal/{id}/edit',  [LegalController::class, 'edit']);
$router->post('/admin/legal/{id}/edit', [LegalController::class, 'update']);

// ── SaaS Rechnungsverwaltung ────────────────────────────────────────────────
$router->get('/admin/invoices',                         [SaasInvoiceController::class, 'index']);
$router->get('/admin/invoices/create',                  [SaasInvoiceController::class, 'create']);
$router->post('/admin/invoices',                        [SaasInvoiceController::class, 'store']);
$router->get('/admin/invoices/tax-export',              [SaasInvoiceController::class, 'taxExport']);
$router->get('/admin/invoices/{id}',                    [SaasInvoiceController::class, 'show']);
$router->get('/admin/invoices/{id}/edit',               [SaasInvoiceController::class, 'edit']);
$router->post('/admin/invoices/{id}/edit',              [SaasInvoiceController::class, 'update']);
$router->post('/admin/invoices/{id}/delete',            [SaasInvoiceController::class, 'delete']);
$router->post('/admin/invoices/{id}/status',            [SaasInvoiceController::class, 'updateStatus']);
$router->post('/admin/invoices/{id}/send-email',        [SaasInvoiceController::class, 'sendEmail']);
$router->post('/admin/invoices/{id}/finalize',          [SaasInvoiceController::class, 'finalize']);
$router->get('/admin/invoices/{id}/pdf',                [SaasInvoiceController::class, 'downloadPdf']);

// ── Einstellungen ──────────────────────────────────────────────────────────
$router->get('/admin/settings',            [SettingsController::class, 'index']);
$router->post('/admin/settings',           [SettingsController::class, 'update']);
$router->post('/admin/settings/test-mail', [SettingsController::class, 'testMail']);

// ── Benachrichtigungen ─────────────────────────────────────────────────────
$router->get('/admin/notifications',                          [NotificationController::class, 'index']);
$router->get('/admin/notifications/api/unread',               [NotificationController::class, 'apiUnread']);
$router->get('/admin/notifications/activity-log',             [NotificationController::class, 'activityLog']);
$router->post('/admin/notifications/mark-all-read',           [NotificationController::class, 'markAllRead']);
$router->post('/admin/notifications/delete-read',             [NotificationController::class, 'deleteRead']);
$router->post('/admin/notifications/{id}/read',               [NotificationController::class, 'markRead']);
$router->post('/admin/notifications/{id}/delete',             [NotificationController::class, 'delete']);

// ── Updates & Versionsverwaltung ───────────────────────────────────────────
$router->get('/admin/updates',             [UpdateController::class, 'index']);
$router->get('/admin/updates/check',       [UpdateController::class, 'checkUpdate']);
$router->get('/admin/updates/changelog',   [UpdateController::class, 'changelog']);
$router->get('/admin/updates/system-info', [UpdateController::class, 'systemInfo']);
$router->post('/admin/updates/apply',      [UpdateController::class, 'applyUpdate']);



// ── Daten-Import / Migration ─────────────────────────────────────────────
$router->get('/admin/migration',      [DataMigrationController::class, 'index']);
$router->post('/admin/migration/run', [DataMigrationController::class, 'run']);
$router->post('/admin/migration/patch-all', [DataMigrationController::class, 'patchAll']);
$router->post('/admin/migration/migrate-all', [DataMigrationController::class, 'migrateAll']);

// ── Payment Settings (Admin) ────────────────────────────────────────────────
$router->get('/admin/payment-settings',              [PaymentSettingsController::class, 'index']);
$router->post('/admin/payment-settings/update',      [PaymentSettingsController::class, 'update']);
$router->get('/admin/payment-settings/test/stripe',  [PaymentSettingsController::class, 'testStripe']);
$router->get('/admin/payment-settings/test/paypal',  [PaymentSettingsController::class, 'testPayPal']);
$router->post('/admin/payment-settings/cron/run',    [PaymentSettingsController::class, 'runCron']);

// ── Payment Webhooks + Callbacks ────────────────────────────────────────────
$router->post('/webhooks/stripe', function (array $params): void {
    $app     = \Saas\Core\Application::getInstance();
    $payment = $app->getContainer()->get(\Saas\Services\PaymentService::class);
    $payload = file_get_contents('php://input') ?: '';
    $sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $ok      = $payment->handleStripeWebhook($payload, $sig);
    http_response_code($ok ? 200 : 400);
    echo json_encode(['ok' => $ok]);
});

$router->get('/register/payment-success', function (array $params): void {
    $app     = \Saas\Core\Application::getInstance();
    $c       = $app->getContainer();
    $payment = $c->get(\Saas\Services\PaymentService::class);
    $view    = $c->get(\Saas\Core\View::class);

    // Stripe session_id
    $sessionId    = $_GET['session_id'] ?? null;
    $tenantId     = (int)($_GET['tenant_id'] ?? 0);
    $ppSubId      = $_GET['subscription_id'] ?? null;

    if ($sessionId) {
        $tenantId = $payment->handleStripeCheckoutSuccess($sessionId);
    } elseif ($ppSubId && $tenantId) {
        $payment->handlePayPalReturn($tenantId, $ppSubId);
    }

    $view->render('register/payment_success.twig', ['page_title' => 'Zahlung erfolgreich']);
});

// ── Feedback ────────────────────────────────────────────────────────────────
$router->get('/admin/feedback',                    [FeedbackController::class, 'index']);
$router->get('/admin/feedback/{id}',               [FeedbackController::class, 'show']);
$router->post('/admin/feedback/{id}/delete',       [FeedbackController::class, 'delete']);
$router->post('/admin/feedback/mark-all-read',     [FeedbackController::class, 'markAllRead']);

// ── Public Feedback API (called by TierPhysio App) ──────────────────────────
$router->post('/api/feedback',                     [FeedbackController::class, 'apiSubmit']);

// ── Root redirect ──────────────────────────────────────────────────────────
$router->get('/admin/dashboard', function (array $params): void {
    header('Location: /admin');
    exit;
});

// ── Pending Migrations (run once after deploy, then remove) ─────────────────
$router->get('/admin/migrate', function (array $params): void {
    $app      = \Saas\Core\Application::getInstance();
    $c        = $app->getContainer();
    $session  = $c->get(\Saas\Core\Session::class);
    if (!$session->has('saas_user_id')) {
        header('Location: /admin/login'); exit;
    }
    $rootPath = $app->getRootPath();
    $db       = $c->get(\Saas\Core\Database::class);
    $pdo      = $db->getPdo();

    $files   = glob($rootPath . '/migrations/*.sql') ?: [];
    sort($files);
    $results = [];
    foreach ($files as $file) {
        $name = basename($file);
        $sql  = file_get_contents($file);
        $stmts = array_filter(array_map('trim', explode(';', $sql)));
        $errors = [];
        foreach ($stmts as $stmt) {
            if ($stmt === '') continue;
            try { $pdo->exec($stmt); }
            catch (\Throwable $e) { $errors[] = $e->getMessage(); }
        }
        $results[$name] = empty($errors) ? 'OK' : $errors;
    }

    header('Content-Type: text/plain; charset=utf-8');
    foreach ($results as $file => $result) {
        if ($result === 'OK') {
            echo "[OK]   {$file}\n";
        } else {
            echo "[WARN] {$file}\n";
            foreach ($result as $err) {
                echo "       → {$err}\n";
            }
        }
    }
    echo "\nFertig. Diese Route kann jetzt aus web.php entfernt werden.\n";
    exit;
});
