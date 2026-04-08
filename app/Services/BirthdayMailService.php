<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\SettingsRepository;
use PHPMailer\PHPMailer\PHPMailer;

class BirthdayMailService
{
    public function __construct(
        private readonly Database            $db,
        private readonly SettingsRepository  $settings,
        private readonly MailService         $mailService
    ) {}

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    /* ═══════════════════════════════════════════════════════════
       PUBLIC: run daily birthday check & send
    ═══════════════════════════════════════════════════════════ */

    public function runDailyCheck(): array
    {
        $log = ['sent' => 0, 'skipped' => 0, 'errors' => []];

        if ($this->settings->get('birthday_mail_enabled', '0') !== '1') {
            $log['errors'][] = 'Geburtstagsmail deaktiviert in Einstellungen.';
            return $log;
        }

        $today = date('m-d');   // month-day for birth_date comparison
        $year  = (int)date('Y');

        /* Patients with birthday today that have an owner with an email */
        $patients = $this->db->fetchAll(
            "SELECT
                p.id          AS patient_id,
                p.name        AS patient_name,
                p.species,
                p.breed,
                p.birth_date,
                p.photo,
                o.id          AS owner_id,
                o.first_name,
                o.last_name,
                o.email
             FROM `{$this->t('patients')}` p
             JOIN `{$this->t('owners')}` o ON o.id = p.owner_id
             WHERE DATE_FORMAT(p.birth_date, '%m-%d') = ?
               AND p.birth_date IS NOT NULL
               AND o.email IS NOT NULL
               AND o.email != ''
               AND p.status != 'verstorben'",
            [$today]
        );

        foreach ($patients as $p) {
            /* Skip if already sent this year */
            if ($this->alreadySentThisYear((int)$p['patient_id'], $year)) {
                $log['skipped']++;
                continue;
            }

            $age = $this->calculateAge($p['birth_date']);
            $ok  = $this->sendBirthdayMail($p, $age);

            if ($ok) {
                $this->markSent((int)$p['patient_id'], $year);
                $log['sent']++;
            } else {
                $log['errors'][] = "Fehler bei {$p['patient_name']} → {$p['email']}: " . $this->mailService->getLastError();
            }
        }

        return $log;
    }

    /* ═══════════════════════════════════════════════════════════
       SEND ONE BIRTHDAY MAIL
    ═══════════════════════════════════════════════════════════ */

    private function sendBirthdayMail(array $p, int $age): bool
    {
        $company     = $this->settings->get('company_name', 'Tierphysio Manager');
        $ownerFirst  = $p['first_name'] ?? '';
        $ownerName   = trim($ownerFirst . ' ' . ($p['last_name'] ?? ''));
        $patientName = $p['patient_name'] ?? 'Ihr Tier';
        $species     = $p['species'] ?? '';
        $breed       = $p['breed'] ?? '';

        $subjectTpl = $this->settings->get(
            'birthday_mail_subject',
            '🎂 Alles Gute zum Geburtstag, {{patient_name}}!'
        );
        $subject = str_replace(
            ['{{patient_name}}', '{{owner_name}}', '{{age}}', '{{company_name}}'],
            [$patientName, $ownerName, (string)$age, $company],
            $subjectTpl
        );

        $html = $this->buildBirthdayHtml($p, $age, $ownerFirst, $ownerName, $company);

        $plainText = $this->buildPlainText($p, $age, $ownerFirst, $company);

        try {
            $mailer = $this->createMailer();
            $mailer->addAddress($p['email'], $ownerName);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $html;
            $mailer->AltBody = $plainText;
            return $mailer->send();
        } catch (\Throwable $e) {
            error_log('[BirthdayMailService] ' . $e->getMessage());
            return false;
        }
    }

    /* ═══════════════════════════════════════════════════════════
       BEAUTIFUL HTML EMAIL
    ═══════════════════════════════════════════════════════════ */

