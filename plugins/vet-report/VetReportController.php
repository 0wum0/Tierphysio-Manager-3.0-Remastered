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
        $patient   = $this->loadPatient($patientId);
        if (!$patient) { $this->abort(404); return; }

        $owner        = $this->extractOwner($patient);
        $timeline     = $this->loadTimeline($patientId);
        $appointments = $this->loadAppointments($patientId);

        $pdfContent = $this->service->generate($patient, $owner, $timeline, $appointments);
        $filename   = $this->buildFilename($patient['name'] ?? 'Patient');

        /* Save to storage and record in DB */
        $this->saveReport($patientId, $filename, $pdfContent);

        // CRLF injection prevention
        $headerFilename = preg_replace('/[\r\n"]/', '', $filename);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $headerFilename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdfContent;
        exit;
    }

    /* ── GET /patienten/{id}/tierarztbericht/verlauf (JSON) ── */
    public function history(array $params = []): void
    {
        $patientId = (int)($params['id'] ?? 0);
        if ($patientId <= 0) { $this->json(['reports' => []]); return; }

        try {
            $rows = $this->db->query(
                "SELECT vr.id, vr.created_at, u.name AS created_by_name
                 FROM vet_reports vr
                 LEFT JOIN users u ON u.id = vr.created_by
                 WHERE vr.patient_id = ?
                 ORDER BY vr.created_at DESC
                 LIMIT 20",
                [$patientId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $rows = [];
        }

        $this->json(['reports' => $rows]);
    }

    /* ── GET /patienten/{id}/tierarztbericht/{reportId}/download ── */
    public function download(array $params = []): void
    {
        $patientId = (int)($params['id']       ?? 0);
        $reportId  = (int)($params['reportId'] ?? 0);

        try {
            $row = $this->db->query(
                "SELECT filename FROM vet_reports WHERE id = ? AND patient_id = ? LIMIT 1",
                [$reportId, $patientId]
            )->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $row = null;
        }

        if (!$row) { $this->abort(404); return; }

        // Path traversal prevention: basename() + containment check
        $safeFilename = basename($row['filename']);
        $storageDir   = realpath(STORAGE_PATH . '/vet-reports');
        $path         = $storageDir . '/' . $safeFilename;
        $realPath     = realpath($path);

        if (!$storageDir || !$realPath || strpos($realPath, $storageDir) !== 0) {
            $this->abort(404);
            return;
        }

        // CRLF injection prevention in header
        $headerFilename = preg_replace('/[\r\n"]/', '', $safeFilename);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $headerFilename . '"');
        header('Content-Length: ' . filesize($realPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($realPath);
        exit;
    }

    /* ── DELETE /patienten/{id}/tierarztbericht/{reportId} ── */
    public function delete(array $params = []): void
    {
        $patientId = (int)($params['id']       ?? 0);
        $reportId  = (int)($params['reportId'] ?? 0);

        if (!$patientId || !$reportId) {
            $this->json(['ok' => false, 'error' => 'invalid_params'], 400);
            return;
        }

        try {
            $row = $this->db->query(
                "SELECT filename FROM vet_reports WHERE id = ? AND patient_id = ? LIMIT 1",
                [$reportId, $patientId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $this->json(['ok' => false, 'error' => 'not_found'], 404);
                return;
            }

            // Path traversal prevention: basename() + containment check
            $safeFilename = basename($row['filename']);
            $storageDir   = realpath(STORAGE_PATH . '/vet-reports');
            if ($storageDir) {
                $path     = $storageDir . '/' . $safeFilename;
                $realPath = realpath($path);
                if ($realPath && strpos($realPath, $storageDir) === 0) {
                    unlink($realPath);
                }
            }
            $this->db->query("DELETE FROM vet_reports WHERE id = ? AND patient_id = ?", [$reportId, $patientId]);
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => 'db_error'], 500);
            return;
        }

        $this->json(['ok' => true]);
    }

    /* ── Private helpers ─────────────────────────────────────────────── */

    private function loadPatient(int $id): ?array
    {
        $row = $this->db->query(
            'SELECT p.*, o.first_name, o.last_name, o.phone, o.email,
                    o.street, o.zip, o.city, o.id AS owner_id_val
             FROM patients p
             LEFT JOIN owners o ON o.id = p.owner_id
             WHERE p.id = ? LIMIT 1',
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function extractOwner(array $row): ?array
    {
        if (empty($row['owner_id_val'])) return null;
        return [
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

    private function loadTimeline(int $patientId): array
    {
        return $this->db->query(
            "SELECT t.*, u.name AS user_name FROM patient_timeline t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.patient_id = ? AND t.type IN ('treatment','note')
             ORDER BY t.entry_date DESC",
            [$patientId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function loadAppointments(int $patientId): array
    {
        try {
            return $this->db->query(
                "SELECT a.*, tt.name AS treatment_type_name
                 FROM appointments a
                 LEFT JOIN treatment_types tt ON tt.id = a.treatment_type_id
                 WHERE a.patient_id = ? AND a.start_at >= NOW()
                 ORDER BY a.start_at ASC LIMIT 10",
                [$patientId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function buildFilename(string $patientName): string
    {
        $safe = preg_replace('/[^A-Za-z0-9\-]/', '_', $patientName);
        return 'Tierarztbericht-' . $safe . '-' . date('Y-m-d_His') . '.pdf';
    }

    private function saveReport(int $patientId, string $filename, string $pdfContent): void
    {
        try {
            $dir = STORAGE_PATH . '/vet-reports';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/' . $filename, $pdfContent);

            $userId = $this->session->get('user_id');
            $this->db->query(
                "INSERT INTO vet_reports (patient_id, created_by, filename) VALUES (?, ?, ?)",
                [$patientId, $userId ?: null, $filename]
            );
        } catch (\Throwable) {
            /* Non-fatal — PDF is still returned even if saving fails */
        }
    }
}
