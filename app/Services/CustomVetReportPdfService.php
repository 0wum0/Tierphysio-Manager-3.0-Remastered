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
     * Layout is identical to VetReportService — only the content section differs.
     */
    public function generate(
        array $reportData,
        array $patient,
        array $owner,
        array $settings
    ): string {
        // ── Settings ────────────────────────────────────────────────────
        $sidebarColor    = $this->hexToRgb($settings['pdf_primary_color'] ?? '#8B9E8B');
        $colorHdrBg      = $sidebarColor;
        $colorHdrText    = [255, 255, 255];
        $colorLine       = [180, 180, 180];
        $colorFooter     = [120, 120, 120];
        $font            = $this->resolvePdfFont($settings['pdf_font'] ?? 'helvetica');
        $fontSize        = max(6.0, min(16.0, (float)($settings['pdf_font_size'] ?? 9)));
        $showWebsite     = ($settings['pdf_show_website'] ?? '0') === '1';

        $companyName    = $settings['company_name']    ?? '';
        $companyStreet  = $settings['company_street']  ?? '';
        $companyZip     = $settings['company_zip']     ?? '';
        $companyCity    = $settings['company_city']    ?? '';
        $companyPhone   = $settings['company_phone']   ?? '';
        $companyEmail   = $settings['company_email']   ?? '';
        $companyWebsite = $settings['company_website'] ?? '';

        // Path-safe logo
        $logoFile = null;
        if (!empty($settings['company_logo'])) {
            $uploadsDir    = realpath(tenant_storage_path('uploads'));
            $logoCandidate = $uploadsDir . '/' . basename($settings['company_logo']);
            $logoReal      = realpath($logoCandidate);
            if ($uploadsDir && $logoReal && strpos($logoReal, $uploadsDir) === 0) {
                $logoFile = $logoReal;
            }
        }

        // ── Layout constants (identical to VetReportService) ─────────────
        $sidebarW  = 42;
        $contentX  = 50;
        $contentW  = 145;
        $rightEdge = 195;
        $pageH     = 297;
        $createdDate = date('d.m.Y', strtotime($reportData['created_at'] ?? 'now'));

        // ── TCPDF setup ───────────────────────────────────────────────────
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

            // Reset text color after sidebar drawing
            $pdf->SetTextColor(30, 30, 30);
        };

        $drawSidebar();

        // ── Company info top right ────────────────────────────────────────
        $pdf->SetFont($font, 'B', $fontSize + 1);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetXY($contentX + ($contentW / 2), 8);
        $pdf->Cell($contentW / 2, 5, $companyName, 0, 1, 'R');

        $pdf->SetFont($font, '', $fontSize - 1.5);
        $pdf->SetTextColor(100, 100, 100);
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

        // "Tierarztbericht" — freeserif italic, identical to auto report
        $titleY = $infoY + 4;
        $pdf->SetFont('freeserif', 'I', 28);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetXY($contentX, $titleY);
        $pdf->Cell($contentW, 14, 'Tierarztbericht', 0, 0, 'R');

        $pdf->SetFont('freeserif', 'I', 14);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY($contentX, $titleY + 13);
        $pdf->Cell($contentW, 8, 'Befund/Rücküberweisung', 0, 0, 'R');
        $titleBottomY = $titleY + 22;

        // ── Info block: owner LEFT, patient fields + photo RIGHT ──────────
        $blockTopY = max($titleBottomY + 4, 58);
        $ownerW    = 58;
        $patColX   = $contentX + $ownerW + 4;
        $patColW   = $contentW - $ownerW - 4;
        $photoW    = 24;
        $lblW      = 24;
        $rowH      = 5.0;

        // Owner block (left)
        if ($owner) {
            $pdf->SetFont($font, 'B', $fontSize);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetXY($contentX, $blockTopY);
            $pdf->Cell($ownerW, $rowH, trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? '')), 0, 0);
            $owY = $blockTopY + $rowH;
            $pdf->SetFont($font, '', $fontSize - 0.5);
            foreach (array_filter([
                $owner['street'] ?? '',
                trim(($owner['zip'] ?? '') . ' ' . ($owner['city'] ?? '')),
            ]) as $line) {
                $pdf->SetXY($contentX, $owY);
                $pdf->Cell($ownerW, $rowH, $line, 0, 0);
                $owY += $rowH;
            }
            if (!empty($owner['phone'])) {
                $owY += 2;
                $pdf->SetFont($font, '', $fontSize - 1.5);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->SetXY($contentX, $owY);
                $pdf->Cell($ownerW, $rowH - 1, 'Tel: ' . $owner['phone'], 0, 0);
            }
        }

        // ── Patient block layout ───────────────────────────────────────────
        $ownerW = 58;
        $patColX = $contentX + $ownerW + 4;
        $patColW = $contentW - $ownerW - 4;
        $photoW = 24;
        $lblW = 24;
        $rowH = 5.0;

        // Patient photo (path-safe)
        $hasPhoto = false;
        $patPhoto = null;
        if (!empty($patient['photo'])) {
            // Try multiple possible photo paths
            $photoPaths = [
                \storage_path('app/public/patients/' . $patient['id'] . '/' . basename($patient['photo'])),
                \tenant_storage_path('patients/' . $patient['id'] . '/' . basename($patient['photo'])),
                \public_path('patients/' . $patient['id'] . '/' . basename($patient['photo'])),
            ];

            foreach ($photoPaths as $photoPath) {
                if (file_exists($photoPath)) {
                    $hasPhoto = true;
                    $patPhoto = $photoPath;
                    break;
                }
            }
        }
        $photoX = $patColX + $patColW - $photoW;
        if ($hasPhoto) {
            $pdf->Image($patPhoto, $photoX, $blockTopY, $photoW, $photoW, '', '', '', true, 150, '', false, false, 1);
        }

        // Value column width: reserve space for photo only when it exists
        $valW = $hasPhoto
            ? ($patColW - $photoW - $lblW - 2)
            : ($patColW - $lblW - 2);

        // Patient fields (label + value, left of photo)
        $patFields = array_filter([
            'Patient'   => $patient['name']        ?? '',
            'Tierart'   => $patient['species']     ?? '',
            'Rasse'     => $patient['breed']       ?? '',
            'Geb.datum' => !empty($patient['birth_date']) ? date('d.m.Y', strtotime($patient['birth_date'])) : '',
            'Geschlecht'=> $patient['gender']      ?? '',
            'Status'    => $patient['status']      ?? '',
        ]);
        $pfy = $blockTopY;
        foreach ($patFields as $lbl => $val) {
            $pdf->SetFont($font, '', $fontSize - 1.5);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY($patColX, $pfy);
            $pdf->Cell($lblW, $rowH, $lbl, 0, 0);
            $pdf->SetFont($font, 'B', $fontSize - 1.5);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetXY($patColX + $lblW, $pfy);
            $pdf->Cell($valW, $rowH, $val, 0, 0);
            $pfy += $rowH;
        }

        // ── Divider ───────────────────────────────────────────────────────
        $dividerY = max($blockTopY + ($hasPhoto ? $photoW : count($patFields) * $rowH) + 6, 100);
        $pdf->SetDrawColor(...$colorLine);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($contentX, $dividerY, $rightEdge, $dividerY);
        $curY = $dividerY + 6;

        // ── RECIPIENT (An:) ───────────────────────────────────────────────
        $recipient = trim($reportData['recipient'] ?? '');
        if (!empty($recipient)) {
            $this->checkPageBreak($pdf, $curY, 20, $pageH, $drawSidebar, $contentX, $font, $fontSize);
            $curY = $pdf->GetY();

            $pdf->SetFont($font, 'B', $fontSize - 1);
            $pdf->SetTextColor(...$colorHdrText);
            $pdf->SetFillColor(...$colorHdrBg);
            $pdf->SetXY($contentX, $curY);
            $pdf->Cell($contentW, 6, '  AN (TIERARZT / KLINIK)', 0, 1, 'L', true);
            $curY = $pdf->GetY() + 3;

            $pdf->SetFont($font, '', $fontSize - 0.5);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetXY($contentX, $curY);
            $pdf->MultiCell($contentW, 5, $recipient, 0, 'L');
            $curY = $pdf->GetY() + 5;
        }

        // ── REPORT CONTENT ────────────────────────────────────────────────
        $content = trim($reportData['content'] ?? '');
        if ($content !== '') {
            $this->checkPageBreak($pdf, $curY, 20, $pageH, $drawSidebar, $contentX, $font, $fontSize);
            $curY = $pdf->GetY();

            $pdf->SetFont($font, 'B', $fontSize - 1);
            $pdf->SetTextColor(...$colorHdrText);
            $pdf->SetFillColor(...$colorHdrBg);
            $pdf->SetXY($contentX, $curY);
            $pdf->Cell($contentW, 6, '  BERICHTSINHALT', 0, 1, 'L', true);
            $curY = $pdf->GetY() + 3;

            $pdf->SetFont($font, '', $fontSize);
            $pdf->SetTextColor(30, 30, 30);

            // Split content by line breaks and check page breaks for each line
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    $curY += 3;
                    continue;
                }

                $numLines = $pdf->getNumLines($line, $contentW);
                $neededHeight = max(5, $numLines * 5);

                $this->checkPageBreak($pdf, $curY, $neededHeight, $pageH, $drawSidebar, $contentX, $font, $fontSize);
                $curY = $pdf->GetY();

                $pdf->SetXY($contentX, $curY);
                $pdf->MultiCell($contentW, 5, $line, 0, 'L');
                $curY = $pdf->GetY();
            }
            $curY += 5;
        }

        // ── Footer ───────────────────────────────────────────────────────────
        $footerTopY = 275;
        if ($curY > $footerTopY - 10) {
            $pdf->AddPage();
            $drawSidebar();
            $curY = 15;
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
            'Dieser Bericht wurde erstellt am ' . date('d.m.Y') . ' · Nur zur tierärztlichen Information',
            0, 1, 'C');

        // ── Output ─────────────────────────────────────────────────────────
        return $pdf->Output('', 'S');
    }

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
            // Reset font to normal weight after page break to prevent bold text on new page
            $pdf->SetFont($font, '', $fontSize);
            $pdf->SetTextColor(30, 30, 30);
        }
        $pdf->SetXY($contentX, $curY);
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