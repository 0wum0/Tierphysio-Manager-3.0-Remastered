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
        private NotificationRepository $notifRepo
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

        $this->render('admin/feedback/show.twig', [
            'item'       => $item,
            'page_title' => 'Feedback #' . $id,
        ]);
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
