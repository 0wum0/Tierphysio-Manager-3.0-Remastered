<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Repositories\NotificationRepository;
use Saas\Repositories\ActivityLogRepository;

class NotificationController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly NotificationRepository $notifications,
        private readonly ActivityLogRepository  $log
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $page   = max(1, (int)$this->get('page', 1));
        $result = $this->notifications->getAll($page, 30);

        $this->render('admin/notifications/index.twig', [
            'page_title'    => 'Benachrichtigungen',
            'notifications' => $result['items'],
            'pagination'    => $result,
            'unread_count'  => $this->notifications->countUnread(),
        ]);
    }

    public function markRead(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $this->notifications->markRead((int)$params['id']);

        if ($this->isAjax()) {
            $this->json(['ok' => true, 'unread' => $this->notifications->countUnread()]);
            return;
        }
        $this->redirect('/admin/notifications');
    }

    public function markAllRead(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $this->notifications->markAllRead();

        if ($this->isAjax()) {
            $this->json(['ok' => true, 'unread' => 0]);
            return;
        }
        $this->session->flash('success', 'Alle Benachrichtigungen als gelesen markiert.');
        $this->redirect('/admin/notifications');
    }

    public function delete(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $this->notifications->delete((int)$params['id']);

        if ($this->isAjax()) {
            $this->json(['ok' => true]);
            return;
        }
        $this->redirect('/admin/notifications');
    }

    public function deleteRead(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $this->notifications->deleteOlderThan(0);
        $this->session->flash('success', 'Gelesene Benachrichtigungen gelöscht.');
        $this->redirect('/admin/notifications');
    }

    public function apiUnread(array $params = []): void
    {
        $this->requireAuth();
        $items = $this->notifications->getUnread(10);
        $this->json([
            'count' => $this->notifications->countUnread(),
            'items' => $items,
        ]);
    }

    public function activityLog(array $params = []): void
    {
        $this->requireAuth();

        $page   = max(1, (int)$this->get('page', 1));
        $action = trim($this->get('action', ''));
        $actor  = trim($this->get('actor', ''));
        $result = $this->log->getPaginated($page, 50, $action, $actor);

        $this->render('admin/notifications/activity_log.twig', [
            'page_title' => 'Aktivitätsprotokoll',
            'logs'       => $result['items'],
            'pagination' => $result,
            'filter_action' => $action,
            'filter_actor'  => $actor,
        ]);
    }

    public function auditLog(array $params = []): void
    {
        $this->requireAuth();

        $page    = max(1, (int)$this->get('page', 1));
        $action  = trim($this->get('action', ''));
        $actor   = trim($this->get('actor', ''));
        $subject = trim($this->get('subject', ''));
        $result  = $this->log->getPaginated($page, 50, $action, $actor);

        $this->render('admin/audit-log.twig', [
            'page_title'     => 'Audit-Log',
            'active_nav'     => 'audit_log',
            'logs'           => $result['items'],
            'pagination'     => $result,
            'filter_action'  => $action,
            'filter_actor'   => $actor,
            'filter_subject' => $subject,
        ]);
    }

    private function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
