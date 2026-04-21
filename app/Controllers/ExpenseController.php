<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Repositories\ExpenseRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\SettingsRepository;
use App\Services\ReceiptParserService;

class ExpenseController extends Controller
{
    private static array $defaultCategories = [
        'Praxisbedarf',
        'Miete & Nebenkosten',
        'Fortbildung & Fachliteratur',
        'Marketing & Werbung',
        'Bürobedarf',
        'Software & IT',
        'Fahrtkosten',
        'Versicherungen',
        'Steuern & Abgaben',
        'Sonstiges',
    ];

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly ExpenseRepository  $expenseRepository,
        private readonly InvoiceRepository  $invoiceRepository,
        private readonly SettingsRepository $settingsRepository,
        private readonly Database $db,
        private readonly ReceiptParserService $receiptParser,
    ) {
        parent::__construct($view, $session, $config, $translator);
        /* Self-healing: Beleg-Spalten werden bei Bedarf ergänzt.
         * receipt_file           = Dateiname im tenant-spezifischen Storage
         * receipt_original_name  = Original-Dateiname für Downloads
         * receipt_mime           = MIME für korrekte Auslieferung
         * receipt_parsed_json    = Rohdaten aus dem Parser (für Debug/Re-Export) */
        $tbl = $this->db->prefix('expenses');
        if ($this->db->columnExists($tbl, 'description')) {
            $this->db->ensureColumn($tbl, 'receipt_file',          'VARCHAR(255) NULL DEFAULT NULL');
            $this->db->ensureColumn($tbl, 'receipt_original_name', 'VARCHAR(255) NULL DEFAULT NULL');
            $this->db->ensureColumn($tbl, 'receipt_mime',          'VARCHAR(100) NULL DEFAULT NULL');
            $this->db->ensureColumn($tbl, 'receipt_parsed_json',   'TEXT NULL DEFAULT NULL');
        }
    }

    public function index(array $params = []): void
    {
        $category = $this->get('category', '');
        $search   = $this->get('search', '');
        $page     = (int)$this->get('page', 1);

        $result     = $this->expenseRepository->getPaginated($page, 20, $category, $search);
        $stats      = $this->expenseRepository->getStats();
        $categories = array_unique(array_merge(
            self::$defaultCategories,
            $this->expenseRepository->getCategories()
        ));
        sort($categories);

        // Invoice revenue stats for net profit calculation
        $invoiceStats = $this->invoiceRepository->getStats();

        $this->render('expenses/index.twig', [
            'page_title'    => 'Ausgaben',
            'expenses'      => $result['items'],
            'pagination'    => $result,
            'stats'         => $stats,
            'invoice_stats' => $invoiceStats,
            'categories'    => $categories,
            'category'      => $category,
            'search'        => $search,
        ]);
    }

    public function create(array $params = []): void
    {
        $categories = array_unique(array_merge(
            self::$defaultCategories,
            $this->expenseRepository->getCategories()
        ));
        sort($categories);

        $this->render('expenses/form.twig', [
            'page_title' => 'Ausgabe erfassen',
            'expense'    => null,
            'categories' => $categories,
        ]);
    }

    public function store(array $params = []): void
    {
        $data = $this->buildData();

        if (!$data['description'] || !$data['date']) {
            $this->session->flash('error', 'Beschreibung und Datum sind Pflichtfelder.');
            $this->redirect('/ausgaben/neu');
            return;
        }

        /* Datei-Upload (PDF/Bild) verarbeiten. Bei Fehler: Ausgabe wird
         * trotzdem erstellt — der Nutzer kann das Dokument später nachreichen. */
        $receipt = $this->handleReceiptUpload();
        if ($receipt !== null) {
            $data['receipt_file']          = $receipt['file'];
            $data['receipt_original_name'] = $receipt['original'];
            $data['receipt_mime']          = $receipt['mime'];
            $data['receipt_parsed_json']   = $receipt['parsed'];
        }

        $this->expenseRepository->create($data);
        $this->session->flash('success', 'Ausgabe wurde erfasst.');
        $this->redirect('/ausgaben');
    }

    public function edit(array $params): void
    {
        $expense = $this->expenseRepository->findById((int)$params['id']);
        if (!$expense) {
            $this->redirect('/ausgaben');
            return;
        }

        $categories = array_unique(array_merge(
            self::$defaultCategories,
            $this->expenseRepository->getCategories()
        ));
        sort($categories);

        $this->render('expenses/form.twig', [
            'page_title' => 'Ausgabe bearbeiten',
            'expense'    => $expense,
            'categories' => $categories,
        ]);
    }

    public function update(array $params): void
    {
        $expense = $this->expenseRepository->findById((int)$params['id']);
        if (!$expense) {
            $this->redirect('/ausgaben');
            return;
        }

        $data = $this->buildData();

        /* Neuen Beleg hochladen (optional) — vorhandenen ersetzen */
        $receipt = $this->handleReceiptUpload();
        if ($receipt !== null) {
            /* Alten Beleg löschen */
            if (!empty($expense['receipt_file'])) {
                $oldPath = $this->receiptStoragePath() . '/' . $expense['receipt_file'];
                if (is_file($oldPath)) { @unlink($oldPath); }
            }
            $data['receipt_file']          = $receipt['file'];
            $data['receipt_original_name'] = $receipt['original'];
            $data['receipt_mime']          = $receipt['mime'];
            $data['receipt_parsed_json']   = $receipt['parsed'];
        }

        $this->expenseRepository->update((int)$params['id'], $data);
        $this->session->flash('success', 'Ausgabe wurde aktualisiert.');
        $this->redirect('/ausgaben');
    }

    /**
     * AJAX-Endpoint: Beleg hochladen, parsen, extrahierte Daten als JSON
     * zurückgeben. Wird von der Form-JS direkt beim File-Select aufgerufen,
     * damit der Nutzer die auto-gefüllten Werte noch prüfen kann, bevor er
     * die Ausgabe endgültig speichert.
     *
     * Die Datei wird noch NICHT in den finalen Storage-Pfad verschoben —
     * sie landet nur temporär im OS-Tempdir und wird parsed. Der eigentliche
     * Upload (mit Persistenz) passiert erst beim `store()` / `update()`.
     */
    public function previewReceipt(array $params = []): void
    {
        $this->validateCsrf();

        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'Keine Datei oder Upload-Fehler.']);
            return;
        }

        $file = $_FILES['receipt'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';

        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            $this->json(['ok' => false, 'error' => 'Dateityp nicht unterstützt. Erlaubt: PDF, JPG, PNG, WEBP.']);
            return;
        }

        $parsed = $this->receiptParser->parse($file['tmp_name'], $mime);

        $this->json([
            'ok'           => $parsed['ok'],
            'mime'         => $mime,
            'filename'     => $file['name'],
            'extracted'    => [
                'date'           => $parsed['date'],
                'amount_gross'   => $parsed['amount_gross'],
                'amount_net'     => $parsed['amount_net'],
                'tax_rate'       => $parsed['tax_rate'],
                'supplier'       => $parsed['supplier'],
                'invoice_number' => $parsed['invoice_number'],
                'description'    => $parsed['description'],
            ],
            'hint' => $parsed['ok']
                ? 'Bitte prüfe die automatisch befüllten Felder — sie wurden aus dem Beleg erkannt.'
                : 'Konnte keine Daten aus dem Beleg extrahieren. Bitte manuell befüllen.',
        ]);
    }

    /**
     * Stream den hochgeladenen Beleg (PDF/Bild) an den Browser.
     * Content-Type aus DB, Filename aus Original-Name.
     */
    public function serveReceipt(array $params = []): void
    {
        $expense = $this->expenseRepository->findById((int)($params['id'] ?? 0));
        if (!$expense || empty($expense['receipt_file'])) {
            http_response_code(404);
            echo 'Beleg nicht gefunden.';
            return;
        }

        $path = $this->receiptStoragePath() . '/' . $expense['receipt_file'];
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Beleg-Datei fehlt auf dem Server.';
            return;
        }

        $mime = (string)($expense['receipt_mime'] ?? 'application/octet-stream');
        $name = (string)($expense['receipt_original_name'] ?? $expense['receipt_file']);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=300');
        readfile($path);
        exit;
    }

    public function delete(array $params): void
    {
        $this->expenseRepository->delete((int)$params['id']);

        $wantsJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        if ($wantsJson) {
            $this->json(['ok' => true]);
            return;
        }
        $this->session->flash('success', 'Ausgabe wurde gelöscht.');
        $this->redirect('/ausgaben');
    }

    public function pdf(array $params): void
    {
        $expense = $this->expenseRepository->findById((int)$params['id']);
        if (!$expense) {
            $this->redirect('/ausgaben');
            return;
        }

        $settings = $this->settingsRepository->all();
        $pdf      = $this->generatePdf($expense, $settings);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Ausgabe_' . $params['id'] . '.pdf"');
        echo $pdf;
        exit;
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private function buildData(): array
    {
        $amountNet  = (float)str_replace(',', '.', $this->post('amount_net', '0'));
        $taxRate    = (float)str_replace(',', '.', $this->post('tax_rate', '19'));
        $amountGross = round($amountNet * (1 + $taxRate / 100), 2);

        return [
            'date'         => $this->post('date', date('Y-m-d')),
            'description'  => trim($this->post('description', '')),
            'category'     => trim($this->post('category', 'Sonstiges')),
            'supplier'     => trim($this->post('supplier', '')) ?: null,
            'amount_net'   => $amountNet,
            'tax_rate'     => $taxRate,
            'amount_gross' => $amountGross,
            'notes'        => trim($this->post('notes', '')) ?: null,
        ];
    }

    /**
     * Liefert den tenant-spezifischen Pfad zum Beleg-Storage.
     * Legt das Verzeichnis bei Bedarf an.
     */
    private function receiptStoragePath(): string
    {
        $prefix = $this->db->prefix('');
        $path   = rtrim(dirname(__DIR__, 2), '/\\') . '/storage/tenants/' . trim($prefix, '_') . '/expense_receipts';
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        return $path;
    }

    /**
     * Verarbeitet den Upload-Input `receipt`. Validiert MIME, speichert die
     * Datei tenant-scoped, parsed sie und gibt die Persistenz-Metadaten
     * zurück. Null bei keinem Upload oder Validierungsfehler.
     *
     * @return array{file:string, original:string, mime:string, parsed:string}|null
     */
    private function handleReceiptUpload(): ?array
    {
        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $file = $_FILES['receipt'];

        /* Max 10 MB — für normale Rechnungen mehr als ausreichend */
        if ((int)$file['size'] > 10 * 1024 * 1024) {
            $this->session->flash('warning', 'Beleg-Datei zu groß (max 10 MB). Ausgabe wurde ohne Anhang gespeichert.');
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';

        $extMap = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
        ];
        if (!isset($extMap[$mime])) {
            $this->session->flash('warning', 'Beleg-Dateityp nicht unterstützt (PDF/JPG/PNG/WEBP). Ausgabe ohne Anhang gespeichert.');
            return null;
        }

        /* Parser läuft auf dem tmp-Upload BEVOR wir ihn verschieben */
        $parsed = $this->receiptParser->parse($file['tmp_name'], $mime);

        $dest = $this->receiptStoragePath();
        $name = bin2hex(random_bytes(16)) . '.' . $extMap[$mime];
        if (!@move_uploaded_file($file['tmp_name'], $dest . '/' . $name)) {
            error_log('[Expense handleReceiptUpload] move_uploaded_file failed');
            return null;
        }

        return [
            'file'     => $name,
            'original' => substr((string)$file['name'], 0, 250),
            'mime'     => $mime,
            'parsed'   => json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ];
    }

    private function generatePdf(array $expense, array $settings): string
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Tierphysio Manager');
        $pdf->SetTitle('Ausgabe #' . $expense['id']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $company = $settings['company_name']  ?? '';
        $font    = 'helvetica';

        // Header
        $pdf->SetFont($font, 'B', 18);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Cell(0, 10, 'AUSGABENBELEG', 0, 1, 'L');

        $pdf->SetFont($font, '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, $company, 0, 1, 'L');
        $pdf->Ln(6);

        // Divider
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(6);

        // Fields
        $rows = [
            'Beleg-Nr.'    => '#' . $expense['id'],
            'Datum'        => date('d.m.Y', strtotime($expense['date'])),
            'Beschreibung' => $expense['description'],
            'Kategorie'    => $expense['category'],
            'Lieferant'    => $expense['supplier'] ?: '—',
            'Nettobetrag'  => number_format((float)$expense['amount_net'], 2, ',', '.') . ' €',
            'MwSt. (' . number_format((float)$expense['tax_rate'], 0) . '%)' =>
                number_format((float)$expense['amount_gross'] - (float)$expense['amount_net'], 2, ',', '.') . ' €',
            'Bruttobetrag' => number_format((float)$expense['amount_gross'], 2, ',', '.') . ' €',
        ];

        foreach ($rows as $label => $value) {
            $pdf->SetFont($font, '', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(50, 7, $label . ':', 0, 0, 'L');
            $pdf->SetFont($font, 'B', 9);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->Cell(0, 7, $value, 0, 1, 'L');
        }

        if (!empty($expense['notes'])) {
            $pdf->Ln(4);
            $pdf->SetFont($font, '', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(50, 6, 'Notizen:', 0, 0);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->MultiCell(0, 6, $expense['notes'], 0, 'L');
        }

        $pdf->Ln(6);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(4);
        $pdf->SetFont($font, '', 7);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 4, 'Erstellt am ' . date('d.m.Y H:i') . ' · ' . $company, 0, 1, 'C');

        return $pdf->Output('', 'S');
    }
}
