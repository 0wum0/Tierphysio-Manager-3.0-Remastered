<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\DatevExportService;

class DatevExportController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        private readonly DatevExportService $service,
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_datev_export');

        $from = (string)$this->get('from', date('Y-m-01'));
        $to   = (string)$this->get('to',   date('Y-m-d'));

        $summary = $this->service->summaryByTaxRate($from, $to);

        $this->render('dogschool/datev/index.twig', [
            'page_title'  => 'Steuerexport',
            'active_nav'  => 'datev',
            'from'        => $from,
            'to'          => $to,
            'summary'     => $summary,
            'total_net'   => array_sum(array_column($summary, 'net')),
            'total_tax'   => array_sum(array_column($summary, 'tax')),
            'total_gross' => array_sum(array_column($summary, 'gross')),
        ]);
    }

    public function download(array $params = []): void
    {
        $this->requireFeature('dogschool_datev_export');
        $this->validateCsrf();

        $from = (string)$this->post('from', date('Y-m-01'));
        $to   = (string)$this->post('to',   date('Y-m-d'));
        $mode = (string)$this->post('mode', 'simple'); /* simple | datev | kassenbuch */

        $export = match($mode) {
            'datev'      => $this->service->generateSteuerberaterCsv($from, $to, 'datev'),
            'kassenbuch' => $this->service->generateKassenbuchCsv($from, $to),
            default      => $this->service->generateSteuerberaterCsv($from, $to, 'simple'),
        };

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
        header('Content-Length: ' . strlen($export['content']));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $export['content'];
        exit;
    }
}
