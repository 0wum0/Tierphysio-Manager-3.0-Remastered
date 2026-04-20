<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\SettingsRepository;

/**
 * DatevExportService
 *
 * Erzeugt DATEV-kompatible CSV-Exporte für Steuerberater.
 * Fokus: Hundeschulen/Trainer — aber nutzt alle Rechnungen im Zeitraum.
 *
 * Format: "DATEV Buchungsstapel" (Format 7) — CSV mit Header-Zeilen.
 * Alternativ wird ein vereinfachter Steuerberater-Export (CSV) angeboten,
 * der von den meisten Programmen (Lexware, BuchhaltungsButler, sevDesk)
 * direkt importiert werden kann.
 *
 * Standard-Kontenrahmen SKR03:
 *   - 8400 Erlöse 19% USt
 *   - 8300 Erlöse 7% USt
 *   - 8200 Erlöse steuerfrei (Kleinunternehmer)
 *   - 1776 Umsatzsteuer 19%
 *   - 1771 Umsatzsteuer 7%
 *   - Personenkonto Debitor: 10000–69999 (hier: 10000 + owner_id)
 */
class DatevExportService
{
    public function __construct(
        private readonly Database $db,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @return array{filename: string, content: string}
     */
    public function generateSteuerberaterCsv(string $from, string $to, string $mode = 'simple'): array
    {
        if ($mode === 'datev') {
            return $this->generateDatevFormat7($from, $to);
        }
        return $this->generateSimpleCsv($from, $to);
    }

    /**
     * Vereinfachter Steuerberater-CSV-Export.
     * Spalten: Belegdatum, Belegnummer, Buchungstext, Betrag brutto,
     *          Betrag netto, MwSt-Betrag, MwSt-Satz, Konto Soll, Konto Haben,
     *          Kunde, Zahlart, Status.
     *
     * Eine Zeile pro Rechnungsposition — damit unterschiedliche Steuersätze
     * innerhalb einer Rechnung korrekt abgebildet werden.
     */
    private function generateSimpleCsv(string $from, string $to): array
    {
        $rows = $this->fetchPositionsInRange($from, $to);

        $out = [];
        $out[] = [
            'Belegdatum', 'Belegnummer', 'Buchungstext', 'Betrag_brutto',
            'Betrag_netto', 'MwSt_Betrag', 'MwSt_Prozent', 'Konto_Soll',
            'Konto_Haben', 'Kunde', 'Zahlart', 'Status', 'Quelle',
        ];

        foreach ($rows as $r) {
            $net   = (float)$r['pos_net'];
            $tax   = (float)$r['pos_tax'];
            $gross = (float)$r['pos_gross'];
            $rate  = (float)$r['tax_rate'];

            /* Konto-Zuordnung */
            $erloesekonto = $this->erloesekontoFor($rate);
            $debitor      = 10000 + (int)($r['owner_id'] ?? 0);

            $out[] = [
                date('d.m.Y', strtotime($r['issue_date'])),
                (string)$r['invoice_number'],
                substr((string)$r['description'], 0, 60),
                $this->formatMoney($gross),
                $this->formatMoney($net),
                $this->formatMoney($tax),
                $this->formatMoney($rate) . '%',
                (string)$debitor,
                (string)$erloesekonto,
                trim(($r['owner_first_name'] ?? '') . ' ' . ($r['owner_last_name'] ?? '')),
                (string)($r['payment_method'] ?? 'rechnung'),
                (string)($r['status'] ?? 'open'),
                (string)($r['source_type'] ?? 'other'),
            ];
        }

        return [
            'filename' => sprintf('steuerexport_%s_%s.csv', $from, $to),
            'content'  => $this->arrayToCsv($out, ';'),
        ];
    }

    /**
     * DATEV Format 7 (Buchungsstapel-Import) — Header + Datenzeilen.
     * Deutlich strengeres Format mit Pflichtfeldern und Metadaten.
     */
    private function generateDatevFormat7(string $from, string $to): array
    {
        $rows     = $this->fetchPositionsInRange($from, $to);
        $company  = $this->settings->get('company_name', 'Hundeschule');
        $ustIdNr  = $this->settings->get('tax_number', '');
        $berater  = (int)$this->settings->get('datev_berater_nr', '0');
        $mandant  = (int)$this->settings->get('datev_mandant_nr', '0');

        /* ── Erste Zeile: Meta-Header (DATEV-Format-Kennung) ── */
        $header = [
            'DTVF', 700, 21, 'Buchungsstapel', 7, date('YmdHis000'), '', 'RE', '',
            '', $berater, $mandant,
            (int)date('Ymd', strtotime($from)),  /* Wirtschaftsjahresbeginn */
            4,  /* Sachkontenlänge */
            (int)date('Ymd', strtotime($from)),
            (int)date('Ymd', strtotime($to)),
            $company . ' ' . date('m-Y', strtotime($from)),
            '',  /* Diktatkürzel */
            1,   /* Buchungstyp: Finanzbuchführung */
            0,   /* Rechnungslegungszweck */
            0,   /* Festschreibung */
            'EUR',
        ];

        /* ── Zweite Zeile: Spalten-Header ── */
        $columns = [
            'Umsatz (ohne Soll/Haben-Kz)', 'Soll/Haben-Kennzeichen', 'WKZ Umsatz',
            'Kurs', 'Basis-Umsatz', 'WKZ Basis-Umsatz', 'Konto', 'Gegenkonto (ohne BU)',
            'BU-Schlüssel', 'Belegdatum', 'Belegfeld 1', 'Belegfeld 2', 'Skonto',
            'Buchungstext',
        ];

        /* ── Datenzeilen ── */
        $csv   = [];
        $csv[] = $header;
        $csv[] = $columns;

        foreach ($rows as $r) {
            $gross = (float)$r['pos_gross'];
            $rate  = (float)$r['tax_rate'];
            $erl   = $this->erloesekontoFor($rate);
            $deb   = 10000 + (int)($r['owner_id'] ?? 0);

            $csv[] = [
                number_format($gross, 2, ',', ''),
                'S',       /* Soll-Buchung auf Debitor */
                'EUR',
                '', '', '',
                $deb,      /* Konto = Debitor */
                $erl,      /* Gegenkonto = Erlöskonto */
                '',
                date('dm', strtotime($r['issue_date'])),
                substr((string)$r['invoice_number'], 0, 12),
                '', '',
                substr((string)$r['description'], 0, 60),
            ];
        }

        return [
            'filename' => sprintf('datev_EXTF_%s_%s.csv', $from, $to),
            'content'  => $this->arrayToCsv($csv, ';'),
        ];
    }

    /**
     * Kassenbuch-Export: alle Barzahlungen im Zeitraum.
     * Wichtig für Hundeschulen die vor Ort bar kassieren.
     */
    public function generateKassenbuchCsv(string $from, string $to): array
    {
        $invTab = $this->db->prefix('invoices');
        $ownTab = $this->db->prefix('owners');

        $rows = $this->db->safeFetchAll(
            "SELECT i.invoice_number, i.issue_date, i.paid_at, i.total_gross, i.total_net, i.total_tax,
                    CONCAT(COALESCE(o.first_name,''), ' ', COALESCE(o.last_name,'')) AS owner_name
               FROM `{$invTab}` i
               LEFT JOIN `{$ownTab}` o ON o.id = i.owner_id
              WHERE i.payment_method = 'bar'
                AND i.status = 'paid'
                AND DATE(COALESCE(i.paid_at, i.issue_date)) BETWEEN ? AND ?
              ORDER BY i.paid_at ASC",
            [$from, $to]
        );

        $out = [];
        $out[] = ['Datum', 'Belegnr', 'Buchungstext', 'Einnahme_brutto', 'Netto', 'USt', 'Kunde'];
        $total = 0.0;
        foreach ($rows as $r) {
            $total += (float)$r['total_gross'];
            $out[] = [
                date('d.m.Y', strtotime($r['paid_at'] ?? $r['issue_date'])),
                (string)$r['invoice_number'],
                'Barzahlung ' . ($r['owner_name'] ?? ''),
                $this->formatMoney((float)$r['total_gross']),
                $this->formatMoney((float)$r['total_net']),
                $this->formatMoney((float)$r['total_tax']),
                (string)($r['owner_name'] ?? ''),
            ];
        }
        $out[] = ['', '', 'SUMME', $this->formatMoney($total), '', '', ''];

        return [
            'filename' => sprintf('kassenbuch_%s_%s.csv', $from, $to),
            'content'  => $this->arrayToCsv($out, ';'),
        ];
    }

    /* ═══════════════════════ Helpers ═══════════════════════ */

    private function fetchPositionsInRange(string $from, string $to): array
    {
        $invTab = $this->db->prefix('invoices');
        $posTab = $this->db->prefix('invoice_positions');
        $ownTab = $this->db->prefix('owners');

        return $this->db->safeFetchAll(
            "SELECT i.id AS invoice_id, i.invoice_number, i.issue_date, i.status, i.payment_method,
                    i.owner_id, o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                    p.description, p.quantity, p.unit_price, p.tax_rate, p.total,
                    (p.quantity * p.unit_price) AS pos_net,
                    (p.quantity * p.unit_price * p.tax_rate / 100) AS pos_tax,
                    (p.quantity * p.unit_price * (1 + p.tax_rate / 100)) AS pos_gross,
                    CASE
                        WHEN i.notes LIKE 'Automatisch aus Kurs-Einschreibung%' THEN 'course'
                        WHEN i.notes LIKE 'Automatisch aus Paket-Verkauf%'      THEN 'package'
                        ELSE 'other'
                    END AS source_type
               FROM `{$invTab}` i
               JOIN `{$posTab}` p ON p.invoice_id = i.id
               LEFT JOIN `{$ownTab}` o ON o.id = i.owner_id
              WHERE i.issue_date BETWEEN ? AND ?
                AND i.status IN ('paid','open','overdue')
              ORDER BY i.issue_date ASC, i.id ASC, p.sort_order ASC",
            [$from, $to]
        );
    }

    private function erloesekontoFor(float $rate): int
    {
        if ($rate >= 18.5) return 8400;   /* 19% */
        if ($rate >= 6.5)  return 8300;   /* 7%  */
        return 8200;                       /* steuerfrei / Kleinunternehmer */
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '');
    }

    private function arrayToCsv(array $rows, string $separator = ';'): string
    {
        $fh = fopen('php://temp', 'r+');
        /* BOM für Excel-Kompatibilität */
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($fh, $row, $separator, '"', '\\');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return (string)$csv;
    }

    /**
     * Umsatz-Zusammenfassung nach Steuersatz für den Zeitraum — nützlich
     * für UStVA (Umsatzsteuervoranmeldung).
     */
    public function summaryByTaxRate(string $from, string $to): array
    {
        $rows = $this->fetchPositionsInRange($from, $to);
        $summary = [];
        foreach ($rows as $r) {
            $rate = number_format((float)$r['tax_rate'], 2);
            if (!isset($summary[$rate])) {
                $summary[$rate] = ['rate' => (float)$r['tax_rate'], 'net' => 0.0, 'tax' => 0.0, 'gross' => 0.0, 'count' => 0];
            }
            $summary[$rate]['net']   += (float)$r['pos_net'];
            $summary[$rate]['tax']   += (float)$r['pos_tax'];
            $summary[$rate]['gross'] += (float)$r['pos_gross'];
            $summary[$rate]['count']++;
        }
        ksort($summary);
        return array_values($summary);
    }
}
