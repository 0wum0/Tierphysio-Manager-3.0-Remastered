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
        $inviteUrl   = $this->getBaseUrl() . '/portal/einladung/' . $token . $this->tenantQuery();
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

    /**
     * Sendet Email an Besitzer wenn ein neuer Hausaufgabenplan erstellt wurde.
     */
    public function sendNewHomework(
        string $email,
        string $ownerName,
        string $petName,
        string $planDate,
        string $therapistName,
        int    $patientId
    ): void {
        $baseUrl     = $this->getBaseUrl();
        $portalUrl   = $baseUrl . '/portal/tiere/' . $patientId . '/hausaufgaben';
        $companyName = $this->settings->get('company_name', 'Tierphysio Praxis');

        $safeOwner   = htmlspecialchars($ownerName,    ENT_QUOTES, 'UTF-8');
        $safePet     = htmlspecialchars($petName,      ENT_QUOTES, 'UTF-8');
        $safeDate    = htmlspecialchars($planDate,     ENT_QUOTES, 'UTF-8');
        $safeTherapist = htmlspecialchars($therapistName, ENT_QUOTES, 'UTF-8');
        $safeName    = htmlspecialchars($companyName,  ENT_QUOTES, 'UTF-8');
        $safeUrl     = htmlspecialchars($portalUrl,    ENT_QUOTES, 'UTF-8');

        $subject = 'Neue Hausaufgaben für ' . $petName . ' — ' . $companyName;
        $htmlBody = '
<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;">
  <h2 style="color:#4f46e5;margin-bottom:8px;">📋 Neue Hausaufgaben verfügbar</h2>
  <p style="color:#374151;">Guten Tag ' . $safeOwner . ',</p>
  <p style="color:#374151;">
    <strong>' . $safeTherapist . '</strong> hat für <strong>' . $safePet . '</strong>
    am ' . $safeDate . ' einen neuen Hausaufgabenplan erstellt.
  </p>
  <p style="color:#374151;">
    Die Hausaufgaben enthalten spezifische Übungen und Therapiemaßnahmen, die Sie zu Hause
    mit ' . $safePet . ' durchführen können.
  </p>
  <p style="margin:28px 0;">
    <a href="' . $safeUrl . '" style="background:#4f46e5;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block;">
      Hausaufgaben im Portal ansehen
    </a>
  </p>
  <p style="color:#6b7280;font-size:13px;">
    Oder kopieren Sie diesen Link:<br>
    <a href="' . $safeUrl . '" style="color:#4f46e5;word-break:break-all;">' . $safeUrl . '</a>
  </p>
  <p style="color:#374151;margin-top:24px;">Mit freundlichen Grüßen<br><strong>' . $safeName . '</strong></p>
</div>';

        $this->mailer->sendRaw($email, $ownerName, $subject, $htmlBody);
    }

    /**
     * Sendet Email an Besitzer wenn eine neue Behandlung/ein neuer Akteneintrag angelegt wurde.
     */
    public function sendNewTreatment(
        string $email,
        string $ownerName,
        string $petName,
        string $entryTitle,
        string $entryDate,
        int    $patientId
    ): void {
        $baseUrl     = $this->getBaseUrl();
        $portalUrl   = $baseUrl . '/portal/tiere/' . $patientId;
        $companyName = $this->settings->get('company_name', 'Tierphysio Praxis');

        $safeOwner = htmlspecialchars($ownerName,   ENT_QUOTES, 'UTF-8');
        $safePet   = htmlspecialchars($petName,     ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($entryTitle,  ENT_QUOTES, 'UTF-8');
        $safeDate  = htmlspecialchars($entryDate,   ENT_QUOTES, 'UTF-8');
        $safeName  = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
        $safeUrl   = htmlspecialchars($portalUrl,   ENT_QUOTES, 'UTF-8');

        $subject = 'Neuer Behandlungseintrag für ' . $petName . ' — ' . $companyName;
        $htmlBody = '
<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;">
  <h2 style="color:#4f46e5;margin-bottom:8px;">💊 Neuer Behandlungseintrag</h2>
  <p style="color:#374151;">Guten Tag ' . $safeOwner . ',</p>
  <p style="color:#374151;">
    Für <strong>' . $safePet . '</strong> wurde am ' . $safeDate . ' ein neuer Eintrag
    <strong>„' . $safeTitle . '"</strong> in der Akte hinterlegt.
  </p>
  <p style="margin:28px 0;">
    <a href="' . $safeUrl . '" style="background:#4f46e5;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block;">
      Im Besitzerportal ansehen
    </a>
  </p>
  <p style="color:#374151;margin-top:24px;">Mit freundlichen Grüßen<br><strong>' . $safeName . '</strong></p>
</div>';

        $this->mailer->sendRaw($email, $ownerName, $subject, $htmlBody);
    }

    private function getBaseUrl(): string
    {
        $configured = $this->settings->get('portal_base_url', '');
        if ($configured !== '') return rtrim($configured, '/');

        $envAppUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        if ($envAppUrl !== '') {
            return $envAppUrl;
        }

        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

        /* Keep dedicated portal subdomain for owner links */
        if (str_starts_with($host, 'app.')) {
            $host = 'portal.' . substr($host, 4);
        }
        $tid = trim(substr($prefix, 2), '_');
        return $tid !== '' ? ('?tid=' . rawurlencode($tid)) : '';
    }

    private function tenantQuery(): string
    {
        $prefix = (string)($_SESSION['tenant_table_prefix'] ?? $_SESSION['portal_tenant_prefix'] ?? '');
        if ($prefix === '' || !str_starts_with($prefix, 't_')) {
            return '';
        }
        $tid = trim(substr($prefix, 2), '_');
        return $tid !== '' ? ('?tid=' . rawurlencode($tid)) : '';
    }
}
