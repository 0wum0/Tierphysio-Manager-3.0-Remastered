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
use App\Controllers\CronAdminController;
use App\Controllers\CronPixelController;
use App\Controllers\HomeworkController;
use App\Controllers\ReminderDunningController;
use App\Controllers\MobileApiController;
use App\Controllers\BefundbogenController;
use App\Controllers\ExpenseController;

/** @var \App\Core\Router $router */

// ── Mobile REST API (Bearer Token) ───────────────────────────────────
$router->post('/api/mobile/login',  [MobileApiController::class, 'login']);
$router->post('/api/mobile/logout', [MobileApiController::class, 'logout']);
$router->get('/api/mobile/me',      [MobileApiController::class, 'me']);
$router->get('/api/mobile/dashboard', [MobileApiController::class, 'dashboard']);

$router->get('/api/mobile/patients',          [MobileApiController::class, 'patientsList']);
$router->post('/api/mobile/patients',         [MobileApiController::class, 'patientCreate']);
$router->get('/api/mobile/patients/{id}',     [MobileApiController::class, 'patientShow']);
$router->post('/api/mobile/patients/{id}',    [MobileApiController::class, 'patientUpdate']);
$router->get('/api/mobile/patients/{id}/timeline',          [MobileApiController::class, 'patientTimeline']);
$router->post('/api/mobile/patients/{id}/timeline',         [MobileApiController::class, 'patientTimelineCreate']);
$router->post('/api/mobile/patients/{id}/timeline/upload',  [MobileApiController::class, 'patientTimelineUpload']);
$router->post('/api/mobile/patients/{id}/timeline/{eid}/delete', [MobileApiController::class, 'patientTimelineDelete']);

$router->get('/api/mobile/owners',        [MobileApiController::class, 'ownersList']);
$router->post('/api/mobile/owners',       [MobileApiController::class, 'ownerCreate']);
$router->get('/api/mobile/owners/{id}',   [MobileApiController::class, 'ownerShow']);
$router->post('/api/mobile/owners/{id}',  [MobileApiController::class, 'ownerUpdate']);

$router->get('/api/mobile/invoices',             [MobileApiController::class, 'invoicesList']);
$router->post('/api/mobile/invoices',            [MobileApiController::class, 'invoiceCreate']);
$router->get('/api/mobile/invoices/stats',       [MobileApiController::class, 'invoiceStats']);
$router->get('/api/mobile/invoices/{id}',        [MobileApiController::class, 'invoiceShow']);
$router->post('/api/mobile/invoices/{id}/status',[MobileApiController::class, 'invoiceUpdateStatus']);
$router->post('/api/mobile/invoices/{id}/storno', [MobileApiController::class, 'invoiceStorno']);

$router->get('/api/mobile/appointments',      [MobileApiController::class, 'appointmentsList']);
$router->post('/api/mobile/appointments',     [MobileApiController::class, 'appointmentCreate']);
$router->post('/api/mobile/appointments/{id}',[MobileApiController::class, 'appointmentUpdate']);
$router->post('/api/mobile/appointments/{id}/loeschen', [MobileApiController::class, 'appointmentDelete']);

$router->get('/api/mobile/treatment-types', [MobileApiController::class, 'treatmentTypes']);
$router->get('/api/mobile/settings',        [MobileApiController::class, 'settingsGet']);

$router->get('/api/mobile/nachrichten',                          [MobileApiController::class, 'messageThreads']);
$router->post('/api/mobile/nachrichten',                         [MobileApiController::class, 'messageCreate']);
$router->get('/api/mobile/nachrichten/ungelesen',                [MobileApiController::class, 'messageUnread']);
$router->get('/api/mobile/nachrichten/{id}',                     [MobileApiController::class, 'messageThread']);
$router->post('/api/mobile/nachrichten/{id}/antworten',          [MobileApiController::class, 'messageReply']);
$router->post('/api/mobile/nachrichten/{id}/status',             [MobileApiController::class, 'messageSetStatus']);
$router->post('/api/mobile/nachrichten/{id}/loeschen',           [MobileApiController::class, 'messageDelete']);
$router->get('/api/mobile/patients/{id}/portal-threads',         [MobileApiController::class, 'portalThreadsByPatient']);

// ── Mobile API v2 — Extended endpoints ──────────────────────────────

