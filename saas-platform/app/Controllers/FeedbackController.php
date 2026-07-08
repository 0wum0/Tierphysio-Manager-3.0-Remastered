<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Database;
use Saas\Repositories\NotificationRepository;

class FeedbackController extends Controller
{
    public function __construct(
        View                       $view,
        Session                    $session,
        private Database           $db,
        private NotificationRepository $notifRepo,
        private \Saas\Services\PushAdminNotificationService $pushAdmin
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $filter   = $_GET['filter'] ?? 'all';
        $category = $_GET['category'] ?? '';
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $perPage  = 20;
        $offset   = ($page - 1) * $perPage;

        $where  = [];
        $bind   = [];

        if ($filter === 'unread') {
            $where[] = 'f.is_read = 0';
        }
        if ($category && in_array($category, ['bug', 'feature', 'praise', 'other'], true)) {
            $where[] = 'f.category = ?';
            $bind[]  = $category;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM feedback f {$whereSQL}", $bind
        );

        $items = $this->db->fetchAll(
            "SELECT f.*, t.practice_name
             FROM feedback f
             LEFT JOIN tenants t ON t.id = f.tenant_id
             {$whereSQL}
             ORDER BY f.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $bind
        );

        $unreadCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM feedback WHERE is_read = 0"
        );

        $stats = [
            'total'   => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM feedback"),
            'unread'  => $unreadCount,
            'bug'     => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM feedback WHERE category = 'bug'"),
            'feature' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM feedback WHERE category = 'feature'"),
            'praise'  => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM feedback WHERE category = 'praise'"),
        ];

