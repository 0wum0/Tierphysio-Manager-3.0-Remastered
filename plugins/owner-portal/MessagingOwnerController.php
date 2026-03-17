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

class MessagingOwnerController extends Controller
{
    private MessagingRepository  $repo;
    private OwnerPortalRepository $portalRepo;
    private MessagingMailService  $mailer;
    private SettingsRepository    $settingsRepository;

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

    /* ── Auth guard ── */
    private function isAjax(): bool
    {
        return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }

    private function requireOwnerAuth(): array
    {
        $userId = $this->session->get('owner_portal_user_id');
        if (!$userId) {
            if ($this->isAjax()) { $this->json(['error' => 'Nicht angemeldet.'], 401); exit; }
            $this->redirect('/portal/login');
            exit;
        }
        $user = $this->portalRepo->findUserById((int)$userId);
        if (!$user || !$user['is_active']) {
            $this->session->remove('owner_portal_user_id');
            $this->session->remove('owner_portal_owner_id');
            if ($this->isAjax()) { $this->json(['error' => 'Sitzung abgelaufen.'], 401); exit; }
            $this->redirect('/portal/login');
            exit;
        }
        return $user;
    }

    /* ── GET /portal/nachrichten ── */
    public function index(array $params = []): void
    {
        $portalUser = $this->requireOwnerAuth();
        $ownerId    = (int)$portalUser['owner_id'];
        $threads    = $this->repo->getThreadsByOwner($ownerId);
        $unread     = $this->repo->countUnreadForOwner($ownerId);

        $this->render('@owner-portal/owner_messages.twig', [
            'page_title'  => 'Nachrichten',
            'portal_user' => $portalUser,
            'active_nav'  => 'nachrichten',
            'threads'     => $threads,
            'unread'      => $unread,
            'csrf_token'  => $this->session->generateCsrfToken(),
        ]);
    }

    /* ── GET /portal/nachrichten/{id} ── */
    public function thread(array $params = []): void
    {
        $portalUser = $this->requireOwnerAuth();
        $ownerId    = (int)$portalUser['owner_id'];
        $id         = (int)($params['id'] ?? 0);

        $thread = $this->repo->getThreadById($id);
        if (!$thread || (int)$thread['owner_id'] !== $ownerId) {
            $this->redirect('/portal/nachrichten');
            return;
        }

        $this->repo->markThreadReadByOwner($id);
        $messages = $this->repo->getMessages($id);

        $this->render('@owner-portal/owner_message_thread.twig', [
            'page_title'  => 'Nachricht: ' . $thread['subject'],
            'portal_user' => $portalUser,
            'active_nav'  => 'nachrichten',
            'thread'      => $thread,
            'messages'    => $messages,
            'csrf_token'  => $this->session->generateCsrfToken(),
        ]);
    }

    /* ── POST /api/portal/nachrichten/{id}/antworten (AJAX) ── */
    public function reply(array $params = []): void
    {
        $portalUser = $this->requireOwnerAuth();
        $ownerId    = (int)$portalUser['owner_id'];
        $id         = (int)($params['id'] ?? 0);

        $thread = $this->repo->getThreadById($id);
        if (!$thread || (int)$thread['owner_id'] !== $ownerId) {
            $this->json(['error' => 'Nicht gefunden.'], 404);
            return;
        }

        $body = trim($this->post('body', ''));
        if ($body === '') {
            $this->json(['error' => 'Nachricht darf nicht leer sein.'], 422);
            return;
        }

        $msgId = $this->repo->addMessage($id, 'owner', (int)$portalUser['id'], $body);

        /* Reopen closed thread when owner replies */
        if ($thread['status'] === 'closed') {
            $this->repo->reopenThread($id);
        }

        /* Notify admin by e-mail */
        try {
            $adminEmail = $this->settingsRepository->get('mail_from', '');
            if ($adminEmail === '') {
                $adminEmail = $this->settingsRepository->get('smtp_user', '');
            }
            if ($adminEmail !== '') {
                $ownerName = trim(($portalUser['first_name'] ?? '') . ' ' . ($portalUser['last_name'] ?? ''));
                $this->mailer->notifyAdminNewMessage(
                    $adminEmail,
                    $ownerName,
                    $id,
                    $thread['subject'],
                    $body
                );
            }
        } catch (\Throwable) {}

        $ownerName = trim(($portalUser['first_name'] ?? '') . ' ' . ($portalUser['last_name'] ?? ''));
        $this->json([
            'ok'          => true,
            'id'          => $msgId,
            'body'        => htmlspecialchars($body, ENT_QUOTES, 'UTF-8'),
            'sender_type' => 'owner',
            'sender_name' => $ownerName,
            'created_at'  => date('d.m.Y H:i'),
        ]);
    }

    /* ── POST /api/portal/nachrichten/neu (AJAX: owner starts new thread) ── */
    public function newThread(array $params = []): void
    {
        $portalUser = $this->requireOwnerAuth();
        $ownerId    = (int)$portalUser['owner_id'];

        $subject = trim($this->post('subject', ''));
        $body    = trim($this->post('body', ''));

        if ($subject === '' || $body === '') {
            $this->json(['error' => 'Bitte Betreff und Nachricht ausfüllen.'], 422);
            return;
        }

        $threadId = $this->repo->createThread($ownerId, $subject, 'owner');
        $this->repo->addMessage($threadId, 'owner', (int)$portalUser['id'], $body);

        /* Notify admin */
        try {
            $adminEmail = $this->settingsRepository->get('mail_from', '');
            if ($adminEmail === '') {
                $adminEmail = $this->settingsRepository->get('smtp_user', '');
            }
            if ($adminEmail !== '') {
                $ownerName = trim(($portalUser['first_name'] ?? '') . ' ' . ($portalUser['last_name'] ?? ''));
                $this->mailer->notifyAdminNewMessage(
                    $adminEmail,
                    $ownerName,
                    $threadId,
                    $subject,
                    $body
                );
            }
        } catch (\Throwable) {}

        $this->json(['ok' => true, 'thread_id' => $threadId]);
    }
}
