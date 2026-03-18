<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Repositories\SettingsRepository;
use App\Services\MailService;

class OwnerPortalMailService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly MailService $mailer
    ) {}

    public function sendInvite(string $email, string $token): void
    {
        $configured  = $this->settings->get('portal_base_url', '');
        $baseUrl     = $configured !== ''
            ? rtrim($configured, '/')
            : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
               . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $inviteUrl   = $baseUrl . '/portal/einladung/' . $token;
        $companyName = $this->settings->get('company_name', 'Tierphysio Praxis');

        $subject   = 'Ihr Zugang zum Besitzerportal — ' . $companyName;
        $safeUrl   = htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8');
        $safeName  = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
        $htmlBody  = '
<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;">
  <h2 style="color:#4f46e5;margin-bottom:8px;">Einladung zum Besitzerportal</h2>
  <p style="color:#374151;">Guten Tag,</p>
  <p style="color:#374151;">Sie wurden eingeladen, das Besitzerportal von <strong>' . $safeName . '</strong> zu nutzen.</p>
  <p style="color:#374151;">Über das Portal können Sie Ihre Tiere, Rechnungen, Termine und Übungen einsehen.</p>
  <p style="margin:28px 0;">
    <a href="' . $safeUrl . '" style="background:#4f46e5;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block;">Passwort festlegen &amp; einloggen</a>
  </p>
  <p style="color:#6b7280;font-size:13px;">Oder kopieren Sie diesen Link in Ihren Browser:<br>
    <a href="' . $safeUrl . '" style="color:#4f46e5;word-break:break-all;">' . $safeUrl . '</a>
  </p>
  <p style="color:#9ca3af;font-size:12px;margin-top:24px;border-top:1px solid #e5e7eb;padding-top:16px;">Dieser Link ist 7 Tage gültig. Wenn Sie diese Einladung nicht erwartet haben, können Sie diese E-Mail ignorieren.</p>
  <p style="color:#374151;margin-top:16px;">Mit freundlichen Grüßen<br><strong>' . $safeName . '</strong></p>
</div>';

        $this->mailer->sendRaw($email, '', $subject, $htmlBody);
    }
}
