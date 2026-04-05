<?php

declare(strict_types=1);

namespace Saas\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Saas\Core\Config;

class MailService
{
    public function __construct(private Config $config) {}

    private function mailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $this->config->get('mail.host', 'localhost');
        $mail->SMTPAuth   = !empty($this->config->get('mail.username'));
        $mail->Username   = $this->config->get('mail.username', '');
        $mail->Password   = $this->config->get('mail.password', '');
        $mail->SMTPSecure = $this->config->get('mail.encryption', 'tls');
        $mail->Port       = (int)$this->config->get('mail.port', 587);
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(
            $this->config->get('mail.from.address', 'noreply@tierphysio.de'),
            $this->config->get('mail.from.name', 'Tierphysio SaaS')
        );
        return $mail;
    }

    public function send(string $to, string $toName, string $subject, string $htmlBody, string $textBody = ''): void
    {
        $mail = $this->mailer();
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);
        $mail->send();
    }

    public function sendWelcome(
        string $email,
        string $name,
        string $practiceName,
        string $loginEmail,
        string $password,
        string $licenseToken
    ): void {
        $platformUrl = rtrim($this->config->get('platform.url', ''), '/');
        $loginUrl    = $platformUrl ? $platformUrl . '/login' : '/login';
        $mail   = $this->mailer();
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Willkommen bei TheraPano – Ihre Zugangsdaten';
        $mail->Body    = $this->welcomeHtml($name, $practiceName, $loginEmail, $password, $loginUrl);
        $mail->AltBody = "Willkommen {$name},\n\nIhre Praxis '{$practiceName}' wurde erfolgreich eingerichtet.\n\nLogin: {$loginEmail}\nPasswort: {$password}\nLogin-URL: {$loginUrl}\n\nBitte ändern Sie Ihr Passwort nach dem ersten Login.\n\nMit freundlichen Grüßen\nDas TheraPano-Team";
        $mail->send();
    }

    public function sendPasswordReset(string $email, string $name, string $resetUrl): void
    {
        $mail = $this->mailer();
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Passwort zurücksetzen – TheraPano';
        $mail->Body    = $this->resetHtml($name, $resetUrl);
        $mail->AltBody = "Hallo {$name},\n\nPasswort zurücksetzen: {$resetUrl}\n\nDieser Link ist 2 Stunden gültig.";
        $mail->send();
    }

    public function sendStatusNotification(string $email, string $name, string $status): void
    {
        $messages = [
            'suspended' => 'Ihr Konto wurde gesperrt. Bitte kontaktieren Sie den Support.',
            'cancelled' => 'Ihr Abonnement wurde gekündigt. Wir bedauern Ihren Abgang.',
            'active'    => 'Ihr Konto wurde reaktiviert. Willkommen zurück!',
        ];
        $mail = $this->mailer();
        $mail->addAddress($email, $name);
        $mail->Subject = 'Kontostatusänderung – TheraPano';
        $mail->Body    = '<p>Hallo ' . htmlspecialchars($name) . ',</p><p>' . ($messages[$status] ?? 'Ihr Kontostatus hat sich geändert.') . '</p>';
        $mail->AltBody = 'Hallo ' . $name . ', ' . ($messages[$status] ?? 'Ihr Kontostatus hat sich geändert.');
        $mail->send();
    }

    private function welcomeHtml(string $name, string $practice, string $email, string $password, string $appUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:8px;overflow:hidden}
.header{background:#2563eb;color:#fff;padding:30px;text-align:center}
.body{padding:30px}
.box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:20px;margin:20px 0}
.box p{margin:4px 0}
.footer{background:#f8fafc;padding:15px;text-align:center;font-size:12px;color:#64748b}
</style></head>
<body>
<div class="wrap">
  <div class="header"><h1>Willkommen bei Tierphysio Manager</h1></div>
  <div class="body">
    <p>Hallo <strong>{$name}</strong>,</p>
    <p>Ihre Praxis <strong>{$practice}</strong> wurde erfolgreich eingerichtet. Hier sind Ihre Zugangsdaten:</p>
    <div class="box">
      <p><strong>Login-E-Mail:</strong> {$email}</p>
      <p><strong>Passwort:</strong> {$password}</p>
      <p><strong>Login-URL:</strong> <a href="{$appUrl}">{$appUrl}</a></p>
    </div>
    <p><strong>Bitte ändern Sie Ihr Passwort nach dem ersten Login!</strong></p>
    <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>
    <p>Mit freundlichen Grüßen<br>Das Tierphysio Team</p>
  </div>
  <div class="footer">TheraPano &bull; DSGVO-konform &bull; EU-Hosting</div>
</div>
</body></html>
HTML;
    }

    private function resetHtml(string $name, string $resetUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.wrap{max-width:600px;margin:40px auto;background:#fff;border-radius:8px;overflow:hidden}
.header{background:#2563eb;color:#fff;padding:30px;text-align:center}
.body{padding:30px}
.btn{display:inline-block;background:#2563eb;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;margin:20px 0}
.footer{background:#f8fafc;padding:15px;text-align:center;font-size:12px;color:#64748b}
</style></head>
<body>
<div class="wrap">
  <div class="header"><h1>Passwort zurücksetzen</h1></div>
  <div class="body">
    <p>Hallo <strong>{$name}</strong>,</p>
    <p>Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts gestellt.</p>
    <a href="{$resetUrl}" class="btn">Passwort jetzt zurücksetzen</a>
    <p>Dieser Link ist <strong>2 Stunden</strong> gültig.</p>
    <p>Falls Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail.</p>
  </div>
  <div class="footer">TheraPano &bull; DSGVO-konform &bull; EU-Hosting</div>
</div>
</body></html>
HTML;
    }
}
