<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Repositories\ExpenseRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\SettingsRepository;

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
        private readonly SettingsRepository $settingsRepository
    ) {
        parent::__construct($view, $session, $config, $translator);
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
        $this->expenseRepository->update((int)$params['id'], $data);
        $this->session->flash('success', 'Ausgabe wurde aktualisiert.');
        $this->redirect('/ausgaben');
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