        $this->render('admin/feedback/index.twig', [
            'items'        => $items,
            'stats'        => $stats,
            'filter'       => $filter,
            'category'     => $category,
            'page'         => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'total_pages'  => (int)ceil($total / $perPage),
            'page_title'   => 'Feedback',
        ]);
    }

    public function show(array $params = []): void
    {
        $this->requireAuth();
        $id   = (int)($params['id'] ?? 0);
        $item = $this->db->fetch(
            "SELECT f.*, t.practice_name, t.email AS tenant_email
             FROM feedback f
             LEFT JOIN tenants t ON t.id = f.tenant_id
             WHERE f.id = ?",
            [$id]
        );
        if (!$item) {
            $this->session->flash('error', 'Feedback nicht gefunden.');
            $this->redirect('/admin/feedback');
        }

        // Mark as read
        if (!$item['is_read']) {
            $this->db->execute(
                "UPDATE feedback SET is_read = 1, read_at = NOW() WHERE id = ?", [$id]
            );
            $item['is_read'] = 1;
        }

        $replies = $this->db->fetchAll(
            "SELECT * FROM feedback_replies WHERE feedback_id = ? ORDER BY created_at ASC",
            [$id]
        );

        $this->render('admin/feedback/show.twig', [
            'item'       => $item,
            'replies'    => $replies,
            'page_title' => 'Feedback #' . $id,
        ]);
    }

    /**
     * GET /admin/feedback/{id}/anhang
     *
     * Liefert den Feedback-Anhang direkt aus dem SaaS-Admin aus, ohne dass der
     * Admin in der Praxis-App (app.therapano.de) als Mitarbeiter des jeweiligen
     * Tenants eingeloggt sein muss. Vorher verlinkte show.twig direkt auf
     * https://app.therapano.de/{attachment_path} — diese Route dort verlangt
     * ['auth'] (Praxis-Mitarbeiter-Session), die ein SaaS-Admin i.d.R. nicht hat.
     * saas-platform liegt auf demselben Server als Sibling-Ordner des Haupt-Repos
     * und kann daher direkt (Dateisystem, kein HTTP) auf storage/feedback/
     * zugreifen — analog zum Muster in TenantHealthService::checkStorage().
     */
    public function attachment(array $params = []): void
    {
        $this->requireAuth();

        $id   = (int)($params['id'] ?? 0);
        $item = $this->db->fetch("SELECT attachment_path FROM feedback WHERE id = ?", [$id]);

        if (!$item || empty($item['attachment_path'])) {
            http_response_code(404);
            echo 'Kein Anhang vorhanden.';
            return;
        }

        /* attachment_path ist serverseitig erzeugt (FeedbackController::handleAttachment()
         * im Hauptrepo, Format 'storage/feedback/feedback_<hex>.<ext>') — trotzdem defensiv
         * per basename() + Containment-Check behandeln, nie dem gespeicherten Pfad blind
         * vertrauen. */
        $file = basename((string)$item['attachment_path']);
        if ($file === '') {
            http_response_code(404);
            echo 'Kein Anhang vorhanden.';
            return;
        }

        $base = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 3) . '/storage';
        $dir  = $base . '/feedback';
        $path = realpath($dir . '/' . $file);

        if ($path === false || !str_starts_with($path, realpath($dir) . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            echo 'Datei nicht gefunden.';
            return;
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    public function reply(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id      = (int)($params['id'] ?? 0);
        $message = trim($_POST['reply_message'] ?? '');

        if ($message === '' || $id === 0) {
            $this->session->flash('error', 'Nachricht darf nicht leer sein.');
            $this->redirect('/admin/feedback/' . $id);
            return;
        }

        $adminName = $this->session->get('saas_user', 'TheraPano Support');

        $this->db->execute(
            "INSERT INTO feedback_replies (feedback_id, sender_type, sender_name, message) VALUES (?, 'admin', ?, ?)",
            [$id, $adminName, $message]
        );

        // Mark feedback as having unread reply for tenant, set status to in_progress if still open
        $this->db->execute(
            "UPDATE feedback SET unread_replies = 1, status = IF(status = 'open', 'in_progress', status) WHERE id = ?",
            [$id]
        );

        // Create SaaS notification
        try {
            $fb = $this->db->fetch("SELECT tenant_id, tenant_name, user_id, subject FROM feedback WHERE id = ?", [$id]);
            $this->notifRepo->create(
                'feedback',
                'Support-Antwort gesendet',
                ($fb['tenant_name'] ?? 'Praxis') . ': Antwort auf Feedback #' . $id,
                ['feedback_id' => $id]
            );

            // Push an den Praxis-Nutzer, der das Feedback eingereicht hat
            if (!empty($fb['tenant_id']) && !empty($fb['user_id'])) {
                $tenant = $this->db->fetch(
                    "SELECT db_name FROM tenants WHERE id = ?",
                    [(int)$fb['tenant_id']]
                );
                if (!empty($tenant['db_name'])) {
                    // Legacy-Zeilen speichern db_name ohne abschließenden Unterstrich —
                    // normalisieren, sonst stimmt crc32(prefix) nicht mit dem Browser-JWT überein
                    $prefix = rtrim((string)$tenant['db_name'], '_') . '_';
                    $this->pushAdmin->notifyTenantUser(
                        $prefix,
                        (int)$fb['user_id'],
                        'feedback_reply',
                        'Antwort auf dein Feedback',
                        'Support hat auf "' . mb_substr((string)($fb['subject'] ?: 'dein Feedback'), 0, 80) . '" geantwortet.',
                        ['screen' => 'feedback', 'feedback_id' => $id]
                    );
                }
            }
        } catch (\Throwable) {}

        $this->session->flash('success', 'Antwort gesendet.');
        $this->redirect('/admin/feedback/' . $id);
    }

    public function apiUnreadCount(array $params = []): void
    {
        header('Content-Type: application/json');
        $this->requireAuth();
        $open = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM feedback WHERE status IN ('open','in_progress')"
        );
        echo json_encode(['open' => $open]);
    }

    public function delete(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $id = (int)($params['id'] ?? 0);
        $this->db->execute("DELETE FROM feedback WHERE id = ?", [$id]);
        $this->session->flash('success', 'Feedback gelöscht.');
        $this->redirect('/admin/feedback');
    }

    public function markAllRead(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->db->execute("UPDATE feedback SET is_read = 1, read_at = NOW() WHERE is_read = 0");
        $this->session->flash('success', 'Alle als gelesen markiert.');
        $this->redirect('/admin/feedback');
    }

    public function updateStatus(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id     = (int)($params['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $note   = trim($_POST['admin_note'] ?? '');

        $allowed = ['open', 'in_progress', 'resolved', 'closed'];
        if (!in_array($status, $allowed, true)) {
            $this->session->flash('error', 'Ungültiger Status.');
            $this->redirect('/admin/feedback/' . $id);
            return;
        }

        $resolvedAt = in_array($status, ['resolved', 'closed'], true) ? 'NOW()' : 'NULL';

        $this->db->execute(
            "UPDATE feedback SET status = ?, admin_note = ?, resolved_at = {$resolvedAt}, is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE id = ?",
            [$status, $note ?: null, $id]
        );

        $this->session->flash('success', 'Status aktualisiert.');
        $this->redirect('/admin/feedback/' . $id);
    }

    /**
     * GET  /admin/feedback/broadcast  — Broadcast-Formular
     * POST /admin/feedback/broadcast  — Broadcast senden
     */
    public function broadcast(array $params = []): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $filter  = $_POST['filter'] ?? 'all'; // all | active | trial

            if ($subject === '' || $message === '') {
                $this->session->flash('error', 'Betreff und Nachricht sind Pflichtfelder.');
                $this->redirect('/admin/feedback/broadcast');
                return;
            }

            $allowed = ['all', 'active', 'trial'];
            if (!in_array($filter, $allowed, true)) {
                $filter = 'all';
            }

            $statusCond = match($filter) {
                'active' => "status = 'active'",
                'trial'  => "status = 'trial'",
                default  => "status IN ('active','trial')",
            };

            $tenants = $this->db->fetchAll(
                "SELECT id, practice_name, email FROM tenants WHERE {$statusCond} ORDER BY practice_name ASC"
            );

            $senderName = 'TheraPano.de';
            $sent       = 0;

            foreach ($tenants as $tenant) {
                try {
                    // Insert as a broadcast feedback entry
                    $this->db->execute(
                        "INSERT INTO feedback (tenant_id, tenant_name, email, category, subject, type, status, message, platform)
                         VALUES (?, ?, ?, 'broadcast', ?, 'broadcast', 'open', ?, 'web')",
                        [
                            $tenant['id'],
                            $tenant['practice_name'],
                            $tenant['email'],
                            $subject,
                            $message,
                        ]
                    );
                    $newId = (int)$this->db->lastInsertId();

                    // Add a reply from admin so it shows in chat
                    $this->db->execute(
                        "INSERT INTO feedback_replies (feedback_id, sender_type, sender_name, message) VALUES (?, 'admin', ?, ?)",
                        [$newId, $senderName, $message]
                    );

                    // Mark as having unread reply for tenant
                    $this->db->execute(
                        "UPDATE feedback SET unread_replies = 1 WHERE id = ?",
                        [$newId]
                    );

                    $sent++;
                } catch (\Throwable $e) {
                    error_log('[FeedbackController::broadcast] tenant ' . $tenant['id'] . ': ' . $e->getMessage());
                }
            }

            $this->session->flash('success', "Nachricht an {$sent} Praxen gesendet.");
            $this->redirect('/admin/feedback/broadcast');
            return;
        }

        // GET — Formular anzeigen
        $stats = [
            'active' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'active'"),
            'trial'  => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'trial'"),
        ];

        $this->render('admin/feedback/broadcast.twig', [
            'stats'      => $stats,
            'page_title' => 'Massen-Nachricht senden',
        ]);
    }

    // ── Public API: called by TierPhysio mobile app ────────────────────────
    public function apiSubmit(array $params = []): void
    {
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true) ?? [];

        // Also accept Bearer token to identify tenant
        $tenantId   = null;
        $tenantName = $data['tenant_name'] ?? null;

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            try {
                $row = $this->db->fetch(
                    "SELECT t.id, t.practice_name FROM mobile_api_tokens mat
                     JOIN users u ON u.id = mat.user_id
                     JOIN tenants t ON t.tid = mat.tenant_prefix
                     WHERE mat.token_hash = SHA2(?, 256) AND mat.expires_at > NOW() AND mat.revoked = 0
                     LIMIT 1",
                    [$token]
                );
                if ($row) {
                    $tenantId   = $row['id'] ?? null;
                    $tenantName = $row['practice_name'] ?? $tenantName;
                }
            } catch (\Throwable) {}
        }

        $message  = trim($data['message'] ?? '');
        $category = $data['category'] ?? 'other';
        $rating   = isset($data['rating']) ? (int)$data['rating'] : null;
        $platform = $data['platform'] ?? 'android';
        $version  = $data['app_version'] ?? null;
        $email    = $data['email'] ?? null;

        if (!$message) {
            http_response_code(422);
            echo json_encode(['error' => 'message is required']);
            return;
        }

        if (!in_array($category, ['bug', 'feature', 'praise', 'other'], true)) {
            $category = 'other';
        }
        if ($rating !== null) {
            $rating = max(1, min(5, $rating));
        }

        try {
            $this->db->execute(
                "INSERT INTO feedback (tenant_id, tenant_name, email, category, message, rating, app_version, platform)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$tenantId, $tenantName, $email, $category, $message, $rating, $version, $platform]
            );

            // Create SaaS notification for new feedback
            try {
                $this->notifRepo->create(
                    'feedback',
                    'Neues Feedback eingegangen',
                    ($tenantName ?? 'Anonym') . ': ' . mb_substr($message, 0, 80) . (mb_strlen($message) > 80 ? '…' : ''),
                    ['category' => $category, 'rating' => $rating]
                );
            } catch (\Throwable) {}

            echo json_encode(['success' => true, 'message' => 'Danke für dein Feedback!']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
        }
    }
}