// Util
$router->get('/api/mobile/ping',                              [MobileApiController::class, 'ping']);
$router->get('/api/mobile/notifications',                     [MobileApiController::class, 'notificationSummary']);
$router->get('/api/mobile/search',                            [MobileApiController::class, 'globalSearch']);

// Invoices — extended
$router->post('/api/mobile/invoices/{id}/update',             [MobileApiController::class, 'invoiceUpdate']);
$router->post('/api/mobile/invoices/{id}/loeschen',           [MobileApiController::class, 'invoiceDelete']);
$router->get('/api/mobile/invoices/{id}/pdf',                 [MobileApiController::class, 'invoicePdfUrl']);
$router->post('/api/mobile/invoices/{id}/senden',             [MobileApiController::class, 'invoiceSendEmail']);

// Reminders
$router->get('/api/mobile/erinnerungen',                      [MobileApiController::class, 'remindersList']);
$router->get('/api/mobile/invoices/{id}/erinnerungen',        [MobileApiController::class, 'remindersForInvoice']);
$router->post('/api/mobile/invoices/{id}/erinnerungen',       [MobileApiController::class, 'reminderCreate']);
$router->post('/api/mobile/invoices/{id}/erinnerungen/{rid}/loeschen', [MobileApiController::class, 'reminderDelete']);
$router->post('/api/mobile/invoices/{id}/erinnerungen/{rid}/senden',   [MobileApiController::class, 'reminderSendEmail']);
$router->get('/api/mobile/ueberfaellig',                      [MobileApiController::class, 'overdueAlerts']);

// Dunnings
$router->get('/api/mobile/mahnungen',                         [MobileApiController::class, 'dunningsList']);
$router->get('/api/mobile/invoices/{id}/mahnungen',           [MobileApiController::class, 'dunningsForInvoice']);
$router->post('/api/mobile/invoices/{id}/mahnungen',          [MobileApiController::class, 'dunningCreate']);
$router->post('/api/mobile/invoices/{id}/mahnungen/{did}/loeschen', [MobileApiController::class, 'dunningDelete']);
$router->post('/api/mobile/invoices/{id}/mahnungen/{did}/senden',   [MobileApiController::class, 'dunningSendEmail']);

// Google Calendar Sync
$router->get('/api/mobile/google-sync/status',                [MobileApiController::class, 'googleSyncStatus']);
$router->post('/api/mobile/google-sync/pull',                 [MobileApiController::class, 'googleSyncPull']);
$router->post('/api/mobile/google-sync/push',                 [MobileApiController::class, 'googleSyncPush']);

// Patients — extended
$router->post('/api/mobile/patients/{id}/loeschen',           [MobileApiController::class, 'patientDelete']);
$router->post('/api/mobile/patients/{id}/foto',               [MobileApiController::class, 'patientPhotoUpload']);
$router->get('/api/mobile/patients/{id}/foto/{file}',         [MobileApiController::class, 'mediaServePhoto']);
$router->get('/api/mobile/patients/{id}/media/{file}',        [MobileApiController::class, 'mediaServeFile']);
$router->post('/api/mobile/patients/{id}/timeline/{eid}/update', [MobileApiController::class, 'patientTimelineUpdate']);

// Owners — extended
$router->post('/api/mobile/owners/{id}/loeschen',             [MobileApiController::class, 'ownerDelete']);
$router->get('/api/mobile/owners/{id}/rechnungen',            [MobileApiController::class, 'ownerInvoices']);
$router->get('/api/mobile/owners/{id}/patienten',             [MobileApiController::class, 'ownerPatients']);

// Appointments — extended
$router->get('/api/mobile/appointments/heute',                [MobileApiController::class, 'appointmentToday']);
$router->get('/api/mobile/appointments/{id}',                 [MobileApiController::class, 'appointmentShow']);
$router->post('/api/mobile/appointments/{id}/status',         [MobileApiController::class, 'appointmentStatusUpdate']);

// Waitlist
$router->get('/api/mobile/warteliste',                        [MobileApiController::class, 'waitlistList']);
$router->post('/api/mobile/warteliste',                       [MobileApiController::class, 'waitlistAdd']);
$router->post('/api/mobile/warteliste/{id}/loeschen',         [MobileApiController::class, 'waitlistDelete']);
$router->post('/api/mobile/warteliste/{id}/einplanen',        [MobileApiController::class, 'waitlistSchedule']);

