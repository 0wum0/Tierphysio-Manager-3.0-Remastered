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

        /* ── Colors from PDF settings ── */
        $primary   = $this->hexToRgb($settings['pdf_primary_color']           ?? '#4A6741');
        $dark      = $this->hexToRgb($settings['pdf_color_company_name']      ?? '#1E1E1E');
        $gray      = $this->hexToRgb($settings['pdf_color_company_info']      ?? '#6E6E6E');
        $hdrBg     = $this->hexToRgb($settings['pdf_color_table_header_bg']   ?? '#4A6741');
        $hdrText   = $this->hexToRgb($settings['pdf_color_table_header_text'] ?? '#FFFFFF');
        $line      = $this->hexToRgb($settings['pdf_color_line']              ?? '#CCCCCC');
        $font      = $this->resolvePdfFont($settings['pdf_font'] ?? 'helvetica');

        $companyName   = $settings['company_name']    ?? '';
        $companyStreet = $settings['company_street']  ?? '';
        $companyZip    = $settings['company_zip']     ?? '';
        $companyCity   = $settings['company_city']    ?? '';
        $companyPhone  = $settings['company_phone']   ?? '';
        $companyEmail  = $settings['company_email']   ?? '';
        $logoPath      = STORAGE_PATH . '/uploads/' . ($settings['company_logo'] ?? '');

        /* ── PDF setup ── */
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Tierphysio Manager');
        $pdf->SetAuthor($companyName);
        $pdf->SetTitle('Tierarztbericht – ' . ($patient['name'] ?? ''));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $pageW = $pdf->getPageWidth() - 40; /* usable width */

        /* ══════════════════════════════════════════════════════
           SIDEBAR (left green strip)
           ══════════════════════════════════════════════════════ */
        $sideW = 52;
        $pdf->SetFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->Rect(0, 0, $sideW, $pdf->getPageHeight(), 'F');

        /* Logo in sidebar */
        $logoY = 18;
        if (!empty($settings['company_logo']) && file_exists($logoPath)) {
            $pdf->Image($logoPath, 6, $logoY, $sideW - 12, 0, '', '', '', true, 150);
            $logoY = $pdf->GetY() + 4;
        }

        /* Company info in sidebar */
        $pdf->SetFont($font, 'B', 8);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(6, $logoY + 4);
        $pdf->MultiCell($sideW - 12, 4, $companyName, 0, 'C', false, 1);

        $pdf->SetFont($font, '', 6.5);
        $pdf->SetTextColor(220, 235, 220);
        foreach (array_filter([$companyStreet, trim($companyZip . ' ' . $companyCity), $companyPhone, $companyEmail]) as $line_) {
            $pdf->SetX(6);
            $pdf->MultiCell($sideW - 12, 3.5, $line_, 0, 'C', false, 1);
        }

        /* "TIERARZTBERICHT" label rotated in sidebar */
        $pdf->StartTransform();
        $pdf->Rotate(90, 26, 200);
        $pdf->SetFont($font, 'B', 7);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetAlpha(0.35);
        $pdf->Text(120, 200, 'TIERARZTBERICHT');
        $pdf->StopTransform();
        $pdf->SetAlpha(1);

        /* ══════════════════════════════════════════════════════
           MAIN CONTENT (right of sidebar)
           ══════════════════════════════════════════════════════ */
        $cx   = $sideW + 8;   /* content x */
        $cw   = $pdf->getPageWidth() - $cx - 12; /* content width */
        $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);

        /* ── Report title ── */
        $pdf->SetXY($cx, 18);
        $pdf->SetFont($font, 'B', 16);
        $pdf->Cell($cw, 8, 'Tierarztbericht', 0, 1, 'L');

        $pdf->SetX($cx);
        $pdf->SetFont($font, '', 8);
        $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
        $pdf->Cell($cw, 5, 'Erstellt am ' . date('d.m.Y') . ' — ' . $companyName, 0, 1, 'L');

        /* Divider */
        $pdf->SetDrawColor($hdrBg[0], $hdrBg[1], $hdrBg[2]);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($cx, $pdf->GetY() + 2, $cx + $cw, $pdf->GetY() + 2);
        $pdf->SetLineWidth(0.2);
        $pdf->Ln(6);

        /* ── Patient data ── */
        $this->sectionHeader($pdf, $font, $hdrBg, $hdrText, 'Angaben zum Tier', $cx, $cw);
        $pdf->Ln(2);

        $photoPath = STORAGE_PATH . '/patients/' . (int)$patient['id'] . '/' . ($patient['photo'] ?? '');
        $hasPhoto  = !empty($patient['photo']) && file_exists($photoPath);

        /* Photo + data side by side */
        $dataX = $cx;
        $dataW = $cw;
        if ($hasPhoto) {
            $imgW = 28;
            $imgX = $cx + $cw - $imgW;
            $dataW = $cw - $imgW - 4;
            $pdf->Image($photoPath, $imgX, $pdf->GetY(), $imgW, $imgW, '', '', '', true, 150, '', false, false, 1);
        }

        $fields = [
            ['Name',         $patient['name']       ?? '—'],
            ['Tierart',      $patient['species']    ?? '—'],
            ['Rasse',        $patient['breed']      ?? '—'],
            ['Geburtsdatum', $patient['birth_date'] ? date('d.m.Y', strtotime($patient['birth_date'])) : '—'],
            ['Geschlecht',   $patient['gender']     ?? '—'],
            ['Farbe',        $patient['color']      ?? '—'],
            ['Chip-Nr.',     $patient['chip_number'] ?? '—'],
            ['Status',       $patient['status']     ?? '—'],
        ];

        $rowH = 5.5;
        foreach ($fields as [$label, $value]) {
            $pdf->SetX($dataX);
            $pdf->SetFont($font, 'B', 7.5);
            $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
            $pdf->Cell($dataW * 0.35, $rowH, $label, 0, 0, 'L');
            $pdf->SetFont($font, '', 7.5);
            $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
            $pdf->Cell($dataW * 0.65, $rowH, $value, 0, 1, 'L');
        }
        $pdf->Ln(4);

        /* ── Owner data ── */
        if ($owner) {
            $this->sectionHeader($pdf, $font, $hdrBg, $hdrText, 'Tierhalter', $cx, $cw);
            $pdf->Ln(2);
            $ownerFields = [
                ['Name',    trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''))],
                ['Adresse', trim(($owner['street'] ?? '') . ', ' . ($owner['zip'] ?? '') . ' ' . ($owner['city'] ?? ''))],
                ['Telefon', $owner['phone'] ?? '—'],
                ['E-Mail',  $owner['email'] ?? '—'],
            ];
            foreach ($ownerFields as [$label, $value]) {
                $pdf->SetX($cx);
                $pdf->SetFont($font, 'B', 7.5);
                $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
                $pdf->Cell($cw * 0.35, $rowH, $label, 0, 0, 'L');
                $pdf->SetFont($font, '', 7.5);
                $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
                $pdf->Cell($cw * 0.65, $rowH, $value, 0, 1, 'L');
            }
            $pdf->Ln(4);
        }

        /* ── Notes ── */
        if (!empty($patient['notes'])) {
            $this->sectionHeader($pdf, $font, $hdrBg, $hdrText, 'Anamnese / Notizen', $cx, $cw);
            $pdf->Ln(2);
            $pdf->SetX($cx);
            $pdf->SetFont($font, '', 8);
            $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
            $pdf->MultiCell($cw, 5, $patient['notes'], 0, 'L', false, 1);
            $pdf->Ln(3);
        }

        /* ── Treatment history ── */
        $treatments = array_values(array_filter($timeline, fn($e) => in_array($e['type'], ['treatment', 'note'], true)));
        if (!empty($treatments)) {
            $this->sectionHeader($pdf, $font, $hdrBg, $hdrText, 'Behandlungshistorie', $cx, $cw);
            $pdf->Ln(2);

            /* Table header */
            $col = [$cw * 0.22, $cw * 0.28, $cw * 0.50];
            $pdf->SetX($cx);
            $pdf->SetFillColor($hdrBg[0], $hdrBg[1], $hdrBg[2]);
            $pdf->SetTextColor($hdrText[0], $hdrText[1], $hdrText[2]);
            $pdf->SetFont($font, 'B', 7);
            $pdf->Cell($col[0], 6, 'Datum', 1, 0, 'L', true);
            $pdf->Cell($col[1], 6, 'Typ / Titel', 1, 0, 'L', true);
            $pdf->Cell($col[2], 6, 'Inhalt', 1, 1, 'L', true);

            $fill = false;
            foreach ($treatments as $entry) {
                $pdf->SetX($cx);
                $bg = $fill ? [248, 249, 250] : [255, 255, 255];
                $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);
                $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
                $pdf->SetFont($font, '', 7);

                $dateStr  = $entry['entry_date'] ? date('d.m.Y', strtotime($entry['entry_date'])) : '—';
                $titleStr = $entry['title'] ?? '—';
                $content  = $entry['content'] ?? '';
                $content  = mb_strlen($content) > 200 ? mb_substr($content, 0, 197) . '…' : $content;

                /* Calculate row height for multi-line content */
                $lines    = $pdf->getNumLines($content ?: '—', $col[2]);
                $rH       = max(6, $lines * 4.5);

                $yBefore = $pdf->GetY();
                $pdf->MultiCell($col[0], $rH, $dateStr,  1, 'L', true, 0);
                $pdf->MultiCell($col[1], $rH, $titleStr, 1, 'L', true, 0);
                $pdf->MultiCell($col[2], $rH, $content ?: '—', 1, 'L', true, 1);
                $fill = !$fill;
            }
            $pdf->Ln(4);
        }

        /* ── Upcoming appointments ── */
        $upcoming = array_values(array_filter($appointments, fn($a) => strtotime($a['start_at']) >= time()));
        if (!empty($upcoming)) {
            $this->sectionHeader($pdf, $font, $hdrBg, $hdrText, 'Kommende Termine', $cx, $cw);
            $pdf->Ln(2);

            $col2 = [$cw * 0.30, $cw * 0.40, $cw * 0.30];
            $pdf->SetX($cx);
            $pdf->SetFillColor($hdrBg[0], $hdrBg[1], $hdrBg[2]);
            $pdf->SetTextColor($hdrText[0], $hdrText[1], $hdrText[2]);
            $pdf->SetFont($font, 'B', 7);
            $pdf->Cell($col2[0], 6, 'Datum & Uhrzeit', 1, 0, 'L', true);
            $pdf->Cell($col2[1], 6, 'Titel',           1, 0, 'L', true);
            $pdf->Cell($col2[2], 6, 'Behandlungsart',  1, 1, 'L', true);

            $fill = false;
            foreach (array_slice($upcoming, 0, 10) as $appt) {
                $bg = $fill ? [248, 249, 250] : [255, 255, 255];
                $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);
                $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
                $pdf->SetFont($font, '', 7);
                $pdf->SetX($cx);
                $pdf->Cell($col2[0], 6, date('d.m.Y H:i', strtotime($appt['start_at'])) . ' Uhr', 1, 0, 'L', true);
                $pdf->Cell($col2[1], 6, $appt['title'] ?? '—', 1, 0, 'L', true);
                $pdf->Cell($col2[2], 6, $appt['treatment_type_name'] ?? '—', 1, 1, 'L', true);
                $fill = !$fill;
            }
            $pdf->Ln(4);
        }

        /* ── Footer note ── */
        $pdf->SetY(-30);
        $pdf->SetDrawColor($line[0], $line[1], $line[2]);
        $pdf->Line($cx, $pdf->GetY(), $cx + $cw, $pdf->GetY());
        $pdf->Ln(3);
        $pdf->SetX($cx);
        $pdf->SetFont($font, 'I', 7);
        $pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
        $pdf->MultiCell($cw, 4,
            'Dieser Bericht wurde automatisch erstellt von ' . $companyName . ' am ' . date('d.m.Y') . '. ' .
            'Er dient ausschließlich der tierärztlichen Information und ersetzt keine persönliche Vorstellung.',
            0, 'L');

        return $pdf->Output('', 'S');
    }

    /* ── Section header helper ── */
    private function sectionHeader(TCPDF $pdf, string $font, array $bg, array $fg, string $title, float $x, float $w): void
    {
        $pdf->SetX($x);
        $pdf->SetFillColor($bg[0], $bg[1], $bg[2]);
        $pdf->SetTextColor($fg[0], $fg[1], $fg[2]);
        $pdf->SetFont($font, 'B', 8);
        $pdf->Cell($w, 6, '  ' . mb_strtoupper($title), 0, 1, 'L', true);
        $pdf->SetTextColor(30, 30, 30);
    }

    /* ── Helpers ── */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
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
