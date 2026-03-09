<?php

declare(strict_types=1);

namespace Plugins\TaxExportPro;

use App\Core\Database;
use App\Repositories\SettingsRepository;
use TCPDF;

class TaxExportService
{
    private TaxExportRepository $repo;
    private SettingsRepository  $settingsRepo;

    public function __construct(Database $db, SettingsRepository $settingsRepo)
    {
        $this->repo         = new TaxExportRepository($db);
        $this->settingsRepo = $settingsRepo;
    }

    // ── Public API ──────────────────────────────────────────────────────────

    public function getStats(string $dateFrom, string $dateTo): array
    {
        return $this->repo->getStatsForPeriod($dateFrom, $dateTo);
    }

    public function getInvoices(string $dateFrom, string $dateTo, string $statusFilter): array
    {
        return $this->repo->findForPeriod($dateFrom, $dateTo, $statusFilter);
    }

    public function getRecentLogs(int $limit = 20): array
    {
        return $this->repo->getRecentLogs($limit);
    }

    public function getAllSettings(): array
    {
        return $this->repo->getAllSettings();
    }

    public function saveSetting(string $key, string $value): void
    {
        $this->repo->setSetting($key, $value);
    }

    // ── CSV Export ───────────────────────────────────────────────────────────

    /**
     * Build the einnahmen.csv content as a string (UTF-8 with BOM for Excel).
     */
    public function buildEinnahmenCsv(array $invoices, string $delimiter = ';'): string
    {
        $d = $delimiter;

        $rows   = [];
        $rows[] = implode($d, [
            'Rechnungsnummer',
            'Rechnungsdatum',
            'Zahlungsdatum',
            'Status',
            'Besitzer',
            'Patient',
            'Betrag Netto',
            'Betrag Brutto',
            'MwSt',
            'Zahlungsart',
            'Verwendungszweck',
        ]);

        foreach ($invoices as $inv) {
            $rows[] = implode($d, [
                $this->csvCell($inv['invoice_number'] ?? ''),
                $this->csvCell($this->formatDate($inv['issue_date'] ?? '')),
                $this->csvCell($inv['paid_at'] ? $this->formatDate($inv['paid_at']) : ''),
                $this->csvCell($this->translateStatus($inv['status'] ?? '')),
                $this->csvCell($inv['owner_name'] ?? ''),
                $this->csvCell($inv['patient_name'] ?? ''),
                $this->csvCell($this->formatMoney((float)($inv['total_net'] ?? 0))),
                $this->csvCell($this->formatMoney((float)($inv['total_gross'] ?? 0))),
                $this->csvCell($this->formatMoney((float)($inv['total_tax'] ?? 0))),
                $this->csvCell($this->translatePaymentMethod($inv['payment_method'] ?? 'rechnung')),
                $this->csvCell($inv['payment_terms'] ?? ''),
            ]);
        }

        // UTF-8 BOM for Excel/LibreOffice compatibility
        return "\xEF\xBB\xBF" . implode("\r\n", $rows) . "\r\n";
    }

    /**
     * Build the rechnungsjournal.csv content as a string.
     */
    public function buildRechnungsjournalCsv(array $invoices, string $delimiter = ';'): string
    {
        $d = $delimiter;

        $rows   = [];
        $rows[] = implode($d, [
            'ID',
            'Rechnungsnummer',
            'Datum',
            'Fälligkeit',
            'Besitzer',
            'Patient',
            'Betrag Netto',
            'Betrag Brutto',
            'Status',
            'Zahlungsart',
            'E-Mail gesendet',
            'Erstellt am',
            'Aktualisiert am',
        ]);

        foreach ($invoices as $inv) {
            $rows[] = implode($d, [
                $this->csvCell((string)($inv['id'] ?? '')),
                $this->csvCell($inv['invoice_number'] ?? ''),
                $this->csvCell($this->formatDate($inv['issue_date'] ?? '')),
                $this->csvCell($inv['due_date'] ? $this->formatDate($inv['due_date']) : ''),
                $this->csvCell($inv['owner_name'] ?? ''),
                $this->csvCell($inv['patient_name'] ?? ''),
                $this->csvCell($this->formatMoney((float)($inv['total_net'] ?? 0))),
                $this->csvCell($this->formatMoney((float)($inv['total_gross'] ?? 0))),
                $this->csvCell($this->translateStatus($inv['status'] ?? '')),
                $this->csvCell($this->translatePaymentMethod($inv['payment_method'] ?? 'rechnung')),
                $this->csvCell($inv['email_sent_at'] ? $this->formatDate($inv['email_sent_at']) : ''),
                $this->csvCell($this->formatDate($inv['created_at'] ?? '')),
                $this->csvCell($this->formatDate($inv['updated_at'] ?? '')),
            ]);
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $rows) . "\r\n";
    }