// Treatment types — full CRUD
$router->get('/api/mobile/behandlungsarten/{id}',             [MobileApiController::class, 'treatmentTypeShow']);
$router->post('/api/mobile/behandlungsarten',                 [MobileApiController::class, 'treatmentTypeCreate']);
$router->post('/api/mobile/behandlungsarten/{id}/update',     [MobileApiController::class, 'treatmentTypeUpdate']);
$router->post('/api/mobile/behandlungsarten/{id}/loeschen',   [MobileApiController::class, 'treatmentTypeDelete']);

// Users (admin only)
$router->get('/api/mobile/benutzer',                          [MobileApiController::class, 'usersList']);
$router->get('/api/mobile/benutzer/{id}',                     [MobileApiController::class, 'userShow']);
$router->post('/api/mobile/benutzer',                         [MobileApiController::class, 'userCreate']);
$router->post('/api/mobile/benutzer/{id}/update',             [MobileApiController::class, 'userUpdate']);
$router->post('/api/mobile/benutzer/{id}/deaktivieren',       [MobileApiController::class, 'userDeactivate']);
$router->get('/api/mobile/benutzer/{id}/tokens',              [MobileApiController::class, 'userApiTokens']);
$router->post('/api/mobile/benutzer/tokens/{tid}/widerrufen', [MobileApiController::class, 'userRevokeToken']);

// Settings — write (admin)
$router->post('/api/mobile/settings',                         [MobileApiController::class, 'settingsUpdate']);

// Analytics
$router->get('/api/mobile/analytics',                         [MobileApiController::class, 'analyticsOverview']);

// Homework (old simple list)
$router->get('/api/mobile/hausaufgaben',                      [MobileApiController::class, 'homeworkList']);
$router->get('/api/mobile/patients/{id}/hausaufgaben',        [MobileApiController::class, 'homeworkList']);
$router->get('/api/mobile/hausaufgaben/{id}',                 [MobileApiController::class, 'homeworkShow']);

// ── Owner Portal Admin API ───────────────────────────────────────────

// Portal user management
$router->get('/api/mobile/portal-admin/stats',                [MobileApiController::class, 'portalStats']);
$router->get('/api/mobile/portal-admin/benutzer',             [MobileApiController::class, 'portalUsersList']);
$router->get('/api/mobile/portal-admin/benutzer/{id}',        [MobileApiController::class, 'portalUserShow']);
$router->post('/api/mobile/portal-admin/einladen',            [MobileApiController::class, 'portalInvite']);
$router->post('/api/mobile/portal-admin/benutzer/{id}/neu-einladen',   [MobileApiController::class, 'portalResendInvite']);
$router->post('/api/mobile/portal-admin/benutzer/{id}/aktivieren',     [MobileApiController::class, 'portalActivate']);
$router->post('/api/mobile/portal-admin/benutzer/{id}/deaktivieren',   [MobileApiController::class, 'portalDeactivate']);
$router->post('/api/mobile/portal-admin/benutzer/{id}/loeschen',       [MobileApiController::class, 'portalUserDelete']);

// Portal owner overview (portal user + pets + exercises + homework plans)
$router->get('/api/mobile/portal-admin/besitzer/{id}/uebersicht', [MobileApiController::class, 'portalOwnerOverview']);

// Exercises (Übungen) per patient
$router->get('/api/mobile/portal-admin/patienten/{id}/uebungen',   [MobileApiController::class, 'exercisesList']);
$router->post('/api/mobile/portal-admin/patienten/{id}/uebungen',  [MobileApiController::class, 'exerciseCreate']);
$router->get('/api/mobile/portal-admin/uebungen/{id}',             [MobileApiController::class, 'exerciseShow']);
$router->post('/api/mobile/portal-admin/uebungen/{id}/update',     [MobileApiController::class, 'exerciseUpdate']);
$router->post('/api/mobile/portal-admin/uebungen/{id}/loeschen',   [MobileApiController::class, 'exerciseDelete']);

