<?php

declare(strict_types=1);

namespace Saas\Services;

use PHPMailer\PHPMailer\PHPMailer;
use Saas\Core\Database;

/**
 * LifecycleMailService
 * ─────────────────────
 * Versendet automatische Lifecycle-E-Mails an Tenants:
 *
 *  welcome         – sofort nach Provisioning (Tag 0)
 *  trial_warning   – 4 Tage vor Trial-Ablauf
 *  trial_expired   – am/nach Trial-Ende, kein aktives Abo
 *  activated       – wenn Abo auf 'active' wechselt
 *
 * Tracking: tenant_lifecycle_emails (UNIQUE tenant_id + email_key)
 * → Jede Mail wird pro Tenant nur einmal gesendet.
 */
class LifecycleMailService
{
    private array $smtpConfig;

    public function __construct(
        private readonly Database $db
    ) {
        $this->smtpConfig = [
            'host'       => (string)($_ENV['MAIL_HOST']     ?? 'localhost'),
            'port'       => (int)($_ENV['MAIL_PORT']        ?? 587),
            'username'   => (string)($_ENV['MAIL_USERNAME'] ?? ''),
            'password'   => (string)($_ENV['MAIL_PASSWORD'] ?? ''),
            'encryption' => (string)($_ENV['MAIL_ENCRYPTION'] ?? 'tls'),
            'from_addr'  => (string)($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@therapano.de'),
            'from_name'  => (string)($_ENV['MAIL_FROM_NAME']    ?? 'TheraPano'),
            'app_url'    => rtrim((string)($_ENV['APP_URL'] ?? 'https://app.therapano.de'), '/'),
        ];
    }

    // ── Public: called by cron ───────────────────────────────────────────────

    /**
     * Main entry point: processes all pending lifecycle mails.
     * Returns a summary array.
     *
     * @return array{sent: int, skipped: int, failed: int, log: list<string>}
     */
    public function processAll(): array
    {
        $this->ensureTable();

        $summary = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'log' => []];

        $this->processWelcome($summary);
        $this->processTrialWarning($summary);
        $this->processTrialExpired($summary);
        $this->processActivated($summary);

        return $summary;
    }

    /**
     * Send a specific lifecycle mail to a single tenant immediately.
     * Returns true on success.
     */
    public function sendTo(int $tenantId, string $emailKey): bool
    {
        $this->ensureTable();
        $tenant = $this->db->fetch("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenant) {
            return false;
        }
        return $this->dispatch($tenant, $emailKey);
    }

    // ── Processing Steps ─────────────────────────────────────────────────────

    private function processWelcome(array &$summary): void
    {
        $tenants = $this->db->fetchAll("
            SELECT t.*
            FROM tenants t
            WHERE t.status IN ('trial','active','pending')
              AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND NOT EXISTS (
                  SELECT 1 FROM tenant_lifecycle_emails le
                  WHERE le.tenant_id = t.id AND le.email_key = 'welcome'
              )
            ORDER BY t.created_at ASC
        ");

        foreach ($tenants as $tenant) {
            $this->dispatchAndRecord($tenant, 'welcome', $summary);
        }
    }

    private function processTrialWarning(array &$summary): void
    {
        $tenants = $this->db->fetchAll("
            SELECT t.*
            FROM tenants t
            LEFT JOIN subscriptions s ON s.tenant_id = t.id AND s.status IN ('trial','trialing')
            WHERE t.status = 'trial'
              AND (
                  s.trial_ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 4 DAY)
                  OR t.trial_ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 4 DAY)
              )
              AND NOT EXISTS (
                  SELECT 1 FROM tenant_lifecycle_emails le
                  WHERE le.tenant_id = t.id AND le.email_key = 'trial_warning'
              )
        ");

        foreach ($tenants as $tenant) {
            $this->dispatchAndRecord($tenant, 'trial_warning', $summary);
        }
    }