    // ── PDF Report ───────────────────────────────────────────────────────────

    /**
     * Generate a compact PDF tax report and return it as a binary string.
     */
    public function buildPdfReport(
        array  $invoices,
        array  $stats,
        string $dateFrom,
        string $dateTo,
        string $statusFilter
    ): string {
        $settings    = $this->settingsRepo->all();
        $companyName = $settings['company_name'] ?? 'Tierphysio Manager';
        $taxNumber   = $settings['tax_number']   ?? '';

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // ── Header bar ──────────────────────────────────────────────────────
        $pdf->SetFillColor(30, 41, 59);
        $pdf->Rect(0, 0, 210, 28, 'F');

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(20, 8);
        $pdf->Cell(0, 8, 'Steuer-Export-Bericht', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(20, 17);
        $pdf->Cell(0, 6, $companyName . ($taxNumber ? '  |  St.-Nr.: ' . $taxNumber : ''), 0, 1, 'L');

        // ── Period line ──────────────────────────────────────────────────────
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetY(35);
        $pdf->SetX(20);

        $periodLabel  = 'Zeitraum: ' . $this->formatDate($dateFrom) . ' – ' . $this->formatDate($dateTo);
        $filterLabel  = 'Filter: ' . $this->translateStatusLabel($statusFilter);
        $generatedAt  = 'Erstellt: ' . date('d.m.Y H:i') . ' Uhr';

        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(90, 6, $periodLabel, 0, 0, 'L');
        $pdf->Cell(60, 6, $filterLabel, 0, 0, 'L');
        $pdf->Cell(0,  6, $generatedAt, 0, 1, 'R');

        $pdf->Ln(3);

        // ── Stats cards ──────────────────────────────────────────────────────
        $this->pdfSection($pdf, 'Übersicht');

        $cardData = [
            ['Rechnungen gesamt',   (string)$stats['total'],                         ''],
            ['Bezahlt',             (string)$stats['paid'],                           ''],
            ['Offen',               (string)$stats['open'],                           ''],
            ['Überfällig',          (string)$stats['overdue'],                        ''],
            ['Entwurf',             (string)$stats['draft'],                          ''],
            ['Einnahmen (bezahlt)', $this->formatMoney($stats['sumPaid']) . ' €',     ''],
            ['Summe gesamt',        $this->formatMoney($stats['sumAll'])  . ' €',     ''],
            ['Offene Beträge',      $this->formatMoney($stats['sumOpen']) . ' €',     ''],
            ['Netto (bezahlt)',     $this->formatMoney($stats['sumNet'])  . ' €',     ''],
            ['MwSt (bezahlt)',      $this->formatMoney($stats['sumTax'])  . ' €',     ''],
        ];

        $col  = 0;
        $colW = 55;
        $colH = 14;
        $startX = 20;
        $x    = $startX;
        $y    = $pdf->GetY();

        foreach ($cardData as $card) {
            if ($col === 3) {
                $col = 0;
                $x   = $startX;
                $y  += $colH + 3;
            }
            $pdf->SetXY($x, $y);
            $pdf->SetFillColor(241, 245, 249);
            $pdf->RoundedRect($x, $y, $colW, $colH, 2, '1111', 'F');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetXY($x + 3, $y + 2);
            $pdf->Cell($colW - 6, 4, $card[0], 0, 1, 'L');
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->SetXY($x + 3, $y + 6);
            $pdf->Cell($colW - 6, 6, $card[1], 0, 1, 'L');
            $x  += $colW + 2;
            $col++;
        }

        $pdf->SetY($y + $colH + 8);
        $pdf->SetTextColor(30, 41, 59);

        // ── Invoice table ────────────────────────────────────────────────────
        $this->pdfSection($pdf, 'Rechnungsübersicht');

        // Table header
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 7.5);

        $cols = [
            ['Rechnungsnr.', 30],
            ['Datum',        20],
            ['Besitzer',     40],
            ['Patient',      25],
            ['Status',       20],
            ['Zahlungsart',  22],
            ['Brutto',       13],
        ];

        foreach ($cols as [$label, $w]) {
            $pdf->Cell($w, 6, $label, 0, 0, 'L', true);
        }
        $pdf->Ln();

        // Table rows
        $pdf->SetFont('helvetica', '', 7);
        $altRow = false;
        foreach ($invoices as $inv) {
            $pdf->SetTextColor(15, 23, 42);
            if ($altRow) {
                $pdf->SetFillColor(248, 250, 252);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            $altRow = !$altRow;

            $fill = true;
            $pdf->Cell(30, 5, $this->truncate($inv['invoice_number'] ?? '', 18), 0, 0, 'L', $fill);
            $pdf->Cell(20, 5, $this->formatDate($inv['issue_date'] ?? ''), 0, 0, 'L', $fill);
            $pdf->Cell(40, 5, $this->truncate($inv['owner_name'] ?? '—', 24), 0, 0, 'L', $fill);
            $pdf->Cell(25, 5, $this->truncate($inv['patient_name'] ?? '—', 15), 0, 0, 'L', $fill);
            $pdf->Cell(20, 5, $this->translateStatus($inv['status'] ?? ''), 0, 0, 'L', $fill);
            $pdf->Cell(22, 5, $this->translatePaymentMethod($inv['payment_method'] ?? 'rechnung'), 0, 0, 'L', $fill);
            $pdf->Cell(13, 5, $this->formatMoney((float)($inv['total_gross'] ?? 0)), 0, 0, 'R', $fill);
            $pdf->Ln();

            // Page break guard
            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
                $pdf->SetFillColor(30, 41, 59);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('helvetica', 'B', 7.5);
                foreach ($cols as [$label, $w]) {
                    $pdf->Cell($w, 6, $label, 0, 0, 'L', true);
                }
                $pdf->Ln();
                $pdf->SetFont('helvetica', '', 7);
            }
        }

        // ── Footer note ──────────────────────────────────────────────────────
        $pdf->SetY(-25);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(148, 163, 184);
        $pdf->Cell(0, 6, 'Dieser Bericht wurde automatisch erstellt. Kein Rechtsanspruch. Alle Angaben ohne Gewähr.', 0, 1, 'C');
        $pdf->Cell(0, 5, $companyName . ' · ' . date('d.m.Y H:i') . ' · TaxExportPro Plugin', 0, 1, 'C');

        return $pdf->Output('', 'S');
    }