// Homework plans (Hausaufgabenpläne)
$router->get('/api/mobile/portal-admin/hausaufgabenplaene',                        [MobileApiController::class, 'homeworkPlanList']);
$router->post('/api/mobile/portal-admin/hausaufgabenplaene',                       [MobileApiController::class, 'homeworkPlanCreate']);
$router->get('/api/mobile/portal-admin/hausaufgabenplaene/{id}',                   [MobileApiController::class, 'homeworkPlanShow']);
$router->post('/api/mobile/portal-admin/hausaufgabenplaene/{id}/update',           [MobileApiController::class, 'homeworkPlanUpdate']);
$router->post('/api/mobile/portal-admin/hausaufgabenplaene/{id}/loeschen',         [MobileApiController::class, 'homeworkPlanDelete']);
$router->get('/api/mobile/portal-admin/hausaufgabenplaene/{id}/pdf',               [MobileApiController::class, 'homeworkPlanPdfUrl']);
$router->post('/api/mobile/portal-admin/hausaufgabenplaene/{id}/senden',           [MobileApiController::class, 'homeworkPlanSend']);

// Homework templates (read-only)
$router->get('/api/mobile/portal-admin/vorlagen',             [MobileApiController::class, 'homeworkTemplates']);

// Portal check-notifications (Besitzer hat Aufgabe/Übung abgehakt)
$router->get('/api/mobile/portal/feedback/neu',               [MobileApiController::class, 'portalFeedbackNew']);

// ── Profile ──────────────────────────────────────────────────────────
$router->get('/api/mobile/profil',                            [MobileApiController::class, 'profileGet']);
$router->post('/api/mobile/profil',                           [MobileApiController::class, 'profileUpdate']);
$router->post('/api/mobile/profil/passwort',                  [MobileApiController::class, 'profileChangePassword']);

// ── Therapy Care Pro (tcp) ───────────────────────────────────────────

// Progress tracking
$router->get('/api/mobile/tcp/fortschritt/kategorien',                   [MobileApiController::class, 'tcpProgressCategories']);
$router->get('/api/mobile/tcp/patienten/{id}/fortschritt',               [MobileApiController::class, 'tcpProgressList']);
$router->get('/api/mobile/tcp/{id}/progress',                            [MobileApiController::class, 'tcpProgress']);
$router->post('/api/mobile/tcp/{id}/save',                               [MobileApiController::class, 'tcpSave']);
$router->post('/api/mobile/tcp/patienten/{id}/fortschritt',              [MobileApiController::class, 'tcpProgressStore']);
$router->post('/api/mobile/tcp/fortschritt/{entry_id}/loeschen',         [MobileApiController::class, 'tcpProgressDelete']);

// Exercise feedback
$router->get('/api/mobile/tcp/patienten/{id}/feedback',                  [MobileApiController::class, 'tcpFeedbackList']);
$router->get('/api/mobile/tcp/feedback/problematisch',                   [MobileApiController::class, 'tcpFeedbackProblematic']);

// Therapy reports
$router->get('/api/mobile/tcp/patienten/{id}/berichte',                  [MobileApiController::class, 'tcpReportList']);
$router->post('/api/mobile/tcp/patienten/{id}/berichte',                 [MobileApiController::class, 'tcpReportCreate']);
$router->get('/api/mobile/tcp/berichte/{id}',                            [MobileApiController::class, 'tcpReportShow']);
$router->get('/api/mobile/tcp/berichte/{id}/pdf',                        [MobileApiController::class, 'tcpReportPdfUrl']);
$router->post('/api/mobile/tcp/berichte/{id}/loeschen',                  [MobileApiController::class, 'tcpReportDelete']);

// Exercise library
$router->get('/api/mobile/tcp/bibliothek',                               [MobileApiController::class, 'tcpLibraryList']);
$router->post('/api/mobile/tcp/bibliothek',                              [MobileApiController::class, 'tcpLibraryCreate']);
$router->get('/api/mobile/tcp/bibliothek/{id}',                          [MobileApiController::class, 'tcpLibraryShow']);
$router->post('/api/mobile/tcp/bibliothek/{id}/update',                  [MobileApiController::class, 'tcpLibraryUpdate']);
$router->post('/api/mobile/tcp/bibliothek/{id}/loeschen',                [MobileApiController::class, 'tcpLibraryDelete']);

