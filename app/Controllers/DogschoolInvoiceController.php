<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\DogschoolInvoiceService;

/**
 * DogschoolInvoiceController
 *
 * Erzeugt Rechnungen aus Enrollments/Paketen. Die Liste ist nur ein
 * gefilterter Blick auf das Haupt-Rechnungssystem — keine eigene Persistenz.
 */
class DogschoolInvoiceController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        private readonly DogschoolInvoiceService $service,
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_invoicing');

        $from   = (string)$this->get('from', date('Y-m-01'));
        $to     = (string)$this->get('to',   date('Y-m-d'));
        $status = (string)$this->get('status', '');

        $invoices = $this->service->listDogschoolInvoices($from, $to, $status);

        $this->render('dogschool/invoices/index.twig', [
            'page_title' => 'Hundeschul-Rechnungen',
            'active_nav' => 'dogschool_invoices',
            'invoices'   => $invoices,
            'filter_from'   => $from,
            'filter_to'     => $to,
            'filter_status' => $status,
        ]);
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
