<?php

declare(strict_types=1);

namespace Plugins\VetReport;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;
use App\Repositories\SettingsRepository;

class VetReportController extends Controller
{
    private VetReportService $service;
    private Database $db;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db,
        SettingsRepository $settingsRepository
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->db      = $db;
        $this->service = new VetReportService($settingsRepository);
    }

    /* ── GET /patienten/{id}/tierarztbericht ── */
    public function generate(array $params = []): void
    {
        $patientId = (int)($params['id'] ?? 0);

        /* Load patient */
        $stmt = $this->db->query('SELECT p.*, o.first_name, o.last_name, o.phone, o.email, o.street, o.zip, o.city, o.id AS owner_id_val FROM patients p LEFT JOIN owners o ON o.id = p.owner_id WHERE p.id = ? LIMIT 1', [$patientId]);
        $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $this->abort(404);
            return;
        }

        $patient = $row;
        $owner   = null;
        if (!empty($row['owner_id_val'])) {
            $owner = [
                'id'         => $row['owner_id_val'],
                'first_name' => $row['first_name'],
                'last_name'  => $row['last_name'],
                'phone'      => $row['phone'],
                'email'      => $row['email'],
                'street'     => $row['street'],
                'zip'        => $row['zip'],
                'city'       => $row['city'],
            ];
        }

        /* Load timeline (treatments + notes) */
        $tlStmt  = $this->db->query(
            "SELECT t.*, u.name AS user_name FROM patient_timeline t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.patient_id = ? AND t.type IN ('treatment','note')
             ORDER BY t.entry_date DESC",
            [$patientId]
        );
        $timeline = $tlStmt->fetchAll(\PDO::FETCH_ASSOC);

        /* Load upcoming appointments */
        try {
            $apptStmt = $this->db->query(
                "SELECT a.*, tt.name AS treatment_type_name
                 FROM appointments a
                 LEFT JOIN treatment_types tt ON tt.id = a.treatment_type_id
                 WHERE a.patient_id = ? AND a.start_at >= NOW()
                 ORDER BY a.start_at ASC
                 LIMIT 10",
                [$patientId]
            );
            $appointments = $apptStmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $appointments = [];
        }

        $pdf      = $this->service->generate($patient, $owner, $timeline, $appointments);
        $filename = 'Tierarztbericht-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $patient['name'] ?? 'Patient') . '-' . date('Y-m-d') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
        exit;
    }
}
