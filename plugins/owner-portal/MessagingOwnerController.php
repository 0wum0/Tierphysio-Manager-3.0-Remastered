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

    private function isHomeworkEnabled(): bool
    {
        return ($this->settingsRepository->get('portal_show_homework', '1') === '1');
    }

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

    private const ALLOWED_MIME = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
        'application/csv',
        // Bilder
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private function formatWhatsApp(string $text): string
    {
        $s = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $s = preg_replace('/```(.+?)```/s', '<code style="font-family:monospace;background:rgba(0,0,0,.06);padding:1px 4px;border-radius:3px;">$1</code>', $s);
        $s = preg_replace('/\*([^\*\n]+)\*/', '<strong>$1</strong>', $s);
        $s = preg_replace('/_([^_\n]+)_/', '<em>$1</em>', $s);
        $s = preg_replace('/~([^~\n]+)~/', '<s>$1</s>', $s);
        return nl2br($s);
    }

    private function fileSizeLabel(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    /* ── Auth guard ── */
    protected function isAjax(): bool
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
            'page_title'          => 'Nachrichten',
            'portal_user'         => $portalUser,
            'active_nav'          => 'nachrichten',
            'threads'             => $threads,
            'unread'              => $unread,
            'portal_unread_count' => $unread,
            'show_homework_nav'   => $this->isHomeworkEnabled(),
            'csrf_token'          => $this->session->generateCsrfToken(),
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
        foreach ($messages as &$m) {
            $m['body_html']  = $this->formatWhatsApp($m['body']);
            $m['size_label'] = $m['attachment_size'] ? $this->fileSizeLabel((int)$m['attachment_size']) : '';
        }
        unset($m);

        $unread = $this->repo->countUnreadForOwner($ownerId);
        $this->render('@owner-portal/owner_message_thread.twig', [
            'page_title'          => 'Nachricht: ' . $thread['subject'],
            'portal_user'         => $portalUser,
            'active_nav'          => 'nachrichten',
            'thread'              => $thread,
            'messages'            => $messages,
            'portal_unread_count' => $unread,
            'show_homework_nav'   => $this->isHomeworkEnabled(),
            'csrf_token'          => $this->session->generateCsrfToken(),
        ]);
    }

    /* ── GET /api/portal/nachrichten/ungelesen ── */
    public function unreadCount(array $params = []): void
    {
        $portalUser = $this->requireOwnerAuth();
        $ownerId    = (int)$portalUser['owner_id'];
        $count      = $this->repo->countUnreadForOwner($ownerId);
        $this->json(['unread' => $count]);
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
            'body'        => $body,
            'body_html'   => $this->formatWhatsApp($body),
            'sender_type' => 'owner',
            'sender_name' => $ownerName,
            'created_at'  => date('H:i'),
        ]);
    }

    /* ── POST /api/portal/nachrichten/{id}/anhang (AJAX: send with file) ── */
    public function upload(array $params = []): void
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
        $file = $_FILES['file'] ?? null;

        if ($body === '' && (!$file || $file['error'] !== UPLOAD_ERR_OK)) {
            $this->json(['error' => 'Nachricht oder Datei erforderlich.'], 422);
            return;
        }

        $attachPath = $attachName = null;
        $attachSize = null;

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] > 10 * 1024 * 1024) {
                $this->json(['error' => 'Datei zu groß (max. 10 MB).'], 422);
                return;
            }
            $mime = mime_content_type($file['tmp_name']) ?: '';
            if (!in_array($mime, self::ALLOWED_MIME, true)) {
                $this->json(['error' => 'Dateityp nicht erlaubt (PDF, Word, Excel, TXT, CSV, JPG, PNG, GIF, WebP).'], 422);
                return;
            }
            $dir = tenant_storage_path('portal-attachments/' . $id);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $safeName)) {
                $this->json(['error' => 'Datei konnte nicht gespeichert werden.'], 500);
                return;
            }
            $attachPath = 'portal-attachments/' . $id . '/' . $safeName;
            $attachName = basename($file['name']);
            $attachSize = (int)$file['size'];
        }

        $msgId = $this->repo->addMessage($id, 'owner', (int)$portalUser['id'], $body, $attachPath, $attachName, $attachSize);

        if ($thread['status'] === 'closed') {
            $this->repo->reopenThread($id);
        }

        try {
            $adminEmail = $this->settingsRepository->get('mail_from', '') ?: $this->settingsRepository->get('smtp_user', '');
            if ($adminEmail !== '') {
                $ownerName = trim(($portalUser['first_name'] ?? '') . ' ' . ($portalUser['last_name'] ?? ''));
                $this->mailer->notifyAdminNewMessage($adminEmail, $ownerName, $id, $thread['subject'], $body ?: '[Dateianhang: ' . $attachName . ']');
            }
        } catch (\Throwable) {}

        $ownerName = trim(($portalUser['first_name'] ?? '') . ' ' . ($portalUser['last_name'] ?? ''));
        $this->json([
            'ok'              => true,
            'id'              => $msgId,
            'body'            => $body,
            'body_html'       => $this->formatWhatsApp($body),
            'sender_type'     => 'owner',
            'sender_name'     => $ownerName,
            'created_at'      => date('H:i'),
            'attachment_name' => $attachName,
            'size_label'      => $attachSize ? $this->fileSizeLabel($attachSize) : '',
        ]);
    }

    /* ── GET /api/portal/nachrichten/{id}/anhang/{msgId} (download) ── */
    public function download(array $params = []): void
    {
        $portalUser = $this->requireOwnerAuth();
        $ownerId    = (int)$portalUser['owner_id'];
        $msgId      = (int)($params['msgId'] ?? 0);

        $msg = $this->repo->getMessageById($msgId);
        if (!$msg || (int)$msg['owner_id'] !== $ownerId || empty($msg['attachment_path'])) {
            http_response_code(404);
            exit;
        }

        $fullPath = tenant_storage_path($msg['attachment_path']);
        if (!is_file($fullPath)) {
            http_response_code(404);
            exit;
        }

        $name = $msg['attachment_name'] ?: basename($fullPath);
        $mime = @mime_content_type($fullPath) ?: 'application/octet-stream';
        $isImage = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
        $disposition = $isImage ? 'inline' : 'attachment';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($name) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=86400');
        readfile($fullPath);
        exit;
    }

    /* ── GET /api/portal/nachrichten/{id}/poll?after={lastMsgId} ── */
    public function poll(array $params = []): void
    {
        $portalUser = $this->requireOwnerAuth();
        $ownerId    = (int)$portalUser['owner_id'];
        $id         = (int)($params['id'] ?? 0);
        $afterId    = (int)($_GET['after'] ?? 0);

        $thread = $this->repo->getThreadById($id);
        if (!$thread || (int)$thread['owner_id'] !== $ownerId) {
            $this->json(['error' => 'Nicht gefunden.'], 404);
            return;
        }

        $this->repo->markThreadReadByOwner($id);

        $newMsgs    = $this->repo->getNewMessages($id, $afterId);
        $readUpdates = $this->repo->getReadUpdates($id, 'owner');

        $outMsgs = [];
        foreach ($newMsgs as $m) {
            $size = isset($m['attachment_size']) && $m['attachment_size'] ? $this->fileSizeLabel((int)$m['attachment_size']) : '';
            $outMsgs[] = [
                'id'              => (int)$m['id'],
                'sender_type'     => $m['sender_type'],
                'sender_name'     => $m['sender_name'] ?? '',
                'body'            => $m['body'],
                'body_html'       => $this->formatWhatsApp($m['body']),
                'created_at'      => isset($m['created_at']) ? (new \DateTime($m['created_at']))->format('H:i') : '',
                'read_at'         => $m['read_at'] ?? null,
                'attachment_name' => $m['attachment_name'] ?? null,
                'size_label'      => $size,
            ];
        }

        $this->json(['messages' => $outMsgs, 'read_updates' => array_values($readUpdates)]);
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
