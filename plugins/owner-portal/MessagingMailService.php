<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Repositories\SettingsRepository;
use App\Services\MailService;

class MessagingMailService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly MailService $mailer
    ) {}

    private function baseUrl(): string
    {
        $configured = $this->settings->get('portal_base_url', '');
        if ($configured !== '') return rtrim($configured, '/');
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    private function companyName(): string
    {
        return $this->settings->get('company_name', 'Tierphysio Praxis');
    }

    /* Notify owner that admin sent a new message */
    public function notifyOwnerNewMessage(string $ownerEmail, string $ownerName, int $threadId, string $subject, string $preview): void
    {
        $url         = $this->baseUrl() . '/portal/nachrichten/' . $threadId;
        $company     = $this->companyName();
        $previewText = mb_substr(strip_tags($preview), 0, 160);

        $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;color:#1e293b;background:#f8fafc;margin:0;padding:2rem;">'
              . '<div style="max-width:540px;margin:0 auto;background:#fff;border-radius:12px;padding:2rem;border:1px solid #e2e8f0;">'
              . '<h2 style="margin:0 0 1rem;font-size:1.1rem;color:#4f7cff;">💬 Neue Nachricht von ' . htmlspecialchars($company) . '</h2>'
              . '<p style="margin:0 0 0.5rem;font-size:0.9rem;color:#64748b;">Betreff: <strong>' . htmlspecialchars($subject) . '</strong></p>'
              . '<p style="margin:0 0 1.5rem;font-size:0.875rem;color:#475569;background:#f1f5f9;padding:0.75rem 1rem;border-radius:8px;">'
              . htmlspecialchars($previewText) . (mb_strlen($preview) > 160 ? '…' : '')
              . '</p>'
              . '<a href="' . htmlspecialchars($url) . '" style="display:inline-block;background:#4f7cff;color:#fff;text-decoration:none;padding:0.6rem 1.4rem;border-radius:8px;font-weight:600;font-size:0.9rem;">Nachricht lesen →</a>'
              . '<p style="margin:1.5rem 0 0;font-size:0.78rem;color:#94a3b8;">Diese E-Mail wurde automatisch von ' . htmlspecialchars($company) . ' gesendet.<br>Melden Sie sich im Portal an, um zu antworten.</p>'
              . '</div></body></html>';

        $this->mailer->sendRaw(
            $ownerEmail,
            $ownerName,
            'Neue Nachricht: ' . $subject . ' — ' . $company,
            $html
        );
    }

    /* Notify admin that owner sent a new message */
    public function notifyAdminNewMessage(string $adminEmail, string $ownerName, int $threadId, string $subject, string $preview): void
    {
        $scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $appBase = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $url     = $appBase . '/portal-admin/nachrichten/' . $threadId;
        $company = $this->companyName();
        $previewText = mb_substr(strip_tags($preview), 0, 160);

        $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;color:#1e293b;background:#f8fafc;margin:0;padding:2rem;">'
              . '<div style="max-width:540px;margin:0 auto;background:#fff;border-radius:12px;padding:2rem;border:1px solid #e2e8f0;">'
              . '<h2 style="margin:0 0 1rem;font-size:1.1rem;color:#22c55e;">💬 Neue Nachricht von ' . htmlspecialchars($ownerName) . '</h2>'
              . '<p style="margin:0 0 0.5rem;font-size:0.9rem;color:#64748b;">Betreff: <strong>' . htmlspecialchars($subject) . '</strong></p>'
              . '<p style="margin:0 0 1.5rem;font-size:0.875rem;color:#475569;background:#f1f5f9;padding:0.75rem 1rem;border-radius:8px;">'
              . htmlspecialchars($previewText) . (mb_strlen($preview) > 160 ? '…' : '')
              . '</p>'
              . '<a href="' . htmlspecialchars($url) . '" style="display:inline-block;background:#22c55e;color:#fff;text-decoration:none;padding:0.6rem 1.4rem;border-radius:8px;font-weight:600;font-size:0.9rem;">Im Admin öffnen →</a>'
              . '<p style="margin:1.5rem 0 0;font-size:0.78rem;color:#94a3b8;">Portalnachricht über ' . htmlspecialchars($company) . '</p>'
              . '</div></body></html>';

        $this->mailer->sendRaw(
            $adminEmail,
            $company,
            '[Portal] Neue Nachricht von ' . $ownerName . ': ' . $subject,
            $html
        );
    }
}
