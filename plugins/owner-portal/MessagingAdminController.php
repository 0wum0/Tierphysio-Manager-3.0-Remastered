<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;
use App\Repositories\SettingsRepository;
use App\Services\MailService;

class MessagingAdminController extends Controller
{
    private MessagingRepository    $repo;
    private OwnerPortalRepository   $portalRepo;
    private MessagingMailService    $mailer;
    private SettingsRepository      $settingsRepository;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db,
        SettingsRepository $settingsRepository,
        MailService $mailService
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo               = new MessagingRepository($db);
        $this->portalRepo         = new OwnerPortalRepository($db);
        $this->mailer             = new MessagingMailService($settingsRepository, $mailService);
        $this->settingsRepository = $settingsRepository;
    }

    /* ── GET /portal-admin/nachrichten ── */
    public function index(array $params = []): void
    {
        $threads     = $this->repo->getAllThreads();
        $unread      = $this->repo->countUnreadForAdmin();
        $portalUsers = $this->portalRepo->getAllPortalUsers();

        $this->render('@owner-portal/admin_messages.twig', [
            'page_title'   => 'Nachrichten — Besitzerportal',
            'threads'      => $threads,
            'unread'       => $unread,
            'portal_users' => $portalUsers,
            'csrf_token'   => $this->session->generateCsrfToken(),
            'success'      => $this->session->getFlash('success'),
            'error'        => $this->session->getFlash('error'),
        ]);
    }

    /* ── GET /portal-admin/nachrichten/{id} ── */
    public function thread(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $thread = $this->repo->getThreadById($id);
        if (!$thread) {
            $this->session->flash('error', 'Nachricht nicht gefunden.');
            $this->redirect('/portal-admin/nachrichten');
            return;
        }

        $this->repo->markThreadReadByAdmin($id);
        $messages = $this->repo->getMessages($id);

        $this->render('@owner-portal/admin_message_thread.twig', [
            'page_title'  => 'Nachricht: ' . $thread['subject'],
            'thread'      => $thread,
            'messages'    => $messages,
            'csrf_token'  => $this->session->generateCsrfToken(),
            'success'     => $this->session->getFlash('success'),
            'error'       => $this->session->getFlash('error'),
        ]);
    }

    /* ── POST /api/portal-admin/nachrichten/{id}/antworten (AJAX) ── */
    public function reply(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $thread = $this->repo->getThreadById($id);
        if (!$thread) {
            $this->json(['error' => 'Thread nicht gefunden.'], 404);
            return;
        }

        $body = trim($this->post('body', ''));
        if ($body === '') {
            $this->json(['error' => 'Nachricht darf nicht leer sein.'], 422);
            return;
        }

        $user   = $this->session->getUser();
        $userId = $user ? (int)$user['id'] : null;

        $msgId = $this->repo->addMessage($id, 'admin', $userId, $body);

        /* Reopen closed thread on admin reply */
        if ($thread['status'] === 'closed') {
            $this->repo->reopenThread($id);
        }

        /* E-mail notification to owner */
        try {
            $adminEmail = $this->settingsRepository->get('mail_from', '');
            if ($adminEmail === '') {
                $adminEmail = $this->settingsRepository->get('smtp_user', '');
            }
            $this->mailer->notifyOwnerNewMessage(
                $thread['owner_email'],
                $thread['owner_name'],
                $id,
                $thread['subject'],
                $body
            );
        } catch (\Throwable) {
            /* Mail errors must not break the AJAX reply */
        }

        /* Return rendered message bubble for AJAX append */
        $senderName = $user['name'] ?? 'Team';
        $this->json([
            'ok'          => true,
            'id'          => $msgId,
            'body'        => htmlspecialchars($body, ENT_QUOTES, 'UTF-8'),
            'sender_type' => 'admin',
            'sender_name' => $senderName,
            'created_at'  => date('d.m.Y H:i'),
        ]);
    }

    /* ── POST /api/portal-admin/nachrichten/{id}/status (AJAX: open/close) ── */
    public function setStatus(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $status = $this->post('status', 'closed');
        if ($status === 'closed') {
            $this->repo->closeThread($id);
        } else {
            $this->repo->reopenThread($id);
        }
        $this->json(['ok' => true, 'status' => $status]);
    }

    /* ── POST /api/portal-admin/nachrichten/neu (AJAX: start new thread from admin) ── */
    public function newThread(array $params = []): void
    {
        $ownerId = (int)$this->post('owner_id', 0);
        $subject = trim($this->post('subject', ''));
        $body    = trim($this->post('body', ''));

        if (!$ownerId || $subject === '' || $body === '') {
            $this->json(['error' => 'Bitte alle Felder ausfüllen.'], 422);
            return;
        }

        $threadId = $this->repo->createThread($ownerId, $subject, 'admin');

        $user   = $this->session->getUser();
        $userId = $user ? (int)$user['id'] : null;
        $this->repo->addMessage($threadId, 'admin', $userId, $body);

        /* Fetch thread for mail */
        $thread = $this->repo->getThreadById($threadId);
        if ($thread) {
            try {
                $this->mailer->notifyOwnerNewMessage(
                    $thread['owner_email'],
                    $thread['owner_name'],
                    $threadId,
                    $subject,
                    $body
                );
            } catch (\Throwable) {}
        }

        $this->json(['ok' => true, 'thread_id' => $threadId]);
    }

    /* ── GET /api/portal-admin/portal-users ── */
    public function portalUsers(array $params = []): void
    {
        /* Return ALL owners so the admin can start a conversation with any owner,
           not only those who have already accepted a portal invitation. */
        $owners = $this->portalRepo->getAllOwners();
        $out    = [];
        foreach ($owners as $o) {
            $out[] = [
                'owner_id'   => (int)$o['id'],
                'first_name' => $o['first_name'] ?? '',
                'last_name'  => $o['last_name']  ?? '',
                'email'      => $o['email']       ?? '',
            ];
        }
        $this->json($out);
    }

    /* ── GET /api/portal-admin/nachrichten-drawer ── */
    public function drawerData(array $params = []): void
    {
        $threads = $this->repo->getAllThreads();
        $unread  = $this->repo->countUnreadForAdmin();

        $out = [];
        foreach (array_slice($threads, 0, 20) as $t) {
            $out[] = [
                'id'           => (int)$t['id'],
                'subject'      => $t['subject'],
                'owner_name'   => $t['owner_name'],
                'status'       => $t['status'],
                'unread'       => (int)($t['unread_count'] ?? 0),
                'last_message' => $t['last_message_at'] ?? $t['created_at'],
            ];
        }

        $this->json(['threads' => $out, 'total_unread' => $unread]);
    }

    /* ── POST /api/portal-admin/nachrichten/{id}/loeschen ── */
    public function delete(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->repo->deleteThread($id);
        $this->json(['ok' => true]);
    }
}
