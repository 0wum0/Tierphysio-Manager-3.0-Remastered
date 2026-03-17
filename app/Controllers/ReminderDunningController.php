<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Repositories\ReminderDunningRepository;
use App\Repositories\SettingsRepository;
use App\Services\InvoiceService;
use App\Services\OwnerService;
use App\Services\PatientService;
use App\Services\MailService;
use App\Services\PdfService;

class ReminderDunningController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly ReminderDunningRepository $repo,
        private readonly InvoiceService $invoiceService,
        private readonly OwnerService $ownerService,
        private readonly PatientService $patientService,
        private readonly MailService $mailService,
        private readonly PdfService $pdfService,
        private readonly SettingsRepository $settingsRepository,
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo->ensureTables();
    }

    /* ══════════════════════════════════════════════════════════
       REMINDERS — LIST
    ══════════════════════════════════════════════════════════ */

    public function reminderIndex(array $params = []): void
    {
        $search  = $this->get('search', '');
        $status  = $this->get('status', '');
        $records = $this->repo->getAllReminders($search, $status);

        $this->render('reminders/index.twig', [
            'page_title' => 'Zahlungserinnerungen',
            'records'    => $records,
            'search'     => $search,
            'status'     => $status,
            'csrf_token' => $this->session->generateCsrfToken(),
            'success'    => $this->session->getFlash('success'),
            'error'      => $this->session->getFlash('error'),
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       REMINDERS — CREATE (POST from invoice modal)
    ══════════════════════════════════════════════════════════ */

    public function reminderStore(array $params = []): void
    {
        $this->validateCsrf();

        $invoiceId = (int)$params['id'];
        $invoice   = $this->invoiceService->findById($invoiceId);
        if (!$invoice) { $this->abort(404); }

        $defaultDays = (int)$this->settingsRepository->get('reminder_default_days', '7');

        $data = [
            'invoice_id' => $invoiceId,
            'due_date'   => $this->post('due_date') ?: date('Y-m-d', strtotime("+{$defaultDays} days")),
            'fee'        => 0.00,
            'notes'      => $this->post('notes', ''),
            'created_by' => (int)$this->session->get('user_id'),
        ];

        $reminderId = $this->repo->createReminder($data);

        /* Immediately send if owner has email */
        $owner   = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        $patient = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;

        $sent    = false;
        $sentMsg = '';

        if ($owner && !empty($owner['email'])) {
            $reminder = $this->repo->findReminderById($reminderId);
            $pdf      = $this->pdfService->generateReminderPdf($invoice, $reminder, $owner, $patient);
            $sent     = $this->mailService->sendInvoiceReminder($invoice, $reminder, $owner, $pdf);

            if ($sent) {
                $this->repo->markReminderSent($reminderId, $owner['email']);
                $sentMsg = 'Zahlungserinnerung erstellt und per E-Mail gesendet an ' . $owner['email'] . '.';
                $this->session->flash('success', $sentMsg);
            } else {
                $err     = $this->mailService->getLastError();
                $sentMsg = 'Erinnerung erstellt, aber E-Mail-Versand fehlgeschlagen' . ($err ? ': ' . $err : '') . '.';
                $this->session->flash('error', $sentMsg);
            }
        } else {
            $sentMsg = 'Zahlungserinnerung erstellt (kein E-Mail-Versand — keine Adresse hinterlegt).';
            $this->session->flash('success', $sentMsg);
        }

        if ($this->isAjax()) {
            $this->json(['ok' => true, 'message' => $sentMsg, 'reminder_id' => $reminderId]);
        }

        $this->redirect('/rechnungen#reminder-' . $reminderId);
    }

    /* ══════════════════════════════════════════════════════════
       REMINDERS — SEND EXISTING
    ══════════════════════════════════════════════════════════ */

    public function reminderSend(array $params = []): void
    {
        $this->validateCsrf();

        $reminder = $this->repo->findReminderById((int)$params['id']);
        if (!$reminder) { $this->abort(404); }

        $invoice = $this->invoiceService->findById((int)$reminder['invoice_id']);
        if (!$invoice) { $this->abort(404); }

        $owner   = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        $patient = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;

        if (!$owner || empty($owner['email'])) {
            $this->session->flash('error', 'Kein E-Mail-Versand möglich — keine E-Mail-Adresse beim Tierhalter hinterlegt.');
            $this->redirect('/mahnwesen/erinnerungen');
            return;
        }

        $pdf  = $this->pdfService->generateReminderPdf($invoice, $reminder, $owner, $patient);
        $sent = $this->mailService->sendInvoiceReminder($invoice, $reminder, $owner, $pdf);

        if ($sent) {
            $this->repo->markReminderSent((int)$params['id'], $owner['email']);
            $this->session->flash('success', 'Zahlungserinnerung erneut gesendet an ' . $owner['email'] . '.');
        } else {
            $err = $this->mailService->getLastError();
            $this->session->flash('error', 'E-Mail-Versand fehlgeschlagen' . ($err ? ': ' . $err : '') . '.');
        }

        $this->redirect('/mahnwesen/erinnerungen');
    }

    /* ══════════════════════════════════════════════════════════
       REMINDERS — PDF DOWNLOAD
    ══════════════════════════════════════════════════════════ */

    public function reminderPdf(array $params = []): void
    {
        $reminder = $this->repo->findReminderById((int)$params['id']);
        if (!$reminder) { $this->abort(404); }

        $invoice = $this->invoiceService->findById((int)$reminder['invoice_id']);
        if (!$invoice) { $this->abort(404); }

        $owner   = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        $patient = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;

        $pdf = $this->pdfService->generateReminderPdf($invoice, $reminder, $owner, $patient);
        $this->repo->markReminderPdfGenerated((int)$params['id']);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Erinnerung-' . $invoice['invoice_number'] . '.pdf"');
        echo $pdf;
        exit;
    }

    /* ══════════════════════════════════════════════════════════
       REMINDERS — DELETE
    ══════════════════════════════════════════════════════════ */

    public function reminderDelete(array $params = []): void
    {
        $this->validateCsrf();
        $this->repo->deleteReminder((int)$params['id']);
        $this->session->flash('success', 'Erinnerung gelöscht.');
        $this->redirect('/mahnwesen/erinnerungen');
    }

    /* ══════════════════════════════════════════════════════════
       DUNNINGS — LIST
    ══════════════════════════════════════════════════════════ */

    public function dunningIndex(array $params = []): void
    {
        $search  = $this->get('search', '');
        $status  = $this->get('status', '');
        $records = $this->repo->getAllDunnings($search, $status);

        $this->render('dunnings/index.twig', [
            'page_title' => 'Mahnungen',
            'records'    => $records,
            'search'     => $search,
            'status'     => $status,
            'csrf_token' => $this->session->generateCsrfToken(),
            'success'    => $this->session->getFlash('success'),
            'error'      => $this->session->getFlash('error'),
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       DUNNINGS — CREATE (POST from invoice modal)
    ══════════════════════════════════════════════════════════ */

    public function dunningStore(array $params = []): void
    {
        $this->validateCsrf();

        $invoiceId = (int)$params['id'];
        $invoice   = $this->invoiceService->findById($invoiceId);
        if (!$invoice) { $this->abort(404); }

        $defaultFee  = (float)$this->settingsRepository->get('dunning_default_fee', '5.00');
        $nextLevel   = $this->repo->getNextDunningLevel($invoiceId);

        $data = [
            'invoice_id' => $invoiceId,
            'level'      => $nextLevel,
            'due_date'   => $this->post('due_date') ?: date('Y-m-d', strtotime('+14 days')),
            'fee'        => (float)($this->post('fee') ?: $defaultFee),
            'notes'      => $this->post('notes', ''),
            'created_by' => (int)$this->session->get('user_id'),
        ];

        $dunningId = $this->repo->createDunning($data);

        /* Immediately send if owner has email */
        $owner   = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        $patient = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;

        if ($owner && !empty($owner['email'])) {
            $dunning = $this->repo->findDunningById($dunningId);
            $pdf     = $this->pdfService->generateDunningPdf($invoice, $dunning, $owner, $patient);
            $sent    = $this->mailService->sendInvoiceDunning($invoice, $dunning, $owner, $pdf);

            if ($sent) {
                $this->repo->markDunningSent($dunningId, $owner['email']);
                $levelLabel = $this->dunningLevelLabel($nextLevel);
                $this->session->flash('success', "{$levelLabel} erstellt und per E-Mail gesendet an " . $owner['email'] . '.');
            } else {
                $err = $this->mailService->getLastError();
                $this->session->flash('error', 'Mahnung erstellt, aber E-Mail-Versand fehlgeschlagen' . ($err ? ': ' . $err : '') . '.');
            }
        } else {
            $levelLabel = $this->dunningLevelLabel($nextLevel);
            $this->session->flash('success', "{$levelLabel} erstellt (kein E-Mail-Versand — keine Adresse hinterlegt).");
        }

        $this->redirect('/rechnungen#dunning-' . $dunningId);
    }

    /* ══════════════════════════════════════════════════════════
       DUNNINGS — SEND EXISTING
    ══════════════════════════════════════════════════════════ */

    public function dunningSend(array $params = []): void
    {
        $this->validateCsrf();

        $dunning = $this->repo->findDunningById((int)$params['id']);
        if (!$dunning) { $this->abort(404); }

        $invoice = $this->invoiceService->findById((int)$dunning['invoice_id']);
        if (!$invoice) { $this->abort(404); }

        $owner   = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        $patient = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;

        if (!$owner || empty($owner['email'])) {
            $this->session->flash('error', 'Kein E-Mail-Versand möglich — keine E-Mail-Adresse beim Tierhalter hinterlegt.');
            $this->redirect('/mahnwesen/mahnungen');
            return;
        }

        $pdf  = $this->pdfService->generateDunningPdf($invoice, $dunning, $owner, $patient);
        $sent = $this->mailService->sendInvoiceDunning($invoice, $dunning, $owner, $pdf);

        if ($sent) {
            $this->repo->markDunningSent((int)$params['id'], $owner['email']);
            $this->session->flash('success', 'Mahnung erneut gesendet an ' . $owner['email'] . '.');
        } else {
            $err = $this->mailService->getLastError();
            $this->session->flash('error', 'E-Mail-Versand fehlgeschlagen' . ($err ? ': ' . $err : '') . '.');
        }

        $this->redirect('/mahnwesen/mahnungen');
    }

    /* ══════════════════════════════════════════════════════════
       DUNNINGS — PDF DOWNLOAD
    ══════════════════════════════════════════════════════════ */

    public function dunningPdf(array $params = []): void
    {
        $dunning = $this->repo->findDunningById((int)$params['id']);
        if (!$dunning) { $this->abort(404); }

        $invoice = $this->invoiceService->findById((int)$dunning['invoice_id']);
        if (!$invoice) { $this->abort(404); }

        $owner   = $invoice['owner_id'] ? $this->ownerService->findById((int)$invoice['owner_id']) : null;
        $patient = $invoice['patient_id'] ? $this->patientService->findById((int)$invoice['patient_id']) : null;

        $pdf = $this->pdfService->generateDunningPdf($invoice, $dunning, $owner, $patient);
        $this->repo->markDunningPdfGenerated((int)$params['id']);

        $level = (int)($dunning['level'] ?? 1);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Mahnung' . $level . '-' . $invoice['invoice_number'] . '.pdf"');
        echo $pdf;
        exit;
    }

    /* ══════════════════════════════════════════════════════════
       DUNNINGS — DELETE
    ══════════════════════════════════════════════════════════ */

    public function dunningDelete(array $params = []): void
    {
        $this->validateCsrf();
        $this->repo->deleteDunning((int)$params['id']);
        $this->session->flash('success', 'Mahnung gelöscht.');
        $this->redirect('/mahnwesen/mahnungen');
    }

    /* ══════════════════════════════════════════════════════════
       API — invoice history (reminders + dunnings) as JSON
       Called from invoice detail modal
    ══════════════════════════════════════════════════════════ */

    public function apiInvoiceHistory(array $params = []): void
    {
        $invoiceId = (int)$params['id'];
        $this->json([
            'ok'        => true,
            'reminders' => $this->repo->getRemindersForInvoice($invoiceId),
            'dunnings'  => $this->repo->getDunningsForInvoice($invoiceId),
        ]);
    }

    public function alertJson(array $params = []): void
    {
        $invoices = $this->repo->getOverdueAlertInvoices();
        $this->json(['ok' => true, 'invoices' => $invoices]);
    }

    /* ── Helpers ── */

    private function dunningLevelLabel(int $level): string
    {
        return match ($level) {
            1 => '1. Mahnung',
            2 => '2. Mahnung',
            3 => 'Letzte Mahnung',
            default => 'Mahnung',
        };
    }
}
