<?php

declare(strict_types=1);

namespace Plugins\VetReport;

use App\Repositories\SettingsRepository;
use TCPDF;

class VetReportService
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository
    ) {}

    public function generate(
        array $patient,
        ?array $owner,
        array $timeline,
        array $appointments
    ): string {
        $settings = $this->settingsRepository->all();

        // ── Exact same settings as PdfService::generateInvoicePdf ────────
        $sidebarColor      = $this->hexToRgb($settings['pdf_primary_color']           ?? '#8B9E8B');
        $accentColor       = $this->hexToRgb($settings['pdf_accent_color']            ?? '#6B7F6B');
        $colorCompanyName  = $this->hexToRgb($settings['pdf_color_company_name']      ?? '#1E1E1E');
        $colorCompanyInfo  = $this->hexToRgb($settings['pdf_color_company_info']      ?? '#6E6E6E');
        $colorRecipient    = $this->hexToRgb($settings['pdf_color_recipient']         ?? '#1E1E1E');
        $colorTableHdrBg   = $this->hexToRgb($settings['pdf_color_table_header_bg']   ?? '#8B9E8B');
        $colorTableHdrText = $this->hexToRgb($settings['pdf_color_table_header_text'] ?? '#FFFFFF');
        $colorTableText    = $this->hexToRgb($settings['pdf_color_table_text']        ?? '#1E1E1E');
        $colorLine         = $this->hexToRgb($settings['pdf_color_line']              ?? '#B4B4B4');
        $colorFooter       = $this->hexToRgb($settings['pdf_color_footer']            ?? '#6E6E6E');
        $darkColor         = $colorCompanyName;
        $grayColor         = $colorCompanyInfo;

        $font        = $this->resolvePdfFont($settings['pdf_font'] ?? 'helvetica');
        $fontSize    = (float)($settings['pdf_font_size'] ?? 9);
        $showWebsite = ($settings['pdf_show_website'] ?? '0') === '1';

        $companyName    = $settings['company_name']    ?? '';
        $companyStreet  = $settings['company_street']  ?? '';
        $companyZip     = $settings['company_zip']     ?? '';
        $companyCity    = $settings['company_city']    ?? '';
        $companyPhone   = $settings['company_phone']   ?? '';
        $companyEmail   = $settings['company_email']   ?? '';
        $companyWebsite = $settings['company_website'] ?? '';
        $logoFile       = !empty($settings['company_logo'])
            ? STORAGE_PATH . '/uploads/' . $settings['company_logo']
            : null;

        // ── Layout constants (identical to invoice) ──────────────────────
        $sidebarW  = 42;
        $contentX  = 50;
        $contentW  = 145;
        $rightEdge = 195;
        $pageH     = 297;

        // ── TCPDF setup ──────────────────────────────────────────────────
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Tierphysio Manager');
        $pdf->SetAuthor($companyName);
        $pdf->SetTitle('Tierarztbericht – ' . ($patient['name'] ?? ''));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // ── Sidebar draw closure (reused on each new page) ───────────────
        $drawSidebar = function () use (
            $pdf, $sidebarColor, $sidebarW, $pageH, $logoFile,
            $font, $fontSize
        ) {
            $pdf->SetFillColor(...$sidebarColor);
            $pdf->Rect(0, 0, $sidebarW, $pageH, 'F');

            $logoY = 14;
            if ($logoFile && file_exists($logoFile)) {
                $pdf->Image($logoFile, 5, $logoY, $sidebarW - 10, 0, '', '', '', false, 300);
                $logoY += 26;
            } else {
                $cx2 = $sidebarW / 2;
                $cy2 = $logoY + 12;
                $pdf->SetDrawColor(255, 255, 255);
                $pdf->SetLineWidth(0.5);
                $pdf->Circle($cx2, $cy2, 11, 0, 360, 'D');
                $pdf->SetFont($font, 'B', 7);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetXY(3, $cy2 - 4);
                $pdf->Cell($sidebarW - 6, 8, 'LOGO', 0, 0, 'C');
                $logoY += 28;
            }

            $sideY = $logoY + 8;
            $pdf->SetFont($font, '', $fontSize - 2);
            $pdf->SetTextColor(220, 235, 220);
            $pdf->SetXY(3, $sideY);
            $pdf->Cell($sidebarW - 6, 4, 'Dokument', 0, 1, 'C');
            $pdf->SetFont($font, 'B', $fontSize - 1);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY(3, $sideY + 4);
            $pdf->Cell($sidebarW - 6, 5, 'Tierarztbericht', 0, 1, 'C');

            $sideY += 22;
            $pdf->SetFont($font, '', $fontSize - 2);
            $pdf->SetTextColor(220, 235, 220);
            $pdf->SetXY(3, $sideY);
            $pdf->Cell($sidebarW - 6, 4, 'Erstellt am', 0, 1, 'C');
            $pdf->SetFont($font, 'B', $fontSize - 1);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY(3, $sideY + 4);
            $pdf->Cell($sidebarW - 6, 5, date('d.m.Y'), 0, 1, 'C');
        };

        $drawSidebar();

        // ── Company info top right ────────────────────────────────────────
        $pdf->SetFont($font, 'B', $fontSize + 1);
        $pdf->SetTextColor(...$darkColor);
        $pdf->SetXY($contentX + ($contentW / 2), 8);
        $pdf->Cell($contentW / 2, 5, $companyName, 0, 1, 'R');

        $pdf->SetFont($font, '', $fontSize - 1.5);
        $pdf->SetTextColor(...$grayColor);
        $infoLines = array_filter([
            $companyStreet,
            trim($companyZip . ' ' . $companyCity),
            $companyPhone ? 'Tel: ' . $companyPhone : '',
            $companyEmail,
            ($showWebsite && $companyWebsite) ? $companyWebsite : '',
        ]);
        $infoY = 15;
        foreach ($infoLines as $il) {
            $pdf->SetXY($contentX + ($contentW / 2), $infoY);
            $pdf->Cell($contentW / 2, 4, $il, 0, 1, 'R');
            $infoY += 4;
        }

        // "Tierarztbericht" heading
        $titleY = $infoY + 4;
        $pdf->SetFont($font, 'BI', 28);
        $pdf->SetTextColor(...$darkColor);
        $pdf->SetXY($contentX, $titleY);
        $pdf->Cell($contentW, 14, 'Tierarztbericht', 0, 1, 'R');
        $titleBottomY = $titleY + 16;

        // ── Recipient block ───────────────────────────────────────────────
        $addrTopY = max($titleBottomY + 4, 58);
        $colW     = $contentW / 2 - 4;

        if ($owner) {
            $pdf->SetFont($font, 'B', $fontSize);
            $pdf->SetTextColor(...$colorRecipient);
            $pdf->SetXY($contentX, $addrTopY);
            $pdf->Cell($colW, 5.5, trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? '')), 0, 1);
            $pdf->SetFont($font, '', $fontSize - 0.5);
            $pdf->SetTextColor(...$colorRecipient);
            if (!empty($owner['street'])) {
                $pdf->SetXY($contentX, $addrTopY + 6);
                $pdf->Cell($colW, 4.5, $owner['street'], 0, 1);
            }
            if (!empty($owner['zip'])) {
                $pdf->SetXY($contentX, $addrTopY + 11);
                $pdf->Cell($colW, 4.5, trim(($owner['zip'] ?? '') . ' ' . ($owner['city'] ?? '')), 0, 1);
            }
            if (!empty($owner['phone'])) {
                $pdf->SetFont($font, '', $fontSize - 1.5);
                $pdf->SetTextColor(...$grayColor);
                $pdf->SetXY($contentX, $addrTopY + 21);
                $pdf->Cell($colW, 4, 'Tel: ' . $owner['phone'], 0, 1);
            }
        }

        // ── Patient info (right column) ───────────────────────────────────
        $patInfoX = $contentX + $colW + 8;
        $patInfoW = $colW - 4;
        $patY     = $addrTopY;

        $patPhoto = STORAGE_PATH . '/patients/' . (int)$patient['id'] . '/' . ($patient['photo'] ?? '');
        if (!empty($patient['photo']) && file_exists($patPhoto)) {
            $pdf->Image($patPhoto, $patInfoX + $patInfoW - 22, $patY, 22, 22, '', '', '', true, 150, '', false, false, 1);
        }

        $patFields = array_filter([
            'Patient'      => $patient['name']       ?? '',
            'Tierart'      => $patient['species']    ?? '',
            'Rasse'        => $patient['breed']      ?? '',
            'Geburtsdatum' => !empty($patient['birth_date']) ? date('d.m.Y', strtotime($patient['birth_date'])) : '',
            'Geschlecht'   => $patient['gender']     ?? '',
            'Chip-Nr.'     => $patient['chip_number'] ?? '',
            'Status'       => $patient['status']     ?? '',
        ]);
        $pfy = $patY;
        foreach ($patFields as $lbl => $val) {
            $pdf->SetFont($font, '', $fontSize - 1.5);
            $pdf->SetTextColor(...$grayColor);
            $pdf->SetXY($patInfoX, $pfy);
            $pdf->Cell($patInfoW * 0.42, 4.5, $lbl, 0, 0);
            $pdf->SetFont($font, 'B', $fontSize - 1.5);
            $pdf->SetTextColor(...$darkColor);
            $pdf->Cell($patInfoW * 0.58, 4.5, $val, 0, 1);
            $pfy += 4.5;
        }

        // ── Divider ───────────────────────────────────────────────────────
        $tableTopY = max($addrTopY + 38, 100);
        $pdf->SetDrawColor(...$colorLine);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($contentX, $tableTopY, $rightEdge, $tableTopY);
        $curY = $tableTopY + 6;

        // ── Anamnese / Notizen ────────────────────────────────────────────
        if (!empty($patient['notes'])) {
            $pdf->SetFont($font, 'B', $fontSize - 1);
            $pdf->SetTextColor(...$colorTableHdrText);
            $pdf->SetFillColor(...$colorTableHdrBg);
            $pdf->SetXY($contentX, $curY);
            $pdf->Cell($contentW, 6, '  ANAMNESE / NOTIZEN', 0, 1, 'L', true);
            $curY = $pdf->GetY() + 3;
            $pdf->SetFont($font, '', $fontSize - 0.5);
            $pdf->SetTextColor(...$darkColor);
            $pdf->SetXY($contentX, $curY);
            $pdf->MultiCell($contentW, 5, $this->htmlToPlainText($patient['notes']), 0, 'L');
            $curY = $pdf->GetY() + 5;
        }

        // ── Behandlungshistorie ───────────────────────────────────────────
        $treatments = array_values(array_filter(
            $timeline,
            fn($e) => in_array($e['type'] ?? '', ['treatment', 'note'], true)
        ));

        if (!empty($treatments)) {
            $this->checkPageBreak($pdf, $curY, 20, $pageH, $drawSidebar, $contentX, $font, $fontSize);
            $curY = $pdf->GetY();

            $pdf->SetFont($font, 'B', $fontSize - 1);
            $pdf->SetTextColor(...$colorTableHdrText);
            $pdf->SetFillColor(...$colorTableHdrBg);
            $pdf->SetXY($contentX, $curY);
            $pdf->Cell($contentW, 6, '  BEHANDLUNGSHISTORIE', 0, 1, 'L', true);
            $curY = $pdf->GetY() + 2;

            $cDate  = 22;
            $cType  = 28;
            $cTitle = 38;
            $cCont  = $contentW - $cDate - $cType - $cTitle;

            $pdf->SetFillColor(...$colorTableHdrBg);
            $pdf->SetTextColor(...$colorTableHdrText);
            $pdf->SetFont($font, 'B', $fontSize - 2);
            $pdf->SetXY($contentX, $curY);
            $pdf->Cell($cDate,  5.5, 'Datum',  1, 0, 'L', true);
            $pdf->Cell($cType,  5.5, 'Typ',    1, 0, 'L', true);
            $pdf->Cell($cTitle, 5.5, 'Titel',  1, 0, 'L', true);
            $pdf->Cell($cCont,  5.5, 'Inhalt', 1, 1, 'L', true);
            $curY = $pdf->GetY();

            $fill = false;
            foreach ($treatments as $entry) {
                $dateStr  = !empty($entry['entry_date']) ? date('d.m.Y', strtotime($entry['entry_date'])) : '—';
                $typeStr  = ucfirst($entry['type'] ?? '—');
                $titleStr = $entry['title'] ?? '—';
                $contStr  = $this->htmlToPlainText($entry['content'] ?? '');
                $contStr  = mb_strlen($contStr) > 250 ? mb_substr($contStr, 0, 247) . '…' : ($contStr ?: '—');

                $numLines = max(
                    $pdf->getNumLines($titleStr, $cTitle),
                    $pdf->getNumLines($contStr,  $cCont)
                );
                $rH = max(5.5, $numLines * 4.5);

                $this->checkPageBreak($pdf, $curY, $rH + 4, $pageH, $drawSidebar, $contentX, $font, $fontSize);
                $curY = $pdf->GetY();

                $bg = $fill ? [248, 249, 250] : [255, 255, 255];
                $pdf->SetFillColor(...$bg);
                $pdf->SetTextColor(...$colorTableText);
                $pdf->SetFont($font, '', $fontSize - 2);
                $pdf->SetXY($contentX, $curY);
                $pdf->MultiCell($cDate,  $rH, $dateStr,  1, 'L', true, 0);
                $pdf->MultiCell($cType,  $rH, $typeStr,  1, 'L', true, 0);
                $pdf->MultiCell($cTitle, $rH, $titleStr, 1, 'L', true, 0);
                $pdf->MultiCell($cCont,  $rH, $contStr,  1, 'L', true, 1);
                $curY = $pdf->GetY();
                $fill = !$fill;
            }
            $curY += 5;
        }

        // ── Kommende Termine ──────────────────────────────────────────────
        $upcoming = array_values(array_filter(
            $appointments,
            fn($a) => strtotime($a['start_at'] ?? '') >= time()
        ));

        if (!empty($upcoming)) {
            $this->checkPageBreak($pdf, $curY, 20, $pageH, $drawSidebar, $contentX, $font, $fontSize);
            $curY = $pdf->GetY();

            $pdf->SetFont($font, 'B', $fontSize - 1);
            $pdf->SetTextColor(...$colorTableHdrText);
            $pdf->SetFillColor(...$colorTableHdrBg);
            $pdf->SetXY($contentX, $curY);
            $pdf->Cell($contentW, 6, '  KOMMENDE TERMINE', 0, 1, 'L', true);
            $curY = $pdf->GetY() + 2;

            $cDt  = 35;
            $cApt = 60;
            $cTt  = $contentW - $cDt - $cApt;

            $pdf->SetFillColor(...$colorTableHdrBg);
            $pdf->SetTextColor(...$colorTableHdrText);
            $pdf->SetFont($font, 'B', $fontSize - 2);
            $pdf->SetXY($contentX, $curY);
            $pdf->Cell($cDt,  5.5, 'Datum & Uhrzeit', 1, 0, 'L', true);
            $pdf->Cell($cApt, 5.5, 'Titel',            1, 0, 'L', true);
            $pdf->Cell($cTt,  5.5, 'Behandlungsart',   1, 1, 'L', true);
            $curY = $pdf->GetY();

            $fill = false;
            foreach (array_slice($upcoming, 0, 10) as $appt) {
                $bg = $fill ? [248, 249, 250] : [255, 255, 255];
                $pdf->SetFillColor(...$bg);
                $pdf->SetTextColor(...$colorTableText);
                $pdf->SetFont($font, '', $fontSize - 2);
                $pdf->SetXY($contentX, $curY);
                $pdf->Cell($cDt,  5.5, date('d.m.Y H:i', strtotime($appt['start_at'])) . ' Uhr', 1, 0, 'L', true);
                $pdf->Cell($cApt, 5.5, $appt['title'] ?? '—', 1, 0, 'L', true);
                $pdf->Cell($cTt,  5.5, $appt['treatment_type_name'] ?? '—', 1, 1, 'L', true);
                $curY = $pdf->GetY();
                $fill = !$fill;
            }
            $curY += 5;
        }

        // ── Footer ────────────────────────────────────────────────────────
        $footerTopY = 275;
        if ($pdf->GetY() > $footerTopY - 10) {
            $pdf->AddPage();
            $drawSidebar();
            $pdf->SetXY($contentX, 15);
        }

        $pdf->SetDrawColor(...$colorLine);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($contentX, $footerTopY, $rightEdge, $footerTopY);

        $pdf->SetFont($font, '', $fontSize - 1.5);
        $pdf->SetTextColor(...$colorFooter);
        $footerParts = array_filter([
            $companyName,
            $companyEmail,
            $companyPhone ? 'Tel: ' . $companyPhone : '',
            ($showWebsite && $companyWebsite) ? $companyWebsite : '',
        ]);
        $pdf->SetXY($contentX, $footerTopY + 3);
        $pdf->Cell($contentW, 4, implode('   ·   ', $footerParts), 0, 1, 'C');

        $pdf->SetFont($font, 'I', $fontSize - 2);
        $pdf->SetXY($contentX, $footerTopY + 8);
        $pdf->Cell($contentW, 4,
            'Dieser Bericht wurde automatisch erstellt am ' . date('d.m.Y') . ' · Nur zur tierärztlichen Information',
            0, 1, 'C');

        return $pdf->Output('', 'S');
    }

    /* ── Page break check + sidebar redraw ──────────────────────────── */
    private function checkPageBreak(
        TCPDF    $pdf,
        float    &$curY,
        float    $neededH,
        float    $pageH,
        callable $drawSidebar,
        float    $contentX,
        string   $font,
        float    $fontSize
    ): void {
        if ($curY + $neededH > $pageH - 22) {
            $pdf->AddPage();
            $drawSidebar();
            $curY = 15;
        }
        $pdf->SetXY($contentX, $curY);
    }

    /* ── Strip HTML → plain text ─────────────────────────────────────── */
    private function htmlToPlainText(string $html): string
    {
        if ($html === '') return '';
        $html = preg_replace('/<\/?(?:p|div|br|li|h[1-6])[^>]*>/i', "\n", $html);
        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/[ \t]+/', ' ', $html);
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        return trim($html);
    }

    /* ── Helpers ─────────────────────────────────────────────────────── */
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
        return match (strtolower($font)) {
            'times', 'times new roman' => 'times',
            'courier'                  => 'courier',
            'dejavusans'               => 'dejavusans',
            default                    => 'helvetica',
        };
    }
}
