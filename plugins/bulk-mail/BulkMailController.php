<?php
declare(strict_types=1);
namespace Plugins\BulkMail;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;
use App\Repositories\SettingsRepository;
use App\Services\MailService;
use PDO;

class BulkMailController extends Controller
{
    private Database           $db;
    private MailService        $mailService;
    private SettingsRepository $settings;

    public function __construct(
        View $view, Session $session, Config $config, Translator $translator,
        Database $db, SettingsRepository $settings, MailService $mailService
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->db          = $db;
        $this->settings    = $settings;
        $this->mailService = $mailService;
    }

    /* GET /bulk-mail */
    public function index(array $params = []): void
    {
        $this->render('@bulk-mail/index.twig', [
            'page_title'   => 'Massen-Kommunikation',
            'owner_groups' => $this->getOwnerGroups(),
            'csrf_token'   => $this->session->generateCsrfToken(),
        ]);
    }

    /* POST /bulk-mail/vorschau  (AJAX) */
    public function preview(array $params = []): void
    {
        $this->validateCsrf();
        $recipients = $this->resolveRecipients($this->post('group', 'all'));
        $this->json([
            'count'      => count($recipients),
            'recipients' => array_map(fn($r) => [
                'name'       => $r['name'],
                'email'      => $r['email'] ?? '',
                'has_portal' => $r['has_portal'],
            ], $recipients),
        ]);
    }

    /* POST /bulk-mail/senden-email  (AJAX) */
    public function sendEmail(array $params = []): void
    {
        $this->validateCsrf();
        $subject = trim($this->post('subject', ''));
        $body    = trim($this->post('body', ''));
        $group   = $this->post('group', 'all');
        if ($subject === '' || $body === '') {
            $this->json(['error' => 'Betreff und Nachricht sind erforderlich.'], 422);
        }
        $recipients  = $this->resolveRecipients($group);
        $companyName = $this->settings->get('company_name', 'Tierphysio Praxis');
        $sent = 0;
        $failed = [];
        foreach ($recipients as $r) {
            if (empty($r['email'])) continue;
            $personal = str_replace(
                ['{{name}}', '{{vorname}}', '{{praxis}}'],
                [$r['name'], $r['first_name'] ?? $r['name'], $companyName],
                $body
            );
            try {
                $html = $this->wrapHtml($subject, nl2br(htmlspecialchars($personal, ENT_QUOTES, 'UTF-8')), $companyName);
                $ok   = $this->sendRaw($r['email'], $r['name'], $subject, $html, $personal);
                $ok ? $sent++ : ($failed[] = $r['email']);
            } catch (\Throwable $e) {
                $failed[] = $r['email'];
                error_log('[BulkMail::sendEmail] ' . $e->getMessage());
            }
        }
        $this->logCampaign('email', $subject, $group, $sent, count($failed));
        $this->json(['sent' => $sent, 'failed' => $failed]);
    }

    /* POST /bulk-mail/senden-portal  (AJAX) */
    public function sendPortal(array $params = []): void
    {
        $this->validateCsrf();
        $subject = trim($this->post('subject', ''));
        $body    = trim($this->post('body', ''));
        $group   = $this->post('group', 'all');
        if ($subject === '' || $body === '') {
            $this->json(['error' => 'Betreff und Nachricht sind erforderlich.'], 422);
        }
        $recipients  = $this->resolveRecipients($group);
        $companyName = $this->settings->get('company_name', 'Tierphysio Praxis');
        $user        = $this->session->getUser();
        $userId      = $user ? (int)$user['id'] : null;
        $sent = 0;
        $failed = [];
        foreach ($recipients as $r) {
            if (empty($r['owner_id']) || !$r['has_portal']) continue;
            $personal = str_replace(
                ['{{name}}', '{{vorname}}', '{{praxis}}'],
                [$r['name'], $r['first_name'] ?? $r['name'], $companyName],
                $body
            );
            try {
                $threadId = $this->createPortalThread((int)$r['owner_id'], $subject, $user['name'] ?? 'Admin');
                $this->addPortalMessage($threadId, 'admin', $userId, $personal);
                $sent++;
            } catch (\Throwable $e) {
                $failed[] = $r['name'];
                error_log('[BulkMail::sendPortal] ' . $e->getMessage());
            }
        }
        $this->logCampaign('portal', $subject, $group, $sent, count($failed));
        $this->json(['sent' => $sent, 'failed' => $failed]);
    }

    /* ── Private helpers ── */

    private function getOwnerGroups(): array
    {
        return [
            'all'          => 'Alle Besitzer',
            'with_portal'  => 'Besitzer mit Portal-Zugang',
            'with_email'   => 'Besitzer mit E-Mail-Adresse',
            'active'       => 'Besitzer mit aktivem Patienten',
        ];
    }

