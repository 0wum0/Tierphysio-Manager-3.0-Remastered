<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Translator;
use App\Repositories\SettingsRepository;
use TCPDF;

class PdfService
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly Translator $translator
    ) {}

    public function generateInvoicePdf(
        array $invoice,
        array $positions,
        ?array $owner,
        ?array $patient
    ): string {
        $t        = fn(string $k) => $this->translator->trans($k);
        $settings = $this->settingsRepository->all();

        // ── Resolve all settings ─────────────────────────────────────────
        $primaryColor  = $this->hexToRgb($settings['pdf_primary_color'] ?? '#2962FF');
        $accentColor   = $this->hexToRgb($settings['pdf_accent_color']  ?? '#2962FF');
        $rowColor      = $this->hexToRgb($settings['pdf_row_color']     ?? '#F5F5FA');
        $darkColor     = [15, 15, 26];
        $grayColor     = [100, 100, 120];
        $font          = $this->resolvePdfFont($settings['pdf_font'] ?? 'helvetica');
        $fontSize      = (float)($settings['pdf_font_size']    ?? 9);
        $headerStyle   = $settings['pdf_header_style']         ?? 'line';
        $logoPosition  = $settings['pdf_logo_position']        ?? 'left';
        $logoWidth     = (float)($settings['pdf_logo_width']   ?? 35);
        $margin        = (float)($settings['pdf_margin']       ?? 20);
        $showLogo      = ($settings['pdf_show_logo']           ?? '1') === '1';
        $showPatient   = ($settings['pdf_show_patient']        ?? '1') === '1';
        $showChip      = ($settings['pdf_show_chip']           ?? '1') === '1';
        $showPageNums  = ($settings['pdf_show_page_numbers']   ?? '1') === '1';
        $showIban      = ($settings['pdf_show_iban']           ?? '1') === '1';
        $showTaxNum    = ($settings['pdf_show_tax_number']     ?? '1') === '1';
        $showWebsite   = ($settings['pdf_show_website']        ?? '0') === '1';
        $zebraRows     = ($settings['pdf_zebra_rows']          ?? '1') === '1';
        $watermark     = trim($settings['pdf_watermark']       ?? '');
        $introText     = trim($settings['pdf_intro_text']      ?? '');
        $closingText   = trim($settings['pdf_closing_text']    ?? '');
        $footerCustom  = trim($settings['pdf_footer_text']     ?? '');

        $companyName    = $settings['company_name']    ?? 'Tierphysio Praxis';
        $companyStreet  = $settings['company_street']  ?? '';
        $companyZip     = $settings['company_zip']     ?? '';
        $companyCity    = $settings['company_city']    ?? '';
        $companyPhone   = $settings['company_phone']   ?? '';
        $companyEmail   = $settings['company_email']   ?? '';
        $companyWebsite = $settings['company_website'] ?? '';
        $bankName       = $settings['bank_name']       ?? '';
        $bankIban       = $settings['bank_iban']       ?? '';
        $bankBic        = $settings['bank_bic']        ?? '';
        $taxNumber      = $settings['tax_number']      ?? '';
        $logoFile       = !empty($settings['company_logo'])
            ? STORAGE_PATH . '/uploads/' . $settings['company_logo']
            : null;

        // Page width inside margins
        $pageW      = 210 - ($margin * 2);
        $rightEdge  = 210 - $margin;

        // Footer height
        $footerLines  = 1;
        if (!empty($footerCustom)) $footerLines++;
        if ($showPageNums)         $footerLines++;
        $footerHeight = $footerLines * 4 + 4;

        // ── TCPDF setup ─────────────────────────────────────────────────
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins($margin, 15, $margin);
        $pdf->SetAutoPageBreak(true, $footerHeight + 8);
        $pdf->AddPage();

        // ── WATERMARK ───────────────────────────────────────────────────
        if ($watermark !== '') {
            $pdf->SetFont($font, 'B', 60);
            $pdf->SetTextColor(220, 220, 230);
            $pdf->StartTransform();
            $pdf->Rotate(35, 105, 148);
            $pdf->SetXY(105 - 70, 148 - 15);
            $pdf->Cell(140, 30, mb_strtoupper($watermark), 0, 0, 'C');
            $pdf->StopTransform();
            $pdf->SetTextColor(...$darkColor);
        }

        // ── HEADER BLOCK ────────────────────────────────────────────────
        $headerTopY = 15;

        // Logo placement
        if ($showLogo && $logoFile && file_exists($logoFile)) {
            $logoX = match($logoPosition) {
                'right'  => $rightEdge - $logoWidth,
                'center' => 105 - ($logoWidth / 2),
                default  => $margin,
            };
            $pdf->Image($logoFile, $logoX, $headerTopY, $logoWidth, 0, '', '', '', false, 300);
        }

        // Company info — opposite side from logo (or right when logo is center/hidden)
        $companyX = ($logoPosition === 'right') ? $margin : 105;
        $companyW = ($logoPosition === 'right' || $logoPosition === 'center') ? $pageW / 2 : ($pageW / 2);
        $companyAlign = ($logoPosition === 'right') ? 'L' : 'R';

        $pdf->SetFont($font, 'B', $fontSize + 7);
        $pdf->SetTextColor(...$darkColor);
        $pdf->SetXY($companyX, $headerTopY);
        $pdf->Cell($companyW, 8, $companyName, 0, 1, $companyAlign);

        $pdf->SetFont($font, '', $fontSize - 1);
        $pdf->SetTextColor(...$grayColor);
        $infoY = $headerTopY + 9;
        $companyLines = array_filter([
            $companyStreet,
            trim($companyZip . ' ' . $companyCity),
            $companyPhone  ? 'Tel: ' . $companyPhone  : '',
            $companyEmail,
            ($showWebsite && $companyWebsite) ? $companyWebsite : '',
        ]);
        foreach ($companyLines as $line) {
            $pdf->SetXY($companyX, $infoY);
            $pdf->Cell($companyW, 4, $line, 0, 1, $companyAlign);
            $infoY += 4;
        }

        // Header divider
        $divY = max($infoY + 2, 46);
        if ($headerStyle === 'band') {
            $pdf->SetFillColor(...$primaryColor);
            $pdf->Rect($margin, $divY, $pageW, 3, 'F');
        } elseif ($headerStyle === 'line') {
            $pdf->SetDrawColor(...$primaryColor);
            $pdf->SetLineWidth(0.6);
            $pdf->Line($margin, $divY, $rightEdge, $divY);
        }

        // ── RECIPIENT + META (below divider) ────────────────────────────
        $blockTopY = $divY + 4;

        // Return address tiny line
        $pdf->SetFont($font, '', $fontSize - 2);
        $pdf->SetTextColor(...$grayColor);
        $pdf->SetXY($margin, $blockTopY);
        $pdf->Cell($pageW / 2, 3.5, $companyName . ' · ' . $companyStreet . ' · ' . $companyZip . ' ' . $companyCity, 0, 1);

        // Recipient
        $pdf->SetFont($font, '', $fontSize + 1);
        $pdf->SetTextColor(...$darkColor);
        $recY = $blockTopY + 5;
        $pdf->SetXY($margin, $recY);
        if ($owner) {
            $pdf->Cell($pageW / 2, 5.5, $owner['first_name'] . ' ' . $owner['last_name'], 0, 1);
            $pdf->SetX($margin);
            if (!empty($owner['street'])) { $pdf->Cell($pageW / 2, 5, $owner['street'], 0, 1); $pdf->SetX($margin); }
            if (!empty($owner['zip']))    { $pdf->Cell($pageW / 2, 5, $owner['zip'] . ' ' . ($owner['city'] ?? ''), 0, 1); }
        }

        // Invoice meta — right column
        $metaX = $margin + ($pageW / 2) + 4;
        $metaW = ($pageW / 2) - 4;

        $pdf->SetFont($font, 'B', $fontSize + 4);
        $pdf->SetTextColor(...$accentColor);
        $pdf->SetXY($metaX, $blockTopY);
        $pdf->Cell($metaW, 8, mb_strtoupper($t('invoices.invoice')), 0, 1, 'R');

        $pdf->SetFont($font, '', $fontSize - 0.5);
        $pdf->SetTextColor(...$darkColor);
        $metaY = $blockTopY + 9;
        $metaRows = [
            $t('invoices.invoice_number') => $invoice['invoice_number'],
            $t('invoices.issue_date')     => $invoice['issue_date'] ? date('d.m.Y', strtotime($invoice['issue_date'])) : '-',
        ];
        if (!empty($invoice['due_date'])) {
            $metaRows[$t('invoices.due_date')] = date('d.m.Y', strtotime($invoice['due_date']));
        }
        if ($showPatient && $patient) {
            $metaRows[$t('invoices.patient')] = $patient['name'] . ' (' . ($patient['species'] ?? '') . ')';
            if ($showChip && !empty($patient['chip_number'])) {
                $metaRows['Chip-Nr.'] = $patient['chip_number'];
            }
        }
        foreach ($metaRows as $label => $value) {
            $pdf->SetXY($metaX, $metaY);
            $pdf->Cell($metaW - 28, 4.5, $label . ':', 0, 0, 'R');
            $pdf->Cell(28, 4.5, $value, 0, 1, 'R');
            $metaY += 4.5;
        }

        // ── INTRO TEXT ──────────────────────────────────────────────────
        $tableY = max($pdf->GetY(), $metaY) + 6;
        if ($introText !== '') {
            $pdf->SetFont($font, '', $fontSize - 0.5);
            $pdf->SetTextColor(...$darkColor);
            $pdf->SetXY($margin, $tableY);
            $pdf->MultiCell($pageW, 4.5, $introText, 0, 'L');
            $tableY = $pdf->GetY() + 4;
        }

        // ── POSITIONS TABLE ─────────────────────────────────────────────
        $pdf->SetFillColor(...$primaryColor);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', $fontSize - 0.5);
        $pdf->SetXY($margin, $tableY);

        // Column widths (relative to pageW)
        $colDesc  = $pageW * 0.49;
        $colQty   = $pageW * 0.09;
        $colPrice = $pageW * 0.14;
        $colTax   = $pageW * 0.08;
        $colTotal = $pageW * 0.14;
        // Remaining width goes to description to keep total = pageW
        $colDesc  = $pageW - $colQty - $colPrice - $colTax - $colTotal;

        $pdf->Cell($colDesc,  6.5, $t('invoices.description'), 1, 0, 'L', true);
        $pdf->Cell($colQty,   6.5, $t('invoices.quantity'),    1, 0, 'C', true);
        $pdf->Cell($colPrice, 6.5, $t('invoices.unit_price'),  1, 0, 'R', true);
        $pdf->Cell($colTax,   6.5, $t('invoices.tax_rate'),    1, 0, 'C', true);
        $pdf->Cell($colTotal, 6.5, $t('invoices.total'),       1, 1, 'R', true);

        $pdf->SetTextColor(...$darkColor);
        $pdf->SetFont($font, '', $fontSize - 0.5);
        $fill = false;

        foreach ($positions as $pos) {
            if ($zebraRows) {
                $pdf->SetFillColor(...$rowColor);
            }
            $lineNet = (float)$pos['quantity'] * (float)$pos['unit_price'];
            $pdf->Cell($colDesc,  6, $pos['description'], 1, 0, 'L', $zebraRows && $fill);
            $pdf->Cell($colQty,   6, number_format((float)$pos['quantity'], 2, ',', '.'), 1, 0, 'C', $zebraRows && $fill);
            $pdf->Cell($colPrice, 6, number_format((float)$pos['unit_price'], 2, ',', '.') . ' €', 1, 0, 'R', $zebraRows && $fill);
            $pdf->Cell($colTax,   6, (float)$pos['tax_rate'] . ' %', 1, 0, 'C', $zebraRows && $fill);
            $pdf->Cell($colTotal, 6, number_format($lineNet, 2, ',', '.') . ' €', 1, 1, 'R', $zebraRows && $fill);
            $fill = !$fill;
        }

        // ── TOTALS ───────────────────────────────────────────────────────
        $totalY = $pdf->GetY() + 4;
        $totalsX = $margin + $colDesc + $colQty;

        $pdf->SetFont($font, '', $fontSize - 0.5);
        $pdf->SetTextColor(...$darkColor);

        $pdf->SetXY($totalsX, $totalY);
        $pdf->Cell($colPrice + $colTax, 5.5, $t('invoices.total_net') . ':', 0, 0, 'R');
        $pdf->Cell($colTotal, 5.5, number_format((float)$invoice['total_net'], 2, ',', '.') . ' €', 0, 1, 'R');

        $pdf->SetXY($totalsX, $totalY + 5.5);
        $pdf->Cell($colPrice + $colTax, 5.5, $t('invoices.total_tax') . ':', 0, 0, 'R');
        $pdf->Cell($colTotal, 5.5, number_format((float)$invoice['total_tax'], 2, ',', '.') . ' €', 0, 1, 'R');

        $pdf->SetDrawColor(...$primaryColor);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($totalsX, $totalY + 12, $rightEdge, $totalY + 12);

        $pdf->SetFont($font, 'B', $fontSize + 1);
        $pdf->SetTextColor(...$accentColor);
        $pdf->SetXY($totalsX, $totalY + 13);
        $pdf->Cell($colPrice + $colTax, 7, $t('invoices.total_gross') . ':', 0, 0, 'R');
        $pdf->Cell($colTotal, 7, number_format((float)$invoice['total_gross'], 2, ',', '.') . ' €', 0, 1, 'R');

        // ── NOTES (invoice-level) ────────────────────────────────────────
        $pdf->SetFont($font, '', $fontSize - 0.5);
        $pdf->SetTextColor(...$darkColor);

        if (!empty($invoice['notes'])) {
            $pdf->SetXY($margin, $totalY);
            $pdf->MultiCell($colDesc + $colQty - 4, 5, $invoice['notes'], 0, 'L');
        }

        // ── CLOSING TEXT ─────────────────────────────────────────────────
        if ($closingText !== '') {
            $afterTotals = max($pdf->GetY(), $totalY + 22) + 5;
            $pdf->SetXY($margin, $afterTotals);
            $pdf->SetFont($font, '', $fontSize - 0.5);
            $pdf->SetTextColor(...$darkColor);
            $pdf->MultiCell($pageW, 4.5, $closingText, 0, 'L');
        }

        // ── PAYMENT TERMS ────────────────────────────────────────────────
        $paymentTerms = $invoice['payment_terms'] ?? $settings['payment_terms'] ?? '';
        if (!empty($paymentTerms)) {
            $afterAll = max($pdf->GetY(), $totalY + 22) + 5;
            $pdf->SetXY($margin, $afterAll);
            $pdf->SetFont($font, 'I', $fontSize - 1.5);
            $pdf->SetTextColor(...$grayColor);
            $pdf->MultiCell($pageW, 3.5, $paymentTerms, 0, 'L');
        }

        // ── FOOTER (pinned to bottom of every page) ──────────────────────
        $pdf->SetAutoPageBreak(false);
        $footerY = -($footerHeight + 2);
        $pdf->SetY($footerY);
        $pdf->SetDrawColor(...$grayColor);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($margin, $pdf->GetY(), $rightEdge, $pdf->GetY());
        $pdf->SetFont($font, '', $fontSize - 2.5);
        $pdf->SetTextColor(...$grayColor);

        $footerParts = [];
        if ($bankName)           $footerParts[] = 'Bank: ' . $bankName;
        if ($showIban && $bankIban) $footerParts[] = 'IBAN: ' . $bankIban;
        if ($bankBic)            $footerParts[] = 'BIC: ' . $bankBic;
        if ($showTaxNum && $taxNumber) $footerParts[] = 'St.-Nr.: ' . $taxNumber;

        $pdf->SetX($margin);
        $pdf->Cell($pageW, 3.5, implode('   |   ', $footerParts), 0, 1, 'C');

        if (!empty($footerCustom)) {
            $pdf->SetX($margin);
            $pdf->Cell($pageW, 3.5, $footerCustom, 0, 1, 'C');
        }

        if ($showPageNums) {
            $pdf->SetX($margin);
            $pdf->Cell($pageW, 3.5, 'Seite ' . $pdf->getPage() . ' / ' . $pdf->getNumPages(), 0, 0, 'R');
        }

        return $pdf->Output('', 'S');
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            (int)hexdec(substr($hex, 0, 2)),
            (int)hexdec(substr($hex, 2, 2)),
            (int)hexdec(substr($hex, 4, 2)),
        ];
    }

    private function resolvePdfFont(string $font): string
    {
        return match($font) {
            'times'    => 'times',
            'courier'  => 'courier',
            'dejavusans' => 'dejavusans',
            default    => 'helvetica',
        };
    }
}
