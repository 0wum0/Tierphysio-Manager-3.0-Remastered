<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PatientController;
use App\Controllers\OwnerController;
use App\Controllers\InvoiceController;
use App\Controllers\SettingsController;
use App\Controllers\ProfileController;
use App\Controllers\UiSettingsController;
use App\Controllers\NotificationController;
use App\Controllers\CronController;
use App\Controllers\HomeworkController;
use App\Controllers\ReminderDunningController;

/** @var \App\Core\Router $router */

// Hausaufgaben Plan-Meta API
$router->get('/api/patients/{patient_id}/homework/plan-meta', [HomeworkController::class, 'getPlanMeta'], ['auth']);
$router->post('/api/patients/{patient_id}/homework/plan-meta', [HomeworkController::class, 'savePlanMeta'], ['auth']);

$router->get('/', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard/chart-data', [DashboardController::class, 'chartData'], ['auth']);
$router->post('/api/dashboard/layout', [DashboardController::class, 'saveLayout'], ['auth']);
$router->get('/api/dashboard/layout', [DashboardController::class, 'loadLayout'], ['auth']);

$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/profil', [ProfileController::class, 'show'], ['auth']);
$router->post('/profil', [ProfileController::class, 'update'], ['auth']);
$router->post('/profil/password', [ProfileController::class, 'updatePassword'], ['auth']);

$router->get('/patienten', [PatientController::class, 'index'], ['auth']);
$router->get('/patienten/neu', [PatientController::class, 'wizard'], ['auth']);
$router->post('/patienten/wizard', [PatientController::class, 'wizardStore'], ['auth']);
$router->get('/api/tierhalter/suche', [PatientController::class, 'ownerSearch'], ['auth']);
$router->get('/api/global-search', [PatientController::class, 'globalSearch'], ['auth']);
$router->post('/patienten', [PatientController::class, 'store'], ['auth']);
$router->get('/patienten/{id}', [PatientController::class, 'show'], ['auth']);
$router->get('/patienten/{id}/json', [PatientController::class, 'showJson'], ['auth']);
$router->post('/patienten/{id}', [PatientController::class, 'update'], ['auth']);
$router->post('/patienten/{id}/loeschen', [PatientController::class, 'delete'], ['auth']);
$router->post('/patienten/{id}/foto', [PatientController::class, 'uploadPhoto'], ['auth']);
$router->post('/patienten/{id}/timeline', [PatientController::class, 'addTimelineEntry'], ['auth']);
$router->post('/patienten/{id}/timeline-json', [PatientController::class, 'addTimelineEntryJson'], ['auth']);
$router->post('/patienten/{id}/attachment-upload', [PatientController::class, 'uploadAttachment'], ['auth']);
$router->post('/patienten/{id}/timeline/{entryId}/loeschen', [PatientController::class, 'deleteTimelineEntry'], ['auth']);
$router->post('/patienten/{id}/timeline/{entryId}/update-json', [PatientController::class, 'updateTimelineEntryJson'], ['auth']);
$router->post('/patienten/{id}/timeline/{entryId}/delete-json', [PatientController::class, 'deleteTimelineEntryJson'], ['auth']);
$router->get('/patienten/{id}/pdf', [PatientController::class, 'downloadPatientPdf'], ['auth']);
$router->get('/patienten/{id}/hausaufgaben/pdf', [PatientController::class, 'downloadHomeworkPdf'], ['auth']);
$router->post('/patienten/{id}/hausaufgaben/email', [PatientController::class, 'sendHomeworkEmail'], ['auth']);
$router->get('/patienten/{id}/dokumente/{file}', [PatientController::class, 'downloadDocument'], ['auth']);
$router->get('/patienten/{id}/foto/{file}', [PatientController::class, 'servePhoto'], ['auth']);
$router->post('/patienten/{id}/dokumente', [PatientController::class, 'uploadDocument'], ['auth']);

$router->get('/tierhalter', [OwnerController::class, 'index'], ['auth']);
$router->post('/tierhalter', [OwnerController::class, 'store'], ['auth']);
$router->get('/tierhalter/{id}', [OwnerController::class, 'show'], ['auth']);
$router->post('/tierhalter/{id}', [OwnerController::class, 'update'], ['auth']);
$router->post('/tierhalter/{id}/loeschen', [OwnerController::class, 'delete'], ['auth']);

$router->get('/rechnungen', [InvoiceController::class, 'index'], ['auth']);
$router->get('/rechnungen/erstellen', [InvoiceController::class, 'create'], ['auth']);
$router->post('/rechnungen', [InvoiceController::class, 'store'], ['auth']);
$router->get('/rechnungen/{id}', [InvoiceController::class, 'show'], ['auth']);
$router->get('/rechnungen/{id}/bearbeiten', [InvoiceController::class, 'edit'], ['auth']);
$router->post('/rechnungen/{id}', [InvoiceController::class, 'update'], ['auth']);
$router->post('/rechnungen/{id}/loeschen', [InvoiceController::class, 'delete'], ['auth']);
$router->post('/rechnungen/{id}/status', [InvoiceController::class, 'updateStatus'], ['auth']);
$router->get('/rechnungen/{id}/pdf', [InvoiceController::class, 'downloadPdf'], ['auth']);
$router->get('/rechnungen/{id}/vorschau', [InvoiceController::class, 'preview'], ['auth']);
$router->get('/rechnungen/{id}/positionen-json', [InvoiceController::class, 'positionsJson'], ['auth']);
$router->post('/rechnungen/{id}/senden', [InvoiceController::class, 'sendEmail'], ['auth']);
$router->get('/rechnungen/{id}/quittung', [InvoiceController::class, 'downloadReceipt'], ['auth']);
$router->post('/rechnungen/{id}/quittung-senden', [InvoiceController::class, 'sendReceiptEmail'], ['auth']);
$router->post('/rechnungen/{id}/status-inline', [InvoiceController::class, 'updateStatusInline'], ['auth']);

// ── Mahnwesen: Erinnerungen ──────────────────────────────────────────
$router->get('/mahnwesen/erinnerungen', [ReminderDunningController::class, 'reminderIndex'], ['auth']);
$router->post('/rechnungen/{id}/erinnerung', [ReminderDunningController::class, 'reminderStore'], ['auth']);
$router->post('/mahnwesen/erinnerungen/{id}/senden', [ReminderDunningController::class, 'reminderSend'], ['auth']);
$router->get('/mahnwesen/erinnerungen/{id}/pdf', [ReminderDunningController::class, 'reminderPdf'], ['auth']);
$router->post('/mahnwesen/erinnerungen/{id}/loeschen', [ReminderDunningController::class, 'reminderDelete'], ['auth']);

// ── Mahnwesen: Mahnungen ─────────────────────────────────────────────
$router->get('/mahnwesen/mahnungen', [ReminderDunningController::class, 'dunningIndex'], ['auth']);
$router->post('/rechnungen/{id}/mahnung', [ReminderDunningController::class, 'dunningStore'], ['auth']);
$router->post('/mahnwesen/mahnungen/{id}/senden', [ReminderDunningController::class, 'dunningSend'], ['auth']);
$router->get('/mahnwesen/mahnungen/{id}/pdf', [ReminderDunningController::class, 'dunningPdf'], ['auth']);
$router->post('/mahnwesen/mahnungen/{id}/loeschen', [ReminderDunningController::class, 'dunningDelete'], ['auth']);

// ── Mahnwesen: API ───────────────────────────────────────────────────
$router->get('/api/rechnungen/{id}/mahnwesen', [ReminderDunningController::class, 'apiInvoiceHistory'], ['auth']);
$router->get('/api/mahnwesen/alert', [ReminderDunningController::class, 'alertJson'], ['auth']);

$router->get('/einstellungen', [SettingsController::class, 'index'], ['admin']);
$router->post('/einstellungen', [SettingsController::class, 'update'], ['admin']);
$router->post('/einstellungen/logo', [SettingsController::class, 'uploadLogo'], ['admin']);
$router->post('/einstellungen/pdf-rechnung-bild', [SettingsController::class, 'uploadPdfRechnungBild'], ['admin']);
$router->post('/einstellungen/pdf-vielen-dank-bild', [SettingsController::class, 'uploadPdfVielenDankBild'], ['admin']);
$router->post('/einstellungen/pdf-quittung-bild', [SettingsController::class, 'uploadPdfQuittungBild'], ['admin']);
$router->post('/einstellungen/pdf-barzahlung-bild', [SettingsController::class, 'uploadPdfBarzahlungBild'], ['admin']);
$router->post('/einstellungen/pdf-erinnerung-bild', [SettingsController::class, 'uploadPdfErinnerungBild'], ['admin']);
$router->post('/einstellungen/pdf-mahnung-bild',    [SettingsController::class, 'uploadPdfMahnungBild'],    ['admin']);
$router->get('/einstellungen/plugins', [SettingsController::class, 'plugins'], ['admin']);
$router->post('/einstellungen/plugins/{name}/aktivieren', [SettingsController::class, 'enablePlugin'], ['admin']);
$router->post('/einstellungen/plugins/{name}/deaktivieren', [SettingsController::class, 'disablePlugin'], ['admin']);
$router->get('/einstellungen/updater', [SettingsController::class, 'updater'], ['admin']);
$router->post('/einstellungen/updater/run', [SettingsController::class, 'runMigrations'], ['admin']);
$router->post('/einstellungen/migrationen', [SettingsController::class, 'runMigrations'], ['admin']);
$router->get('/einstellungen/benutzer', [SettingsController::class, 'users'], ['admin']);
$router->post('/einstellungen/benutzer', [SettingsController::class, 'createUser'], ['admin']);
$router->post('/einstellungen/benutzer/{id}', [SettingsController::class, 'updateUser'], ['admin']);
$router->post('/einstellungen/benutzer/{id}/loeschen', [SettingsController::class, 'deleteUser'], ['admin']);

$router->post('/einstellungen/behandlungsarten', [SettingsController::class, 'createTreatmentType'], ['admin']);
$router->post('/einstellungen/behandlungsarten/{id}', [SettingsController::class, 'updateTreatmentType'], ['admin']);
$router->post('/einstellungen/behandlungsarten/{id}/loeschen', [SettingsController::class, 'deleteTreatmentType'], ['admin']);

// Hausaufgaben-Templates (Admin)
$router->post('/einstellungen/hausaufgaben-templates', [SettingsController::class, 'createHomeworkTemplate'], ['admin']);
$router->post('/einstellungen/hausaufgaben-templates/{id}', [SettingsController::class, 'updateHomeworkTemplate'], ['admin']);
$router->post('/einstellungen/hausaufgaben-templates/{id}/loeschen', [SettingsController::class, 'deleteHomeworkTemplate'], ['admin']);
$router->get('/api/behandlungsarten', [SettingsController::class, 'treatmentTypesJson'], ['auth']);

$router->post('/api/ui-settings', [UiSettingsController::class, 'save'], ['auth']);
$router->get('/api/ui-settings', [UiSettingsController::class, 'load'], ['auth']);

// Hausaufgaben-Routes
$router->get('/api/homework/templates', [HomeworkController::class, 'getTemplates'], ['auth']);
$router->get('/api/patients/{patient_id}/homework', [HomeworkController::class, 'getPatientHomework'], ['auth']);
$router->post('/api/patients/{patient_id}/homework', [HomeworkController::class, 'createPatientHomework'], ['auth']);
$router->delete('/api/patients/{patient_id}/homework/{homework_id}', [HomeworkController::class, 'deletePatientHomework'], ['auth']);

// Hausaufgaben-Pläne API (portal_homework_plans)
$router->get('/api/patients/{patient_id}/plans', [HomeworkController::class, 'getPatientPlans'], ['auth']);
$router->post('/api/patients/{patient_id}/plans', [HomeworkController::class, 'createPatientPlan'], ['auth']);
$router->delete('/api/patients/{patient_id}/plans/{plan_id}', [HomeworkController::class, 'deletePatientPlan'], ['auth']);

$router->get('/api/notifications', [NotificationController::class, 'index'], ['auth']);
$router->get('/api/invoice-form-data', [InvoiceController::class, 'formData'], ['auth']);

$router->get('/cron/geburtstag', [CronController::class, 'birthday']);

// Serve storage files via index.php — storage/ is outside DocumentRoot when public/ is the root
function serveStorageFile(string $dir, string $file): void {
    $base = realpath(STORAGE_PATH . '/' . $dir);
    if ($base === false || $file === '') { http_response_code(403); exit; }
    $path = realpath($base . '/' . $file);
    if ($path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
        http_response_code(403); exit;
    }
    if (!is_file($path)) { http_response_code(404); exit; }
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'gif'         => 'image/gif',
        'webp'        => 'image/webp',
        'svg'         => 'image/svg+xml',
        'pdf'         => 'application/pdf',
        default       => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=86400');
    readfile($path);
    exit;
}
$router->get('/uploads/exercises/{file}', function(array $p) {
    serveStorageFile('uploads/exercises', $p['file'] ?? '');
});
$router->get('/uploads/{file}', function(array $p) {
    serveStorageFile('uploads', $p['file'] ?? '');
});
$router->get('/patients/{file}', function(array $p) {
    serveStorageFile('patients', $p['file'] ?? '');
});
