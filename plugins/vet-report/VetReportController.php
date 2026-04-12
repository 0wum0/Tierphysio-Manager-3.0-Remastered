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
use App\Services\CustomVetReportPdfService;

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

    private function t(string $table): string
    {
        return $this->db->prefix($table);
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

    /* ── POST /patienten/{id}/tierarztbericht/custom ── */
    public function createCustom(array $params = []): void
    {
        $patientId = (int)($params['id'] ?? 0);
        $patient   = $this->loadPatient($patientId);
        if (!$patient) { $this->abort(404); return; }

        $content   = $this->post('content', '');
        $recipient = $this->post('recipient', '');

        $owner    = $this->extractOwner($patient);
        $settingsRepo = \App\Core\Application::getInstance()->getContainer()->get(SettingsRepository::class);

        $customService = new CustomVetReportPdfService($settingsRepo);
        $settings = $settingsRepo->all();

        $reportData = [
            'created_at' => date('Y-m-d H:i:s'),
            'content'    => $content,
            'recipient'  => $recipient,
        ];

        try {
            $pdfContent = $customService->generate($reportData, $patient, $owner ?? [], $settings);
            $filename   = $this->buildFilename($patient['name'] ?? 'Patient', true);

            $dir = tenant_storage_path('vet-reports');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/' . $filename, $pdfContent);

            $userId = $this->session->get('user_id');
            $this->db->query(
                "INSERT INTO `{$this->t('vet_reports')}` (patient_id, created_by, filename, type, content, recipient) VALUES (?, ?, ?, 'custom', ?, ?)",
                [$patientId, $userId ?: null, $filename, $content, $recipient]
            );
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
            return;
        }

        $this->json(['ok' => true]);
    }

    /* ── GET /patienten/{id}/tierarztbericht/verlauf (JSON) ── */
    public function history(array $params = []): void
    {
        $patientId = (int)($params['id'] ?? 0);
        if ($patientId <= 0) { $this->json(['reports' => []]); return; }

        try {
            $rows = $this->db->query(
                "SELECT vr.id, vr.created_at, vr.type, vr.recipient, u.name AS created_by_name
                 FROM `{$this->t('vet_reports')}` vr
                 LEFT JOIN `{$this->t('users')}` u ON u.id = vr.created_by
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
                "SELECT filename FROM `{$this->t('vet_reports')}` WHERE id = ? AND patient_id = ? LIMIT 1",
                [$reportId, $patientId]
            )->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $row = null;
        }

        if (!$row) { $this->abort(404); return; }

        // Path traversal prevention: basename() + containment check
        $safeFilename = basename($row['filename']);
        $storageDir   = realpath(tenant_storage_path('vet-reports'));
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

        if ($patientId < 1 || $reportId < 0) {
            $this->json(['ok' => false, 'error' => 'invalid_params'], 400);
            return;
        }

        try {
            $row = $this->db->query(
                "SELECT filename FROM `{$this->t('vet_reports')}` WHERE id = ? AND patient_id = ? LIMIT 1",
                [$reportId, $patientId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $this->json(['ok' => false, 'error' => 'not_found'], 404);
                return;
            }

            // Path traversal prevention: basename() + containment check
            $safeFilename = basename($row['filename']);
            $storageDir   = realpath(tenant_storage_path('vet-reports'));
            if ($storageDir) {
                $path     = $storageDir . '/' . $safeFilename;
                $realPath = realpath($path);
                if ($realPath && strpos($realPath, $storageDir) === 0) {
                    unlink($realPath);
                }
            }
            $this->db->query("DELETE FROM `{$this->t('vet_reports')}` WHERE id = ? AND patient_id = ?", [$reportId, $patientId]);
        } catch (\Throwable) {
            $this->json(['ok' => false, 'error' => 'db_error'], 500);
            return;
        }

        $this->json(['ok' => true]);
    }

    /* ── POST /patienten/{id}/tierarztbericht/{reportId}/email ── */
    public function sendEmail(array $params = []): void
    {
        $patientId = (int)($params['id']       ?? 0);
        $reportId  = (int)($params['reportId'] ?? 0);
        if ($patientId < 1 || $reportId < 0) {
            $this->json(['ok' => false, 'error' => 'Ungültige Parameter'], 400);
            return;
        }

        try {
            $row = $this->db->query(
                "SELECT filename FROM `{$this->t('vet_reports')}` WHERE id = ? AND patient_id = ? LIMIT 1",
                [$reportId, $patientId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $this->json(['ok' => false, 'error' => 'Bericht nicht gefunden'], 404);
                return;
            }

            $patient = $this->loadPatient($patientId);
            if (!$patient) {
                $this->json(['ok' => false, 'error' => 'Patient nicht gefunden'], 404);
                return;
            }

            $owner = $this->extractOwner($patient);
            if (!$owner || empty($owner['email'])) {
                $this->json(['ok' => false, 'error' => 'Keine E-Mail-Adresse beim Tierhalter hinterlegt'], 422);
                return;
            }

            $safeFilename = basename($row['filename']);
            $storageDir   = realpath(tenant_storage_path('vet-reports'));
            $path         = $storageDir . '/' . $safeFilename;
            $realPath     = realpath($path);

            if (!$storageDir || !$realPath || strpos($realPath, $storageDir) !== 0) {
                $this->json(['ok' => false, 'error' => 'PDF-Datei nicht gefunden'], 404);
                return;
            }

            $pdfContent  = file_get_contents($realPath);
            $settingsRepo = \App\Core\Application::getInstance()->getContainer()->get(\App\Repositories\SettingsRepository::class);
            $mailService  = new \App\Services\MailService($settingsRepo);

            $ok = $mailService->sendVetReport($patient, $owner, $pdfContent, $safeFilename);

            if ($ok) {
                $this->json(['ok' => true, 'email' => $owner['email']]);
            } else {
                $this->json(['ok' => false, 'error' => $mailService->getLastError()], 500);
            }
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /* ── Private helpers ─────────────────────────────────────────────── */

    private function loadPatient(int $id): ?array
    {
        $row = $this->db->query(
            "SELECT p.*, o.first_name, o.last_name, o.phone, o.email,
                    o.street, o.zip, o.city, o.id AS owner_id_val
             FROM `{$this->t('patients')}` p
             LEFT JOIN `{$this->t('owners')}` o ON o.id = p.owner_id
             WHERE p.id = ? LIMIT 1",
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
            "SELECT t.*, u.name AS user_name FROM `{$this->t('patient_timeline')}` t
             LEFT JOIN `{$this->t('users')}` u ON u.id = t.user_id
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
                 FROM `{$this->t('appointments')}` a
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 WHERE a.patient_id = ? AND a.start_at >= NOW()
                 ORDER BY a.start_at ASC LIMIT 10",
                [$patientId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function buildFilename(string $patientName, bool $isCustom = false): string
    {
        $safe = preg_replace('/[^A-Za-z0-9\-]/', '_', $patientName);
        $prefix = $isCustom ? 'Tierarztbericht-Manuell-' : 'Tierarztbericht-';
        return $prefix . $safe . '-' . date('Y-m-d_His') . '.pdf';
    }

    private function saveReport(int $patientId, string $filename, string $pdfContent): void
    {
        try {
            $dir = tenant_storage_path('vet-reports');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/' . $filename, $pdfContent);

            $userId = $this->session->get('user_id');
            $this->db->query(
                "INSERT INTO `{$this->t('vet_reports')}` (patient_id, created_by, filename, type) VALUES (?, ?, ?, 'auto')",
                [$patientId, $userId ?: null, $filename]
            );
        } catch (\Throwable) {
            /* Non-fatal — PDF is still returned even if saving fails */
        }
    }
}