// Natural therapy
$router->get('/api/mobile/tcp/patienten/{id}/naturheilkunde',            [MobileApiController::class, 'tcpNaturalList']);
$router->get('/api/mobile/tcp/{id}/natural',                            [MobileApiController::class, 'tcpNatural']);
$router->post('/api/mobile/tcp/patienten/{id}/naturheilkunde',           [MobileApiController::class, 'tcpNaturalCreate']);
$router->post('/api/mobile/tcp/naturheilkunde/{id}/update',              [MobileApiController::class, 'tcpNaturalUpdate']);
$router->post('/api/mobile/tcp/naturheilkunde/{id}/loeschen',            [MobileApiController::class, 'tcpNaturalDelete']);

$router->get('/api/mobile/tcp/{id}/reports',                            [MobileApiController::class, 'tcpReports']);

// Reminder queue (TCP)
$router->get('/api/mobile/tcp/erinnerungen/vorlagen',                    [MobileApiController::class, 'tcpReminderTemplates']);
$router->get('/api/mobile/tcp/patienten/{id}/erinnerungen',              [MobileApiController::class, 'tcpReminderQueue']);
$router->post('/api/mobile/tcp/patienten/{id}/erinnerungen',             [MobileApiController::class, 'tcpReminderQueueStore']);

// ── Tax Export Pro (Steuerexport) ────────────────────────────────────
$router->get('/api/mobile/steuerexport',                                 [MobileApiController::class, 'taxExportList']);
$router->get('/api/mobile/steuerexport/export-url',                      [MobileApiController::class, 'taxExportUrls']);
$router->get('/api/mobile/steuerexport/audit-log',                       [MobileApiController::class, 'taxExportAuditLog']);
$router->post('/api/mobile/steuerexport/{id}/finalisieren',              [MobileApiController::class, 'taxExportFinalize']);
$router->post('/api/mobile/steuerexport/{id}/stornieren',                [MobileApiController::class, 'taxExportCancel']);

// ── Mailbox (IMAP/SMTP) ──────────────────────────────────────────────
$router->get('/api/mobile/mailbox/status',                               [MobileApiController::class, 'mailboxStatus']);
$router->get('/api/mobile/mailbox/nachrichten',                          [MobileApiController::class, 'mailboxList']);
$router->get('/api/mobile/mailbox/nachrichten/{uid}',                    [MobileApiController::class, 'mailboxShow']);
$router->post('/api/mobile/mailbox/senden',                              [MobileApiController::class, 'mailboxSend']);
$router->post('/api/mobile/mailbox/nachrichten/{uid}/loeschen',          [MobileApiController::class, 'mailboxDelete']);

// ── Google Calendar Sync ─────────────────────────────────────────────
$router->get('/api/mobile/google-kalender/status',                       [MobileApiController::class, 'googleCalendarStatus']);
$router->post('/api/mobile/google-kalender/sync',                        [MobileApiController::class, 'googleCalendarTriggerSync']);

// ── System / Cron ────────────────────────────────────────────────────
$router->get('/api/mobile/system/status',                                [MobileApiController::class, 'systemStatus']);
$router->get('/api/mobile/system/cronjobs',                              [MobileApiController::class, 'systemCronJobs']);

// ── Owner Portal — Auth (Besitzerportal) ─────────────────────────────
$router->post('/api/mobile/portal/login',                                [MobileApiController::class, 'portalLogin']);
$router->post('/api/mobile/portal/logout',                               [MobileApiController::class, 'portalLogout']);
$router->post('/api/mobile/portal/passwort-setzen/{token}',              [MobileApiController::class, 'portalSetPassword']);

// ── Owner Portal — Dashboard ──────────────────────────────────────────
$router->get('/api/mobile/portal/dashboard',                             [MobileApiController::class, 'ownerPortalDashboard']);

// ── Owner Portal — Meine Tiere ────────────────────────────────────────
$router->get('/api/mobile/portal/tiere',                                 [MobileApiController::class, 'ownerPortalPetList']);
$router->get('/api/mobile/portal/tiere/{id}',                            [MobileApiController::class, 'ownerPortalPetDetail']);
$router->post('/api/mobile/portal/tiere/{id}/bearbeiten',                [MobileApiController::class, 'ownerPortalPetEdit']);

// ── Owner Portal — Rechnungen ─────────────────────────────────────────
$router->get('/api/mobile/portal/rechnungen',                            [MobileApiController::class, 'ownerPortalInvoices']);
$router->get('/api/mobile/portal/rechnungen/{id}/pdf-url',               [MobileApiController::class, 'ownerPortalInvoicePdfUrl']);