    private function buildBirthdayHtml(array $p, int $age, string $ownerFirst, string $ownerName, string $company): string
    {
        $patientName = htmlspecialchars($p['patient_name'] ?? 'Ihr Tier');
        $ownerFirstH = htmlspecialchars($ownerFirst ?: $ownerName);
        $companyH    = htmlspecialchars($company);
        $speciesH    = htmlspecialchars($p['species'] ?? '');
        $breedH      = htmlspecialchars($p['breed'] ?? '');
        $ageLabel    = $age > 0 ? ($age === 1 ? '1 Jahr' : "{$age} Jahre") : '';

        /* Pick a fun emoji per species */
        $speciesLower = mb_strtolower($p['species'] ?? '');
        $animalEmoji  = match(true) {
            str_contains($speciesLower, 'hund')  => '🐕',
            str_contains($speciesLower, 'katze') => '🐈',
            str_contains($speciesLower, 'pferd') => '🐴',
            str_contains($speciesLower, 'hase')  => '🐰',
            str_contains($speciesLower, 'vogel') => '🦜',
            str_contains($speciesLower, 'fisch') => '🐟',
            str_contains($speciesLower, 'hamster') => '🐹',
            str_contains($speciesLower, 'maus')  => '🐭',
            str_contains($speciesLower, 'schil') => '🐢',
            default => '🐾',
        };

        $ageBadge = $ageLabel
            ? "<span style=\"display:inline-block;background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.3);border-radius:100px;padding:4px 18px;font-size:0.85rem;font-weight:700;color:#fff;margin-top:10px;\">{$ageLabel} alt {$animalEmoji}</span>"
            : '';

        $speciesBreed = trim(implode(' · ', array_filter([$speciesH, $breedH])));
        $speciesLine  = $speciesBreed
            ? "<p style=\"margin:4px 0 0;color:rgba(255,255,255,0.55);font-size:0.78rem;\">{$speciesBreed}</p>"
            : '';

        $balloons = '🎈🎂🎉🎁🥳🎈';

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Alles Gute zum Geburtstag, {$patientName}!</title>
</head>
<body style="margin:0;padding:0;background:#0a0a1a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background:linear-gradient(180deg,#0a0a1a 0%,#12003a 100%);padding:40px 16px;min-height:100vh;">
<tr><td align="center">

  <table role="presentation" width="600" cellpadding="0" cellspacing="0"
         style="max-width:600px;width:100%;border-radius:24px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,0.7);">

    <!-- ░░ HERO BANNER ░░ -->
    <tr><td style="background:linear-gradient(135deg,#7c2ef8 0%,#e040fb 40%,#ff6d94 75%,#ffb347 100%);padding:52px 40px 44px;text-align:center;position:relative;">
      <div style="font-size:3.5rem;line-height:1;margin-bottom:16px;filter:drop-shadow(0 4px 12px rgba(0,0,0,0.3));">{$balloons}</div>
      <h1 style="margin:0 0 6px;color:#ffffff;font-size:2rem;font-weight:800;letter-spacing:-0.5px;text-shadow:0 2px 8px rgba(0,0,0,0.3);">
        Alles Gute zum Geburtstag,<br><span style="color:#fff7b0;">{$patientName}!</span>
      </h1>
      {$ageBadge}
      {$speciesLine}
    </td></tr>

    <!-- ░░ KONFETTI DIVIDER ░░ -->
    <tr><td style="background:linear-gradient(135deg,#1a0040,#2d0060);padding:0;">
      <div style="height:6px;background:linear-gradient(90deg,#7c2ef8,#e040fb,#ff6d94,#ffb347,#7c2ef8);"></div>
    </td></tr>

    <!-- ░░ MAIN BODY ░░ -->
    <tr><td style="background:linear-gradient(180deg,#1a0040 0%,#150030 100%);padding:44px 44px 36px;">

      <!-- Greeting -->
      <p style="margin:0 0 24px;color:rgba(255,255,255,0.9);font-size:1.05rem;line-height:1.7;">
        Liebe/r <strong style="color:#e8b4ff;">{$ownerFirstH}</strong>,
      </p>

      <!-- Big message card -->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,200,255,0.2);border-radius:16px;padding:28px 32px;margin-bottom:28px;">
          <p style="margin:0 0 14px;font-size:1.5rem;font-weight:800;color:#fff;text-align:center;line-height:1.3;">
            {$animalEmoji} {$patientName} hat heute Geburtstag! {$animalEmoji}
          </p>
          <p style="margin:0;color:rgba(255,255,255,0.72);font-size:0.95rem;line-height:1.8;text-align:center;">
            Heute ist ein ganz besonderer Tag — denn heute dreht sich alles um <strong style="color:#f0c0ff;">{$patientName}</strong>!
            Ich von <strong style="color:#c8a0ff;">{$companyH}</strong> drücke ganz fest die Pfoten und wünsche
            dem kleinen Geburtstagskind alles Liebe, viel Gesundheit und jede Menge Freude! 🥳
          </p>
        </td></tr>
      </table>

      <div style="height:24px;"></div>

