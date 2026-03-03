<?php

declare(strict_types=1);

namespace Plugins\PatientIntake;

use App\Repositories\SettingsRepository;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class IntakeMailService
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function sendNewSubmissionNotification(array $submission): bool
    {
        $to      = $this->settings->get('mail_from_address', $this->settings->get('company_email', ''));
        $toName  = $this->settings->get('mail_from_name', 'Tierphysio Manager');

        if (empty($to)) {
            return false;
        }

        $subject = '🐾 Neue Patientenanmeldung: ' . $submission['patient_name']
            . ' (' . $submission['owner_first_name'] . ' ' . $submission['owner_last_name'] . ')';

        $appName = $this->settings->get('company_name', 'Tierphysio Manager');
        $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
        $inboxUrl = $appUrl . '/eingangsmeldungen';

        $date = date('d.m.Y H:i', strtotime($submission['created_at']));

        $html = $this->buildHtml($submission, $appName, $inboxUrl, $date);
        $text = $this->buildText($submission, $appName, $inboxUrl, $date);

        return $this->sendMail($to, $toName, $subject, $html, $text);
    }

    public function sendOwnerConfirmation(array $submission): bool
    {
        $to     = $submission['owner_email'] ?? '';
        if (empty($to)) {
            return false;
        }

        $toName  = $submission['owner_first_name'] . ' ' . $submission['owner_last_name'];
        $appName = $this->settings->get('company_name', 'Tierphysio Manager');
        $subject = 'Ihre Anmeldung bei ' . $appName;
        $html  = $this->buildConfirmationHtml($submission, $appName);
        $text  = $this->buildConfirmationText($submission, $appName);

        return $this->sendMail($to, $toName, $subject, $html, $text);
    }

    private function sendMail(string $to, string $toName, string $subject, string $html, string $text): bool
    {
        $host = $this->settings->get('smtp_host', '');
        if (!empty($host)) {
            return $this->sendSmtp($to, $toName, $subject, $html, $text);
        }
        return $this->sendPhpMail($to, $toName, $subject, $html, $text);
    }

    private function sendSmtp(string $to, string $toName, string $subject, string $html, string $text): bool
    {
        $host       = $this->settings->get('smtp_host', 'localhost');
        $port       = (int)$this->settings->get('smtp_port', 587);
        $username   = $this->settings->get('smtp_username', '');
        $password   = $this->settings->get('smtp_password', '');
        $encryption = $this->settings->get('smtp_encryption', 'tls');
        $fromAddr   = $this->settings->get('mail_from_address', $username);
        $fromName   = $this->settings->get('mail_from_name', 'Tierphysio Manager');

        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            try {
                $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host       = $host;
                $mailer->SMTPAuth   = !empty($username);
                $mailer->Username   = $username;
                $mailer->Password   = $password;
                $mailer->Port       = $port;
                $mailer->CharSet    = 'UTF-8';

                if ($encryption === 'ssl') {
                    $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($encryption === 'tls') {
                    $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }

                $mailer->setFrom($fromAddr, $fromName);
                $mailer->addAddress($to, $toName);
                $mailer->isHTML(true);
                $mailer->Subject = $subject;
                $mailer->Body    = $html;
                $mailer->AltBody = $text;

                $mailer->send();
                return true;
            } catch (\Throwable $e) {
                error_log('[PatientIntake] SMTP error: ' . $e->getMessage());
                return false;
            }
        }

        return $this->sendPhpMail($to, $toName, $subject, $html, $text);
    }

    private function sendPhpMail(string $to, string $toName, string $subject, string $html, string $text): bool
    {
        $fromAddr = $this->settings->get('mail_from_address', '');
        $fromName = $this->settings->get('mail_from_name', 'Tierphysio Manager');
        $boundary = md5(uniqid());

        $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromAddr>\r\n";
        $headers .= "Reply-To: $fromAddr\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= $text . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $html . "\r\n";
        $body .= "--$boundary--";

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedTo      = '=?UTF-8?B?' . base64_encode($toName) . "?= <$to>";

        return @mail($encodedTo, $encodedSubject, $body, $headers);
    }

    private function buildHtml(array $s, string $appName, string $inboxUrl, string $date): string
    {
        $ownerName    = htmlspecialchars($s['owner_first_name'] . ' ' . $s['owner_last_name']);
        $patientName  = htmlspecialchars($s['patient_name']);
        $species      = htmlspecialchars($s['patient_species']);
        $breed        = htmlspecialchars($s['patient_breed']);
        $reason       = nl2br(htmlspecialchars($s['reason']));
        $aptWish      = htmlspecialchars($s['appointment_wish']);
        $email        = htmlspecialchars($s['owner_email']);
        $phone        = htmlspecialchars($s['owner_phone']);
        $appName      = htmlspecialchars($appName);

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Neue Patientenanmeldung</title></head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f1a;padding:32px 16px;">
  <tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:16px;overflow:hidden;">
      <tr><td style="background:linear-gradient(135deg,rgba(79,124,255,0.3),rgba(139,92,246,0.3));padding:32px 40px;text-align:center;">
        <div style="font-size:2rem;margin-bottom:8px;">🐾</div>
        <h1 style="margin:0;color:#ffffff;font-size:1.4rem;font-weight:700;">Neue Patientenanmeldung</h1>
        <p style="margin:8px 0 0;color:rgba(255,255,255,0.7);font-size:0.9rem;">$date</p>
      </td></tr>
      <tr><td style="padding:32px 40px;">
        <div style="background:rgba(79,124,255,0.1);border:1px solid rgba(79,124,255,0.3);border-radius:12px;padding:20px;margin-bottom:24px;">
          <h2 style="margin:0 0 16px;color:#4f7cff;font-size:1rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">🐕 Tier</h2>
          <table width="100%" cellpadding="4" cellspacing="0">
            <tr><td style="color:rgba(255,255,255,0.5);font-size:0.85rem;width:40%;">Name</td><td style="color:#fff;font-size:0.9rem;font-weight:600;">$patientName</td></tr>
            <tr><td style="color:rgba(255,255,255,0.5);font-size:0.85rem;">Tierart</td><td style="color:#fff;font-size:0.9rem;">$species</td></tr>
            <tr><td style="color:rgba(255,255,255,0.5);font-size:0.85rem;">Rasse</td><td style="color:#fff;font-size:0.9rem;">$breed</td></tr>
          </table>
        </div>
        <div style="background:rgba(139,92,246,0.1);border:1px solid rgba(139,92,246,0.3);border-radius:12px;padding:20px;margin-bottom:24px;">
          <h2 style="margin:0 0 16px;color:#8b5cf6;font-size:1rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">👤 Besitzer</h2>
          <table width="100%" cellpadding="4" cellspacing="0">
            <tr><td style="color:rgba(255,255,255,0.5);font-size:0.85rem;width:40%;">Name</td><td style="color:#fff;font-size:0.9rem;font-weight:600;">$ownerName</td></tr>
            <tr><td style="color:rgba(255,255,255,0.5);font-size:0.85rem;">E-Mail</td><td style="color:#fff;font-size:0.9rem;">$email</td></tr>
            <tr><td style="color:rgba(255,255,255,0.5);font-size:0.85rem;">Telefon</td><td style="color:#fff;font-size:0.9rem;">$phone</td></tr>
          </table>
        </div>
        <div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:20px;margin-bottom:24px;">
          <h2 style="margin:0 0 12px;color:rgba(255,255,255,0.8);font-size:1rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">📋 Anliegen</h2>
          <p style="margin:0 0 12px;color:rgba(255,255,255,0.7);font-size:0.9rem;line-height:1.6;">$reason</p>
          <p style="margin:0;color:rgba(255,255,255,0.5);font-size:0.85rem;">Terminwunsch: <span style="color:#fff;">$aptWish</span></p>
        </div>
        <div style="text-align:center;">
          <a href="$inboxUrl" style="display:inline-block;background:linear-gradient(135deg,#4f7cff,#8b5cf6);color:#fff;text-decoration:none;padding:14px 32px;border-radius:100px;font-weight:600;font-size:0.9rem;">Eingangsmeldungen öffnen →</a>
        </div>
      </td></tr>
      <tr><td style="padding:16px 40px 24px;text-align:center;border-top:1px solid rgba(255,255,255,0.08);">
        <p style="margin:0;color:rgba(255,255,255,0.3);font-size:0.78rem;">$appName · Automatisch generierte Nachricht</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;
    }

    private function buildText(array $s, string $appName, string $inboxUrl, string $date): string
    {
        $ownerName = $s['owner_first_name'] . ' ' . $s['owner_last_name'];
        return <<<TEXT
Neue Patientenanmeldung — $date

TIER
Name:    {$s['patient_name']}
Tierart: {$s['patient_species']}
Rasse:   {$s['patient_breed']}

BESITZER
Name:    $ownerName
E-Mail:  {$s['owner_email']}
Telefon: {$s['owner_phone']}

ANLIEGEN
{$s['reason']}

Terminwunsch: {$s['appointment_wish']}

Eingangsmeldungen: $inboxUrl

-- $appName
TEXT;
    }

    private function buildConfirmationHtml(array $s, string $appName): string
    {
        $ownerName   = htmlspecialchars($s['owner_first_name']);
        $patientName = htmlspecialchars($s['patient_name']);
        $appName     = htmlspecialchars($appName);

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Anmeldung erhalten</title></head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f1a;padding:32px 16px;">
  <tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:16px;overflow:hidden;">
      <tr><td style="background:linear-gradient(135deg,rgba(79,124,255,0.3),rgba(139,92,246,0.3));padding:32px 40px;text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:8px;">✅</div>
        <h1 style="margin:0;color:#ffffff;font-size:1.4rem;font-weight:700;">Anmeldung erhalten!</h1>
      </td></tr>
      <tr><td style="padding:32px 40px;">
        <p style="color:rgba(255,255,255,0.8);font-size:1rem;line-height:1.6;">Liebe/r $ownerName,</p>
        <p style="color:rgba(255,255,255,0.7);font-size:0.95rem;line-height:1.7;">wir haben Ihre Anmeldung für <strong style="color:#fff;">$patientName</strong> erhalten und werden uns so schnell wie möglich bei Ihnen melden, um einen Termin zu vereinbaren.</p>
        <p style="color:rgba(255,255,255,0.5);font-size:0.85rem;line-height:1.6;margin-top:24px;">Mit tierischen Grüßen,<br>Ihr $appName Team</p>
      </td></tr>
      <tr><td style="padding:16px 40px 24px;text-align:center;border-top:1px solid rgba(255,255,255,0.08);">
        <p style="margin:0;color:rgba(255,255,255,0.3);font-size:0.78rem;">$appName · Automatisch generierte Nachricht</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;
    }

    private function buildConfirmationText(array $s, string $appName): string
    {
        $ownerName   = $s['owner_first_name'] . ' ' . $s['owner_last_name'];
        $patientName = $s['patient_name'];
        return <<<TEXT
Liebe/r $ownerName,

wir haben Ihre Anmeldung für $patientName erhalten und werden uns so schnell wie möglich bei Ihnen melden, um einen Termin zu vereinbaren.

Mit tierischen Grüßen,
Ihr $appName Team
TEXT;
    }
}
