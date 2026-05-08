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
        foreach ($messages as &$m) {
            $m['body_html']  = $this->formatWhatsApp($m['body']);
            $m['size_label'] = $m['attachment_size'] ? $this->fileSizeLabel((int)$m['attachment_size']) : '';
        }
        unset($m);

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

        $senderName = $user['name'] ?? 'Team';
        $this->json([
            'ok'          => true,
            'id'          => $msgId,
            'body'        => $body,
            'body_html'   => $this->formatWhatsApp($body),
            'sender_type' => 'admin',
            'sender_name' => $senderName,
            'created_at'  => date('H:i'),
        ]);
    }

    /* ── POST /api/portal-admin/nachrichten/{id}/anhang (AJAX: send with file) ── */
    public function upload(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $thread = $this->repo->getThreadById($id);
        if (!$thread) {
            $this->json(['error' => 'Thread nicht gefunden.'], 404);
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

        $user   = $this->session->getUser();
        $userId = $user ? (int)$user['id'] : null;
        $msgId  = $this->repo->addMessage($id, 'admin', $userId, $body, $attachPath, $attachName, $attachSize);

        if ($thread['status'] === 'closed') {
            $this->repo->reopenThread($id);
        }

        try {
            $this->mailer->notifyOwnerNewMessage(
                $thread['owner_email'],
                $thread['owner_name'],
                $id,
                $thread['subject'],
                $body ?: '[Dateianhang: ' . $attachName . ']'
            );
        } catch (\Throwable) {}

        $senderName = $user['name'] ?? 'Team';
        $this->json([
            'ok'              => true,
            'id'              => $msgId,
            'body'            => $body,
            'body_html'       => $this->formatWhatsApp($body),
            'sender_type'     => 'admin',
            'sender_name'     => $senderName,
            'created_at'      => date('H:i'),
            'attachment_name' => $attachName,
            'size_label'      => $attachSize ? $this->fileSizeLabel($attachSize) : '',
        ]);
    }

    /* ── GET /api/portal-admin/nachrichten/{id}/anhang/{msgId} (download) ── */
    public function download(array $params = []): void
    {
        $msgId = (int)($params['msgId'] ?? 0);
        $msg   = $this->repo->getMessageById($msgId);
        if (!$msg || empty($msg['attachment_path'])) {
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

    /* ── GET /api/portal-admin/nachrichten/{id}/poll?after={lastMsgId} ── */
    public function poll(array $params = []): void
    {
        $id      = (int)($params['id'] ?? 0);
        $afterId = (int)($_GET['after'] ?? 0);

        $thread = $this->repo->getThreadById($id);
        if (!$thread) {
            $this->json(['error' => 'Nicht gefunden.'], 404);
            return;
        }

        $this->repo->markThreadReadByAdmin($id);

        $newMsgs     = $this->repo->getNewMessages($id, $afterId);
        $readUpdates = $this->repo->getReadUpdates($id, 'admin');

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

    /* ── GET /api/portal-admin/nachrichten/{id}/messages (drawer inline) ── */
    public function messages(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $thread = $this->repo->getThreadById($id);
        if (!$thread) {
            $this->json(['error' => 'Nicht gefunden.'], 404);
            return;
        }

        $this->repo->markThreadReadByAdmin($id);
        $msgs = $this->repo->getMessages($id);

        $out = [];
        foreach ($msgs as $m) {
            $out[] = [
                'id'          => (int)$m['id'],
                'sender_type' => $m['sender_type'],
                'sender_name' => $m['sender_name'] ?? '',
                'body'        => $m['body'],
                'created_at'  => isset($m['created_at'])
                    ? (new \DateTime($m['created_at']))->format('d.m.Y H:i')
                    : '',
            ];
        }

        $this->json([
            'thread'   => [
                'id'      => (int)$thread['id'],
                'subject' => $thread['subject'],
                'status'  => $thread['status'],
            ],
            'messages' => $out,
        ]);
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
