<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Services\InvoiceCancellationService;
use App\Services\InvoiceService;
use App\Services\PatientService;
use App\Services\OwnerService;
use App\Services\PdfService;
use App\Services\MailService;
use App\Repositories\TreatmentTypeRepository;
use App\Repositories\SettingsRepository;
use App\Core\PerformanceLogger;

class InvoiceController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly InvoiceService $invoiceService,
        private readonly PatientService $patientService,
        private readonly OwnerService $ownerService,
        private readonly PdfService $pdfService,
        private readonly MailService $mailService,
        private readonly TreatmentTypeRepository $treatmentTypeRepository,
        private readonly SettingsRepository $settingsRepository,
        private readonly InvoiceCancellationService $cancellationService
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    public function index(array $params = []): void
    {
        /* Throttle: only run overdue check once per 15 minutes via session timestamp */
        $lastOverdueCheck = (int)($this->session->get('_overdue_check_ts') ?? 0);
        if ((time() - $lastOverdueCheck) > 900) {
            try { $this->invoiceService->markOverdueAutomatic(); } catch (\Throwable) {}
            $this->session->set('_overdue_check_ts', time());
        }

        $status = $this->get('status', '');
        $search = $this->get('search', '');
        $page   = (int)$this->get('page', 1);
        $result     = $this->invoiceService->getPaginated($page, 15, $status, $search);
        $stats      = $this->invoiceService->getStats();
        $chartData  = $this->invoiceService->getMonthlyChartData();

        $this->render('invoices/index.twig', [
            'page_title' => $this->translator->trans('nav.invoices'),
            'invoices'   => $result['items'],
            'pagination' => $result,
            'stats'      => $stats,
            'chart_data' => $chartData,
            'status'     => $status,
            'search'     => $search,
        ]);
    }

    public function create(array $params = []): void
    {
        $patients = $this->patientService->findAll();
        $owners   = $this->ownerService->findAll();

        $preselected_patient = $this->get('patient_id');
        $preselected_owner   = $this->get('owner_id');

        $treatmentTypes = [];
        try { $treatmentTypes = $this->treatmentTypeRepository->findActive(); } catch (\Throwable) {}

        $settings = $this->settingsRepository->all();
        $this->render('invoices/create.twig', [
            'page_title'          => $this->translator->trans('invoices.create'),
            'patients'            => $patients,
            'owners'              => $owners,
            'preselected_patient' => $preselected_patient,
            'preselected_owner'   => $preselected_owner,
            'next_number'         => $this->invoiceService->generateInvoiceNumber(),
            'treatment_types'     => $treatmentTypes,
            'kleinunternehmer'    => ($settings['kleinunternehmer'] ?? '0') === '1',
            'default_tax_rate'    => $settings['default_tax_rate'] ?? '19',
        ]);
    }

    public function store(array $params = []): void
    {
        PerformanceLogger::startRequest('invoice.store');
        $this->validateCsrf();
        PerformanceLogger::mark('csrf_ok');

        $paymentMethod = $this->sanitize($this->post('payment_method', 'rechnung'));
        if (!in_array($paymentMethod, ['rechnung', 'bar'], true)) {
            $paymentMethod = 'rechnung';
        }

        $isCash = ($paymentMethod === 'bar');

        $data = [
            'invoice_number' => $this->sanitize($this->post('invoice_number', '')),
            'patient_id'     => (int)$this->post('patient_id', 0) ?: null,
            'owner_id'       => (int)$this->post('owner_id', 0),
            'status'         => $isCash ? 'paid' : $this->sanitize($this->post('status', 'draft')),
            'issue_date'     => $this->post('issue_date') ?: date('Y-m-d'),
            'due_date'       => $isCash ? null : ($this->post('due_date', null) ?: null),
            'notes'          => $this->post('notes', ''),
            'diagnosis'      => $this->post('diagnosis', '') ?: null,
            'payment_terms'  => $this->post('payment_terms', ''),
            'payment_method' => $paymentMethod,
            'paid_at'        => $isCash ? date('Y-m-d H:i:s') : null,
        ];

        PerformanceLogger::mark('validation_start');
        $positions = $this->parsePositions();

        if (empty($data['owner_id']) || empty($positions)) {
            $this->session->flash('error', $this->translator->trans('invoices.fill_required'));
            PerformanceLogger::finish('validation_failed: missing owner or positions');
            $this->redirect('/rechnungen/erstellen');
            return;
        }
        PerformanceLogger::mark('validation_ok');

        PerformanceLogger::startTimer('db_save');
        $id = $this->invoiceService->create($data, $positions);
        PerformanceLogger::stopTimer('db_save');

        /* ── Automatischer Timeline-Eintrag bei Barzahlung (schnell, nur 1 INSERT) ── */
        if ($isCash && !empty($data['patient_id'])) {
            $paidAtFormatted = date('d.m.Y \u\m H:i \U\h\r');
            try {
                $this->patientService->addTimelineEntry([
                    'patient_id'   => (int)$data['patient_id'],
                    'type'         => 'payment',
                    'title'        => 'Quittung ' . ($data['invoice_number'] ?? '') . ' bezahlt',
                    'content'      => 'Barzahlung am ' . $paidAtFormatted . ' verbucht.',
                    'status_badge' => 'bar',
                    'entry_date'   => date('Y-m-d H:i:s'),
                    'user_id'      => (int)$this->session->get('user_id'),
                ]);
            } catch (\Throwable) {}
        }

        $sendEmail = $this->post('_send_email', '0') === '1';

        $msg = $isCash
            ? 'Quittung erstellt und als Barzahlung verbucht.'
            : $this->translator->trans('invoices.created');
        $this->session->flash('success', $msg);

        PerformanceLogger::finish();

        /* ── PDF + Mail nach dem Redirect — blockiert den Speichervorgang NICHT ──
         * register_shutdown_function runs after output is sent and session is closed,
         * so the redirect completes immediately for the user.
         */
        if ($sendEmail && !$isCash) {
            $invoiceId      = (int)$id;
            $ownerId        = (int)$data['owner_id'];
            $patientId      = !empty($data['patient_id']) ? (int)$data['patient_id'] : null;
            $invoiceService = $this->invoiceService;
            $ownerService   = $this->ownerService;
            $patientService = $this->patientService;
            $pdfService     = $this->pdfService;
            $mailService    = $this->mailService;

            register_shutdown_function(function () use (
                $invoiceId, $ownerId, $patientId,
                $invoiceService, $ownerService, $patientService,
                $pdfService, $mailService
            ) {
                try {
                    $owner = $ownerService->findById($ownerId);
                    if (!$owner || empty($owner['email'])) return;

                    PerformanceLogger::startRequest('invoice.store.async_mail');
                    PerformanceLogger::startTimer('pdf_generate');
                    $inv  = $invoiceService->findById($invoiceId);
                    $pos  = $invoiceService->getPositions($invoiceId);
                    $pat  = $patientId ? $patientService->findById($patientId) : null;
                    $pdf  = $pdfService->generateInvoicePdf($inv, $pos, $owner, $pat);
                    PerformanceLogger::stopTimer('pdf_generate');

                    PerformanceLogger::startTimer('mail_send');
                    $sent = $mailService->sendInvoice($inv, $owner, $pdf);
                    PerformanceLogger::stopTimer('mail_send');

                    if ($sent) {
                        $invoiceService->markEmailSent($invoiceId);
                    }
                    PerformanceLogger::finish($sent ? null : 'mail_failed');
                } catch (\Throwable $e) {
                    PerformanceLogger::finish('async_mail_exception: ' . $e->getMessage());
                }
            });
        }

        $this->redirect("/rechnungen/{$id}");
    }

    public function show(array $params = []): void
    {
        $invoice   = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        $positions = $this->invoiceService->getPositions((int)$params['id']);
        $owner     = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        $patient   = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;

        /* Load related invoice (Stornobeleg ↔ Original) for the Verknüpfte-Dokumente panel */
        $related = null;
        if (!empty($invoice['cancellation_invoice_id'])) {
            $related = $this->invoiceService->findById((int)$invoice['cancellation_invoice_id']);
        } elseif (!empty($invoice['cancels_invoice_id'])) {
            $related = $this->invoiceService->findById((int)$invoice['cancels_invoice_id']);
        }

        $isCancellable = in_array($invoice['status'] ?? '', ['open', 'paid', 'overdue'], true)
            && ($invoice['invoice_type'] ?? 'normal') !== 'cancellation'
            && empty($invoice['cancellation_invoice_id']);

        $this->render('invoices/show.twig', [
            'page_title'     => $this->translator->trans('invoices.invoice') . ' ' . $invoice['invoice_number'],
            'invoice'        => $invoice,
            'positions'      => $positions,
            'owner'          => $owner,
            'patient'        => $patient,
            'related'        => $related,
            'is_cancellable' => $isCancellable,
        ]);
    }

    public function edit(array $params = []): void
    {
        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        /* GoBD: Bezahlte, stornierte und Storno-Belege sind unveränderlich */
        if (in_array($invoice['status'] ?? '', ['paid', 'cancelled', 'cancellation'], true)
            || ($invoice['invoice_type'] ?? 'normal') === 'cancellation') {
            $this->session->flash('error', 'Bezahlte, stornierte oder Storno-Belege können nicht bearbeitet werden (GoBD). Erstellen Sie stattdessen eine Stornorechnung.');
            $this->redirect("/rechnungen/{$params['id']}");
            return;
        }

        $positions = $this->invoiceService->getPositions((int)$params['id']);
        $patients  = $this->patientService->findAll();
        $owners    = $this->ownerService->findAll();

        $settings = $this->settingsRepository->all();
        $this->render('invoices/edit.twig', [
            'page_title'       => $this->translator->trans('invoices.edit'),
            'invoice'          => $invoice,
            'positions'        => $positions,
            'patients'         => $patients,
            'owners'           => $owners,
            'kleinunternehmer' => ($settings['kleinunternehmer'] ?? '0') === '1',
            'default_tax_rate' => $settings['default_tax_rate'] ?? '19',
        ]);
    }

    public function update(array $params = []): void
    {
        $this->validateCsrf();

        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        /* GoBD: finalisierte Rechnungen sind unveränderlich */
        if (!empty($invoice['finalized_at'])) {
            $this->session->flash('error', 'Diese Rechnung ist finalisiert und kann nicht mehr bearbeitet werden (GoBD). Erstellen Sie stattdessen eine Stornorechnung.');
            $this->redirect("/rechnungen/{$params['id']}");
            return;
        }

        $data = [
            'invoice_number' => $this->sanitize($this->post('invoice_number', '')),
            'patient_id'     => (int)$this->post('patient_id', 0) ?: null,
            'owner_id'       => (int)$this->post('owner_id', 0),
            'status'         => $this->sanitize($this->post('status', 'draft')),
            'issue_date'     => $this->post('issue_date') ?: date('Y-m-d'),
            'due_date'       => $this->post('due_date', null),
            'notes'          => $this->post('notes', ''),
            'diagnosis'      => $this->post('diagnosis', '') ?: null,
            'payment_terms'  => $this->post('payment_terms', ''),
        ];

        $positions = $this->parsePositions();
        $this->invoiceService->update((int)$params['id'], $data, $positions);

        /* GoBD: Audit-Log via Plugin-Hook */
        try {
            $pluginManager = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\PluginManager::class);
            $pluginManager->fireHook('invoice.updated', [
                'invoice_id'     => (int)$params['id'],
                'invoice_number' => $invoice['invoice_number'] ?? '',
                'old_values'     => $invoice,
                'new_values'     => $data,
                'user_id'        => (int)$this->session->get('user_id'),
            ]);
        } catch (\Throwable) {}

        $this->session->flash('success', $this->translator->trans('invoices.updated'));
        $this->redirect("/rechnungen/{$params['id']}");
    }

    public function delete(array $params = []): void
    {
        $this->validateCsrf();

        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        /* GoBD: finalisierte und bezahlte Rechnungen dürfen nicht gelöscht werden */
        if (!empty($invoice['finalized_at'])) {
            $this->session->flash('error', 'Finalisierte Rechnungen dürfen nicht gelöscht werden (GoBD §147 AO). Erstellen Sie stattdessen eine Stornorechnung.');
            $this->redirect("/rechnungen/{$params['id']}");
            return;
        }
        if (in_array($invoice['status'] ?? '', ['paid', 'cancelled'], true)) {
            $this->session->flash('error', 'Bezahlte oder stornierte Rechnungen dürfen nicht gelöscht werden (GoBD). Nutzen Sie den Steuerexport für eine Stornorechnung.');
            $this->redirect("/rechnungen/{$params['id']}");
            return;
        }

        /* GoBD: Audit-Log vor dem Löschen */
        try {
            $pluginManager = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\PluginManager::class);
            $pluginManager->fireHook('invoice.deleted', [
                'invoice_id'     => (int)$params['id'],
                'invoice_number' => $invoice['invoice_number'] ?? '',
                'old_values'     => $invoice,
                'user_id'        => (int)$this->session->get('user_id'),
            ]);
        } catch (\Throwable) {}

        $this->invoiceService->delete((int)$params['id']);
        $this->session->flash('success', $this->translator->trans('invoices.deleted'));
        $this->redirect('/rechnungen');
    }

    public function updateStatus(array $params = []): void
    {
        $this->validateCsrf();

        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        $status = $this->sanitize($this->post('status', ''));
        /* GoBD: 'cancelled' und 'cancellation' dürfen nur über den Storno-Workflow gesetzt werden */
        $allowed = ['draft', 'open', 'paid', 'overdue', 'mahnung'];

        if (!in_array($status, $allowed, true)) {
            $this->session->flash('error', 'Ungültiger Status. Zum Stornieren bitte den Storno-Button verwenden.');
            $this->redirect("/rechnungen/{$params['id']}");
            return;
        }

        $paidAt = ($status === 'paid') ? date('Y-m-d H:i:s') : null;
        
        $this->invoiceService->updateStatus((int)$params['id'], $status, $paidAt);

        /* ── Automatischer Timeline-Eintrag bei Bezahlung ── */
        if ($status === 'paid' && $invoice['patient_id']) {
            $paidAtFormatted = date('d.m.Y \u\m H:i \U\h\r');
            try {
                $this->patientService->addTimelineEntry([
                    'patient_id'   => (int)$invoice['patient_id'],
                    'type'         => 'payment',
                    'title'        => 'Rechnung ' . ($invoice['invoice_number'] ?? '') . ' bezahlt',
                    'content'      => 'Rechnung am ' . $paidAtFormatted . ' als bezahlt markiert.',
                    'status_badge' => 'bezahlt',
                    'entry_date'   => date('Y-m-d H:i:s'),
                    'user_id'      => (int)$this->session->get('user_id'),
                ]);
            } catch (\Throwable) {}
        }

        $msg = match($status) {
            'paid'      => '✅ Rechnung als bezahlt markiert.',
            'open'      => 'Rechnung auf "Offen" gesetzt.',
            'draft'     => 'Rechnung als Entwurf gespeichert.',
            'overdue'   => '⚠️ Rechnung als überfällig markiert.',
            'mahnung'   => '📬 Rechnung als Mahnung markiert.',
            'cancelled' => '❌ Rechnung wurde storniert.',
            default     => $this->translator->trans('invoices.status_updated'),
        };
        $this->session->flash($status === 'paid' ? 'paid' : 'success', $msg);
        $this->redirect("/rechnungen/{$params['id']}");
    }

    public function updateStatusInline(array $params = []): void
    {
        $this->validateCsrf();

        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->json(['ok' => false, 'error' => 'Rechnung nicht gefunden'], 404);
        }

        $status  = $this->sanitize($this->post('status', ''));
        /* GoBD: 'cancelled' und 'cancellation' nur über Storno-Workflow */
        $allowed = ['draft', 'open', 'paid', 'overdue', 'mahnung'];

        if (!in_array($status, $allowed, true)) {
            $this->json(['ok' => false, 'error' => 'Ungültiger Status. Für Storno bitte /stornieren verwenden.'], 422);
        }

        $paidAt = ($status === 'paid') ? date('Y-m-d H:i:s') : null;
        
        $this->invoiceService->updateStatus((int)$params['id'], $status, $paidAt);

        if ($status === 'paid' && $invoice['patient_id']) {
            try {
                $this->patientService->addTimelineEntry([
                    'patient_id'   => (int)$invoice['patient_id'],
                    'type'         => 'payment',
                    'title'        => 'Rechnung ' . ($invoice['invoice_number'] ?? '') . ' bezahlt',
                    'content'      => 'Rechnung als bezahlt markiert.',
                    'status_badge' => 'bezahlt',
                    'entry_date'   => date('Y-m-d H:i:s'),
                    'user_id'      => (int)$this->session->get('user_id'),
                ]);
            } catch (\Throwable) {}
        }

        $this->json(['ok' => true, 'status' => $status]);
    }

    public function positionsJson(array $params = []): void
    {
        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'not found']);
            exit;
        }

        $positions = $this->invoiceService->getPositions((int)$params['id']);

        header('Content-Type: application/json');
        echo json_encode(['positions' => $positions]);
        exit;
    }

    public function preview(array $params = []): void
    {
        // Allow embedding in same-origin iframes (overrides server-level X-Frame-Options/CSP)
        header('X-Frame-Options: SAMEORIGIN', true);
        header("Content-Security-Policy: frame-ancestors 'self'", true);

        $invoice   = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        $positions = $this->invoiceService->getPositions((int)$params['id']);
        $owner     = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        $patient   = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;
        $settings  = $this->pdfService->getSettings();

        $this->render('invoices/preview.twig', [
            'page_title' => 'Rechnung ' . $invoice['invoice_number'],
            'invoice'    => $invoice,
            'positions'  => $positions,
            'owner'      => $owner,
            'patient'    => $patient,
            'settings'   => $settings,
        ]);
    }

    public function downloadPdf(array $params = []): void
    {
        $invoice   = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        $positions = $this->invoiceService->getPositions((int)$params['id']);
        $owner     = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        $patient   = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;

        $pdf = $this->pdfService->generateInvoicePdf($invoice, $positions, $owner, $patient);

        header('Content-Type: application/pdf');
        // Use inline so iframes/modals can display it, still downloadable via browser save
        header('Content-Disposition: inline; filename="Rechnung-' . $invoice['invoice_number'] . '.pdf"');
        echo $pdf;
        exit;
    }

    public function sendEmail(array $params = []): void
    {
        $this->validateCsrf();

        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        $owner = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        if (!$owner || empty($owner['email'])) {
            $this->session->flash('error', $this->translator->trans('invoices.no_email'));
            $this->redirect("/rechnungen/{$params['id']}");
            return;
        }

        $positions = $this->invoiceService->getPositions((int)$params['id']);
        $patient   = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;
        $pdf       = $this->pdfService->generateInvoicePdf($invoice, $positions, $owner, $patient);

        $sent = $this->mailService->sendInvoice($invoice, $owner, $pdf);

        if ($sent) {
            $this->invoiceService->markEmailSent((int)$params['id']);
            $this->session->flash('success', $this->translator->trans('invoices.email_sent'));
        } else {
            $err = $this->mailService->getLastError();
            $this->session->flash('error', $this->translator->trans('invoices.email_failed') . ($err ? ': ' . $err : ''));
        }

        $this->redirect("/rechnungen/{$params['id']}");
    }

    public function downloadReceipt(array $params = []): void
    {
        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        $positions = $this->invoiceService->getPositions((int)$params['id']);
        $owner     = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        $patient   = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;

        $pdf = $this->pdfService->generateReceiptPdf($invoice, $positions, $owner, $patient);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Quittung-' . $invoice['invoice_number'] . '.pdf"');
        echo $pdf;
        exit;
    }

    public function formData(array $params = []): void
    {
        $settings = $this->settingsRepository->all();
        $owners   = $this->ownerService->findAll();
        $patients = $this->patientService->findAll();

        $treatmentTypes = [];
        try { $treatmentTypes = $this->treatmentTypeRepository->findActive(); } catch (\Throwable) {}

        $this->json([
            'next_number'      => $this->invoiceService->generateInvoiceNumber(),
            'kleinunternehmer' => ($settings['kleinunternehmer'] ?? '0') === '1',
            'default_tax_rate' => $settings['default_tax_rate'] ?? '19',
            'issue_date'       => date('Y-m-d'),
            'due_date'         => date('Y-m-d', strtotime('+14 days')),
            'owners'           => array_values($owners),
            'patients'         => array_values($patients),
            'treatment_types'  => array_values($treatmentTypes),
        ]);
    }

    public function sendReceiptEmail(array $params = []): void
    {
        $this->validateCsrf();

        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        $owner = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        if (!$owner || empty($owner['email'])) {
            $this->session->flash('error', $this->translator->trans('invoices.no_email'));
            $this->redirect("/rechnungen/{$params['id']}");
            return;
        }

        $positions = $this->invoiceService->getPositions((int)$params['id']);
        $patient   = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;
        $pdf       = $this->pdfService->generateReceiptPdf($invoice, $positions, $owner, $patient);

        $sent = $this->mailService->sendReceipt($invoice, $owner, $pdf);

        if ($sent) {
            $this->session->flash('success', 'Quittung erfolgreich per E-Mail gesendet.');
        } else {
            $err = $this->mailService->getLastError();
            $this->session->flash('error', 'Quittung konnte nicht gesendet werden' . ($err ? ': ' . $err : ''));
        }

        $this->redirect("/rechnungen/{$params['id']}");
    }

    public function analytics(array $params = []): void
    {
        $this->requireAdmin();
        $this->render('invoices/analytics.twig', [
            'page_title' => 'Finanz-Analyse',
        ]);
    }

    public function analyticsJson(array $params = []): void
    {
        $this->requireAdmin();

        $summary            = $this->invoiceService->getFinancialSummary();
        $byMonth            = $this->invoiceService->getRevenueByMonth(24);
        $byQuarter          = $this->invoiceService->getRevenueByQuarter(3);
        $byYear             = $this->invoiceService->getRevenueByYear();
        $ownerSpeed         = $this->invoiceService->getOwnerPaymentSpeed();
        $ownerRevenue       = $this->invoiceService->getOwnerRevenue(15);
        $ownerActivity      = $this->invoiceService->getOwnerActivity(15);
        $ownerMonthly       = $this->invoiceService->getOwnerMonthlyRevenue(5);
        $aging              = $this->invoiceService->getOverdueAging();
        $payMethods         = $this->invoiceService->getPaymentMethodStats();
        $forecast           = $this->invoiceService->getRevenueForForecast(18);
        $topPositions       = $this->invoiceService->getTopPositions(10);

        /* ── Linear regression forecast (next 6 months) ── */
        $values = array_column($forecast, 'revenue');
        $n = count($values);
        $forecastMonths = [];
        if ($n >= 3) {
            $sumX = $sumY = $sumXY = $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) {
                $sumX  += $i;
                $sumY  += $values[$i];
                $sumXY += $i * $values[$i];
                $sumX2 += $i * $i;
            }
            $denom = ($n * $sumX2 - $sumX * $sumX);
            $slope = $denom != 0 ? ($n * $sumXY - $sumX * $sumY) / $denom : 0;
            $intercept = ($sumY - $slope * $sumX) / $n;
            for ($f = 1; $f <= 6; $f++) {
                $predictedVal = max(0, $intercept + $slope * ($n - 1 + $f));
                $forecastMonths[] = [
                    'month'   => date('M Y', strtotime('+' . $f . ' months')),
                    'value'   => round($predictedVal, 2),
                    'is_forecast' => true,
                ];
            }
        }

        /* ── Growth rate YoY ── */
        $thisYear  = (float)($summary['paid'] ?? 0);
        $lastYearRow = $this->invoiceService->getRevenueByMonth(13);
        $lastYearRev = array_sum(array_slice($lastYearRow['revenue'], 0, 12));
        $yoyGrowth = $lastYearRev > 0 ? round((array_sum(array_slice($lastYearRow['revenue'], 12)) - $lastYearRev) / $lastYearRev * 100, 1) : null;

        $this->json([
            'summary'          => $summary,
            'by_month'         => $byMonth,
            'by_quarter'       => $byQuarter,
            'by_year'          => $byYear,
            'owner_speed'      => $ownerSpeed,
            'owner_revenue'    => $ownerRevenue,
            'owner_activity'   => $ownerActivity,
            'owner_monthly'    => $ownerMonthly,
            'aging'            => $aging,
            'pay_methods'      => $payMethods,
            'forecast_history' => $forecast,
            'forecast_next'    => $forecastMonths,
            'top_positions'    => $topPositions,
            'yoy_growth'       => $yoyGrowth,
        ]);
    }

    /**
     * GoBD-konformer Storno-Workflow.
     * Erstellt eine Stornorechnung als Gegenbeleg — löscht NICHT die Originalrechnung.
     */
    public function cancel(array $params = []): void
    {
        $this->validateCsrf();

        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        $reason = trim($this->post('cancellation_reason', ''));

        try {
            $result = $this->cancellationService->cancel(
                (int)$params['id'],
                $reason,
                (int)$this->session->get('user_id')
            );

            $this->session->flash(
                'success',
                'Stornorechnung <strong>' . htmlspecialchars($result['cancellation_number'], ENT_QUOTES, 'UTF-8') . '</strong> wurde erfolgreich erstellt. '
                . '<a href="/rechnungen/' . $result['cancellation_id'] . '" style="color:inherit;text-decoration:underline;">Zur Stornorechnung</a>'
            );
            $this->redirect('/rechnungen/' . $result['cancellation_id']);
        } catch (\RuntimeException $e) {
            $this->session->flash('error', $e->getMessage());
            $this->redirect('/rechnungen/' . $params['id']);
        }
    }

    /**
     * PDF-Download der Stornorechnung.
     * Nur für Rechnungen mit invoice_type='cancellation'.
     */
    public function downloadCancellationPdf(array $params = []): void
    {
        $invoice = $this->invoiceService->findById((int)$params['id']);
        if (!$invoice) {
            $this->abort(404);
        }

        /* Kein Storno-Beleg → auf normales PDF umleiten */
        if (($invoice['invoice_type'] ?? 'normal') !== 'cancellation') {
            $this->redirect('/rechnungen/' . $params['id'] . '/pdf');
            return;
        }

        $positions = $this->invoiceService->getPositions((int)$params['id']);
        $owner     = $invoice['owner_id']   ? $this->ownerService->findById((int)$invoice['owner_id'])   : null;
        $patient   = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;

        $original = null;
        if (!empty($invoice['cancels_invoice_id'])) {
            $original = $this->invoiceService->findById((int)$invoice['cancels_invoice_id']);
        }

        $pdf = $this->pdfService->generateCancellationPdf($invoice, $positions, $owner, $patient, $original);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Stornorechnung-' . $invoice['invoice_number'] . '.pdf"');
        echo $pdf;
        exit;
    }

    private function parsePositions(): array
    {
        $descriptions = $_POST['position_description'] ?? [];
        $quantities   = $_POST['position_quantity']    ?? [];
        $prices       = $_POST['position_price']       ?? [];
        $taxRates     = $_POST['position_tax_rate']    ?? [];

        $positions = [];
        foreach ($descriptions as $i => $description) {
            if (empty(trim($description))) continue;
            $quantity  = (float)str_replace(',', '.', (string)($quantities[$i] ?? 1));
            $price     = (float)str_replace(',', '.', (string)($prices[$i] ?? 0));
            $taxRate   = (float)str_replace(',', '.', (string)($taxRates[$i] ?? 0));
            $positions[] = [
                'description' => htmlspecialchars(trim($description), ENT_QUOTES, 'UTF-8'),
                'quantity'    => $quantity,
                'unit_price'  => $price,
                'tax_rate'    => $taxRate,
                'total'       => round($quantity * $price, 2),
            ];
        }

        return $positions;
    }
}
