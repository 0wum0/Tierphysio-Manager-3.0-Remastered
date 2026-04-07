<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Repositories\SaasInvoiceRepository;
use Saas\Repositories\TenantRepository;

class SaasInvoiceController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly SaasInvoiceRepository $invoiceRepo,
        private readonly TenantRepository      $tenantRepo
    ) {
        parent::__construct($view, $session);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function index(array $params = []): void
    {
        $this->requireAuth();
        $this->invoiceRepo->markOverdueAutomatic();

        $status = trim($this->get('status', ''));
        $search = trim($this->get('search', ''));
        $page   = max(1, (int)$this->get('page', 1));

        $result    = $this->invoiceRepo->getPaginated($page, 20, $status, $search);
        $stats     = $this->invoiceRepo->getStats();
        $chartData = $this->invoiceRepo->getMonthlyChartData();

        $this->render('admin/invoices/index.twig', [
            'page_title' => 'Rechnungsverwaltung',
            'invoices'   => $result['items'],
            'pagination' => $result,
            'stats'      => $stats,
            'chart_data' => $chartData,
            'status'     => $status,
            'search'     => $search,
        ]);
    }

    // ── Erstellen ─────────────────────────────────────────────────────────────

    public function create(array $params = []): void
    {
        $this->requireAuth();

        $tenants = $this->tenantRepo->all();

        $this->render('admin/invoices/create.twig', [
            'page_title'  => 'Rechnung erstellen',
            'tenants'     => $tenants,
            'next_number' => $this->invoiceRepo->getNextInvoiceNumber('TP'),
            'issue_date'  => date('Y-m-d'),
            'due_date'    => date('Y-m-d', strtotime('+14 days')),
            'preselected' => (int)$this->get('tenant_id', 0),
        ]);
    }

    public function store(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int)$this->post('tenant_id', 0);
        if (!$tenantId) {
            $this->session->flash('error', 'Bitte einen Kunden auswählen.');
            $this->redirect('/admin/invoices/create');
            return;
        }

        $paymentMethod = $this->post('payment_method', 'rechnung');
        if (!in_array($paymentMethod, ['rechnung','ueberweisung','lastschrift','bar'], true)) {
            $paymentMethod = 'rechnung';
        }
        $isCash = ($paymentMethod === 'bar');

        $positions = $this->parsePositions();
        if (empty($positions)) {
            $this->session->flash('error', 'Mindestens eine Rechnungsposition ist erforderlich.');
            $this->redirect('/admin/invoices/create');
            return;
        }

        $totals = $this->calculateTotals($positions);

        $data = [
            'tenant_id'      => $tenantId,
            'invoice_number' => trim($this->post('invoice_number', '')),
            'status'         => $isCash ? 'paid' : $this->post('status', 'draft'),
            'payment_method' => $paymentMethod,
            'issue_date'     => $this->post('issue_date') ?: date('Y-m-d'),
            'due_date'       => $isCash ? null : ($this->post('due_date') ?: null),
            'total_net'      => $totals['net'],
            'total_tax'      => $totals['tax'],
            'total_gross'    => $totals['gross'],
            'notes'          => $this->post('notes', ''),
            'payment_terms'  => $this->post('payment_terms', ''),
            'paid_at'        => $isCash ? date('Y-m-d H:i:s') : null,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        if (empty($data['invoice_number'])) {
            $data['invoice_number'] = $this->invoiceRepo->getNextInvoiceNumber('TP');
        }

        $id = $this->invoiceRepo->create($data);
        foreach ($positions as $i => $pos) {
            $this->invoiceRepo->addPosition((int)$id, $pos, $i + 1);
        }

        $sendEmail = $this->post('_send_email', '0') === '1';
        $this->session->flash('success', $isCash ? 'Quittung erstellt und als Barzahlung verbucht.' : 'Rechnung erfolgreich erstellt.');

        if ($sendEmail && !$isCash) {
            $this->doSendEmail((int)$id);
        }

        $this->redirect("/admin/invoices/{$id}");
    }

    // ── Anzeigen ──────────────────────────────────────────────────────────────

    public function show(array $params = []): void
    {
        $this->requireAuth();

        $invoice = $this->invoiceRepo->findById((int)$params['id']);
        if (!$invoice) { $this->notFound(); }

        $positions = $this->invoiceRepo->getPositions((int)$params['id']);
        $tenant    = $this->tenantRepo->find((int)$invoice['tenant_id']);

        $this->render('admin/invoices/show.twig', [
            'page_title' => 'Rechnung ' . $invoice['invoice_number'],
            'invoice'    => $invoice,
            'positions'  => $positions,
            'tenant'     => $tenant,
        ]);
    }

    // ── Bearbeiten ────────────────────────────────────────────────────────────

    public function edit(array $params = []): void
    {
        $this->requireAuth();

        $invoice = $this->invoiceRepo->findById((int)$params['id']);
        if (!$invoice) { $this->notFound(); }

        if (!empty($invoice['finalized_at'])) {
            $this->session->flash('error', 'Finalisierte Rechnungen können nicht bearbeitet werden (GoBD).');
            $this->redirect("/admin/invoices/{$params['id']}");
            return;
        }

        $positions = $this->invoiceRepo->getPositions((int)$params['id']);
        $tenants   = $this->tenantRepo->all();

        $this->render('admin/invoices/edit.twig', [
            'page_title' => 'Rechnung bearbeiten',
            'invoice'    => $invoice,
            'positions'  => $positions,
            'tenants'    => $tenants,
        ]);
    }

    public function update(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $invoice = $this->invoiceRepo->findById((int)$params['id']);
        if (!$invoice) { $this->notFound(); }

        if (!empty($invoice['finalized_at'])) {
            $this->session->flash('error', 'Finalisierte Rechnungen können nicht bearbeitet werden (GoBD).');
            $this->redirect("/admin/invoices/{$params['id']}");
            return;
        }

        $positions = $this->parsePositions();
        if (empty($positions)) {
            $this->session->flash('error', 'Mindestens eine Rechnungsposition ist erforderlich.');
            $this->redirect("/admin/invoices/{$params['id']}/edit");
            return;
        }

        $totals = $this->calculateTotals($positions);

        $data = [
            'tenant_id'      => (int)$this->post('tenant_id', $invoice['tenant_id']),
            'invoice_number' => trim($this->post('invoice_number', $invoice['invoice_number'])),
            'status'         => $this->post('status', $invoice['status']),
            'payment_method' => $this->post('payment_method', $invoice['payment_method']),
            'issue_date'     => $this->post('issue_date') ?: $invoice['issue_date'],
            'due_date'       => $this->post('due_date') ?: null,
            'total_net'      => $totals['net'],
            'total_tax'      => $totals['tax'],
            'total_gross'    => $totals['gross'],
            'notes'          => $this->post('notes', ''),
            'payment_terms'  => $this->post('payment_terms', ''),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $this->invoiceRepo->update((int)$params['id'], $data);
        $this->invoiceRepo->deletePositions((int)$params['id']);
        foreach ($positions as $i => $pos) {
            $this->invoiceRepo->addPosition((int)$params['id'], $pos, $i + 1);
        }

        $this->session->flash('success', 'Rechnung aktualisiert.');
        $this->redirect("/admin/invoices/{$params['id']}");
    }

    // ── Löschen ───────────────────────────────────────────────────────────────

    public function delete(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $invoice = $this->invoiceRepo->findById((int)$params['id']);
        if (!$invoice) { $this->notFound(); }

        if (!empty($invoice['finalized_at'])) {
            $this->session->flash('error', 'Finalisierte Rechnungen dürfen nicht gelöscht werden (GoBD §147 AO).');
            $this->redirect("/admin/invoices/{$params['id']}");
            return;
        }
        if (in_array($invoice['status'], ['paid', 'cancelled'], true)) {
            $this->session->flash('error', 'Bezahlte oder stornierte Rechnungen dürfen nicht gelöscht werden (GoBD).');
            $this->redirect("/admin/invoices/{$params['id']}");
            return;
        }

        $this->invoiceRepo->deletePositions((int)$params['id']);
        $this->invoiceRepo->delete((int)$params['id']);
        $this->session->flash('success', 'Rechnung gelöscht.');
        $this->redirect('/admin/invoices');
    }

    // ── Status-Update ─────────────────────────────────────────────────────────

    public function updateStatus(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $invoice = $this->invoiceRepo->findById((int)$params['id']);
        if (!$invoice) { $this->notFound(); }

        $status  = $this->post('status', '');
        $allowed = ['draft', 'open', 'paid', 'overdue', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            $this->session->flash('error', 'Ungültiger Status.');
            $this->redirect("/admin/invoices/{$params['id']}");
            return;
        }

        $paidAt = ($status === 'paid') ? date('Y-m-d H:i:s') : null;
        $this->invoiceRepo->updateStatus((int)$params['id'], $status, $paidAt);

        $msg = match($status) {
            'paid'      => '✅ Rechnung als bezahlt markiert.',
            'open'      => 'Rechnung auf "Offen" gesetzt.',
            'draft'     => 'Rechnung als Entwurf gespeichert.',
            'overdue'   => '⚠️ Rechnung als überfällig markiert.',
            'cancelled' => 'Rechnung storniert.',
            default     => 'Status aktualisiert.',
        };
        $this->session->flash('success', $msg);
        $this->redirect("/admin/invoices/{$params['id']}");
    }

    // ── PDF Download ──────────────────────────────────────────────────────────

    public function downloadPdf(array $params = []): void
    {
        $this->requireAuth();

        $invoice   = $this->invoiceRepo->findById((int)$params['id']);
        if (!$invoice) { $this->notFound(); }

        $positions = $this->invoiceRepo->getPositions((int)$params['id']);
        $tenant    = $this->tenantRepo->find((int)$invoice['tenant_id']);
        $settings  = $this->loadSettings();

        $pdf = $this->generatePdf($invoice, $positions, $tenant, $settings);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Rechnung-' . $invoice['invoice_number'] . '.pdf"');
        echo $pdf;
        exit;
    }

    // ── E-Mail senden ─────────────────────────────────────────────────────────

    public function sendEmail(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $invoice = $this->invoiceRepo->findById((int)$params['id']);
        if (!$invoice) { $this->notFound(); }

        $tenant = $this->tenantRepo->find((int)$invoice['tenant_id']);
        if (!$tenant || empty($tenant['email'])) {
            $this->session->flash('error', 'Kein E-Mail-Adresse beim Kunden hinterlegt.');
            $this->redirect("/admin/invoices/{$params['id']}");
            return;
        }

        $this->doSendEmail((int)$params['id']);
        $this->redirect("/admin/invoices/{$params['id']}");
    }

    // ── Steuerexport (CSV) ────────────────────────────────────────────────────

    public function taxExport(array $params = []): void
    {
        $this->requireAuth();

        $dateFrom = $this->get('date_from', date('Y-01-01'));
        $dateTo   = $this->get('date_to',   date('Y-m-d'));
        $format   = $this->get('format', 'csv');

        $invoices = $this->invoiceRepo->getForTaxExport($dateFrom, $dateTo);
        $summary  = $this->invoiceRepo->getTaxSummary($dateFrom, $dateTo);

        if ($format === 'csv') {
            $this->exportCsv($invoices, $summary, $dateFrom, $dateTo);
        }

        $this->render('admin/invoices/tax_export.twig', [
            'page_title' => 'Steuerexport',
            'invoices'   => $invoices,
            'summary'    => $summary,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
        ]);
    }

    // ── Finalisieren ──────────────────────────────────────────────────────────

    public function finalize(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $invoice = $this->invoiceRepo->findById((int)$params['id']);
        if (!$invoice) { $this->notFound(); }

        if (!empty($invoice['finalized_at'])) {
            $this->session->flash('info', 'Rechnung ist bereits finalisiert.');
            $this->redirect("/admin/invoices/{$params['id']}");
            return;
        }

        $this->invoiceRepo->update((int)$params['id'], [
            'finalized_at' => date('Y-m-d H:i:s'),
            'status'       => $invoice['status'] === 'draft' ? 'open' : $invoice['status'],
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->session->flash('success', '🔒 Rechnung finalisiert – GoBD-konform gesichert.');
        $this->redirect("/admin/invoices/{$params['id']}");
    }

    // ── Privat: Hilfsmethoden ─────────────────────────────────────────────────

    private function parsePositions(): array
    {
        $descriptions = $_POST['position_description'] ?? [];
        $quantities   = $_POST['position_quantity']    ?? [];
        $prices       = $_POST['position_price']       ?? [];
        $taxRates     = $_POST['position_tax_rate']    ?? [];

        $positions = [];
        foreach ($descriptions as $i => $desc) {
            if (empty(trim((string)$desc))) continue;
            $qty      = (float)str_replace(',', '.', (string)($quantities[$i] ?? 1));
            $price    = (float)str_replace(',', '.', (string)($prices[$i] ?? 0));
            $taxRate  = (float)str_replace(',', '.', (string)($taxRates[$i] ?? 19));
            $positions[] = [
                'description' => htmlspecialchars(trim((string)$desc), ENT_QUOTES, 'UTF-8'),
                'quantity'    => $qty,
                'unit_price'  => $price,
                'tax_rate'    => $taxRate,
                'total'       => round($qty * $price * (1 + $taxRate / 100), 2),
            ];
        }
        return $positions;
    }

    private function calculateTotals(array $positions): array
    {
        $net = $tax = 0.0;
        foreach ($positions as $pos) {
            $lineNet  = (float)$pos['quantity'] * (float)$pos['unit_price'];
            $lineTax  = $lineNet * ((float)$pos['tax_rate'] / 100);
            $net     += $lineNet;
            $tax     += $lineTax;
        }
        return [
            'net'   => round($net, 2),
            'tax'   => round($tax, 2),
            'gross' => round($net + $tax, 2),
        ];
    }

    private function doSendEmail(int $invoiceId): void
    {
        $invoice   = $this->invoiceRepo->findById($invoiceId);
        $tenant    = $this->tenantRepo->find((int)$invoice['tenant_id']);
        $positions = $this->invoiceRepo->getPositions($invoiceId);
        $settings  = $this->loadSettings();

        if (!$tenant || empty($tenant['email'])) {
            $this->session->flash('error', 'Kein E-Mail-Adresse beim Kunden hinterlegt.');
            return;
        }

        try {
            $pdf = $this->generatePdf($invoice, $positions, $tenant, $settings);
            $this->sendMail($tenant, $invoice, $pdf, $settings);
            $this->invoiceRepo->markEmailSent($invoiceId);
            $this->session->flash('success', '📧 Rechnung per E-Mail gesendet an ' . $tenant['email']);
        } catch (\Throwable $e) {
            $this->session->flash('error', 'E-Mail konnte nicht gesendet werden: ' . $e->getMessage());
        }
    }

    private function loadSettings(): array
    {
        try {
            $app = \Saas\Core\Application::getInstance();
            $db  = $app->getContainer()->get(\Saas\Core\Database::class);
            $rows = $db->fetchAll("SELECT `key`, `value` FROM saas_settings");
            $s = [];
            foreach ($rows as $r) { $s[$r['key']] = $r['value']; }
            return $s;
        } catch (\Throwable) {
            return [];
        }
    }

    private function generatePdf(array $invoice, array $positions, ?array $tenant, array $settings): string
    {
        ob_start();
        $tenantDisplay = $tenant
            ? (trim($tenant['practice_name'] ?? '') ?: trim($tenant['owner_name'] ?? ''))
            : 'Unbekannter Kunde';

        $companyName = $settings['company_name'] ?? 'TheraPano SaaS';
        $companyAddr = $settings['company_address'] ?? '';
        $companyCity = $settings['company_city'] ?? '';
        $companyZip  = $settings['company_zip'] ?? '';
        $companyEmail= $settings['company_email'] ?? '';
        $bankIban    = $settings['bank_iban'] ?? '';
        $bankBic     = $settings['bank_bic'] ?? '';
        $taxId       = $settings['tax_id'] ?? '';
        $vatId       = $settings['vat_id'] ?? '';
        $kleinunternehmer = ($settings['kleinunternehmer'] ?? '0') === '1';

        $logoData = '';
        if (!empty($settings['logo_path']) && file_exists($settings['logo_path'])) {
            $logoData = base64_encode(file_get_contents($settings['logo_path']));
        }

        $issueFormatted = \DateTime::createFromFormat('Y-m-d', $invoice['issue_date'] ?? date('Y-m-d'));
        $issueFormatted = $issueFormatted ? $issueFormatted->format('d.m.Y') : $invoice['issue_date'];
        $dueFormatted   = null;
        if (!empty($invoice['due_date'])) {
            $d = \DateTime::createFromFormat('Y-m-d', $invoice['due_date']);
            $dueFormatted = $d ? $d->format('d.m.Y') : $invoice['due_date'];
        }

        $statusLabels = [
            'draft'     => 'Entwurf',
            'open'      => 'Offen',
            'paid'      => 'Bezahlt',
            'overdue'   => 'Überfällig',
            'cancelled' => 'Storniert',
        ];

        $html  = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">';
        $html .= '<style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family: Arial, Helvetica, sans-serif; font-size:10pt; color:#1e293b; background:#fff; }
            .page { padding:16mm 20mm 20mm; }
            .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10mm; }
            .logo img { max-height:30mm; max-width:60mm; }
            .company-info { text-align:right; font-size:9pt; color:#475569; line-height:1.6; }
            .company-info strong { font-size:11pt; color:#0f172a; }
            .recipient { margin-bottom:8mm; }
            .recipient .sender-line { font-size:7pt; color:#94a3b8; border-bottom:1px solid #e2e8f0; margin-bottom:4mm; padding-bottom:1mm; }
            .recipient address { font-style:normal; line-height:1.7; }
            .invoice-meta { display:flex; justify-content:space-between; margin-bottom:8mm; }
            .invoice-title { font-size:16pt; font-weight:700; color:#0f172a; }
            .invoice-details { text-align:right; font-size:9pt; color:#475569; line-height:1.8; }
            .invoice-details strong { color:#0f172a; }
            table { width:100%; border-collapse:collapse; margin-bottom:6mm; }
            thead th { background:#f1f5f9; color:#374151; font-size:9pt; padding:3mm 2mm; text-align:left; border-bottom:2px solid #cbd5e1; }
            thead th.right { text-align:right; }
            tbody td { padding:2.5mm 2mm; font-size:9.5pt; border-bottom:1px solid #f1f5f9; vertical-align:top; }
            tbody td.right { text-align:right; white-space:nowrap; }
            tbody tr:hover td { background:#f8fafc; }
            .totals { margin-left:auto; width:72mm; margin-bottom:8mm; }
            .totals table { width:100%; }
            .totals td { padding:1.5mm 2mm; font-size:9.5pt; }
            .totals td.label { color:#475569; }
            .totals td.amount { text-align:right; font-weight:600; }
            .totals .gross td { border-top:2px solid #0f172a; font-size:11pt; font-weight:700; padding-top:2mm; }
            .note { margin-bottom:8mm; font-size:9pt; color:#475569; line-height:1.7; }
            .payment-info { background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:4mm; margin-bottom:8mm; font-size:9pt; }
            .payment-info strong { color:#0f172a; }
            .footer { border-top:1px solid #e2e8f0; padding-top:3mm; display:flex; justify-content:space-between; font-size:7.5pt; color:#94a3b8; }
            .stamp { display:inline-block; border:2px solid #22c55e; color:#16a34a; border-radius:4px; padding:1.5mm 4mm; font-size:10pt; font-weight:700; transform:rotate(-8deg); }
        </style></head><body><div class="page">';

        // Header
        $html .= '<div class="header"><div class="logo">';
        if ($logoData) {
            $html .= '<img src="data:image/png;base64,' . $logoData . '" alt="Logo">';
        } else {
            $html .= '<div style="font-size:18pt;font-weight:900;color:#2563eb;">' . htmlspecialchars($companyName) . '</div>';
        }
        $html .= '</div><div class="company-info">';
        $html .= '<strong>' . htmlspecialchars($companyName) . '</strong><br>';
        if ($companyAddr) $html .= htmlspecialchars($companyAddr) . '<br>';
        if ($companyZip || $companyCity) $html .= htmlspecialchars($companyZip . ' ' . $companyCity) . '<br>';
        if ($companyEmail) $html .= htmlspecialchars($companyEmail) . '<br>';
        if ($taxId)  $html .= 'StNr.: ' . htmlspecialchars($taxId) . '<br>';
        if ($vatId)  $html .= 'USt-IdNr.: ' . htmlspecialchars($vatId);
        $html .= '</div></div>';

        // Empfänger
        $html .= '<div class="recipient">';
        $html .= '<div class="sender-line">' . htmlspecialchars($companyName) . ' · ' . htmlspecialchars($companyZip . ' ' . $companyCity) . '</div>';
        $html .= '<address>';
        $html .= '<strong>' . htmlspecialchars($tenantDisplay) . '</strong><br>';
        if (!empty($tenant['address']))  $html .= htmlspecialchars($tenant['address'])  . '<br>';
        if (!empty($tenant['zip']) || !empty($tenant['city']))
            $html .= htmlspecialchars(($tenant['zip'] ?? '') . ' ' . ($tenant['city'] ?? '')) . '<br>';
        if (!empty($tenant['country']))  $html .= htmlspecialchars($tenant['country']);
        $html .= '</address></div>';

        // Rechnungsinfo
        $html .= '<div class="invoice-meta">';
        $html .= '<div>';
        $paymentMethodLabel = $invoice['payment_method'] === 'bar' ? 'Barzahlung (Quittung)' : 'Rechnung';
        $html .= '<div class="invoice-title">' . htmlspecialchars($paymentMethodLabel) . '</div>';
        if (($invoice['status'] ?? '') === 'paid') {
            $html .= '<div style="margin-top:2mm;"><span class="stamp">BEZAHLT</span></div>';
        }
        $html .= '</div>';
        $html .= '<div class="invoice-details">';
        $html .= 'Rechnungsnummer: <strong>' . htmlspecialchars($invoice['invoice_number']) . '</strong><br>';
        $html .= 'Rechnungsdatum: <strong>' . $issueFormatted . '</strong><br>';
        if ($dueFormatted) $html .= 'Fälligkeitsdatum: <strong>' . $dueFormatted . '</strong><br>';
        $html .= 'Kundennummer: <strong>K-' . str_pad((string)$invoice['tenant_id'], 4, '0', STR_PAD_LEFT) . '</strong>';
        $html .= '</div></div>';

        // Positionen
        $html .= '<table><thead><tr>';
        $html .= '<th style="width:5%">Pos.</th>';
        $html .= '<th style="width:45%">Beschreibung</th>';
        $html .= '<th class="right" style="width:8%">Menge</th>';
        $html .= '<th class="right" style="width:14%">Einzelpreis</th>';
        $html .= '<th class="right" style="width:10%">MwSt.</th>';
        $html .= '<th class="right" style="width:18%">Gesamt</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($positions as $i => $pos) {
            $html .= '<tr>';
            $html .= '<td>' . ($i + 1) . '</td>';
            $html .= '<td>' . nl2br(htmlspecialchars($pos['description'])) . '</td>';
            $html .= '<td class="right">' . number_format((float)$pos['quantity'], 2, ',', '.') . '</td>';
            $html .= '<td class="right">' . number_format((float)$pos['unit_price'], 2, ',', '.') . ' €</td>';
            $html .= '<td class="right">' . number_format((float)$pos['tax_rate'], 0, ',', '.') . ' %</td>';
            $html .= '<td class="right">' . number_format((float)$pos['total'], 2, ',', '.') . ' €</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        // Summen
        $html .= '<div class="totals"><table>';
        $html .= '<tr><td class="label">Nettobetrag</td><td class="amount">' . number_format((float)$invoice['total_net'], 2, ',', '.') . ' €</td></tr>';
        if ($kleinunternehmer) {
            $html .= '<tr><td class="label" colspan="2" style="font-size:8pt;color:#64748b;">Kein Steuerausweis gem. §19 UStG (Kleinunternehmer)</td></tr>';
        } else {
            $html .= '<tr><td class="label">Mehrwertsteuer</td><td class="amount">' . number_format((float)$invoice['total_tax'], 2, ',', '.') . ' €</td></tr>';
        }
        $html .= '<tr class="gross"><td class="label"><strong>Gesamtbetrag</strong></td><td class="amount"><strong>' . number_format((float)$invoice['total_gross'], 2, ',', '.') . ' €</strong></td></tr>';
        $html .= '</table></div>';

        // Hinweise / Zahlungsinfo
        if (!empty($invoice['notes'])) {
            $html .= '<div class="note"><strong>Hinweis:</strong><br>' . nl2br(htmlspecialchars($invoice['notes'])) . '</div>';
        }

        if (!empty($invoice['payment_terms'])) {
            $html .= '<div class="note">' . nl2br(htmlspecialchars($invoice['payment_terms'])) . '</div>';
        }

        if ($invoice['payment_method'] !== 'bar' && ($bankIban || $bankBic)) {
            $html .= '<div class="payment-info">';
            $html .= '<strong>Zahlungsinformationen</strong><br>';
            if ($bankIban) $html .= 'IBAN: ' . htmlspecialchars($bankIban) . '<br>';
            if ($bankBic)  $html .= 'BIC: ' . htmlspecialchars($bankBic) . '<br>';
            $html .= 'Verwendungszweck: ' . htmlspecialchars($invoice['invoice_number']);
            $html .= '</div>';
        }

        // Footer
        $html .= '<div class="footer">';
        $html .= '<span>' . htmlspecialchars($companyName) . '</span>';
        $html .= '<span>Seite 1 von 1</span>';
        $html .= '<span>Erstellt am ' . date('d.m.Y') . '</span>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        // dompdf verwenden falls verfügbar, sonst HTML zurückgeben
        try {
            $dompdfPaths = [
                dirname(__DIR__, 3) . '/vendor/dompdf/dompdf/src/Dompdf.php',
                dirname(__DIR__, 4) . '/vendor/dompdf/dompdf/src/Dompdf.php',
            ];
            $loaded = false;
            foreach ($dompdfPaths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $loaded = true;
                    break;
                }
            }

            if ($loaded && class_exists('\Dompdf\Dompdf')) {
                ob_end_clean();
                $options = new \Dompdf\Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', false);
                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                return $dompdf->output();
            }
        } catch (\Throwable) {}

        // Fallback: HTML als "PDF" servieren (kein dompdf installiert)
        ob_end_clean();
        return $html;
    }

    private function sendMail(array $tenant, array $invoice, string $pdfContent, array $settings): void
    {
        $toEmail = $tenant['email'];
        $toName  = trim($tenant['practice_name'] ?? '') ?: trim($tenant['owner_name'] ?? '');

        $fromEmail = $settings['mail_from_address'] ?? ($settings['company_email'] ?? 'noreply@therapano.de');
        $fromName  = $settings['mail_from_name']    ?? ($settings['company_name']  ?? 'TheraPano SaaS');

        $subject = 'Ihre Rechnung ' . $invoice['invoice_number'];
        $body    = "Sehr geehrte Damen und Herren,\r\n\r\n"
                 . "im Anhang finden Sie Ihre Rechnung " . $invoice['invoice_number'] . " vom " . date('d.m.Y') . ".\r\n\r\n"
                 . "Gesamtbetrag: " . number_format((float)$invoice['total_gross'], 2, ',', '.') . " €\r\n\r\n"
                 . "Mit freundlichen Grüßen\r\n"
                 . $fromName;

        $boundary  = md5(uniqid('', true));
        $pdfBase64 = chunk_split(base64_encode($pdfContent));

        $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        $message  = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $body . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: application/pdf; name=\"Rechnung-{$invoice['invoice_number']}.pdf\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"Rechnung-{$invoice['invoice_number']}.pdf\"\r\n\r\n";
        $message .= $pdfBase64 . "\r\n";
        $message .= "--{$boundary}--";

        $toStr = $toName ? "=?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>" : $toEmail;

        // PHPMailer falls verfügbar, sonst PHP mail()
        try {
            $mailerPaths = [
                dirname(__DIR__, 3) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
                dirname(__DIR__, 4) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
            ];
            $loaded = false;
            foreach ($mailerPaths as $path) {
                if (file_exists($path)) { require_once $path; $loaded = true; break; }
            }

            if ($loaded && class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $settings['smtp_host'] ?? 'localhost';
                $mail->Port       = (int)($settings['smtp_port'] ?? 587);
                $mail->SMTPAuth   = !empty($settings['smtp_username']);
                $mail->Username   = $settings['smtp_username'] ?? '';
                $mail->Password   = $settings['smtp_password'] ?? '';
                $mail->SMTPSecure = $settings['smtp_encryption'] ?? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom($fromEmail, $fromName);
                $mail->addAddress($toEmail, $toName);
                $mail->Subject    = $subject;
                $mail->Body       = $body;
                $mail->addStringAttachment($pdfContent, 'Rechnung-' . $invoice['invoice_number'] . '.pdf', 'base64', 'application/pdf');
                $mail->send();
                return;
            }
        } catch (\Throwable) {}

        // Fallback: PHP mail()
        if (!mail($toStr, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, $headers)) {
            throw new \RuntimeException('mail() gibt false zurück – prüfen Sie die PHP-Mailkonfiguration.');
        }
    }

    private function exportCsv(array $invoices, array $summary, string $dateFrom, string $dateTo): void
    {
        $filename = 'Steuerexport_' . str_replace('-', '', $dateFrom) . '_' . str_replace('-', '', $dateTo) . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM für Excel

        $fp = fopen('php://output', 'w');

        // Steuer-Zusammenfassung oben
        fputcsv($fp, ['=== STEUER-ZUSAMMENFASSUNG ==='], ';');
        fputcsv($fp, ['Steuersatz', 'Anzahl Rechnungen', 'Nettobetrag', 'Steuerbetrag', 'Bruttobetrag'], ';');
        foreach ($summary as $row) {
            fputcsv($fp, [
                number_format((float)$row['tax_rate'], 0) . '%',
                $row['invoice_count'],
                number_format((float)$row['total_net'], 2, ',', '.') . ' €',
                number_format((float)$row['total_tax'], 2, ',', '.') . ' €',
                number_format((float)$row['total_gross'], 2, ',', '.') . ' €',
            ], ';');
        }

        fputcsv($fp, [], ';');
        fputcsv($fp, ['=== EINZELNE RECHNUNGEN ==='], ';');
        fputcsv($fp, [
            'Rechnungsnummer', 'Datum', 'Fällig bis', 'Kunde', 'E-Mail',
            'Status', 'Zahlungsart',
            'Netto (€)', 'MwSt. (€)', 'Brutto (€)',
            'Bezahlt am',
        ], ';');

        $statusMap = [
            'draft'=>'Entwurf','open'=>'Offen','paid'=>'Bezahlt',
            'overdue'=>'Überfällig','cancelled'=>'Storniert',
        ];
        $methodMap = [
            'rechnung'=>'Rechnung','ueberweisung'=>'Überweisung',
            'lastschrift'=>'Lastschrift','bar'=>'Bar',
        ];

        foreach ($invoices as $inv) {
            $paidAt = '';
            if (!empty($inv['paid_at'])) {
                $d = \DateTime::createFromFormat('Y-m-d H:i:s', $inv['paid_at']);
                $paidAt = $d ? $d->format('d.m.Y') : $inv['paid_at'];
            }
            $issueDate = '';
            if (!empty($inv['issue_date'])) {
                $d = \DateTime::createFromFormat('Y-m-d', $inv['issue_date']);
                $issueDate = $d ? $d->format('d.m.Y') : $inv['issue_date'];
            }
            $dueDate = '';
            if (!empty($inv['due_date'])) {
                $d = \DateTime::createFromFormat('Y-m-d', $inv['due_date']);
                $dueDate = $d ? $d->format('d.m.Y') : $inv['due_date'];
            }
            fputcsv($fp, [
                $inv['invoice_number'],
                $issueDate,
                $dueDate,
                $inv['tenant_display'] ?? '',
                $inv['tenant_email'] ?? '',
                $statusMap[$inv['status']] ?? $inv['status'],
                $methodMap[$inv['payment_method']] ?? $inv['payment_method'],
                number_format((float)$inv['total_net'],   2, ',', '.'),
                number_format((float)$inv['total_tax'],   2, ',', '.'),
                number_format((float)$inv['total_gross'], 2, ',', '.'),
                $paidAt,
            ], ';');
        }

        fclose($fp);
        exit;
    }
}