// ── Owner Portal — Termine ────────────────────────────────────────────
$router->get('/api/mobile/portal/termine',                               [MobileApiController::class, 'ownerPortalAppointments']);

// ── Owner Portal — Nachrichten ────────────────────────────────────────
$router->get('/api/mobile/portal/nachrichten/ungelesen',                 [MobileApiController::class, 'ownerPortalUnread']);
$router->get('/api/mobile/portal/nachrichten',                           [MobileApiController::class, 'ownerPortalThreadList']);
$router->post('/api/mobile/portal/nachrichten/neu',                      [MobileApiController::class, 'ownerPortalNewThread']);
$router->get('/api/mobile/portal/nachrichten/{id}',                      [MobileApiController::class, 'ownerPortalThreadShow']);
$router->post('/api/mobile/portal/nachrichten/{id}/antworten',           [MobileApiController::class, 'ownerPortalReply']);

// ── Owner Portal — Befundbögen ─────────────────────────────────────────
$router->get('/api/mobile/portal/befunde',                               [MobileApiController::class, 'ownerPortalBefunde']);
$router->get('/api/mobile/portal/befunde/{id}/pdf-url',                  [MobileApiController::class, 'ownerPortalBefundPdfUrl']);

// ── Befundbögen (admin API) ────────────────────────────────────────────
$router->get('/api/mobile/befunde',                                      [MobileApiController::class, 'befundeList']);
$router->get('/api/mobile/befunde/patient/{id}',                         [MobileApiController::class, 'befundeByPatient']);
$router->get('/api/mobile/befunde/{id}/pdf-url',                         [MobileApiController::class, 'befundePdfUrl']);
$router->get('/api/mobile/befunde/{id}',                                 [MobileApiController::class, 'befundeShow']);

// ── Owner Portal — Profil ─────────────────────────────────────────────
$router->get('/api/mobile/portal/profil',                                [MobileApiController::class, 'ownerPortalProfile']);
$router->post('/api/mobile/portal/profil/passwort',                      [MobileApiController::class, 'ownerPortalChangePassword']);

// ── Patient Intake (Patientenanmeldung) ──────────────────────────────
$router->post('/api/mobile/anmeldung',                                   [MobileApiController::class, 'intakeSubmit']);
$router->get('/api/mobile/anmeldung/benachrichtigungen',                 [MobileApiController::class, 'intakeNotifications']);
$router->get('/api/mobile/anmeldung',                                    [MobileApiController::class, 'intakeInbox']);
$router->get('/api/mobile/anmeldung/{id}',                               [MobileApiController::class, 'intakeShow']);
$router->post('/api/mobile/anmeldung/{id}/annehmen',                     [MobileApiController::class, 'intakeAccept']);
$router->post('/api/mobile/anmeldung/{id}/ablehnen',                     [MobileApiController::class, 'intakeReject']);
// ── Patient Invite (Einladungslinks) ─────────────────────────────────
$router->get('/api/mobile/einladungen/benachrichtigungen',               [MobileApiController::class, 'inviteNotifications']);
$router->get('/api/mobile/einladungen',                                  [MobileApiController::class, 'inviteList']);
$router->post('/api/mobile/einladungen',                                 [MobileApiController::class, 'inviteSend']);
$router->post('/api/mobile/einladungen/{id}/widerrufen',                 [MobileApiController::class, 'inviteRevoke']);
$router->post('/api/mobile/einladungen/{id}/bearbeiten',                 [MobileApiController::class, 'inviteUpdate']);
$router->get('/api/mobile/einladungen/{id}/whatsapp',                    [MobileApiController::class, 'inviteWhatsapp']);

// Hausaufgaben Plan-Meta API
$router->get('/api/patients/{patient_id}/homework/plan-meta', [HomeworkController::class, 'getPlanMeta'], ['auth']);
$router->post('/api/patients/{patient_id}/homework/plan-meta', [HomeworkController::class, 'savePlanMeta'], ['auth']);

