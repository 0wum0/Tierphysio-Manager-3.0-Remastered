<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private string $lastError = '';

    public function __construct(
        private readonly SettingsRepository $settingsRepository
    ) {}

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /* ══════════════════════════════════════════════════════════
       PUBLIC SEND METHODS
    ══════════════════════════════════════════════════════════ */

    public function sendInvoice(array $invoice, array $owner, string $pdfContent): bool
    {
        try {
            $placeholders = $this->buildInvoicePlaceholders($invoice, $owner);
            $subject = $this->applyPlaceholders(
                $this->settingsRepository->get('email_invoice_subject', 'Deine Rechnung {{invoice_number}}'),
                $placeholders
            );
            $bodyText = $this->applyPlaceholders(
                $this->settingsRepository->get('email_invoice_body',
                    "Hallo {{owner_name}},\n\nanbei erhältst du deine Rechnung {{invoice_number}} vom {{issue_date}}.\n\nGesamtbetrag: {{total_gross}}\nBitte überweise den Betrag bis zum {{due_date}}.\n\nLiebe Grüße\n{{company_name}}"
                ),
                $placeholders
            );

            $mailer = $this->createMailer();
            $mailer->addAddress($owner['email'], ($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '📄');
            $mailer->AltBody = $bodyText;
            $mailer->addStringAttachment($pdfContent, 'Rechnung-' . $invoice['invoice_number'] . '.pdf', PHPMailer::ENCODING_BASE64, 'application/pdf');

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendInvoice] ' . $e->getMessage());
            return false;
        }
    }

    public function sendReceipt(array $invoice, array $owner, string $pdfContent): bool
    {
        try {
            $placeholders = $this->buildInvoicePlaceholders($invoice, $owner);
            $subject = $this->applyPlaceholders(
                $this->settingsRepository->get('email_receipt_subject', 'Deine Quittung {{invoice_number}}'),
                $placeholders
            );
            $bodyText = $this->applyPlaceholders(
                $this->settingsRepository->get('email_receipt_body',
                    "Hallo {{owner_name}},\n\nvielen Dank für deine Zahlung. Anbei erhältst du deine Quittung für Rechnung {{invoice_number}} vom {{issue_date}}.\n\nBezahlter Betrag: {{total_gross}}\n\nLiebe Grüße\n{{company_name}}"
                ),
                $placeholders
            );

            $mailer = $this->createMailer();
            $mailer->addAddress($owner['email'], ($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '✅');
            $mailer->AltBody = $bodyText;
            $mailer->addStringAttachment($pdfContent, 'Quittung-' . $invoice['invoice_number'] . '.pdf', PHPMailer::ENCODING_BASE64, 'application/pdf');

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendReceipt] ' . $e->getMessage());
            return false;
        }
    }

    public function sendReminder(array $appointment): bool
    {
        try {
            $placeholders = $this->buildReminderPlaceholders($appointment);
            $subject = $this->applyPlaceholders(
                $this->settingsRepository->get('email_reminder_subject', 'Terminerinnerung: {{appointment_title}} am {{appointment_date}}'),
                $placeholders
            );
            $bodyText = $this->applyPlaceholders(
                $this->settingsRepository->get('email_reminder_body',
                    "Hallo {{owner_name}},\n\nhiermit möchte ich dich an deinen bevorstehenden Termin erinnern:\n\n📅 {{appointment_title}}\nDatum: {{appointment_date}}\nUhrzeit: {{appointment_time}}\n{{appointment_patient}}\n\nFalls du den Termin absagen oder verschieben möchtest, melde dich gerne bei mir.\n\nLiebe Grüße\n{{company_name}}"
                ),
                $placeholders
            );

            $mailer = $this->createMailer();
            $mailer->addAddress($appointment['owner_email'], trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? '')));
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '📅');
            $mailer->AltBody = $bodyText;

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendReminder] ' . $e->getMessage());
            return false;
        }
    }

    public function sendPatientReminder(array $appointment): bool
    {
        try {
            $placeholders = $this->buildReminderPlaceholders($appointment);
            $subject = $this->applyPlaceholders(
                $this->settingsRepository->get('email_patient_reminder_subject', 'Ihr Termin: {{appointment_title}} am {{appointment_date}}'),
                $placeholders
            );
            $bodyText = $this->applyPlaceholders(
                $this->settingsRepository->get('email_patient_reminder_body',
                    "Hallo,\n\nhiermit möchten wir Sie an Ihren bevorstehenden Termin erinnern:\n\n📅 {{appointment_title}}\nDatum: {{appointment_date}}\nUhrzeit: {{appointment_time}}\n{{appointment_patient}}\n\nFalls Sie den Termin absagen oder verschieben möchten, kontaktieren Sie uns bitte.\n\nLiebe Grüße\n{{company_name}}"
                ),
                $placeholders
            );

            $mailer = $this->createMailer();
            $mailer->addAddress($appointment['patient_email']);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '📅');
            $mailer->AltBody = $bodyText;

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendPatientReminder] ' . $e->getMessage());
            return false;
        }
    }

    public function sendInvoiceReminder(array $invoice, array $reminder, array $owner, string $pdfContent): bool
    {
        try {
            $placeholders = $this->buildInvoicePlaceholders($invoice, $owner);
            $dueDate = '';
            if (!empty($reminder['due_date'])) {
                try { $dueDate = (new \DateTime($reminder['due_date']))->format('d.m.Y'); } catch (\Throwable) { $dueDate = $reminder['due_date']; }
            }
            $placeholders['{{reminder_due_date}}'] = $dueDate;

            $subject = $this->applyPlaceholders(
                $this->settingsRepository->get('email_payment_reminder_subject', 'Zahlungserinnerung: Rechnung {{invoice_number}}'),
                $placeholders
            );
            $bodyText = $this->applyPlaceholders(
                $this->settingsRepository->get('email_payment_reminder_body',
                    "Hallo {{owner_name}},\n\nich möchte dich freundlich daran erinnern, dass die Rechnung {{invoice_number}} vom {{issue_date}} über {{total_gross}} noch aussteht.\n\nBitte überweise den Betrag bis zum {{reminder_due_date}} auf mein Konto.\n\nFalls du die Zahlung bereits veranlasst hast, bitte ich dich, dieses Schreiben als gegenstandslos zu betrachten.\n\nLiebe Grüße\n{{company_name}}"
                ),
                $placeholders
            );

            $mailer = $this->createMailer();
            $mailer->addAddress($owner['email'], trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? '')));
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '⏰');
            $mailer->AltBody = $bodyText;
            $mailer->addStringAttachment($pdfContent, 'Zahlungserinnerung-' . $invoice['invoice_number'] . '.pdf', PHPMailer::ENCODING_BASE64, 'application/pdf');

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendInvoiceReminder] ' . $e->getMessage());
            return false;
        }
    }

    public function sendInvoiceDunning(array $invoice, array $dunning, array $owner, string $pdfContent): bool
    {
        try {
            $placeholders = $this->buildInvoicePlaceholders($invoice, $owner);
            $level    = (int)($dunning['level'] ?? 1);
            $fee      = number_format((float)($dunning['fee'] ?? 0), 2, ',', '.') . ' €';
            $total    = number_format((float)($invoice['total_gross'] ?? 0) + (float)($dunning['fee'] ?? 0), 2, ',', '.') . ' €';
            $dueDate  = '';
            if (!empty($dunning['due_date'])) {
                try { $dueDate = (new \DateTime($dunning['due_date']))->format('d.m.Y'); } catch (\Throwable) { $dueDate = $dunning['due_date']; }
            }
            $placeholders['{{dunning_level}}']    = (string)$level;
            $placeholders['{{dunning_due_date}}'] = $dueDate;
            $placeholders['{{fee}}']              = $fee;
            $placeholders['{{total_with_fee}}']   = $total;

            $subject = $this->applyPlaceholders(
                $this->settingsRepository->get('email_dunning_subject', '{{dunning_level}}. Mahnung: Rechnung {{invoice_number}}'),
                $placeholders
            );
            $bodyText = $this->applyPlaceholders(
                $this->settingsRepository->get('email_dunning_body',
                    "Hallo {{owner_name}},\n\ntrotz meiner Zahlungserinnerung ist die Rechnung {{invoice_number}} vom {{issue_date}} über {{total_gross}} noch nicht beglichen worden.\n\nIch fordere dich hiermit auf, den ausstehenden Betrag zuzüglich einer Mahngebühr von {{fee}} bis zum {{dunning_due_date}} zu begleichen.\n\nGesamtbetrag: {{total_with_fee}}\n\nLiebe Grüße\n{{company_name}}"
                ),
                $placeholders
            );

            $levelMap  = [1 => '1. Mahnung', 2 => '2. Mahnung', 3 => 'Letzte Mahnung'];
            $levelLabel = $levelMap[$level] ?? 'Mahnung';
            $filename  = $levelLabel . '-' . $invoice['invoice_number'] . '.pdf';

            $mailer = $this->createMailer();
            $mailer->addAddress($owner['email'], trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? '')));
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '⚠️');
            $mailer->AltBody = $bodyText;
            $mailer->addStringAttachment($pdfContent, $filename, PHPMailer::ENCODING_BASE64, 'application/pdf');

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendInvoiceDunning] ' . $e->getMessage());
            return false;
        }
    }

    public function sendInvite(string $toEmail, string $inviteUrl, string $note = ''): bool
    {
        try {
            $companyName = $this->settingsRepository->get('company_name', 'Tierphysio Manager');
            $fromName    = $this->settingsRepository->get('mail_from_name', $companyName);

            $placeholders = [
                '{{invite_url}}'    => $inviteUrl,
                '{{note}}'          => $note,
                '{{company_name}}'  => $companyName,
                '{{from_name}}'     => $fromName,
            ];

            $subject = $this->applyPlaceholders(
                $this->settingsRepository->get('email_invite_subject', 'Deine Einladung zur Anmeldung — {{company_name}}'),
                $placeholders
            );
            $bodyText = $this->applyPlaceholders(
                $this->settingsRepository->get('email_invite_body',
                    "Du wurdest eingeladen!\n\n{{from_name}} lädt dich ein, dein Tier und dich als Besitzer direkt in meinem System zu registrieren.\n\n{{note}}\n\nJetzt registrieren:\n{{invite_url}}\n\nDieser Link ist 7 Tage gültig.\n\nLiebe Grüße\n{{company_name}}"
                ),
                $placeholders
            );

            $extraHtml = $inviteUrl ? '<div style="text-align:center;margin:28px 0;"><a href="' . htmlspecialchars($inviteUrl) . '" style="display:inline-block;background:linear-gradient(135deg,#4f7cff,#8b5cf6);color:#fff;text-decoration:none;padding:14px 36px;border-radius:100px;font-size:0.95rem;font-weight:700;">Jetzt registrieren →</a></div><p style="font-size:0.78rem;color:rgba(255,255,255,0.35);text-align:center;">Dieser Link ist 7 Tage gültig.</p>' : '';

            $mailer = $this->createMailer();
            $mailer->addAddress($toEmail);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '🐾', $extraHtml);
            $mailer->AltBody = $bodyText;

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendInvite] ' . $e->getMessage());
            return false;
        }
    }

    public function sendPasswordReset(string $toEmail, string $name, string $resetUrl): bool
    {
        try {
            $companyName = $this->settingsRepository->get('company_name', 'Tierphysio Manager');

            $bodyText = "Hallo {$name},\n\ndu hast eine Anfrage zum Zurücksetzen deines Passworts gestellt.\n\nKlicke auf den folgenden Link, um dein Passwort zurückzusetzen:\n{$resetUrl}\n\nDieser Link ist 2 Stunden gültig.\n\nFalls du diese Anfrage nicht gestellt hast, kannst du diese E-Mail ignorieren.\n\nLiebe Grüße\n{$companyName}";

            $extraHtml = '<div style="text-align:center;margin:28px 0;"><a href="' . htmlspecialchars($resetUrl) . '" style="display:inline-block;background:linear-gradient(135deg,#4f7cff,#8b5cf6);color:#fff;text-decoration:none;padding:14px 36px;border-radius:100px;font-size:0.95rem;font-weight:700;">Passwort zurücksetzen →</a></div><p style="font-size:0.78rem;color:rgba(255,255,255,0.35);text-align:center;">Dieser Link ist 2 Stunden gültig. Falls du diese Anfrage nicht gestellt hast, ignoriere diese E-Mail.</p>';

            $mailer = $this->createMailer();
            $mailer->addAddress($toEmail, $name);
            $mailer->Subject = 'Passwort zurücksetzen — ' . $companyName;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout('Passwort zurücksetzen', $bodyText, '🔑', $extraHtml);
            $mailer->AltBody = $bodyText;

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendPasswordReset] ' . $e->getMessage());
            return false;
        }
    }

    public function testConnection(array $config, string $toEmail): bool
    {
        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host       = $config['smtp_host'] ?? '';
            $mailer->Port       = (int)($config['smtp_port'] ?? 587);
            $mailer->Username   = $config['smtp_username'] ?? '';
            $mailer->Password   = $config['smtp_password'] ?? '';
            $mailer->SMTPAuth   = !empty($mailer->Username);
            $mailer->Timeout    = 10;
            
            $enc = $config['smtp_encryption'] ?? 'tls';
            if ($enc === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'none') {
                $mailer->SMTPSecure  = '';
                $mailer->SMTPAutoTLS = false;
            } else {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mailer->setFrom($config['mail_from_address'] ?? 'test@example.com', $config['mail_from_name'] ?? 'SMTP Test');
            $mailer->addAddress($toEmail);
            $mailer->Subject = '🚀 SMTP Verbindungstest — TheraPano';
            $mailer->isHTML(true);
            
            $body = "<h3>SMTP Test erfolgreich!</h3><p>Diese Nachricht bestätigt, dass die E-Mail-Einstellungen in TheraPano korrekt konfiguriert sind.</p><hr><p>Zeitpunkt: " . date('d.m.Y H:i:s') . "</p>";
            $mailer->Body = $this->wrapInEmailLayout('SMTP Test Erfolgreich', $body, '🚀');
            
            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function sendRaw(string $to, string $toName, string $subject, string $body, array $attachments = []): bool
    {
        try {
            $mailer = $this->createMailer();
            $mailer->addAddress($to, $toName);
            $mailer->Subject = $subject;
            $mailer->Body    = $body;
            $mailer->isHTML(true);

            foreach ($attachments as $attachment) {
                if (isset($attachment['content'], $attachment['name'])) {
                    $mailer->addStringAttachment(
                        $attachment['content'],
                        $attachment['name'],
                        PHPMailer::ENCODING_BASE64,
                        $attachment['mime'] ?? 'application/octet-stream'
                    );
                }
            }

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendRaw] ' . $e->getMessage());
            return false;
        }
    }

    /* ══════════════════════════════════════════════════════════
       CENTRAL HTML EMAIL LAYOUT
       All mails share the same visual wrapper. Only the icon,
       title (= subject) and content block differ.
    ══════════════════════════════════════════════════════════ */

    public function wrapInEmailLayout(string $title, string $bodyText, string $icon = '🐾', string $extraHtml = ''): string
    {
        $company    = htmlspecialchars($this->settingsRepository->get('company_name', 'Tierphysio Manager'));
        $titleHtml  = htmlspecialchars($title);
        $contentHtml = nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8'));

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{$titleHtml}</title>
</head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f1a;padding:32px 16px;">
  <tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:16px;overflow:hidden;">

      <!-- HEADER -->
      <tr><td style="background:linear-gradient(135deg,rgba(79,124,255,0.35),rgba(139,92,246,0.35));padding:32px 40px;text-align:center;">
        <div style="font-size:2.2rem;margin-bottom:10px;">{$icon}</div>
        <h1 style="margin:0;color:#ffffff;font-size:1.35rem;font-weight:700;line-height:1.3;">{$titleHtml}</h1>
        <p style="margin:6px 0 0;color:rgba(255,255,255,0.55);font-size:0.8rem;">{$company}</p>
      </td></tr>

      <!-- BODY -->
      <tr><td style="padding:36px 40px;">
        <div style="color:rgba(255,255,255,0.82);font-size:0.95rem;line-height:1.8;">
          {$contentHtml}
        </div>
        {$extraHtml}
      </td></tr>

      <!-- FOOTER -->
      <tr><td style="padding:16px 40px 24px;text-align:center;border-top:1px solid rgba(255,255,255,0.08);">
        <p style="margin:0;color:rgba(255,255,255,0.25);font-size:0.75rem;">{$company} &middot; Automatisch generierte Nachricht</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    /* ══════════════════════════════════════════════════════════
       PLACEHOLDER BUILDERS
    ══════════════════════════════════════════════════════════ */

    private function buildInvoicePlaceholders(array $invoice, array $owner): array
    {
        $companyName = $this->settingsRepository->get('company_name', 'Tierphysio Praxis');
        $ownerName   = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));

        $issueDate = '';
        if (!empty($invoice['issue_date'])) {
            try { $issueDate = (new \DateTime($invoice['issue_date']))->format('d.m.Y'); } catch (\Throwable) { $issueDate = $invoice['issue_date']; }
        }
        $dueDate = '';
        if (!empty($invoice['due_date'])) {
            try { $dueDate = (new \DateTime($invoice['due_date']))->format('d.m.Y'); } catch (\Throwable) { $dueDate = $invoice['due_date']; }
        }

        $gross = number_format((float)($invoice['total_gross'] ?? 0), 2, ',', '.') . ' €';

        return [
            '{{invoice_number}}' => $invoice['invoice_number'] ?? '',
            '{{owner_name}}'     => $ownerName,
            '{{owner_first}}'    => $owner['first_name'] ?? '',
            '{{owner_last}}'     => $owner['last_name'] ?? '',
            '{{owner_email}}'    => $owner['email'] ?? '',
            '{{issue_date}}'     => $issueDate,
            '{{due_date}}'       => $dueDate,
            '{{total_gross}}'    => $gross,
            '{{company_name}}'   => $companyName,
        ];
    }

    private function buildReminderPlaceholders(array $a): array
    {
        $company  = $this->settingsRepository->get('company_name', 'Tierphysio Praxis');
        $ownerName = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
        $date     = !empty($a['start_at']) ? date('d.m.Y', strtotime($a['start_at'])) : '';
        $timeFrom = !empty($a['start_at']) ? date('H:i', strtotime($a['start_at'])) : '';
        $timeTo   = !empty($a['end_at'])   ? date('H:i', strtotime($a['end_at']))   : '';
        $time     = $timeFrom . ($timeTo ? ' – ' . $timeTo : '') . ' Uhr';
        $patient  = !empty($a['patient_name']) ? '🐾 Patient: ' . $a['patient_name'] : '';

        return [
            '{{owner_name}}'          => $ownerName,
            '{{owner_first}}'         => $a['first_name'] ?? '',
            '{{appointment_title}}'   => $a['title'] ?? '',
            '{{appointment_date}}'    => $date,
            '{{appointment_time}}'    => $time,
            '{{appointment_patient}}' => $patient,
            '{{appointment_note}}'    => $a['description'] ?? '',
            '{{company_name}}'        => $company,
            '{{company_phone}}'       => $this->settingsRepository->get('company_phone', ''),
            '{{company_email}}'       => $this->settingsRepository->get('company_email', ''),
        ];
    }

    private function applyPlaceholders(string $template, array $placeholders): string
    {
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    public function sendHomeworkNotification(array $patient, array $owner, string $planTitle, string $portalUrl): bool
    {
        try {
            $companyName = $this->settingsRepository->get('company_name', 'Tierphysio Praxis');
            $ownerName   = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
            $patientName = $patient['name'] ?? '';

            $subject  = "Neue Übungen für {$patientName} – {$companyName}";
            $bodyText = "Hallo {$ownerName},\n\n"
                      . "für {$patientName} wurden neue Übungen/Hausaufgaben erstellt: **{$planTitle}**\n\n"
                      . "Du kannst diese direkt in deinem Besitzerportal einsehen und herunterladen:\n"
                      . "{$portalUrl}\n\n"
                      . "Viele Grüße\n{$companyName}";

            $htmlBody = "<p>Hallo {$ownerName},</p>"
                      . "<p>für <strong>{$patientName}</strong> wurden neue Übungen/Hausaufgaben erstellt: <strong>" . htmlspecialchars($planTitle) . "</strong></p>"
                      . "<p><a href=\"{$portalUrl}\" style=\"display:inline-block;padding:12px 24px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;\">📋 Zum Besitzerportal</a></p>"
                      . "<p style=\"font-size:12px;color:#666;\">Oder kopiere diesen Link: <a href=\"{$portalUrl}\">{$portalUrl}</a></p>"
                      . "<p>Viele Grüße<br>{$companyName}</p>";

            $mailer = $this->createMailer();
            $mailer->addAddress($owner['email'], $ownerName);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $htmlBody, '📋');
            $mailer->AltBody = $bodyText;

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendHomeworkNotification] ' . $e->getMessage());
            return false;
        }
    }

    public function sendBefundbogen(array $befundbogen, array $owner, string $pdfContent): bool
    {
        try {
            $companyName = $this->settingsRepository->get('company_name', 'Tierphysio Praxis');
            $ownerName   = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
            $patName     = $befundbogen['patient_name'] ?? 'Ihr Tier';
            $datumStr    = !empty($befundbogen['datum'])
                ? (new \DateTime($befundbogen['datum']))->format('d.m.Y')
                : date('d.m.Y');

            $subject  = "Befundbogen für {$patName} vom {$datumStr} – {$companyName}";
            $bodyText = "Hallo {$ownerName},\n\n"
                      . "anbei erhalten Sie den Befundbogen für {$patName} vom {$datumStr}.\n\n"
                      . "Bei Fragen stehen wir Ihnen gerne zur Verfügung.\n\n"
                      . "Viele Grüße\n{$companyName}";

            $filename = 'Befundbogen-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $patName) . '-' . date('Ymd', strtotime($befundbogen['datum'])) . '.pdf';

            $mailer = $this->createMailer();
            $mailer->addAddress($owner['email'], $ownerName);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '🐾');
            $mailer->AltBody = $bodyText;
            $mailer->addStringAttachment($pdfContent, $filename, PHPMailer::ENCODING_BASE64, 'application/pdf');

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendBefundbogen] ' . $e->getMessage());
            return false;
        }
    }

    public function sendHomework(array $patient, array $owner, string $pdfContent): bool
    {
        try {
            $companyName = $this->settingsRepository->get('company_name', 'Tierphysio Praxis');
            $ownerName   = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
            $patientName = $patient['name'] ?? '';

            $subject = $this->settingsRepository->get(
                'email_homework_subject',
                'Hausaufgaben für ' . $patientName
            );
            $bodyText = $this->settingsRepository->get(
                'email_homework_body',
                "Hallo {$ownerName},\n\nanbei erhältst du die Hausaufgaben für {$patientName}.\n\nBitte führe die Übungen regelmäßig durch.\n\nViele Grüße\n{$companyName}"
            );

            $filename = 'Hausaufgaben-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $patientName) . '.pdf';

            $mailer = $this->createMailer();
            $mailer->addAddress($owner['email'], $ownerName);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '📋');
            $mailer->AltBody = $bodyText;
            $mailer->addStringAttachment($pdfContent, $filename, PHPMailer::ENCODING_BASE64, 'application/pdf');

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendHomework] ' . $e->getMessage());
            return false;
        }
    }

    public function sendVetReport(array $patient, array $owner, string $pdfContent, string $filename): bool
    {
        try {
            $patName   = $patient['name'] ?? 'Ihrem Tier';
            $ownerName = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
            $company   = $this->settingsRepository->get('company_name', 'Ihre Tierphysiotherapie');
            $subject   = 'Tierarztbericht für ' . $patName;
            $bodyText  = "Hallo " . $ownerName . ",\n\nanbei erhalten Sie den Tierarztbericht für " . $patName . ".\n\nBei Fragen stehen wir Ihnen gerne zur Verfügung.\n\nMit freundlichen Grüßen\n" . $company;

            $mailer = $this->createMailer();
            $mailer->addAddress($owner['email'], $ownerName);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '🐾');
            $mailer->AltBody = $bodyText;
            $mailer->addStringAttachment($pdfContent, $filename, PHPMailer::ENCODING_BASE64, 'application/pdf');

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendVetReport] ' . $e->getMessage());
            return false;
        }
    }

    /* ══════════════════════════════════════════════════════════
       TENANT-TYPE HELPERS
       Alle öffentlichen Mail-Funktionen unten nutzen diese Helfer,
       damit die Texte je nach Praxis-Modus (Tierphysio vs Hunde-
       schule/Trainer) korrekt getont sind. So vermeiden wir
       Vermischungen wie „Ihre Behandlung" in einer Hundeschul-Mail.
    ══════════════════════════════════════════════════════════ */

    private function isTrainerTenant(): bool
    {
        return (string)$this->settingsRepository->get('practice_type', 'therapeut') === 'trainer';
    }

    /**
     * Setup-Metadaten für eine Kurs-/Buchungs-Mail. Liefert
     * gemeinsame Strings (Unternehmen, Anrede für Tier vs Patient,
     * Trainer vs Therapeut) je nach Tenant-Type.
     */
    private function tenantMailCtx(): array
    {
        $isTrainer = $this->isTrainerTenant();
        return [
            'is_trainer'    => $isTrainer,
            'company'       => $this->settingsRepository->get('company_name', $isTrainer ? 'Hundeschule' : 'Tierphysio Praxis'),
            'team_label'    => $isTrainer ? 'Trainer-Team' : 'Praxis-Team',
            'animal_label'  => $isTrainer ? 'Hund' : 'Tier',
            'session_label' => $isTrainer ? 'Training' : 'Termin',
            'icon'          => $isTrainer ? '🐾' : '🐾',
        ];
    }

    /* ══════════════════════════════════════════════════════════
       KURS- UND BUCHUNGS-MAILS (tenant-type-aware)
    ══════════════════════════════════════════════════════════ */

    /**
     * Eingangsbestätigung für eine /buchung-Anfrage (öffentliches Portal).
     * Wird direkt nach dem Absenden des Formulars verschickt.
     *
     * @param string      $email     Empfänger
     * @param string      $firstName Vorname des Anfragers
     * @param string|null $dogName   Name des Hundes (optional)
     * @param string|null $subject2  Betreff der Anfrage (z.B. Probetraining, Kursname)
     */
    public function sendBookingRequestConfirmation(
        string $email,
        string $firstName,
        ?string $dogName = null,
        ?string $requestSubject = null
    ): bool {
        try {
            $ctx     = $this->tenantMailCtx();
            $company = $ctx['company'];
            $name    = trim($firstName) !== '' ? trim($firstName) : ($ctx['is_trainer'] ? 'Halter' : 'Tierhalter');
            $dogLine = $dogName ? "\n🐕 Hund: {$dogName}" : '';
            $reqLine = $requestSubject ? "\n📌 Anliegen: {$requestSubject}" : '';

            if ($ctx['is_trainer']) {
                $subject  = "Wir haben deine Anfrage erhalten – {$company}";
                $bodyText = "Hallo {$name},\n\n"
                          . "vielen Dank für deine Anfrage bei {$company}. "
                          . "Wir haben deine Nachricht erhalten und melden uns innerhalb von 2 Werktagen bei dir zurück."
                          . $dogLine . $reqLine . "\n\n"
                          . "Bitte beachte: Diese Mail ist eine reine Eingangsbestätigung. "
                          . "Dein Trainingsplatz ist erst nach unserer Rückmeldung verbindlich reserviert.\n\n"
                          . "Bis bald\nDein {$ctx['team_label']} von {$company}";
            } else {
                $subject  = "Wir haben Ihre Anfrage erhalten – {$company}";
                $bodyText = "Hallo {$name},\n\n"
                          . "vielen Dank für Ihre Anfrage bei {$company}. "
                          . "Wir haben Ihre Nachricht erhalten und melden uns innerhalb von 2 Werktagen bei Ihnen zurück."
                          . $dogLine . $reqLine . "\n\n"
                          . "Bitte beachten Sie: Diese Mail ist eine reine Eingangsbestätigung. "
                          . "Ihr Termin ist erst nach unserer Rückmeldung verbindlich reserviert.\n\n"
                          . "Herzliche Grüße\nIhr {$ctx['team_label']} von {$company}";
            }

            $mailer = $this->createMailer();
            $mailer->addAddress($email, $name);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '✅');
            $mailer->AltBody = $bodyText;
            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendBookingRequestConfirmation] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Bestätigung einer Kurs-Einschreibung (sofort nach dem Enroll).
     * @param array $enrollment Row mit owner_*, patient_name, course_name, start_date, start_time, location
     */
    public function sendCourseEnrollmentConfirmation(array $enrollment): bool
    {
        try {
            $email = (string)($enrollment['owner_email'] ?? '');
            if ($email === '') return false;

            $ctx        = $this->tenantMailCtx();
            $company    = $ctx['company'];
            $firstName  = trim((string)($enrollment['owner_first_name'] ?? '')) ?: ($ctx['is_trainer'] ? 'Halter' : 'Tierhalter');
            $dogName    = (string)($enrollment['patient_name'] ?? '');
            $courseName = (string)($enrollment['course_name'] ?? 'unser Kurs');
            $startDate  = !empty($enrollment['start_date']) ? date('d.m.Y', strtotime((string)$enrollment['start_date'])) : '';
            $startTime  = !empty($enrollment['start_time']) ? substr((string)$enrollment['start_time'], 0, 5) . ' Uhr' : '';
            $location   = (string)($enrollment['location'] ?? '');

            $dateLine = $startDate ? "\n📅 Start: {$startDate}" . ($startTime ? ' um ' . $startTime : '') : '';
            $locLine  = $location !== '' ? "\n📍 Ort: {$location}" : '';
            $dogLine  = $dogName !== '' ? "\n🐕 {$ctx['animal_label']}: {$dogName}" : '';

            if ($ctx['is_trainer']) {
                $subject  = "Anmeldung bestätigt: {$courseName} – {$company}";
                $bodyText = "Hallo {$firstName},\n\n"
                          . "super, dass du dabei bist! Wir haben deine Anmeldung für den Kurs "
                          . "**{$courseName}** erhalten und bestätigt."
                          . $dogLine . $dateLine . $locLine . "\n\n"
                          . "Du bekommst 24 Stunden vor Kursbeginn eine automatische Erinnerungsmail "
                          . "mit allen Details. Solltest du einmal nicht kommen können, sag uns "
                          . "bitte rechtzeitig Bescheid.\n\n"
                          . "Wir freuen uns auf euch!\nDein {$ctx['team_label']} von {$company}";
            } else {
                $subject  = "Anmeldung bestätigt: {$courseName} – {$company}";
                $bodyText = "Hallo {$firstName},\n\n"
                          . "vielen Dank für Ihre Anmeldung zum Kurs **{$courseName}**. "
                          . "Wir haben Ihre Anmeldung erhalten und bestätigt."
                          . $dogLine . $dateLine . $locLine . "\n\n"
                          . "Sie erhalten 24 Stunden vor Kursbeginn eine automatische Erinnerung "
                          . "mit allen Details. Bei Fragen melden Sie sich gerne.\n\n"
                          . "Herzliche Grüße\nIhr {$ctx['team_label']} von {$company}";
            }

            $mailer = $this->createMailer();
            $mailer->addAddress($email, trim($firstName . ' ' . ($enrollment['owner_last_name'] ?? '')));
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '🎉');
            $mailer->AltBody = $bodyText;
            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendCourseEnrollmentConfirmation] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 24-Stunden-Erinnerung vor Kursbeginn. Wird vom Cron-Job aufgerufen.
     * Identischer Row-Shape wie sendCourseEnrollmentConfirmation.
     */
    public function sendCourseReminder(array $enrollment): bool
    {
        try {
            $email = (string)($enrollment['owner_email'] ?? '');
            if ($email === '') return false;

            $ctx        = $this->tenantMailCtx();
            $company    = $ctx['company'];
            $firstName  = trim((string)($enrollment['owner_first_name'] ?? '')) ?: ($ctx['is_trainer'] ? 'Halter' : 'Tierhalter');
            $dogName    = (string)($enrollment['patient_name'] ?? '');
            $courseName = (string)($enrollment['course_name'] ?? 'dein Kurs');
            $startDate  = !empty($enrollment['start_date']) ? date('d.m.Y', strtotime((string)$enrollment['start_date'])) : '';
            $startTime  = !empty($enrollment['start_time']) ? substr((string)$enrollment['start_time'], 0, 5) . ' Uhr' : '';
            $location   = (string)($enrollment['location'] ?? '');

            $dateLine = $startDate ? "\n📅 Termin: {$startDate}" . ($startTime ? ' um ' . $startTime : '') : '';
            $locLine  = $location !== '' ? "\n📍 Ort: {$location}" : '';
            $dogLine  = $dogName !== '' ? "\n🐕 {$ctx['animal_label']}: {$dogName}" : '';

            if ($ctx['is_trainer']) {
                $subject  = "Erinnerung: {$courseName} morgen – {$company}";
                $bodyText = "Hallo {$firstName},\n\n"
                          . "eine kleine Erinnerung: Morgen startet dein Kurs **{$courseName}**."
                          . $dogLine . $dateLine . $locLine . "\n\n"
                          . "Denk bitte an:\n"
                          . "• Leckerlis (klein & weich)\n"
                          . "• Schleppleine / normale Leine\n"
                          . "• Impfpass beim ersten Termin\n"
                          . "• Wasser & Handtuch\n\n"
                          . "Solltest du kurzfristig nicht kommen können, sag uns bitte Bescheid.\n\n"
                          . "Wir freuen uns auf euch!\nDein {$ctx['team_label']} von {$company}";
            } else {
                $subject  = "Erinnerung: {$courseName} morgen – {$company}";
                $bodyText = "Hallo {$firstName},\n\n"
                          . "wir möchten Sie an den morgigen Termin erinnern: **{$courseName}**."
                          . $dogLine . $dateLine . $locLine . "\n\n"
                          . "Sollten Sie kurzfristig nicht kommen können, geben Sie uns bitte rechtzeitig Bescheid.\n\n"
                          . "Herzliche Grüße\nIhr {$ctx['team_label']} von {$company}";
            }

            $mailer = $this->createMailer();
            $mailer->addAddress($email, trim($firstName . ' ' . ($enrollment['owner_last_name'] ?? '')));
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $this->wrapInEmailLayout($subject, $bodyText, '⏰');
            $mailer->AltBody = $bodyText;
            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendCourseReminder] ' . $e->getMessage());
            return false;
        }
    }

    /* ══════════════════════════════════════════════════════════
       MAILER FACTORY
    ══════════════════════════════════════════════════════════ */

    private function createMailer(): PHPMailer
    {
        $mailer      = new PHPMailer(true);
        $smtpHost    = trim($this->settingsRepository->get('smtp_host', ''));
        $fromAddress = $this->settingsRepository->get('mail_from_address', 'noreply@tierphysio.local');
        $fromName    = $this->settingsRepository->get('mail_from_name', 'Tierphysio Manager');

        if ($smtpHost !== '') {
            /* ── SMTP mode ── */
            $mailer->isSMTP();
            $mailer->Host          = $smtpHost;
            $mailer->Port          = (int)$this->settingsRepository->get('smtp_port', '587');
            $mailer->Username      = $this->settingsRepository->get('smtp_username', '');
            $mailer->Password      = $this->settingsRepository->get('smtp_password', '');
            $mailer->SMTPAuth      = !empty($mailer->Username);
            $mailer->Timeout       = 10;
            $mailer->SMTPKeepAlive = false;
            $enc = $this->settingsRepository->get('smtp_encryption', 'tls');
            if ($enc === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'none') {
                $mailer->SMTPSecure  = '';
                $mailer->SMTPAutoTLS = false;
            } else {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        } else {
            /* ── PHP mail() fallback (no SMTP configured) ── */
            $mailer->isMail();
        }

        $mailer->setFrom($fromAddress, $fromName);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        return $mailer;
    }
}