    // ── ZIP Export ───────────────────────────────────────────────────────────

    /**
     * Build a ZIP archive with:
     *   /rechnungen/   — individual invoice PDFs
     *   /export/       — einnahmen.csv, rechnungsjournal.csv, steuerbericht.pdf
     *
     * Returns path to temp ZIP file. Caller must delete after sending.
     */
    public function buildZip(
        array  $invoices,
        array  $stats,
        string $dateFrom,
        string $dateTo,
        string $statusFilter,
        string $delimiter
    ): string {
        if (!class_exists('\ZipArchive')) {
            throw new \RuntimeException('ZipArchive extension is not available on this server.');
        }

        $tmpDir  = sys_get_temp_dir();
        $zipPath = $tmpDir . '/tax-export-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Konnte ZIP-Archiv nicht erstellen: ' . $zipPath);
        }

        // ── Invoice PDFs ─────────────────────────────────────────────────────
        $db             = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
        $settingsRepo   = $this->settingsRepo;
        $translator     = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Translator::class);
        $pdfService     = new \App\Services\PdfService($settingsRepo, $translator);
        $ownerRepo      = new \App\Repositories\OwnerRepository($db);
        $patientRepo    = new \App\Repositories\PatientRepository($db);

        foreach ($invoices as $inv) {
            try {
                $positions = $this->repo->getPositions((int)$inv['id']);
                $ownerId   = !empty($inv['owner_id'])   ? (int)$inv['owner_id']   : null;
                $patientId = !empty($inv['patient_id']) ? (int)$inv['patient_id'] : null;
                $owner     = $ownerId   ? $ownerRepo->findById($ownerId)     : null;
                $patient   = $patientId ? $patientRepo->findById($patientId) : null;

                // Use the existing PdfService — no duplication, no breakage
                $pdfBytes = $pdfService->generateInvoicePdf($inv, $positions, $owner, $patient);
                $filename = 'rechnungen/Rechnung-' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $inv['invoice_number']) . '.pdf';
                $zip->addFromString($filename, $pdfBytes);
            } catch (\Throwable) {
                /* Skip a single failed PDF — do not abort entire ZIP */
            }
        }

        // ── CSV files ────────────────────────────────────────────────────────
        $hashes = [];

        $einnahmenCsv = $this->buildEinnahmenCsv($invoices, $delimiter);
        $zip->addFromString('export/einnahmen.csv', $einnahmenCsv);
        $hashes['export/einnahmen.csv'] = hash('sha256', $einnahmenCsv);

        $journalCsv = $this->buildRechnungsjournalCsv($invoices, $delimiter);
        $zip->addFromString('export/rechnungsjournal.csv', $journalCsv);
        $hashes['export/rechnungsjournal.csv'] = hash('sha256', $journalCsv);

        $datevCsv = $this->buildDatevCsv($invoices, $dateFrom, $dateTo);
        $zip->addFromString('export/datev_buchungsstapel.csv', $datevCsv);
        $hashes['export/datev_buchungsstapel.csv'] = hash('sha256', $datevCsv);

        // ── PDF report ───────────────────────────────────────────────────────
        $pdfReport = $this->buildPdfReport($invoices, $stats, $dateFrom, $dateTo, $statusFilter);
        $zip->addFromString('export/steuerbericht.pdf', $pdfReport);
        $hashes['export/steuerbericht.pdf'] = hash('sha256', $pdfReport);

        // ── GoBD Manifest with SHA-256 hashes ────────────────────────────────
        $manifest = $this->buildZipManifest($invoices, $dateFrom, $dateTo, $statusFilter, $hashes);
        $zip->addFromString('export/MANIFEST.txt', $manifest);

        // ── README ───────────────────────────────────────────────────────────
        $readme  = "TaxExportPro – Steuerexport\r\n";
        $readme .= "============================\r\n\r\n";
        $readme .= "Exportiert am: " . date('d.m.Y H:i') . " Uhr\r\n";
        $readme .= "Zeitraum:      " . $this->formatDate($dateFrom) . " – " . $this->formatDate($dateTo) . "\r\n";
        $readme .= "Filter:        " . $this->translateStatusLabel($statusFilter) . "\r\n";
        $readme .= "Rechnungen:    " . count($invoices) . "\r\n\r\n";
        $readme .= "Inhalt:\r\n";
        $readme .= "  /rechnungen/                         Einzel-PDFs aller Rechnungen\r\n";
        $readme .= "  /export/einnahmen.csv                Einnahmen-Übersicht (Excel)\r\n";
        $readme .= "  /export/rechnungsjournal.csv         Vollständiges Rechnungsjournal\r\n";
        $readme .= "  /export/datev_buchungsstapel.csv     DATEV-Import-Datei (Format EXTF v510)\r\n";
        $readme .= "  /export/steuerbericht.pdf            Kompakter Steuerbericht\r\n";
        $readme .= "  /export/MANIFEST.txt                 SHA-256 Prüfsummen (GoBD)\r\n\r\n";
        $readme .= "GoBD-Hinweis:\r\n";
        $readme .= "  Dieser Export dient als maschinell auswertbare Buchführungshilfe\r\n";
        $readme .= "  gem. GoBD (BMF-Schreiben v. 28.11.2019). Die DATEV-CSV kann direkt\r\n";
        $readme .= "  an Ihren Steuerberater / in DATEV Unternehmen Online übermittelt werden.\r\n";
        $readme .= "  Kein Ersatz für steuerliche oder rechtliche Beratung.\r\n";
        $zip->addFromString('README.txt', $readme);

        $zip->close();

        return $zipPath;
    }

    // ── DATEV Export ─────────────────────────────────────────────────────────

    /**
     * Erzeugt eine DATEV-kompatible Buchungsstapel-CSV (Datenformat DATEV v510).
     * Kann direkt in DATEV Unternehmen Online / Kanzlei-Rechnungswesen importiert werden.
     */
    public function buildDatevCsv(array $invoices, string $dateFrom, string $dateTo): string
    {
        $settings        = $this->settingsRepo->all();
        $beraterNr       = $this->repo->getSetting('datev_berater_nummer',    '0');
        $mandantenNr     = $this->repo->getSetting('datev_mandanten_nummer',  '0');
        $wirtschaftsjahr = $this->repo->getSetting('datev_wirtschaftsjahr',   '01');
        $kontoErloese    = $this->repo->getSetting('datev_konto_erloese',     '8400');
        $kontoKasse      = $this->repo->getSetting('datev_konto_kasse',       '1000');
        $kontoForderung  = $this->repo->getSetting('datev_konto_forderungen', '1400');
        $kleinunternehmer = ($settings['kleinunternehmer'] ?? '0') === '1';

        $fromDt = new \DateTime($dateFrom);
        $toDt   = new \DateTime($dateTo);

        // ── DATEV Header (Zeile 1 — Metadaten-Header) ────────────────────────
        $header1 = implode(';', [
            '"EXTF"',               // Kennzeichen
            '510',                  // Versionsnummer
            '21',                   // Datenkategorie (21 = Buchungsstapel)
            '"Buchungsstapel"',
            '7',                    // Format-Version
            date('YmdHis') . '000', // Erstellt am (YYYYMMDDHHmmssmmm)
            '',                     // Importiert
            '"RE"',                 // Herkunft
            '"TaxExportPro"',       // Exportiert von
            '',                     // Importiert von
            $beraterNr,             // Beraternummer
            $mandantenNr,           // Mandantennummer
            $fromDt->format('Y') . $wirtschaftsjahr . '01', // WJ-Beginn (YYYYMMDD)
            '4',                    // Sachkontenlänge
            $fromDt->format('Ymd'), // Datum von
            $toDt->format('Ymd'),   // Datum bis
            '"' . date('d.m.Y') . ' Steuerexport"', // Bezeichnung
            '',                     // Diktatkürzel
            '1',                    // Buchungstyp (1=Fibu, 2=Jahresabschluss)
            '0',                    // Rechnungslegungszweck
            '0',                    // Festschreibung (0=nein)
            '',                     // WKZ
            '',                     // reserviert
            '',                     // Derivatskennzeichen
            '',                     // reserviert
            '',                     // reserviert
            '',                     // SKR
            '',                     // Branchen-Lösungs-ID
            '',                     // reserviert
            '',                     // reserviert
            '',                     // Anwendungsinformation
        ]);

        // ── DATEV Header (Zeile 2 — Spaltenköpfe) ────────────────────────────
        $header2 = implode(';', [
            '"Umsatz (ohne Soll/Haben-Kz)"',
            '"Soll/Haben-Kennzeichen"',
            '"WKZ Umsatz"',
            '"Kurs"',
            '"Basis-Umsatz"',
            '"WKZ Basis-Umsatz"',
            '"Konto"',
            '"Gegenkonto (ohne BU-Schlüssel)"',
            '"BU-Schlüssel"',
            '"Belegdatum"',
            '"Belegfeld 1"',
            '"Belegfeld 2"',
            '"Skonto"',
            '"Buchungstext"',
            '"Postensperre"',
            '"Diverse Adressnummer"',
            '"Geschäftspartnerbank"',
            '"Sachverhalt"',
            '"Zinssperre"',
            '"Beleglink"',
            '"Beleginfo - Art 1"',
            '"Beleginfo - Inhalt 1"',
            '"Beleginfo - Art 2"',
            '"Beleginfo - Inhalt 2"',
            '"Beleginfo - Art 3"',
            '"Beleginfo - Inhalt 3"',
            '"Beleginfo - Art 4"',
            '"Beleginfo - Inhalt 4"',
            '"Beleginfo - Art 5"',
            '"Beleginfo - Inhalt 5"',
            '"Beleginfo - Art 6"',
            '"Beleginfo - Inhalt 6"',
            '"Beleginfo - Art 7"',
            '"Beleginfo - Inhalt 7"',
            '"Beleginfo - Art 8"',
            '"Beleginfo - Inhalt 8"',
            '"KOST1 - Kostenstelle"',
            '"KOST2 - Kostenstelle"',
            '"Kost-Menge"',
            '"EU-Land u. UStID"',
            '"EU-Steuersatz"',
            '"Abw. Versteuerungsart"',
            '"Sachverhalt L+L"',
            '"Funktionsergänzung L+L"',
            '"BU 49 Hauptfunktionstyp"',
            '"BU 49 Hauptfunktionsnummer"',
            '"BU 49 Funktionsergänzung"',
            '"Zusatzinformation - Art 1"',
            '"Zusatzinformation - Inhalt 1"',
            '"Zusatzinformation - Art 2"',
            '"Zusatzinformation - Inhalt 2"',
            '"Stück"',
            '"Gewicht"',
            '"Zahlweise"',
            '"Forderungsart"',
            '"Veranlagungsjahr"',
            '"Zugeordnete Fälligkeit"',
            '"Skontotyp"',
            '"Auftragsnummer"',
            '"Land"',
            '"Abrechnungsreferenz"',
            '"BVV-Position"',
            '"EU-Mitgliedstaat u. UStID (Ursprung)"',
            '"EU-Steuersatz (Ursprung)"',
        ]);

        $rows = ["\xEF\xBB\xBF" . $header1, $header2];

        foreach ($invoices as $inv) {
            $status = $inv['status'] ?? '';
            // Nur finanzwirksame Buchungen: paid, open, overdue
            if (in_array($status, ['draft', 'cancelled'], true)) continue;

            $bruttoAmt = abs((float)($inv['total_gross'] ?? 0));
            if ($bruttoAmt == 0.0) continue;

            $sollHaben  = ($status === 'paid') ? 'H' : 'S';
            $datum      = (new \DateTime($inv['issue_date'] ?? date('Y-m-d')))->format('dm'); // DATEV: TTMM
            $belegnr    = preg_replace('/[^A-Za-z0-9\-]/', '', $inv['invoice_number'] ?? '');
            $buchtext   = $this->truncate(($inv['owner_name'] ?? '') . ' ' . ($inv['patient_name'] ?? ''), 60);

            // Konto-Logik:
            // Bezahlt Bar     → Kasse (1000) an Erlöse (8400)
            // Bezahlt Überweis → Forderungen (1400) an Erlöse (8400)
            // Offen/Überfällig → Forderungen (1400) an Erlöse (8400)
            $isCash     = ($inv['payment_method'] ?? 'rechnung') === 'bar';
            $konto      = ($status === 'paid' && $isCash) ? $kontoKasse : $kontoForderung;
            $gegenkonto = $kontoErloese;

            // BU-Schlüssel: leer bei 19% (Normalsteuersatz), oder für § 19 UStG Kleinunternehmer
            $buKey = '';
            if ($kleinunternehmer) {
                $buKey = '40'; // § 19 UStG — keine USt
            }

            // Umsatz im DATEV-Format: Komma als Dezimaltrennzeichen, kein Tausenderpunkt
            $umsatzStr = number_format($bruttoAmt, 2, ',', '');

            $row = implode(';', [
                $umsatzStr,      // Umsatz
                $sollHaben,      // S/H
                'EUR',           // WKZ
                '',              // Kurs
                '',              // Basis-Umsatz
                '',              // WKZ Basis
                $konto,          // Konto
                $gegenkonto,     // Gegenkonto
                $buKey,          // BU-Schlüssel
                $datum,          // Belegdatum TTMM
                '"' . $belegnr . '"', // Belegfeld 1
                '',              // Belegfeld 2
                '',              // Skonto
                '"' . str_replace('"', '', $buchtext) . '"', // Buchungstext
            ]);

            $rows[] = $row;
        }

        return implode("\r\n", $rows) . "\r\n";
    }

    /**
     * Adds SHA-256 hashes per invoice PDF to the ZIP manifest.
     * Called internally by buildZip.
     */
    private function buildZipManifest(array $invoices, string $dateFrom, string $dateTo, string $statusFilter, array $hashes): string
    {
        $lines   = [];
        $lines[] = 'TaxExportPro – Exportmanifest (GoBD-Prüfprotokoll)';
        $lines[] = str_repeat('=', 55);
        $lines[] = '';
        $lines[] = 'Erstellt am:   ' . date('d.m.Y H:i:s') . ' UTC';
        $lines[] = 'Zeitraum:      ' . $this->formatDate($dateFrom) . ' – ' . $this->formatDate($dateTo);
        $lines[] = 'Statusfilter:  ' . $this->translateStatusLabel($statusFilter);
        $lines[] = 'Rechnungen:    ' . count($invoices);
        $lines[] = 'Hash-Algo:     SHA-256';
        $lines[] = '';
        $lines[] = str_repeat('-', 55);
        $lines[] = sprintf('%-40s  %s', 'Datei', 'SHA-256');
        $lines[] = str_repeat('-', 55);

        foreach ($hashes as $filename => $hash) {
            $lines[] = sprintf('%-40s  %s', $filename, $hash);
        }

        $lines[] = '';
        $lines[] = str_repeat('-', 55);
        $lines[] = 'Dieses Manifest dient dem Nachweis der Unverändertheit';
        $lines[] = 'der exportierten Dateien gemäß GoBD Rn. 91 ff.';
        $lines[] = 'Software: TaxExportPro Plugin – Tierphysio Manager';

        return implode("\r\n", $lines) . "\r\n";
    }

    // ── Logging ──────────────────────────────────────────────────────────────

    public function logExport(string $type, string $dateFrom, string $dateTo, string $statusFilter, int $rowCount): void
    {
        $this->repo->logExport($type, $dateFrom, $dateTo, $statusFilter, $rowCount);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function csvCell(string $value): string
    {
        // Escape double-quotes, wrap in quotes if contains delimiter/newline/quotes
        $value = str_replace('"', '""', $value);
        if (str_contains($value, ';') || str_contains($value, ',') ||
            str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . $value . '"';
        }
        return $value;
    }

    private function formatDate(string $date): string
    {
        if (!$date) return '';
        try {
            $dt = new \DateTime($date);
            return $dt->format('d.m.Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function formatMoney(float $val): string
    {
        return number_format($val, 2, ',', '.');
    }

    private function translateStatus(string $status): string
    {
        return match($status) {
            'paid'      => 'Bezahlt',
            'open'      => 'Offen',
            'draft'     => 'Entwurf',
            'overdue'   => 'Überfällig',
            'cancelled' => 'Storniert',
            default     => $status,
        };
    }

    private function translateStatusLabel(string $filter): string
    {
        return match($filter) {
            'paid'      => 'Nur bezahlt',
            'open'      => 'Nur offen',
            'draft'     => 'Nur Entwürfe',
            'overdue'   => 'Nur überfällig',
            'cancelled' => 'Nur storniert',
            default     => 'Alle',
        };
    }

    private function translatePaymentMethod(string $method): string
    {
        return match($method) {
            'bar'     => 'Bar',
            'rechnung' => 'Rechnung',
            default   => $method,
        };
    }

    private function truncate(string $str, int $max): string
    {
        return mb_strlen($str) > $max ? mb_substr($str, 0, $max - 1) . '…' : $str;
    }

    private function pdfSection(TCPDF $pdf, string $title): void
    {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(0, 7, $title, 0, 1, 'L');
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(3);
    }
}
