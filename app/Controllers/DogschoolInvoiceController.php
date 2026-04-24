<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\SettingsRepository;
use App\Repositories\TreatmentTypeRepository;
use App\Services\DogschoolInvoiceService;
use App\Services\InvoiceService;
use App\Services\OwnerService;
use App\Services\PatientService;

/**
 * DogschoolInvoiceController
 *
 * Vollwertiges Rechnungsmodul für Hundeschul-/Trainer-Tenants mit
 * identischer Funktionalität wie die Praxis-Ansicht, nur mit
 * Trainer-Terminologie (Halter statt Besitzer, Hund statt Patient).
 *
 * Nutzt das zentrale InvoiceService für Persistenz — die gleiche
 * `invoices`-Tabelle wie Praxen, damit Dashboard-KPIs, Steuerexport,
 * Mail-Versand und PDF-Generierung ohne Codeverdopplung funktionieren.
 * Zusätzlich erzeugt das DogschoolInvoiceService automatische Rechnungen
 * aus Kurs-Enrollments und Paket-Käufen (bidirektionale Verknüpfung).
 */
class DogschoolInvoiceController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        private readonly DogschoolInvoiceService $service,
        private readonly InvoiceService $invoiceService,
        private readonly PatientService $patientService,
        private readonly OwnerService $ownerService,
        private readonly TreatmentTypeRepository $treatmentTypeRepository,
        private readonly SettingsRepository $settingsRepository,
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    /**
     * Liste aller Rechnungen des Tenants (identisch zur Praxis-Ansicht,
     * aber mit Trainer-Terminologie). Zeigt ALLE Rechnungen — nicht nur
     * die via Enrollment/Paket verknüpften —, damit Trainer ihre manuell
     * erstellten Rechnungen und Kurs-Rechnungen zusammen sehen.
     */
    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_invoicing');

        $status = (string)$this->get('status', '');
        $search = (string)$this->get('search', '');
        $page   = (int)$this->get('page', 1);

        $result    = $this->invoiceService->getPaginated($page, 15, $status, $search);
        $stats     = $this->invoiceService->getStats();
        $chartData = $this->invoiceService->getMonthlyChartData();

        /* Parallel: Kurs-/Paket-spezifische Rechnungen für den Hundeschul-Report-Abschnitt.
         * Liefert {invoice_number, owner_name, source_type, total_gross, ...}. */
        $from = (string)$this->get('from', date('Y-m-01'));
        $to   = (string)$this->get('to',   date('Y-m-d'));
        $dogschoolInvoices = [];
        try {
            $dogschoolInvoices = $this->service->listDogschoolInvoices($from, $to, $status);
        } catch (\Throwable) { /* Feature evtl. ohne Enrollment-Tabelle → leer */ }

        /* Rendert das gemeinsame Invoice-Listen-Template mit is_trainer-Flag.
         * So sehen Hundeschul-Tenants ALLE ihre Rechnungen (manuelle + Kurs-/Paket-
         * generierte) mit Halter-/Hund-Terminologie und den richtigen Listen-/
         * Create-URLs (/hundeschule/rechnungen/*). */
        $this->render('invoices/index.twig', [
            'page_title'         => 'Hundeschul-Rechnungen',
            'active_nav'         => 'dogschool_invoices',
            'invoices'           => $result['items'],
            'pagination'         => $result,
            'stats'              => $stats,
            'chart_data'         => $chartData,
            'status'             => $status,
            'search'             => $search,
            'is_trainer'         => true,
            'list_base_url'      => '/hundeschule/rechnungen',
            'create_url'         => '/hundeschule/rechnungen/erstellen',
            /* Zusätzlich für den Hundeschul-Report-Abschnitt verfügbar,
             * falls ein separates Template diese Liste anzeigen möchte. */
            'dogschool_invoices' => $dogschoolInvoices,
            'filter_from'        => $from,
            'filter_to'          => $to,
            'filter_status'      => $status,
        ]);
    }

    /**
     * Neue-Rechnung-Formular — identischer Flow wie Praxis, nur mit
     * Trainer-Terminologie im Template.
     */
    public function create(array $params = []): void
    {
        $this->requireFeature('dogschool_invoicing');

        $patients = $this->patientService->findAll();
        $owners   = $this->ownerService->findAll();

        $preselected_patient = $this->get('patient_id');
        $preselected_owner   = $this->get('owner_id');

        $treatmentTypes = [];
        try { $treatmentTypes = $this->treatmentTypeRepository->findActive(); } catch (\Throwable) {}

        $settings = $this->settingsRepository->all();
        /* Rendert das gemeinsame Invoice-Create-Template. is_trainer aktiviert
         * die Halter-/Hund-Labels sowie die Hundeschul-Form-Action. Kein
         * separates Template notwendig — DRY. */
        $this->render('invoices/create.twig', [
            'page_title'          => 'Neue Rechnung',
            'active_nav'          => 'dogschool_invoices',
            'patients'            => $patients,
            'owners'              => $owners,
            'preselected_patient' => $preselected_patient,
            'preselected_owner'   => $preselected_owner,
            'next_number'         => $this->invoiceService->generateInvoiceNumber(),
            'treatment_types'     => $treatmentTypes,
            'kleinunternehmer'    => ($settings['kleinunternehmer'] ?? '0') === '1',
            'default_tax_rate'    => $settings['default_tax_rate'] ?? '19',
            'is_trainer'          => true,
            'back_url'            => '/hundeschule/rechnungen',
            'form_action'         => '/hundeschule/rechnungen/speichern',
        ]);
    }

    /**
     * Speichert eine neue Hundeschul-Rechnung. Delegiert an InvoiceService
     * und leitet nach Erfolg auf die Detail-Ansicht (die /rechnungen/{id}
     * ist tenant-aware und zeigt für Trainer automatisch die Halter-Labels).
     */
    public function store(array $params = []): void
    {
        $this->requireFeature('dogschool_invoicing');
        $this->validateCsrf();

        $paymentMethod = $this->sanitize($this->post('payment_method', 'rechnung'));
        if (!in_array($paymentMethod, ['rechnung', 'bar'], true)) {
            $paymentMethod = 'rechnung';
        }
        $isCash = ($paymentMethod === 'bar');

        $data = [
            'invoice_number' => $this->sanitize($this->post('invoice_number', '')),
            'patient_id'     => (int)$this->post('patient_id', 0) ?: null,
            'owner_id'       => (int)$this->post('owner_id', 0),
            'status'         => $isCash ? 'paid' : $this->sanitize($this->post('status', 'draft')),
            'issue_date'     => $this->post('issue_date') ?: date('Y-m-d'),
            'due_date'       => $isCash ? null : ($this->post('due_date', null) ?: null),
            'notes'          => $this->post('notes', ''),
            'diagnosis'      => $this->post('diagnosis', '') ?: null,
            'payment_terms'  => $this->post('payment_terms', ''),
            'payment_method' => $paymentMethod,
            'paid_at'        => $isCash ? date('Y-m-d H:i:s') : null,
        ];

        $positions = $this->parsePositions();

        if (empty($data['owner_id']) || empty($positions)) {
            $this->session->flash('error', 'Bitte wähle einen Halter und füge mindestens eine Position hinzu.');
            $this->redirect('/hundeschule/rechnungen/erstellen');
            return;
        }

        $id  = $this->invoiceService->create($data, $positions);
        $msg = $isCash
            ? 'Quittung erstellt und als Barzahlung verbucht.'
            : 'Rechnung erstellt.';
        $this->session->flash('success', $msg);

        $this->redirect("/rechnungen/{$id}");
    }

    /**
     * Parsiert Rechnungspositionen aus den POST-Daten (identisch zum
     * Format der Praxis-InvoiceController — selbes Formular-Schema).
     *
     * @return array<int, array{description:string, quantity:float, unit_price:float, tax_rate:float, total:float}>
     */
    private function parsePositions(): array
    {
        $raw = $_POST['positions'] ?? [];
        if (!is_array($raw)) return [];

        $out = [];
        foreach ($raw as $p) {
            $desc = trim((string)($p['description'] ?? ''));
            $qty  = (float)str_replace(',', '.', (string)($p['quantity']   ?? '0'));
            $unit = (float)str_replace(',', '.', (string)($p['unit_price'] ?? '0'));
            $tax  = (float)str_replace(',', '.', (string)($p['tax_rate']   ?? '19'));

            if ($desc === '' || $qty <= 0) continue;

            $out[] = [
                'description' => $desc,
                'quantity'    => $qty,
                'unit_price'  => $unit,
                'tax_rate'    => $tax,
                'total'       => round($qty * $unit, 2),
            ];
        }
        return $out;
    }

    public function createForEnrollment(array $params = []): void
    {
        $this->requireFeature('dogschool_invoicing');
        $this->validateCsrf();

        $enrollmentId = (int)($params['enrollment_id'] ?? 0);
        $invoiceId    = $this->service->createForEnrollment($enrollmentId);

        if ($invoiceId === null) {
            $this->flash('warning', 'Rechnung konnte nicht erstellt werden (Kurs kostenlos oder Daten fehlen).');
        } else {
            $this->flash('success', 'Rechnung erstellt.');
        }

        $returnTo = (string)$this->post('return_to', '/hundeschule/rechnungen');
        $this->redirect($returnTo);
    }

    public function createForPackage(array $params = []): void
    {
        $this->requireFeature('dogschool_invoicing');
        $this->validateCsrf();

        $balanceId = (int)($params['balance_id'] ?? 0);
        $invoiceId = $this->service->createForPackage($balanceId);

        if ($invoiceId === null) {
            $this->flash('warning', 'Rechnung konnte nicht erstellt werden.');
        } else {
            $this->flash('success', 'Paket-Rechnung erstellt.');
        }

        $returnTo = (string)$this->post('return_to', '/pakete/balance/' . $balanceId);
        $this->redirect($returnTo);
    }
}