$router->get('/', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard/chart-data', [DashboardController::class, 'chartData'], ['auth']);
$router->post('/api/dashboard/layout', [DashboardController::class, 'saveLayout'], ['auth']);
$router->get('/api/dashboard/layout', [DashboardController::class, 'loadLayout'], ['auth']);

$router->get('/datenschutz', function() use ($router) {
    $app = \App\Core\Application::getInstance();
    $settingsService = $app->getContainer()->get(\App\Services\SettingsService::class);
    $settings = $settingsService->all();

    // Get GDPR text from settings
    $gdprText = $settings['gdpr_text'] ?? '';

    // Replace placeholders with actual values
    $placeholders = [
        '{{company_name}}' => $settings['company_name'] ?? '',
        '{{company_street}}' => $settings['company_street'] ?? '',
        '{{company_zip}}' => $settings['company_zip'] ?? '',
        '{{company_city}}' => $settings['company_city'] ?? '',
        '{{company_email}}' => $settings['company_email'] ?? '',
        '{{company_phone}}' => $settings['company_phone'] ?? '',
    ];

    $gdprText = str_replace(array_keys($placeholders), array_values($placeholders), $gdprText);

    // Convert Markdown to HTML (simple implementation)
    $gdprHtml = preg_replace('/^### (.*$)/m', '<h3 style="font-size:1rem;font-weight:600;margin:1.5rem 0 0.75rem;">$1</h3>', $gdprText);
    $gdprHtml = preg_replace('/^## (.*$)/m', '<h2 style="font-size:1.1rem;font-weight:700;margin:0 0 1.5rem;">$1</h2>', $gdprHtml);
    $gdprHtml = preg_replace('/^# (.*$)/m', '<h1 style="font-size:1.3rem;font-weight:800;margin:0 0 1.5rem;">$1</h1>', $gdprHtml);
    $gdprHtml = preg_replace('/^- (.*$)/m', '<li>$1</li>', $gdprHtml);
    $gdprHtml = preg_replace('/^(\d+)\. (.*$)/m', '<li>$2</li>', $gdprHtml);
    $gdprHtml = preg_replace('/(<li>.*<\/li>\n?)/', '<ul>$1</ul>', $gdprHtml);
    $gdprHtml = preg_replace('/<\/ul>\n<ul>/', '', $gdprHtml);
    $gdprHtml = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $gdprHtml);
    $gdprHtml = preg_replace('/\n\n/', '</p><p style="margin:0.5rem 0;">', $gdprHtml);
    $gdprHtml = '<p style="margin:0.5rem 0;">' . $gdprHtml . '</p>';
    $gdprHtml = preg_replace('/<p style="margin:0\.5rem 0;"><h/', '<h', $gdprHtml);
    $gdprHtml = preg_replace('/<\/h([1-6])><\/p>/', '</h$1>', $gdprHtml);
    $gdprHtml = preg_replace('/<p style="margin:0\.5rem 0;"><ul>/', '<ul>', $gdprHtml);
    $gdprHtml = preg_replace('/<\/ul><\/p>/', '</ul>', $gdprHtml);

    // Output HTML directly without template (SaaS-compatible)
    echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenschutzerklärung</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 900px; margin: 0 auto; padding: 20px; }
        h1, h2, h3 { color: #2c3e50; }
        ul { padding-left: 20px; }
    </style>
</head>
<body>
    <h1>Datenschutzerklärung</h1>
    <div style="line-height:1.8;font-size:0.95rem;">
        ' . $gdprHtml . '
    </div>
</body>
</html>';
    exit;
}, []);

$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword'], ['guest']);
$router->post('/forgot-password', [AuthController::class, 'forgotPasswordSubmit'], ['guest']);
$router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword'], ['guest']);
$router->post('/reset-password/{token}', [AuthController::class, 'resetPasswordSubmit'], ['guest']);


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

// ── Befundbögen — per Patient ─────────────────────────────────────────
$router->get('/api/patienten/{patient_id}/befunde',                        [BefundbogenController::class, 'apiByPatient'], ['auth']);
$router->get('/patienten/{patient_id}/befunde',                            [BefundbogenController::class, 'index'],  ['auth']);
$router->get('/patienten/{patient_id}/befunde/neu',                        [BefundbogenController::class, 'create'], ['auth']);
$router->post('/patienten/{patient_id}/befunde/speichern',                 [BefundbogenController::class, 'store'],  ['auth']);
$router->get('/patienten/{patient_id}/befunde/{id}',                       [BefundbogenController::class, 'show'],   ['auth']);
$router->get('/patienten/{patient_id}/befunde/{id}/bearbeiten',            [BefundbogenController::class, 'edit'],   ['auth']);
$router->post('/patienten/{patient_id}/befunde/{id}/aktualisieren',        [BefundbogenController::class, 'update'], ['auth']);
$router->get('/patienten/{patient_id}/befunde/{id}/pdf',                   [BefundbogenController::class, 'pdf'],    ['auth']);
$router->post('/patienten/{patient_id}/befunde/{id}/senden',               [BefundbogenController::class, 'senden'], ['auth']);
$router->post('/patienten/{patient_id}/befunde/{id}/loeschen',             [BefundbogenController::class, 'delete'], ['auth']);

