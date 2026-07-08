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
        private readonly \App\Services\PushNotificationService $push,
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

            // Push an alle SaaS-Admins: neues Feedback eingegangen
            try {
                $this->push->notifyAdmins(
                    'saas_feedback',
                    sprintf(
                        '%s von %s: %s',
                        ucfirst($type),
                        $tenantInfo['tenant_name'] ?: 'Unbekannte Praxis',
                        mb_substr($subject !== '' ? $subject : $message, 0, 120)
                    ),
                    ['screen' => 'feedback'],
                    $priority === 'critical' || $priority === 'high' ? 'high' : 'normal'
                );
            } catch (\Throwable $e) {
                error_log('[FeedbackController] push notify failed: ' . $e->getMessage());
            }

            echo json_encode(['success' => true, 'message' => 'Dein Feedback wurde gesendet. Danke!']);
        } catch (\Throwable $e) {
            error_log('[FeedbackController] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Speichern. Bitte versuche es erneut.']);
        }
    }

    /**
     * GET /feedback/replies
     * Returns unread admin replies for the current tenant (JSON, for polling).
     */
    public function replies(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            return;
        }

        $tenantInfo = $this->resolveTenantInfo();
        $tenantId   = $tenantInfo['tenant_id'];

        if (!$tenantId) {
            echo json_encode(['unread' => 0, 'items' => []]);
            return;
        }

        try {
            $pdo = $this->getSaasDb();
            if ($pdo === null) {
                echo json_encode(['unread' => 0, 'items' => []]);
                return;
            }

            $stmt = $pdo->prepare("
                SELECT r.id, r.feedback_id, r.sender_name, r.message, r.is_read, r.created_at,
                       f.subject, f.type
                FROM feedback_replies r
                JOIN feedback f ON f.id = r.feedback_id
                WHERE f.tenant_id = ?
                  AND r.sender_type = 'admin'
                  AND r.is_read = 0
                ORDER BY r.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$tenantId]);
            $items = $stmt->fetchAll();

            echo json_encode(['unread' => count($items), 'items' => $items]);
        } catch (\Throwable $e) {
            error_log('[FeedbackController::replies] ' . $e->getMessage());
            echo json_encode(['unread' => 0, 'items' => []]);
        }
    }

    /**
     * POST /feedback/replies/read
     * Marks admin replies as read for the current tenant.
     */
    public function markRepliesRead(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            return;
        }

        $token = $this->post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$this->session->validateCsrfToken($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalid']);
            return;
        }

        $tenantInfo = $this->resolveTenantInfo();
        $tenantId   = $tenantInfo['tenant_id'];
        if (!$tenantId) {
            echo json_encode(['success' => true]);
            return;
        }

        try {
            $pdo = $this->getSaasDb();
            if ($pdo) {
                $stmt = $pdo->prepare("
                    UPDATE feedback_replies r
                    JOIN feedback f ON f.id = r.feedback_id
                    SET r.is_read = 1, r.read_at = NOW()
                    WHERE f.tenant_id = ? AND r.sender_type = 'admin' AND r.is_read = 0
                ");
                $stmt->execute([$tenantId]);

                $pdo->prepare("UPDATE feedback SET unread_replies = 0 WHERE tenant_id = ?")->execute([$tenantId]);
            }
        } catch (\Throwable) {}

        echo json_encode(['success' => true]);
    }

    /**
     * POST /feedback/{id}/reply
     * Allows the practice user to reply to a support conversation.
     */
    public function replyFromPractice(array $params = []): void
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

        $feedbackId = (int)($params['id'] ?? 0);
        $message    = $this->sanitize($this->post('message', ''));

        if ($message === '' || $feedbackId === 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Nachricht darf nicht leer sein.']);
            return;
        }

        $tenantInfo = $this->resolveTenantInfo();
        $tenantId   = $tenantInfo['tenant_id'];

        try {
            $pdo = $this->getSaasDb();
            if ($pdo === null) {
                http_response_code(500);
                echo json_encode(['error' => 'Datenbankfehler.']);
                return;
            }

            // Verify feedback belongs to this tenant
            $check = $pdo->prepare("SELECT id FROM feedback WHERE id = ? AND tenant_id = ? LIMIT 1");
            $check->execute([$feedbackId, $tenantId]);
            if (!$check->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Kein Zugriff.']);
                return;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO feedback_replies (feedback_id, sender_type, sender_name, message) VALUES (?, 'tenant', ?, ?)"
            );
            $stmt->execute([$feedbackId, $user['name'] ?? 'Praxis', $message]);

            $replyId = (int)$pdo->lastInsertId();

            $row = $pdo->prepare("SELECT id, sender_type, sender_name, message, created_at FROM feedback_replies WHERE id = ?");
            $row->execute([$replyId]);
            $reply = $row->fetch();

            echo json_encode(['success' => true, 'reply' => $reply]);
        } catch (\Throwable $e) {
            error_log('[FeedbackController::replyFromPractice] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Speichern.']);
        }
    }

    /**
     * GET /feedback/history
     * Returns the last 10 feedback items (any status) for the current tenant.
     */
    public function history(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            return;
        }

        $tenantInfo = $this->resolveTenantInfo();
        $tenantId   = $tenantInfo['tenant_id'];

        if (!$tenantId) {
            echo json_encode(['items' => []]);
            return;
        }

        try {
            $pdo = $this->getSaasDb();
            if ($pdo === null) {
                echo json_encode(['items' => []]);
                return;
            }

            $stmt = $pdo->prepare("
                SELECT f.id, f.subject, f.type, f.status, f.created_at,
                       f.unread_replies,
                       (SELECT COUNT(*) FROM feedback_replies r WHERE r.feedback_id = f.id) AS reply_count,
                       COALESCE(
                           (SELECT r2.message FROM feedback_replies r2 WHERE r2.feedback_id = f.id ORDER BY r2.created_at DESC LIMIT 1),
                           f.message
                       ) AS last_message
                FROM feedback f
                WHERE f.tenant_id = ?
                ORDER BY f.created_at DESC
                LIMIT 15
            ");
            $stmt->execute([$tenantId]);
            $items = $stmt->fetchAll();

            // Count total unread — use feedback.unread_replies flag (covers both replies and broadcasts)
            $unreadStmt = $pdo->prepare("
                SELECT COUNT(*) FROM feedback WHERE tenant_id = ? AND unread_replies = 1
            ");
            $unreadStmt->execute([$tenantId]);
            $unread = (int)$unreadStmt->fetchColumn();

            echo json_encode(['items' => $items, 'unread' => $unread]);
        } catch (\Throwable $e) {
            error_log('[FeedbackController::history] ' . $e->getMessage());
            echo json_encode(['items' => []]);
        }
    }

    /**
     * GET /feedback/{id}/thread
     * Returns full conversation thread for a specific feedback item.
     */
    public function thread(array $params = []): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = Auth::user();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            return;
        }

        $feedbackId = (int)($params['id'] ?? 0);
        $tenantInfo = $this->resolveTenantInfo();
        $tenantId   = $tenantInfo['tenant_id'];

        try {
            $pdo = $this->getSaasDb();
            if ($pdo === null) {
                echo json_encode(['items' => []]);
                return;
            }

            $stmt = $pdo->prepare("
                SELECT f.id, f.subject, f.type, f.message, f.created_at, f.status,
                       CASE WHEN f.type = 'broadcast' THEN 'TheraPano.de' ELSE f.user_name END AS sender_name,
                       CASE WHEN f.type = 'broadcast' THEN 'admin' ELSE 'tenant' END AS sender_type
                FROM feedback f
                WHERE f.id = ? AND f.tenant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$feedbackId, $tenantId]);
            $original = $stmt->fetch();

            if (!$original) {
                http_response_code(403);
                echo json_encode(['error' => 'Kein Zugriff.']);
                return;
            }

            $stmt2 = $pdo->prepare(
                "SELECT id, sender_type, sender_name, message, created_at FROM feedback_replies WHERE feedback_id = ? ORDER BY created_at ASC"
            );
            $stmt2->execute([$feedbackId]);
            $replies = $stmt2->fetchAll();

            // Mark all admin replies as read
            $pdo->prepare("
                UPDATE feedback_replies SET is_read = 1, read_at = NOW()
                WHERE feedback_id = ? AND sender_type = 'admin' AND is_read = 0
            ")->execute([$feedbackId]);
            $pdo->prepare("UPDATE feedback SET unread_replies = 0 WHERE id = ?")->execute([$feedbackId]);

            echo json_encode(['original' => $original, 'replies' => $replies]);
        } catch (\Throwable $e) {
            error_log('[FeedbackController::thread] ' . $e->getMessage());
            echo json_encode(['items' => []]);
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
        $user  = Auth::user();
        $email = $user['email'] ?? null;

        $tenantId   = null;
        $tenantName = null;

        if ($email !== null) {
            try {
                $pdo = $this->getSaasDb();
                if ($pdo !== null) {
                    $stmt = $pdo->prepare(
                        "SELECT id, practice_name FROM tenants WHERE email = ? AND status IN ('active','trial') LIMIT 1"
                    );
                    $stmt->execute([$email]);
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
