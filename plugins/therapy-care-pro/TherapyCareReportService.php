<?php

declare(strict_types=1);

namespace Plugins\TherapyCarePro;

use App\Repositories\SettingsRepository;
use TCPDF;

class TherapyCareReportService
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository
    ) {}

    public function generate(
        array  $report,
        array  $patient,
        ?array $owner,
        array  $progressLatest,
        array  $homework,
        array  $naturalEntries,
        array  $timeline
    ): string {
        $settings = $this->settingsRepository->all();

        // ── Color scheme (reuse PDF settings from core) ──────────────────
        $sidebarColor      = $this->hexToRgb($settings['pdf_primary_color']  ?? '#8B9E8B');
        $colorTableHdrBg   = $sidebarColor;
        $colorTableHdrText = [255, 255, 255];
        $colorLine         = [180, 180, 180];
        $colorDark         = [30,  30,  30 ];
        $colorGray         = [100, 100, 100];
        $colorFooter       = [120, 120, 120];

        $font     = $this->resolvePdfFont($settings['pdf_font'] ?? 'helvetica');
        $fs       = (float)($settings['pdf_font_size'] ?? 9);
        $fs       = max(6.0, min(14.0, $fs));

        $companyName    = $settings['company_name']    ?? '';
        $companyStreet  = $settings['company_street']  ?? '';
        $companyZip     = $settings['company_zip']     ?? '';
        $companyCity    = $settings['company_city']    ?? '';
        $companyPhone   = $settings['company_phone']   ?? '';
        $companyEmail   = $settings['company_email']   ?? '';

        $logoFile = null;
        if (!empty($settings['company_logo'])) {
            $uploadsDir    = realpath(tenant_storage_path('uploads'));
            $logoCandidate = $uploadsDir . '/' . basename($settings['company_logo']);
            $logoReal      = realpath($logoCandidate);
            if ($uploadsDir && $logoReal && str_starts_with($logoReal, $uploadsDir)) {
                $logoFile = $logoReal;
            }
        }

        // ── Layout constants ─────────────────────────────────────────────
        $sidebarW  = 42;
        $contentX  = 50;
        $contentW  = 145;
        $rightEdge = 195;
        $pageH     = 297;

        // ── TCPDF setup ──────────────────────────────────────────────────
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('TherapyCare Pro');
        $pdf->SetAuthor($companyName);
        $pdf->SetTitle('Therapiebericht – ' . $patient['name']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // ── Sidebar draw function (reusable) ─────────────────────────────
        $drawSidebar = function () use (
            $pdf, $sidebarColor, $sidebarW, $pageH, $logoFile, $font, $fs, $colorGray
        ): void {
            $pdf->SetFillColor(...$sidebarColor);
            $pdf->Rect(0, 0, $sidebarW, $pageH, 'F');

            $logoY = 12;
            if ($logoFile && file_exists($logoFile)) {
                $logoMaxW = $sidebarW - 8;
                $pdf->Image($logoFile, 4, $logoY, $logoMaxW, 0, '', '', '', false, 300);
                $logoY += 24;
            } else {
                $cx = $sidebarW / 2;
                $cy = $logoY + 10;
                $pdf->SetDrawColor(255, 255, 255);
                $pdf->SetLineWidth(0.4);
                $pdf->Circle($cx, $cy, 9, 0, 360, 'D');
                $pdf->SetFont($font, 'B', 6);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetXY(3, $cy - 3);
                $pdf->Cell($sidebarW - 6, 6, 'LOGO', 0, 0, 'C');
                $logoY += 22;
            }

            // "Therapiebericht" label in sidebar
            $pdf->SetFont($font, '', $fs - 2.5);
            $pdf->SetTextColor(210, 230, 210);
            $pdf->SetXY(2, $logoY + 4);
            $pdf->Cell($sidebarW - 4, 4, 'THERAPIEBERICHT', 0, 1, 'C');
            $pdf->SetFont($font, 'B', $fs - 1);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY(2, $logoY + 10);
            $pdf->MultiCell($sidebarW - 4, 4, date('d.m.Y'), 0, 'C');
        };

        $drawSidebar();

        // ── Company header (top right) ────────────────────────────────────
        $pdf->SetFont($font, 'B', $fs + 1);
        $pdf->SetTextColor(...$colorDark);
        $pdf->SetXY($contentX + ($contentW / 2), 8);
        $pdf->Cell($contentW / 2, 5, $companyName, 0, 1, 'R');

        $pdf->SetFont($font, '', $fs - 1.5);
        $pdf->SetTextColor(...$colorGray);
        $infoLines = array_filter([
            $companyStreet,
            trim($companyZip . ' ' . $companyCity),
            $companyPhone ? 'Tel: ' . $companyPhone : '',
            $companyEmail,
        ]);
        $infoY = 14;
        foreach ($infoLines as $line) {
            $pdf->SetXY($contentX + ($contentW / 2), $infoY);
            $pdf->Cell($contentW / 2, 4, $line, 0, 1, 'R');
            $infoY += 4;
        }

        // ── Report title ─────────────────────────────────────────────────
        $titleY = max($infoY + 6, 40);
        $pdf->SetFont($font, 'B', $fs + 6);
        $pdf->SetTextColor(...$colorDark);
        $pdf->SetXY($contentX, $titleY);
        $pdf->Cell($contentW, 10, 'Therapiebericht', 0, 1, 'L');

        $pdf->SetFont($font, '', $fs);
        $pdf->SetTextColor(...$colorGray);
        $pdf->SetX($contentX);
        $pdf->Cell($contentW, 5, $report['title'], 0, 1, 'L');

        // ── Horizontal rule ──────────────────────────────────────────────
        $hrY = $pdf->GetY() + 3;
        $pdf->SetDrawColor(...$sidebarColor);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($contentX, $hrY, $rightEdge, $hrY);

        // ── Patient & Owner info block ────────────────────────────────────
        $blockY = $hrY + 5;
        $colW2  = ($contentW / 2) - 4;

        $pdf->SetFont($font, 'B', $fs - 0.5);
        $pdf->SetTextColor(...$sidebarColor);
        $pdf->SetXY($contentX, $blockY);
        $pdf->Cell($colW2, 5, 'Patient', 0, 1);
        $pdf->SetFont($font, '', $fs - 0.5);
        $pdf->SetTextColor(...$colorDark);

        $patInfoLines = array_filter([
            $patient['name'],
            $patient['species'] ? $patient['species'] . ($patient['breed'] ? ', ' . $patient['breed'] : '') : '',
            $patient['birth_date'] ? 'Geb.: ' . date('d.m.Y', strtotime($patient['birth_date'])) : '',
            $patient['chip_number'] ? 'Chip: ' . $patient['chip_number'] : '',
        ]);
        $patY = $blockY + 6;
        foreach ($patInfoLines as $line) {
            $pdf->SetXY($contentX, $patY);
            $pdf->Cell($colW2, 4, $line, 0, 1);
            $patY += 4;
        }

        if ($owner) {
            $pdf->SetFont($font, 'B', $fs - 0.5);
            $pdf->SetTextColor(...$sidebarColor);
            $pdf->SetXY($contentX + $colW2 + 8, $blockY);
            $pdf->Cell($colW2, 5, 'Tierhalter', 0, 1);
            $pdf->SetFont($font, '', $fs - 0.5);
            $pdf->SetTextColor(...$colorDark);

            $ownerInfoLines = array_filter([
                ($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''),
                $owner['street'] ?? '',
                trim(($owner['zip'] ?? '') . ' ' . ($owner['city'] ?? '')),
                $owner['email'] ?? '',
                $owner['phone'] ?? '',
            ]);
            $owY = $blockY + 6;
            foreach ($ownerInfoLines as $line) {
                $pdf->SetXY($contentX + $colW2 + 8, $owY);
                $pdf->Cell($colW2, 4, $line, 0, 1);
                $owY += 4;
            }
        }

        $pdf->SetY(max($patY, $owY ?? $patY) + 4);

        // ── Section helper closure ────────────────────────────────────────
        $drawSection = function (string $title) use ($pdf, $font, $fs, $contentX, $contentW, $rightEdge, $sidebarColor, $colorDark): void {
            if ($pdf->GetY() > 255) { $pdf->AddPage(); }
            $y = $pdf->GetY() + 3;
            $pdf->SetFillColor(...$sidebarColor);
            $pdf->Rect($contentX, $y, $contentW, 7, 'F');
            $pdf->SetFont($font, 'B', $fs);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($contentX + 2, $y + 1.5);
            $pdf->Cell($contentW - 4, 4, $title, 0, 1, 'L');
            $pdf->SetTextColor(...$colorDark);
            $pdf->SetY($y + 9);
        };

        $drawText = function (string $label, string $value) use ($pdf, $font, $fs, $contentX, $contentW, $colorGray, $colorDark): void {
            if (!trim($value)) return;
            if ($pdf->GetY() > 260) { $pdf->AddPage(); }
            $pdf->SetFont($font, 'B', $fs - 0.5);
            $pdf->SetTextColor(...$colorGray);
            $pdf->SetXY($contentX, $pdf->GetY());
            $pdf->Cell(38, 5, $label . ':', 0, 0, 'L');
            $pdf->SetFont($font, '', $fs - 0.5);
            $pdf->SetTextColor(...$colorDark);
            $pdf->SetXY($contentX + 38, $pdf->GetY());
            $pdf->MultiCell($contentW - 38, 5, $value, 0, 'L');
            $pdf->SetY($pdf->GetY() + 1);
        };

        // ── SECTION: Anlass / Diagnose ────────────────────────────────────
        if (!empty($report['diagnosis'])) {
            $drawSection('Anlass / Diagnose');
            $pdf->SetFont($font, '', $fs - 0.5);
            $pdf->SetTextColor(...$colorDark);
            $pdf->SetX($contentX);
            $pdf->MultiCell($contentW, 5, $report['diagnosis'], 0, 'L');
            $pdf->SetY($pdf->GetY() + 2);
        }

        // ── SECTION: Verwendete Therapieformen ────────────────────────────
        if (!empty($report['therapies_used'])) {
            $drawSection('Verwendete Therapieformen');
            $pdf->SetFont($font, '', $fs - 0.5);
            $pdf->SetX($contentX);
            $pdf->MultiCell($contentW, 5, $report['therapies_used'], 0, 'L');
            $pdf->SetY($pdf->GetY() + 2);
        }

        // ── SECTION: Fortschrittswerte ────────────────────────────────────
        if (!empty($progressLatest)) {
            $drawSection('Therapiefortschritt (aktuelle Werte)');
            $colLabel = 60;
            $colScore = 25;
            $colBar   = $contentW - $colLabel - $colScore;

            foreach ($progressLatest as $entry) {
                if ($pdf->GetY() > 265) { $pdf->AddPage(); }
                $y = $pdf->GetY();
                $score    = (int)$entry['score'];
                $scaleMax = (int)($entry['scale_max'] ?? 10);
                $scaleMin = (int)($entry['scale_min'] ?? 1);
                $pct      = ($scaleMax > $scaleMin) ? ($score - $scaleMin) / ($scaleMax - $scaleMin) : 0;

                $pdf->SetFont($font, '', $fs - 0.5);
                $pdf->SetTextColor(...$colorDark);
                $pdf->SetXY($contentX, $y);
                $pdf->Cell($colLabel, 5.5, $entry['category_name'], 0, 0, 'L');

                $pdf->SetFont($font, 'B', $fs);
                $pdf->Cell($colScore, 5.5, $score . '/' . $scaleMax, 0, 0, 'C');

                // Progress bar
                $barX = $contentX + $colLabel + $colScore;
                $barH = 3;
                $barW = $colBar - 6;
                $pdf->SetFillColor(220, 220, 220);
                $pdf->Rect($barX, $y + 1.25, $barW, $barH, 'F');
                $rgb = $this->hexToRgb($entry['category_color'] ?? '#4f7cff');
                $pdf->SetFillColor(...$rgb);
                $pdf->Rect($barX, $y + 1.25, max(1, $barW * $pct), $barH, 'F');

                $pdf->SetY($y + 6.5);
            }
            $pdf->SetY($pdf->GetY() + 2);
        }

        // ── SECTION: Heimübungen ──────────────────────────────────────────
        if (!empty($homework)) {
            $drawSection('Heimübungen / Hausaufgaben');
            foreach ($homework as $hw) {
                if ($pdf->GetY() > 265) { $pdf->AddPage(); }
                $pdf->SetFont($font, 'B', $fs - 0.5);
                $pdf->SetTextColor(...$colorDark);
                $pdf->SetX($contentX);
                $status = match($hw['status'] ?? '') {
                    'completed' => ' ✓', 'in_progress' => ' …', 'cancelled' => ' ✗', default => ''
                };
                $pdf->Cell($contentW, 5, '• ' . $hw['title'] . $status, 0, 1);
                if (!empty($hw['description'])) {
                    $pdf->SetFont($font, '', $fs - 1.5);
                    $pdf->SetTextColor(...$colorGray);
                    $pdf->SetX($contentX + 4);
                    $pdf->MultiCell($contentW - 4, 4, $hw['description'], 0, 'L');
                }
                $pdf->SetY($pdf->GetY() + 1);
            }
        }

        // ── SECTION: Naturheilkunde ───────────────────────────────────────
        if (!empty($naturalEntries)) {
            $drawSection('Naturheilkundliche Maßnahmen');
            foreach ($naturalEntries as $ne) {
                if ($pdf->GetY() > 265) { $pdf->AddPage(); }
                $pdf->SetFont($font, 'B', $fs - 0.5);
                $pdf->SetTextColor(...$colorDark);
                $pdf->SetX($contentX);
                $label = $ne['therapy_type'];
                if (!empty($ne['agent'])) $label .= ' — ' . $ne['agent'];
                if (!empty($ne['dosage'])) $label .= ' (' . $ne['dosage'] . ')';
                $pdf->Cell($contentW, 5, '• ' . $label, 0, 1);
                if (!empty($ne['notes'])) {
                    $pdf->SetFont($font, '', $fs - 1.5);
                    $pdf->SetTextColor(...$colorGray);
                    $pdf->SetX($contentX + 4);
                    $pdf->MultiCell($contentW - 4, 4, $ne['notes'], 0, 'L');
                }
                $pdf->SetY($pdf->GetY() + 1);
            }
        }

        // ── SECTION: Behandlungsverlauf (Timeline) ────────────────────────
        if (!empty($timeline)) {
            $drawSection('Behandlungsverlauf (letzten 10 Einträge)');
            $recent = array_slice($timeline, 0, 10);
            foreach ($recent as $entry) {
                if ($pdf->GetY() > 265) { $pdf->AddPage(); }
                $dateStr = $entry['entry_date'] ? date('d.m.Y', strtotime($entry['entry_date'])) : '';
                $pdf->SetFont($font, '', $fs - 1);
                $pdf->SetTextColor(...$colorGray);
                $pdf->SetXY($contentX, $pdf->GetY());
                $pdf->Cell(20, 4.5, $dateStr, 0, 0);
                $pdf->SetFont($font, 'B', $fs - 0.5);
                $pdf->SetTextColor(...$colorDark);
                $pdf->Cell($contentW - 20, 4.5, $entry['title'], 0, 1);
                if (!empty($entry['content'])) {
                    $pdf->SetFont($font, '', $fs - 1.5);
                    $pdf->SetTextColor(...$colorGray);
                    $pdf->SetX($contentX + 20);
                    $lines = explode("\n", $entry['content']);
                    $firstLine = trim($lines[0]);
                    if ($firstLine) {
                        $pdf->MultiCell($contentW - 20, 4, mb_strimwidth($firstLine, 0, 120, '…'), 0, 'L');
                    }
                }
            }
            $pdf->SetY($pdf->GetY() + 2);
        }

        // ── SECTION: Empfehlungen ─────────────────────────────────────────
        if (!empty($report['recommendations'])) {
            $drawSection('Empfehlungen');
            $pdf->SetFont($font, '', $fs - 0.5);
            $pdf->SetTextColor(...$colorDark);
            $pdf->SetX($contentX);
            $pdf->MultiCell($contentW, 5, $report['recommendations'], 0, 'L');
            $pdf->SetY($pdf->GetY() + 2);
        }

        // ── SECTION: Wiedervorstellung ────────────────────────────────────
        if (!empty($report['followup_recommendation'])) {
            $drawSection('Wiedervorstellung / Nächste Schritte');
            $pdf->SetFont($font, '', $fs - 0.5);
            $pdf->SetTextColor(...$colorDark);
            $pdf->SetX($contentX);
            $pdf->MultiCell($contentW, 5, $report['followup_recommendation'], 0, 'L');
            $pdf->SetY($pdf->GetY() + 2);
        }

        // ── FOOTER ────────────────────────────────────────────────────────
        $footerY = 276;
        $pdf->SetDrawColor(...$colorLine);
        $pdf->SetLineWidth(0.25);
        $pdf->Line($contentX, $footerY, $rightEdge, $footerY);

        $pdf->SetFont($font, '', $fs - 2);
        $pdf->SetTextColor(...$colorFooter);
        $pdf->SetXY($contentX, $footerY + 2);
        $footerParts = array_filter([$companyName, $companyEmail, $companyPhone]);
        $pdf->Cell($contentW, 4, implode('   ·   ', $footerParts), 0, 0, 'C');

        $pdf->SetXY($contentX, $footerY + 6);
        $pdf->Cell($contentW, 4,
            'Erstellt am ' . date('d.m.Y') . ' — TherapyCare Pro',
            0, 0, 'C');

        return $pdf->Output('', 'S');
    }

    /* ── Helpers ── */

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function resolvePdfFont(string $name): string
    {
        return match (strtolower($name)) {
            'helvetica', 'arial'  => 'helvetica',
            'times', 'times new roman' => 'times',
            'courier'             => 'courier',
            'dejavusans'          => 'dejavusans',
            'dejavuserif'         => 'dejavuserif',
            default               => 'helvetica',
        };
    }
}