    private function processTrialExpired(array &$summary): void
    {
        $tenants = $this->db->fetchAll("
            SELECT t.*
            FROM tenants t
            WHERE t.status = 'trial'
              AND (
                  t.trial_ends_at < NOW()
                  OR NOT EXISTS (
                      SELECT 1 FROM subscriptions s
                      WHERE s.tenant_id = t.id AND s.status IN ('trial','trialing','active')
                  )
              )
              AND t.trial_ends_at IS NOT NULL
              AND t.trial_ends_at < NOW()
              AND NOT EXISTS (
                  SELECT 1 FROM tenant_lifecycle_emails le
                  WHERE le.tenant_id = t.id AND le.email_key = 'trial_expired'
              )
        ");

        foreach ($tenants as $tenant) {
            $this->dispatchAndRecord($tenant, 'trial_expired', $summary);
        }
    }

    private function processActivated(array &$summary): void
    {
        $tenants = $this->db->fetchAll("
            SELECT t.*
            FROM tenants t
            JOIN subscriptions s ON s.tenant_id = t.id AND s.status = 'active'
            WHERE t.status = 'active'
              AND NOT EXISTS (
                  SELECT 1 FROM tenant_lifecycle_emails le
                  WHERE le.tenant_id = t.id AND le.email_key = 'activated'
              )
        ");

        foreach ($tenants as $tenant) {
            $this->dispatchAndRecord($tenant, 'activated', $summary);
        }
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private function dispatchAndRecord(array $tenant, string $key, array &$summary): void
    {
        $ok = $this->dispatch($tenant, $key);
        if ($ok) {
            $summary['sent']++;
            $summary['log'][] = "[OK] {$key} → {$tenant['email']} ({$tenant['practice_name']})";
        } else {
            $summary['failed']++;
            $summary['log'][] = "[FAIL] {$key} → {$tenant['email']}";
        }
    }

    private function dispatch(array $tenant, string $emailKey): bool
    {
        $email = trim((string)($tenant['email'] ?? ''));
        if ($email === '') {
            return false;
        }

        [$subject, $html] = $this->buildMail($emailKey, $tenant);
        if ($subject === '') {
            return false;
        }

        $status = 'sent';
        $error  = null;

        try {
            $this->sendMail($email, (string)($tenant['owner_name'] ?? ''), $subject, $html);
        } catch (\Throwable $e) {
            $status = 'failed';
            $error  = $e->getMessage();
        }

        try {
            $this->db->execute("
                INSERT IGNORE INTO tenant_lifecycle_emails
                    (tenant_id, email_key, to_email, subject, status, error)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [(int)$tenant['id'], $emailKey, $email, $subject, $status, $error]);
        } catch (\Throwable) {}

        return $status === 'sent';
    }

    /**
     * @return array{0: string, 1: string} [subject, html]
     */
    private function buildMail(string $key, array $tenant): array
    {
        $name     = htmlspecialchars((string)($tenant['owner_name']    ?? 'Praxisinhaber'));
        $practice = htmlspecialchars((string)($tenant['practice_name'] ?? 'Ihre Praxis'));
        $appUrl   = $this->smtpConfig['app_url'];

        $trialEnd = '';
        if (!empty($tenant['trial_ends_at'])) {
            $trialEnd = date('d.m.Y', strtotime((string)$tenant['trial_ends_at']));
        }

        $upgradeUrl = $appUrl . '/upgrade';
        $loginUrl   = $appUrl . '/login';

        return match ($key) {
            'welcome'      => [
                'Willkommen bei TheraPano – Ihre Praxis ist bereit! 🐾',
                $this->tpl('welcome', $name, $practice, [
                    'intro'  => 'Ihre Praxis wurde erfolgreich eingerichtet. Sie können sich jetzt einloggen und loslegen.',
                    'cta_url'  => $loginUrl,
                    'cta_text' => 'Jetzt einloggen',
                    'extra'  => "
                        <p style='margin:12px 0;font-size:.875rem;color:#64748b;'>
                          <strong>Ihre Testphase läuft " . ($trialEnd ? "bis zum {$trialEnd}" : '14 Tage') . ".</strong><br>
                          In dieser Zeit stehen Ihnen alle Funktionen kostenlos zur Verfügung.
                        </p>",
                ]),
            ],
            'trial_warning' => [
                'Ihre Testphase endet bald – Jetzt upgraden & weiterarbeiten',
                $this->tpl('warning', $name, $practice, [
                    'intro'    => "Ihre kostenlose Testphase endet " . ($trialEnd ? "am <strong>{$trialEnd}</strong>" : 'in Kürze') . ". Damit Sie weiterhin auf alle Patientendaten und Termine zugreifen können, aktivieren Sie jetzt Ihr Abonnement.",
                    'cta_url'  => $upgradeUrl,
                    'cta_text' => 'Abonnement aktivieren',
                    'extra'    => "<p style='font-size:.8rem;color:#94a3b8;margin-top:16px;'>Bei Fragen zu unseren Tarifen antworten Sie einfach auf diese E-Mail.</p>",
                ]),
            ],
            'trial_expired' => [
                'Ihre Testphase ist abgelaufen – Daten weiterhin sichern',
                $this->tpl('expired', $name, $practice, [
                    'intro'    => 'Ihre kostenlose Testphase ist abgelaufen. Ihre Daten sind noch <strong>30 Tage</strong> gesichert. Aktivieren Sie jetzt Ihr Abonnement, um ohne Unterbrechung weiterzuarbeiten.',
                    'cta_url'  => $upgradeUrl,
                    'cta_text' => 'Jetzt Abonnement starten',
                    'extra'    => "<p style='font-size:.8rem;color:#f87171;margin-top:16px;'>Nach Ablauf der Nachfrist werden Ihre Daten gemäß DSGVO gelöscht.</p>",
                ]),
            ],
            'activated' => [
                'Ihr TheraPano-Abonnement ist aktiv – Danke! 🎉',
                $this->tpl('success', $name, $practice, [
                    'intro'    => 'Herzlichen Dank! Ihr Abonnement ist jetzt aktiv. Alle Funktionen von TheraPano stehen Ihnen unbegrenzt zur Verfügung.',
                    'cta_url'  => $loginUrl,
                    'cta_text' => 'Zur Praxissoftware',
                    'extra'    => "
                        <p style='margin:12px 0;font-size:.875rem;color:#64748b;'>
                          Rechnungen und Zahlungsdetails finden Sie in Ihrem Profil unter <a href='{$appUrl}/profile/billing' style='color:#2563eb;'>Abrechnung</a>.
                        </p>",
                ]),
            ],
            default => ['', ''],
        };
    }

    /**
     * Builds a consistent HTML email from a template.
     * @param array{intro: string, cta_url: string, cta_text: string, extra: string} $vars
     */
    private function tpl(string $type, string $name, string $practice, array $vars): string
    {
        $headerColor = match($type) {
            'warning' => '#d97706',
            'expired' => '#dc2626',
            'success' => '#059669',
            default   => '#2563eb',
        };
        $headerIcon = match($type) {
            'warning' => '⚠️',
            'expired' => '⏰',
            'success' => '✅',
            default   => '🐾',
        };

        $intro  = $vars['intro']    ?? '';
        $ctaUrl = $vars['cta_url']  ?? '#';
        $ctaTxt = $vars['cta_text'] ?? 'Jetzt öffnen';
        $extra  = $vars['extra']    ?? '';

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
*{box-sizing:border-box}
body{margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#1e293b}
.wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.header{background:{$headerColor};padding:32px 40px;text-align:center}
.header h1{margin:0;color:#fff;font-size:1.35rem;font-weight:700;letter-spacing:-.02em}
.header .icon{font-size:2rem;margin-bottom:8px}
.body{padding:36px 40px}
.greeting{font-size:1rem;color:#1e293b;margin-bottom:16px}
.intro{font-size:.9375rem;color:#475569;line-height:1.7;margin-bottom:24px}
.cta{display:block;width:fit-content;margin:0 auto 24px;background:{$headerColor};color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.9375rem;letter-spacing:.01em}
.divider{border:none;border-top:1px solid #e2e8f0;margin:24px 0}
.footer{background:#f8fafc;padding:20px 40px;text-align:center;font-size:.75rem;color:#94a3b8;border-top:1px solid #e2e8f0}
.footer a{color:#64748b;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div class="icon">{$headerIcon}</div>
    <h1>TheraPano</h1>
  </div>
  <div class="body">
    <p class="greeting">Hallo <strong>{$name}</strong>,</p>
    <p class="intro">{$intro}</p>
    <a href="{$ctaUrl}" class="cta">{$ctaTxt} →</a>
    <hr class="divider">
    <p style="font-size:.8rem;color:#94a3b8;">Diese E-Mail gilt für Ihre Praxis <strong>{$practice}</strong>.</p>
    {$extra}
  </div>
  <div class="footer">
    <p>TheraPano · Tierphysio Manager 3.0 · <a href="https://therapano.de">therapano.de</a></p>
    <p>© {$this->year()} TheraPano · DSGVO-konform · EU-Hosting</p>
  </div>
</div>
</body>
</html>
HTML;
    }

    private function sendMail(string $to, string $toName, string $subject, string $html): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $this->smtpConfig['host'];
        $mail->SMTPAuth   = $this->smtpConfig['username'] !== '';
        $mail->Username   = $this->smtpConfig['username'];
        $mail->Password   = $this->smtpConfig['password'];
        $mail->SMTPSecure = $this->smtpConfig['encryption'];
        $mail->Port       = $this->smtpConfig['port'];
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($this->smtpConfig['from_addr'], $this->smtpConfig['from_name']);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags(str_replace(['</p>','<br>','</div>'], "\n", $html));
        $mail->send();
    }

    private function ensureTable(): void
    {
        try {
            $this->db->getPdo()->exec("
                CREATE TABLE IF NOT EXISTS `tenant_lifecycle_emails` (
                  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `tenant_id`  INT UNSIGNED NOT NULL,
                  `email_key`  VARCHAR(80)  NOT NULL,
                  `sent_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `to_email`   VARCHAR(255) NOT NULL,
                  `subject`    VARCHAR(255) NOT NULL DEFAULT '',
                  `status`     ENUM('sent','failed','skipped') NOT NULL DEFAULT 'sent',
                  `error`      TEXT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_tenant_key` (`tenant_id`, `email_key`),
                  KEY `idx_le_tenant` (`tenant_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable) {}
    }

    private function year(): string
    {
        return date('Y');
    }
}