    private function resolveRecipients(string $group): array
    {
        $sql = "
            SELECT
                o.id AS owner_id,
                CONCAT(o.first_name, ' ', o.last_name) AS name,
                o.first_name,
                o.email,
                (SELECT COUNT(*) FROM owner_portal_users pu WHERE pu.owner_id = o.id AND pu.is_active = 1 LIMIT 1) AS has_portal
            FROM owners o
        ";
        $where = [];
        if ($group === 'with_portal') {
            $where[] = "EXISTS (SELECT 1 FROM owner_portal_users pu WHERE pu.owner_id = o.id AND pu.is_active = 1)";
        } elseif ($group === 'with_email') {
            $where[] = "o.email IS NOT NULL AND o.email != ''";
        } elseif ($group === 'active') {
            $where[] = "EXISTS (SELECT 1 FROM patients p WHERE p.owner_id = o.id AND p.status = 'aktiv')";
        }
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY o.last_name, o.first_name";
        try {
            $rows = $this->db->fetchAll($sql);
            return array_map(fn($r) => array_merge($r, ['has_portal' => (bool)$r['has_portal']]), $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    private function sendRaw(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): bool
    {
        try {
            $mailer = $this->createMailer();
            $mailer->addAddress($toEmail, $toName);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $htmlBody;
            $mailer->AltBody = $textBody;
            return $mailer->send();
        } catch (\Throwable $e) {
            $this->mailService->getLastError();
            error_log('[BulkMail::sendRaw] ' . $e->getMessage());
            return false;
        }
    }

    private function createMailer(): \PHPMailer\PHPMailer\PHPMailer
    {
        $pm = new \PHPMailer\PHPMailer\PHPMailer(true);
        $pm->isSMTP();
        $pm->Host       = $this->settings->get('smtp_host', 'localhost');
        $pm->Port       = (int)$this->settings->get('smtp_port', '587');
        $pm->Username   = $this->settings->get('smtp_username', '');
        $pm->Password   = $this->settings->get('smtp_password', '');
        $pm->SMTPAuth   = !empty($pm->Username);
        $pm->Timeout    = 10;
        $pm->SMTPKeepAlive = true;
        $enc = $this->settings->get('smtp_encryption', 'tls');
        if ($enc === 'ssl') {
            $pm->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'none') {
            $pm->SMTPSecure = '';
            $pm->SMTPAutoTLS = false;
        } else {
            $pm->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $pm->setFrom(
            $this->settings->get('mail_from_address', 'noreply@tierphysio.local'),
            $this->settings->get('mail_from_name', 'Tierphysio Manager')
        );
        $pm->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        return $pm;
    }

    private function createPortalThread(int $ownerId, string $subject, string $createdBy): int
    {
        $this->db->execute(
            "INSERT INTO portal_message_threads (owner_id, subject, status, created_by, last_message_at, created_at)
             VALUES (?, ?, 'open', ?, NOW(), NOW())",
            [$ownerId, $subject, $createdBy]
        );
        return (int)$this->db->lastInsertId();
    }

    private function addPortalMessage(int $threadId, string $senderType, ?int $senderId, string $body): void
    {
        $this->db->execute(
            "INSERT INTO portal_messages (thread_id, sender_type, sender_id, body, is_read, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())",
            [$threadId, $senderType, $senderId, trim($body)]
        );
        $this->db->execute(
            "UPDATE portal_message_threads SET last_message_at = NOW() WHERE id = ?",
            [$threadId]
        );
    }

    private function logCampaign(string $type, string $subject, string $group, int $sent, int $failed): void
    {
        try {
            $this->db->execute(
                "INSERT INTO bulk_mail_log (type, subject, recipient_group, sent_count, failed_count, sent_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$type, $subject, $group, $sent, $failed, $this->session->getUser()['id'] ?? null]
            );
        } catch (\Throwable) { /* table might not exist yet */ }
    }

    private function wrapHtml(string $subject, string $body, string $companyName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:0}
.wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.header{background:#4f46e5;color:#fff;padding:24px 32px}
.header h1{margin:0;font-size:20px}
.body{padding:32px;color:#333;line-height:1.7;font-size:15px}
.footer{background:#f9f9f9;padding:16px 32px;font-size:12px;color:#999;border-top:1px solid #eee}
</style></head>
<body><div class="wrap">
<div class="header"><h1>{$subject}</h1></div>
<div class="body">{$body}</div>
<div class="footer">{$companyName} &mdash; Diese E-Mail wurde automatisch generiert.</div>
</div></body></html>
HTML;
    }
}