// ── Befundbögen — Portal Admin ────────────────────────────────────────
$router->get('/portal-admin/befunde',                                      [BefundbogenController::class, 'adminIndex'],  ['auth']);
$router->get('/portal-admin/befunde/{id}',                                 [BefundbogenController::class, 'adminShow'],   ['auth']);
$router->post('/portal-admin/befunde/{id}/senden',                         [BefundbogenController::class, 'adminSenden'], ['auth']);

// ── Befundbögen — Owner Portal ────────────────────────────────────────
$router->get('/portal/befunde',                                            [BefundbogenController::class, 'portalIndex'], []);
$router->get('/portal/befunde/{id}',                                       [BefundbogenController::class, 'portalShow'],  []);
$router->get('/portal/befunde/{id}/pdf',                                   [BefundbogenController::class, 'portalPdf'],   []);

$router->get('/tierhalter', [OwnerController::class, 'index'], ['auth']);
$router->post('/tierhalter', [OwnerController::class, 'store'], ['auth']);
$router->get('/tierhalter/{id}', [OwnerController::class, 'show'], ['auth']);
$router->post('/tierhalter/{id}', [OwnerController::class, 'update'], ['auth']);
$router->post('/tierhalter/{id}/loeschen', [OwnerController::class, 'delete'], ['auth']);

$router->get('/rechnungen', [InvoiceController::class, 'index'], ['auth']);
$router->get('/rechnungen/erstellen', [InvoiceController::class, 'create'], ['auth']);
$router->get('/rechnungen/analyse',   [InvoiceController::class, 'analytics'], ['auth']);
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
$router->post('/rechnungen/{id}/stornieren',    [InvoiceController::class, 'cancel'],             ['auth']);
$router->get('/rechnungen/{id}/storno-pdf',     [InvoiceController::class, 'downloadCancellationPdf'], ['auth']);
$router->get('/api/rechnungen/analytics',        [InvoiceController::class, 'analyticsJson'],      ['auth']);

// ── Ausgaben (Expenses) ───────────────────────────────────────────────
$router->get('/ausgaben',                [ExpenseController::class, 'index'],  ['auth']);
$router->get('/ausgaben/neu',            [ExpenseController::class, 'create'], ['auth']);
$router->post('/ausgaben',               [ExpenseController::class, 'store'],  ['auth']);
$router->get('/ausgaben/{id}/bearbeiten',[ExpenseController::class, 'edit'],   ['auth']);
$router->post('/ausgaben/{id}',          [ExpenseController::class, 'update'], ['auth']);
$router->post('/ausgaben/{id}/loeschen', [ExpenseController::class, 'delete'], ['auth']);
$router->get('/ausgaben/{id}/pdf',       [ExpenseController::class, 'pdf'],    ['auth']);

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
$router->post('/einstellungen/smtp/test', [SettingsController::class, 'testSmtp'], ['admin']);
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
$router->get('/cron/dispatcher', [CronController::class, 'dispatcher']);
$router->get('/cron/pixel.gif',  [CronPixelController::class, 'pixel']);

// ── Cron Admin Panel entfernt - wird in SaaS-Platform verwaltet ─────────

// Serve storage files via index.php — storage/ is outside DocumentRoot when public/ is the root
function serveStorageFile(string $dir, string $file): void {
    $base = realpath(tenant_storage_path($dir));
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
$router->get('/patient-photos/{id}/{file}', function(array $p) {
    serveStorageFile('patients/' . (int)($p['id'] ?? 0), $p['file'] ?? '');
});
$router->get('/patient-timeline/{id}/{file}', function(array $p) {
    serveStorageFile('patients/' . (int)($p['id'] ?? 0) . '/timeline', $p['file'] ?? '');
});
