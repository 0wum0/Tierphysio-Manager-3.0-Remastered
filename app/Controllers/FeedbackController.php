<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Session;

/**
 * FeedbackController — Praxis-App
 *
 * Nimmt Feedback-/Support-Anfragen von eingeloggten Praxis-Nutzern entgegen
 * und speichert sie in der zentralen SaaS-Feedback-Tabelle.
 * Optional: Datei-Anhang (Screenshot) als image/pdf.
 */
class FeedbackController
{
    public function __construct(
        private readonly Config  $config,
        private readonly Session $session,
    ) {}

    public function submit(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Nicht authentifiziert.']);
            return;
        }

        $token = $this->post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$this->session->validateCsrfToken($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Ungültiger CSRF-Token.']);
            return;
        }

        $type       = $this->sanitize($this->post('type', 'other'));
        $subject    = $this->sanitize($this->post('subject', ''));
        $message    = $this->sanitize($this->post('message', ''));
        $priority   = $this->sanitize($this->post('priority', 'normal'));
        $currentUrl = $this->sanitize($this->post('current_url', ''));
        $userAgent  = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

        if ($message === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Nachricht darf nicht leer sein.']);
            return;
        }

        $allowedTypes = ['bug', 'feature', 'support', 'question', 'praise', 'other'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'other';
        }

        $allowedPriorities = ['low', 'normal', 'high', 'critical'];
        if (!in_array($priority, $allowedPriorities, true)) {
            $priority = 'normal';
        }

        if (mb_strlen($subject) > 255) {
            $subject = mb_substr($subject, 0, 255);
        }

        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $attachmentPath = $this->handleAttachment();
        }

        $tenantInfo = $this->resolveTenantInfo();

        try {
            $pdo = $this->getSaasDb();
            if ($pdo === null) {
                http_response_code(500);
                echo json_encode(['error' => 'Datenbankverbindung nicht verfügbar.']);
                return;
            }

            $stmt = $pdo->prepare("
                INSERT INTO feedback
                    (tenant_id, tenant_name, email, category, subject, type, status, priority,
                     message, platform, user_id, user_name, user_agent, current_url, attachment_path)
                VALUES
                    (?, ?, ?, ?, ?, ?, 'open', ?,
                     ?, 'web', ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $tenantInfo['tenant_id'],
                $tenantInfo['tenant_name'],
                $tenantInfo['email'],
                $type,
                $subject ?: null,
                $type,
                $priority,
                $message,
                (int)$user['id'],
                $user['name'] ?? null,
                $userAgent ?: null,
                $currentUrl ?: null,
                $attachmentPath,
            ]);

            echo json_encode(['success' => true, 'message' => 'Dein Feedback wurde gesendet. Danke!']);
        } catch (\Throwable $e) {
            error_log('[FeedbackController] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Speichern. Bitte versuche es erneut.']);
        }
    }

    // ── Private Helpers ──────────────────────────────────────────────────────

    private function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    private function sanitize(mixed $value): string
    {
        return htmlspecialchars(strip_tags(trim((string)$value)), ENT_QUOTES, 'UTF-8');
    }

    private function getSaasDb(): ?\PDO
    {
        $saasDb = $this->config->get('saas_db.database', '');
        if ($saasDb === '') {
            return null;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config->get('saas_db.host', 'localhost'),
                (int)$this->config->get('saas_db.port', 3306),
                $saasDb
            );
            return new \PDO($dsn, $this->config->get('saas_db.username'), $this->config->get('saas_db.password'), [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        } catch (\Throwable $e) {
            error_log('[FeedbackController getSaasDb] ' . $e->getMessage());
            return null;
        }
    }

    private function resolveTenantInfo(): array
    {
        $saasDb = $this->config->get('saas_db.database', '');
        $prefix = $this->session->get('tenant_table_prefix', '');
        $user   = Auth::user();

        $tenantId   = null;
        $tenantName = null;
        $email      = $user['email'] ?? null;

        if ($saasDb !== '' && $prefix !== '') {
            try {
                $pdo = $this->getSaasDb();
                if ($pdo !== null) {
                    $stmt = $pdo->prepare("SELECT id, practice_name FROM tenants WHERE db_name = ? LIMIT 1");
                    $stmt->execute([trim($prefix, '_')]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $tenantId   = (int)$row['id'];
                        $tenantName = $row['practice_name'];
                    }
                }
            } catch (\Throwable) {}
        }

        return [
            'tenant_id'   => $tenantId,
            'tenant_name' => $tenantName,
            'email'       => $email,
        ];
    }

    private function handleAttachment(): ?string
    {
        $file          = $_FILES['attachment'];
        $maxSize       = 5 * 1024 * 1024; // 5 MB
        $allowedMimes  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

        if ($file['size'] > $maxSize) {
            return null;
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedMimes, true)) {
            return null;
        }

        $mimeExtMap = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'image/gif'       => 'gif',
            'application/pdf' => 'pdf',
        ];
        $ext      = $mimeExtMap[$mimeType] ?? 'bin';
        $filename = 'feedback_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $destDir  = rtrim($this->config->get('storage.path', ''), '/') . '/feedback';

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $destPath = $destDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return null;
        }

        return 'storage/feedback/' . $filename;
    }
}
