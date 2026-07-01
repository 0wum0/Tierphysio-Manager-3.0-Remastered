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
use App\Services\AiService;

class VetReportController extends Controller
{
    private VetReportService $service;
    private Database $db;
    private SettingsRepository $settingsRepository;
    private AiService $aiService;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db,
        SettingsRepository $settingsRepository,
        AiService $aiService
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->db      = $db;
        $this->settingsRepository = $settingsRepository;
        $this->service = new VetReportService($settingsRepository);
        $this->aiService = $aiService;
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

        $content   = $this->sanitizeRichText($this->post('content', ''));
        $recipient = $this->post('recipient', '');

        $owner    = $this->extractOwner($patient);
        $customService = new CustomVetReportPdfService($this->settingsRepository);
        $settings = $this->settingsRepository->all();

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

            /* Compute next ID explicitly to avoid Duplicate entry '0' on tables
             * that were created without AUTO_INCREMENT (prefix-migration bug). */
            $nextId = 1 + (int)$this->db->fetchColumn(
                "SELECT COALESCE(MAX(id), 0) FROM `{$this->t('vet_reports')}`"
            );

            $this->db->query(
                "INSERT INTO `{$this->t('vet_reports')}` (id, patient_id, created_by, filename, type, content, recipient) VALUES (?, ?, ?, ?, 'custom', ?, ?)",
                [$nextId, $patientId, $userId ?: null, $filename, $content, $recipient]
            );
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
            return;
        }

        $this->json(['ok' => true]);
    }

    /* ── GET /patienten/{id}/tierarztbericht/{reportId}/load ── */
    public function loadReport(array $params = []): void
    {
        $patientId = (int)($params['id']       ?? 0);
        $reportId  = (int)($params['reportId'] ?? 0);
        if ($patientId < 1 || $reportId < 1) {
            $this->json(['ok' => false, 'error' => 'invalid_params'], 400);
            return;
        }

        try {
            $row = $this->db->query(
                "SELECT id, type, content, recipient FROM `{$this->t('vet_reports')}`
                 WHERE id = ? AND patient_id = ? LIMIT 1",
                [$reportId, $patientId]
            )->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $row = null;
        }

        if (!$row) {
            $this->json(['ok' => false, 'error' => 'not_found'], 404);
            return;
        }

        if ($row['type'] !== 'custom') {
            $this->json(['ok' => false, 'error' => 'auto_report_not_editable'], 422);
            return;
        }

        $this->json([
            'ok'        => true,
            'content'   => $row['content']   ?? '',
            'recipient' => $row['recipient'] ?? '',
        ]);
    }

    /* ── POST /patienten/{id}/tierarztbericht/{reportId}/update ── */
    public function updateCustom(array $params = []): void
    {
        $patientId = (int)($params['id']       ?? 0);
        $reportId  = (int)($params['reportId'] ?? 0);

        $patient = $this->loadPatient($patientId);
        if (!$patient) { $this->abort(404); return; }

        if ($patientId < 1 || $reportId < 1) {
            $this->json(['ok' => false, 'error' => 'invalid_params'], 400);
            return;
        }

        // Verify report belongs to this patient and is a custom report
        try {
            $existing = $this->db->query(
                "SELECT id, filename FROM `{$this->t('vet_reports')}`
                 WHERE id = ? AND patient_id = ? AND type = 'custom' LIMIT 1",
                [$reportId, $patientId]
            )->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
            return;
        }

        if (!$existing) {
            $this->json(['ok' => false, 'error' => 'not_found'], 404);
            return;
        }

        $content   = $this->sanitizeRichText($this->post('content', ''));
        $recipient = $this->post('recipient', '');

        $owner         = $this->extractOwner($patient);
        $customService = new CustomVetReportPdfService($this->settingsRepository);
        $settings      = $this->settingsRepository->all();

        $reportData = [
            'created_at' => date('Y-m-d H:i:s'),
            'content'    => $content,
            'recipient'  => $recipient,
        ];

        try {
            $pdfContent = $customService->generate($reportData, $patient, $owner ?? [], $settings);

            // Delete old PDF file if it exists
            $oldFilename  = basename($existing['filename'] ?? '');
            $storageDir   = realpath(tenant_storage_path('vet-reports'));
            if ($storageDir && $oldFilename) {
                $oldPath     = $storageDir . '/' . $oldFilename;
                $oldRealPath = realpath($oldPath);
                if ($oldRealPath && strpos($oldRealPath, $storageDir) === 0 && is_file($oldRealPath)) {
                    unlink($oldRealPath);
                }
            }

            // Write new PDF file
            $newFilename = $this->buildFilename($patient['name'] ?? 'Patient', true);
            $dir = tenant_storage_path('vet-reports');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/' . $newFilename, $pdfContent);

            // Update DB record
            $this->db->query(
                "UPDATE `{$this->t('vet_reports')}` SET filename = ?, content = ?, recipient = ? WHERE id = ? AND patient_id = ?",
                [$newFilename, $content, $recipient, $reportId, $patientId]
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

    /* ── POST /patienten/{id}/tierarztbericht/ki-entwurf ── */
    /**
     * Erstellt einen KI-Entwurf für den manuellen Tierarztbericht aus
     * Timeline (Behandlungen/Notizen) + Patientendaten. Befüllt NICHT
     * automatisch die DB — liefert nur Text, den der Nutzer im
     * Quill-Editor sieht, prüft und selbst speichert (wie eine Vorlage).
     *
     * Additiv & ausfallsicher: kein Provider konfiguriert oder KI-Aufruf
     * schlägt fehl → sauberes ok:false, nie ein Fatal Error.
     */
    public function aiDraft(array $params = []): void
    {
        $this->validateCsrf();
        $this->requireFeature('ki_assistance');

        $patientId = (int)($params['id'] ?? 0);
        $patient   = $this->loadPatient($patientId);
        if (!$patient) {
            $this->json(['ok' => false, 'error' => 'not_found'], 404);
            return;
        }

        if (!$this->aiService->isConfigured()) {
            $this->json(['ok' => false, 'error' => 'ai_not_configured']);
            return;
        }

        $timeline = $this->loadTimeline($patientId);
        if (empty($timeline) && empty($patient['notes'])) {
            $this->json(['ok' => false, 'error' => 'no_data']);
            return;
        }

        // Nur die letzten 25 Timeline-Einträge in den Prompt geben.
        $recent = array_slice($timeline, 0, 25);
        $lines  = [];
        foreach ($recent as $e) {
            $lines[] = sprintf(
                '%s | %s: %s',
                substr((string)($e['entry_date'] ?? ''), 0, 10),
                (string)($e['type'] ?? 'Eintrag'),
                trim((string)($e['title'] ?? '') . ' ' . strip_tags((string)($e['content'] ?? '')))
            );
        }

        $systemPrompt = 'Du bist eine fachliche Schreibassistenz für Tierphysiotherapeuten. '
            . 'Verfasse aus den gegebenen Behandlungsdaten einen strukturierten Entwurf für einen '
            . 'Tierarztbericht auf Deutsch. Gliedere in Abschnitte: Anamnese, Behandlungsverlauf, '
            . 'aktueller Befund, Empfehlung. Nutze NUR die gegebenen Fakten, erfinde nichts hinzu. '
            . 'Formuliere sachlich-professionell wie ein kollegiales Schreiben. '
            . 'Gib NUR Fließtext zurück, ohne HTML- oder Markdown-Formatierung, Absätze durch Leerzeilen getrennt.';

        $userPrompt = sprintf(
            "Patient: %s (%s%s)\nAnamnese-Notiz: %s\n\nBehandlungsverlauf (Datum | Typ: Inhalt):\n%s",
            (string)($patient['name'] ?? 'Patient'),
            (string)($patient['species'] ?? 'Tier'),
            !empty($patient['breed']) ? ', ' . $patient['breed'] : '',
            strip_tags((string)($patient['notes'] ?? '')) ?: '—',
            implode("\n", $lines) ?: '—'
        );

        $draft = $this->aiService->generateText($systemPrompt, $userPrompt);

        if ($draft === null) {
            $this->json(['ok' => false, 'error' => 'ai_unavailable']);
            return;
        }

        // Server-seitig sicheres HTML aus dem Klartext bauen (Absätze → <p>,
        // vollständig escaped) — verhindert jede Möglichkeit von HTML-Injection
        // durch KI-Output, ohne dass das Frontend rohes Modell-HTML einfügen muss.
        $paragraphs = preg_split('/\n\s*\n/', trim($draft)) ?: [$draft];
        $html = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') { continue; }
            $html .= '<p>' . nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        $this->json(['ok' => true, 'content' => $html]);
    }

    /* ── Private helpers ─────────────────────────────────────────────── */

    /**
     * Strip dangerous HTML from rich-text content before storing.
     * Allows all safe formatting tags (p, strong, em, h1-h6, ul, ol, li,
     * blockquote, br, table, tr, td, th, pre, hr, span, div, a) while
     * removing scripts, event handlers, and dangerous URI schemes.
     */
    private function sanitizeRichText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Remove script/style/iframe/form elements entirely
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/i',   '', $html) ?? $html;
        $html = preg_replace('/<(iframe|object|embed|form|input|button|select|meta|link|base)\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\/(iframe|object|embed|form|input|button|select|meta|link|base)>/i',       '', $html) ?? $html;

        // Strip all event handler attributes
        $html = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html) ?? $html;

        // Strip javascript:/data: URI schemes
        $html = preg_replace('/\s+(?:href|src)\s*=\s*["\']?\s*(?:javascript|data):[^"\'>\s]*/i', '', $html) ?? $html;

        return trim($html);
    }

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