      <!-- Fun facts / wishes row -->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
        <tr>
          <td width="33%" style="padding:0 6px 0 0;vertical-align:top;">
            <div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,180,255,0.15);border-radius:12px;padding:20px 16px;text-align:center;">
              <div style="font-size:2rem;margin-bottom:8px;">🏥</div>
              <div style="color:rgba(255,255,255,0.9);font-size:0.8rem;font-weight:700;margin-bottom:4px;">Gesundheit</div>
              <div style="color:rgba(255,255,255,0.5);font-size:0.72rem;line-height:1.5;">Immer fit und voller Energie!</div>
            </div>
          </td>
          <td width="33%" style="padding:0 3px;vertical-align:top;">
            <div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,180,255,0.15);border-radius:12px;padding:20px 16px;text-align:center;">
              <div style="font-size:2rem;margin-bottom:8px;">🍖</div>
              <div style="color:rgba(255,255,255,0.9);font-size:0.8rem;font-weight:700;margin-bottom:4px;">Leckereien</div>
              <div style="color:rgba(255,255,255,0.5);font-size:0.72rem;line-height:1.5;">Der Tag gehört ganz dem Geburtstagskind!</div>
            </div>
          </td>
          <td width="33%" style="padding:0 0 0 6px;vertical-align:top;">
            <div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,180,255,0.15);border-radius:12px;padding:20px 16px;text-align:center;">
              <div style="font-size:2rem;margin-bottom:8px;">🌟</div>
              <div style="color:rgba(255,255,255,0.9);font-size:0.8rem;font-weight:700;margin-bottom:4px;">Liebe</div>
              <div style="color:rgba(255,255,255,0.5);font-size:0.72rem;line-height:1.5;">Ganz viele Kuscheleinheiten!</div>
            </div>
          </td>
        </tr>
      </table>

      <!-- Personal message from practice -->
      <div style="background:linear-gradient(135deg,rgba(124,46,248,0.2),rgba(224,64,251,0.15));border:1px solid rgba(200,150,255,0.25);border-radius:14px;padding:24px 28px;margin-bottom:28px;">
        <p style="margin:0 0 10px;color:#e8b4ff;font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;">Von deiner Tierphysio 🐾</p>
        <p style="margin:0;color:rgba(255,255,255,0.80);font-size:0.92rem;line-height:1.75;font-style:italic;">
          „Ich freue mich, {$patientName} auf dem Weg zu Gesundheit und Wohlbefinden zu begleiten.
          Heute feiere ich mit dir diesen besonderen Tag! Herzlichen Glückwunsch an
          das Geburtstagskind und an dich als stolze/n Besitzer/in.“ 🎉
        </p>
      </div>

      <!-- Closing -->
      <p style="margin:0 0 8px;color:rgba(255,255,255,0.80);font-size:0.95rem;line-height:1.7;">
        Mit herzlichen Geburtstagsgrüßen,
      </p>
      <p style="margin:0;color:#c8a0ff;font-size:1rem;font-weight:700;">{$companyH} 🐾</p>

    </td></tr>

    <!-- ░░ FOOTER ░░ -->
    <tr><td style="background:#0a0010;padding:20px 44px 28px;text-align:center;border-top:1px solid rgba(255,255,255,0.06);">
      <p style="margin:0 0 4px;color:rgba(255,255,255,0.22);font-size:0.72rem;">
        {$companyH} &middot; Automatisch generierte Geburtstagsmail
      </p>
      <p style="margin:0;color:rgba(255,255,255,0.12);font-size:0.68rem;">
        Du erhältst diese E-Mail, weil {$patientName} heute Geburtstag hat. 🎂
      </p>
    </td></tr>

  </table>
</td></tr>
</table>

</body>
</html>
HTML;
    }

    private function buildPlainText(array $p, int $age, string $ownerFirst, string $company): string
    {
        $name    = $p['patient_name'] ?? 'Ihr Tier';
        $ageText = $age > 0 ? " ({$age} Jahre alt)" : '';
        return "Hallo {$ownerFirst},\n\n"
            . "🎂 Herzlichen Glückwunsch zum Geburtstag, {$name}!{$ageText}\n\n"
            . "Ich wünsche {$name} alles Gute, beste Gesundheit und viel Freude!\n\n"
            . "Mit herzlichen Geburtstagsgrüßen,\n{$company} 🐾";
    }

    /* ═══════════════════════════════════════════════════════════
       HELPERS
    ═══════════════════════════════════════════════════════════ */

    private function alreadySentThisYear(int $patientId, int $year): bool
    {
        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('birthday_emails_sent')}` WHERE patient_id = ? AND year_sent = ?",
            [$patientId, $year]
        );
        return (int)$count > 0;
    }

    private function markSent(int $patientId, int $year): void
    {
        $this->db->execute(
            "INSERT IGNORE INTO `{$this->t('birthday_emails_sent')}` (patient_id, year_sent, sent_at) VALUES (?, ?, NOW())",
            [$patientId, $year]
        );
    }

    private function calculateAge(string $birthDate): int
    {
        try {
            $birth = new \DateTime($birthDate);
            $now   = new \DateTime();
            return (int)$birth->diff($now)->y;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function createMailer(): PHPMailer
    {
        $mailer      = new PHPMailer(true);
        $smtpHost    = trim($this->settings->get('smtp_host', ''));
        $fromAddress = $this->settings->get('mail_from_address', 'noreply@tierphysio.local');
        $fromName    = $this->settings->get('mail_from_name', 'Tierphysio Manager');

        if ($smtpHost !== '') {
            /* ── SMTP mode ── */
            $mailer->isSMTP();
            $mailer->Host       = $smtpHost;
            $mailer->Port       = (int)$this->settings->get('smtp_port', '587');
            $mailer->Username   = $this->settings->get('smtp_username', '');
            $mailer->Password   = $this->settings->get('smtp_password', '');
            $mailer->SMTPAuth   = !empty($mailer->Username);
            $enc = $this->settings->get('smtp_encryption', 'tls');
            if ($enc === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'none') {
                $mailer->SMTPSecure = '';
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
