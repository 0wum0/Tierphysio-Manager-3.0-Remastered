<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;
use TCPDF;

class CustomVetReportPdfService
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository
    ) {}

    /**
     * Generate a Custom Vet Report PDF and return the raw binary string.
     */
    public function generate(
        array $reportData,
        array $patient,
        array $owner,
        array $settings
    ): string {
        // ── Settings ────────────────────────────────────────────────────
        $primaryColor = $this->hexToRgb($settings['pdf_primary_color'] ?? '#8B9E8B');
        $accentColor  = $this->hexToRgb($settings['pdf_accent_color']  ?? '#6B7F6B');
        $darkColor    = $this->hexToRgb($settings['pdf_color_company_name'] ?? '#1E1E1E');
        $grayColor    = $this->hexToRgb($settings['pdf_color_company_info'] ?? '#6E6E6E');
        $lineColor    = $this->hexToRgb($settings['pdf_color_line'] ?? '#B4B4B4');
        $font         = $this->resolvePdfFont($settings['pdf_font'] ?? 'helvetica');
        $fontSize     = (float)($settings['pdf_font_size'] ?? 9);

        $companyName    = $settings['company_name']    ?? '';
        $companyStreet  = $settings['company_street']  ?? '';
        $companyZip     = $settings['company_zip']     ?? '';
        $companyCity    = $settings['company_city']    ?? '';
        $companyPhone   = $settings['company_phone']   ?? '';
        $companyEmail   = $settings['company_email']   ?? '';
        $logoFile       = !empty($settings['company_logo'])
            ? tenant_storage_path('uploads/' . $settings['company_logo'])
            : null;

        // ── Layout constants (identical to PdfService) ───────────────────
        $sidebarW  = 42;
        $contentX  = 50;
        $contentW  = 145;
        $rightEdge = 195;
        $pageH     = 297;

        // ── TCPDF setup ───────────────────────────────────────────────────
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // ── LEFT SIDEBAR ──────────────────────────────────────────────────
        $pdf->SetFillColor(...$primaryColor);
        $pdf->Rect(0, 0, $sidebarW, $pageH, 'F');

        $logoY = 14;
        if ($logoFile && file_exists($logoFile)) {
            $logoMaxW = $sidebarW - 10;
            $pdf->Image($logoFile, 5, $logoY, $logoMaxW, 0, '', '', '', false, 300);
            $logoY += 26;
        } else {
            $cx = $sidebarW / 2;
            $cy = $logoY + 12;
            $pdf->SetDrawColor(255, 255, 255);
            $pdf->SetLineWidth(0.5);
            $pdf->Circle($cx, $cy, 11, 0, 360, 'D');
            $pdf->SetFont($font, 'B', 7);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY(3, $cy - 4);
            $pdf->Cell($sidebarW - 6, 8, 'LOGO', 0, 0, 'C');
            $logoY += 28;
        }

        // Sidebar: Dokument Typ
        $sideY = $logoY + 10;
        $pdf->SetFont($font, '', $fontSize - 2);
        $pdf->SetTextColor(220, 235, 220);
        $pdf->SetXY(3, $sideY);
        $pdf->Cell($sidebarW - 6, 4, 'Dokument', 0, 1, 'C');
        $pdf->SetFont($font, 'B', $fontSize - 1);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(3, $sideY + 4);
        $pdf->Cell($sidebarW - 6, 5, 'Tierarztbericht', 0, 1, 'C');

        // Sidebar: Datum
        $sideY += 22;
        $pdf->SetFont($font, '', $fontSize - 2);
        $pdf->SetTextColor(220, 235, 220);
        $pdf->SetXY(3, $sideY);
        $pdf->Cell($sidebarW - 6, 4, 'Datum', 0, 1, 'C');
        $pdf->SetFont($font, 'B', $fontSize - 1);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(3, $sideY + 4);
        $pdf->Cell($sidebarW - 6, 5, date('d.m.Y', strtotime($reportData['created_at'] ?? 'now')), 0, 1, 'C');

        // ── HEADER: Company info top right ────────────────────────────────
        $pdf->SetFont($font, 'B', $fontSize + 2);
        $pdf->SetTextColor(...$darkColor);
        $pdf->SetXY($contentX + ($contentW / 2), 8);
        $pdf->Cell($contentW / 2, 5, $companyName, 0, 1, 'R');

        $pdf->SetFont($font, '', $fontSize - 1.5);
        $pdf->SetTextColor(...$grayColor);
        $infoLines = array_filter([
            $companyStreet,
            trim($companyZip . ' ' . $companyCity),
            $companyPhone  ? 'Tel: ' . $companyPhone  : '',
            $companyEmail,
        ]);
        $infoY = 15;
        foreach ($infoLines as $line) {
            $pdf->SetXY($contentX + ($contentW / 2), $infoY);
            $pdf->Cell($contentW / 2, 4, $line, 0, 1, 'R');
            $infoY += 4;
        }

        // "Tierarztbericht" + "Befund/Rücküberweisung" titles — right aligned
        $titleY = max($infoY + 4, 30);
        $pdf->SetFont($font, 'B', 26);
        $pdf->SetTextColor(...$darkColor);
        $pdf->SetXY($contentX, $titleY);
        $pdf->Cell($contentW, 14, 'Tierarztbericht', 0, 1, 'R');

        $pdf->SetFont($font, 'B', 16);
        $pdf->SetTextColor(...$grayColor);
        $pdf->SetXY($contentX, $titleY + 12);
        $pdf->Cell($contentW, 10, 'Befund/Rücküberweisung', 0, 1, 'R');

        // ── Patient & Owner info block ─────────────────────────────────────
        $blockY = max($titleY + 28, 56);

        $ownerName  = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
        $patName    = $patient['name'] ?? '—';
        $patSpecies = $patient['species'] ?? '';
        $patBreed   = $patient['breed']   ?? '';
        $patBirth   = !empty($patient['birth_date']) ? date('d.m.Y', strtotime($patient['birth_date'])) : '';

        // Patient block (left)
        $pdf->SetFont($font, 'B', $fontSize);
        $pdf->SetTextColor(...$darkColor);
        $pdf->SetXY($contentX, $blockY);
        $pdf->Cell(($contentW / 2) - 4, 5, 'Patient', 0, 1);
        $pdf->SetFont($font, '', $fontSize - 0.5);
        $pdf->SetTextColor(...$grayColor);
        $pdf->SetXY($contentX, $blockY + 5);
        $pdf->Cell(($contentW / 2) - 4, 4, $patName . ($patSpecies ? ' (' . $patSpecies . ')' : ''), 0, 1);
        if ($patBreed) {
            $pdf->SetXY($contentX, $blockY + 9);
            $pdf->Cell(($contentW / 2) - 4, 4, 'Rasse: ' . $patBreed, 0, 1);
        }
        if ($patBirth) {
            $pdf->SetXY($contentX, $blockY + 13);
            $pdf->Cell(($contentW / 2) - 4, 4, 'Geb.: ' . $patBirth, 0, 1);
        }

        // Owner block (right)
        $colR = $contentX + ($contentW / 2) + 4;
        $colRW = ($contentW / 2) - 4;
        $pdf->SetFont($font, 'B', $fontSize);
        $pdf->SetTextColor(...$darkColor);
        $pdf->SetXY($colR, $blockY);
        $pdf->Cell($colRW, 5, 'Tierhalter', 0, 1, 'R');
        $pdf->SetFont($font, '', $fontSize - 0.5);
        $pdf->SetTextColor(...$grayColor);
        $pdf->SetXY($colR, $blockY + 5);
        $pdf->Cell($colRW, 4, $ownerName, 0, 1, 'R');
        if (!empty($owner['phone'])) {
            $pdf->SetXY($colR, $blockY + 9);
            $pdf->Cell($colRW, 4, $owner['phone'], 0, 1, 'R');
        }
        if (!empty($owner['email'])) {
            $pdf->SetXY($colR, $blockY + 13);
            $pdf->Cell($colRW, 4, $owner['email'], 0, 1, 'R');
        }

        // Separator line
        $sepY = $blockY + 22;
        $pdf->SetDrawColor(...$lineColor);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($contentX, $sepY, $rightEdge, $sepY);

        // ── REPORT CONTENT ────────────────────────────────────────────────
        $curY = $sepY + 8;
        
        $content = $reportData['content'] ?? '';
        
        if (!empty(trim($content))) {
            $pdf->SetFont($font, '', $fontSize);
            $pdf->SetTextColor(...$darkColor);
            
            // Output Multicell for continuous wrap text
            $pdf->SetXY($contentX, $curY);
            
            // Replace tabs/weird linebreaks and ensure plain text looks ok
            $cleanContent = str_replace("\r", "", $content); // standardize linebreaks
            
            $strLines = explode("\n", $cleanContent);
            
            foreach ($strLines as $line) {
                // If it pushes page bounds, add a page
                $numLines = $pdf->getNumLines($line, $contentW);
                $estimatedH = max(1, $numLines) * 5.0; // aprox 5mm per row
                
                $curY = $this->ensurePageSpace($pdf, $primaryColor, $sidebarW, $pageH, $curY, $estimatedH);
                
                $pdf->SetXY($contentX, $curY);
                if (trim($line) === '') {
                    $curY += 5; // Paragraph spacing
                    continue;
                }
                
                $pdf->MultiCell($contentW, 5, $line, 0, 'L');
                $curY = $pdf->GetY();
            }
        }
        
        $curY += 10;

        // ── SIGNATURE LINE ───────────────────────────────────────────────
        $curY = $this->ensurePageSpace($pdf, $primaryColor, $sidebarW, $pageH, $curY, 20);
        $curY += 4;

        $pdf->SetDrawColor(...$lineColor);
        $pdf->SetLineWidth(0.2);

        $sigW = ($contentW - 10) / 2;
        // Datum
        $pdf->Line($contentX, $curY + 8, $contentX + $sigW, $curY + 8);
        $pdf->SetFont($font, '', $fontSize - 2);
        $pdf->SetTextColor(...$grayColor);
        $pdf->SetXY($contentX, $curY + 9);
        $pdf->Cell($sigW, 4, 'Datum', 0, 0, 'C');

        // Unterschrift Behandler + Stempel
        $sig2X = $contentX + $sigW + 10;
        $pdf->Line($sig2X, $curY + 8, $sig2X + $sigW, $curY + 8);
        $pdf->SetXY($sig2X, $curY + 9);
        $pdf->Cell($sigW, 4, 'Tierarzt / Behandler / Stempel', 0, 0, 'C');

        // ── FOOTER (all pages) ────────────────────────────────────────────
        $pageCount = $pdf->getNumPages();
        for ($p = 1; $p <= $pageCount; $p++) {
            $pdf->setPage($p);
            $pdf->SetFont($font, '', $fontSize - 2.5);
            $pdf->SetTextColor(...$grayColor);
            $pdf->SetXY($contentX, $pageH - 8);
            $pdf->Cell($contentW / 2, 4, $companyName . ' · Tierarztbericht', 0, 0, 'L');
            $pdf->Cell($contentW / 2, 4, 'Seite ' . $p . ' von ' . $pageCount, 0, 0, 'R');
            $pdf->SetXY($contentX, $pageH - 5);
            $pdf->Cell($contentW, 4, 'Vertraulich — nur für den internen Gebrauch bestimmt.', 0, 0, 'C');
        }

        return $pdf->Output('', 'S');
    }

    private function ensurePageSpace(
        TCPDF $pdf,
        array $primaryColor,
        float $sidebarW,
        float $pageH,
        float $curY,
        float $needed
    ): float {
        if ($curY + $needed > $pageH - 20) {
            $pdf->AddPage();
            $pdf->SetFillColor(...$primaryColor);
            $pdf->Rect(0, 0, $sidebarW, $pageH, 'F');
            return 15;
        }
        return $curY;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function resolvePdfFont(string $fontName): string
    {
        $map = [
            'helvetica' => 'helvetica',
            'times'     => 'times',
            'courier'   => 'courier',
            'dejavusans'=> 'dejavusans',
        ];
        return $map[strtolower($fontName)] ?? 'helvetica';
    }
}
