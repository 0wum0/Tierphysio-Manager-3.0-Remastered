<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Repositories\SettingsRepository;
use Saas\Repositories\ActivityLogRepository;

class SettingsController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private readonly SettingsRepository   $settings,
        private readonly ActivityLogRepository $log
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();
        $tab = $this->get('tab', 'company');

        $this->render('admin/settings/index.twig', [
            'page_title' => 'Einstellungen',
            'settings'   => $this->settings->all(),
            'flat'       => $this->settings->allFlat(),
            'tab'        => $tab,
        ]);
    }

    public function update(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tab      = $this->post('_tab', 'company');
        $allowed  = [
            'company'       => ['company_name','company_email','company_address','company_zip','company_city','company_country','company_phone','company_website','tax_id','vat_id'],
            'billing'       => ['bank_iban','bank_bic','bank_name','invoice_prefix','invoice_start_number','invoice_payment_days','kleinunternehmer'],
            'mail'          => ['smtp_host','smtp_port','smtp_encryption','smtp_username','smtp_password','mail_from_name','mail_from_address'],
            'notifications' => ['notify_new_tenant','notify_payment','notify_overdue','notify_trial_expiry','notify_email'],
            'system'        => ['update_channel','update_check_url','maintenance_mode','registration_open','max_tenants'],
        ];

        $keys = $allowed[$tab] ?? [];
        foreach ($keys as $key) {
            $val = $_POST[$key] ?? null;
            if ($val === null) {
                // Checkbox: false falls nicht gesetzt
                $this->settings->set($key, '0');
            } else {
                $this->settings->set($key, trim((string)$val));
            }
        }

        $actor = $this->session->get('saas_user') ?? 'admin';
        $this->log->log('settings.update', $actor, 'settings', null, "Tab: {$tab}");

        $this->session->flash('success', 'Einstellungen gespeichert.');
        $this->redirect("/admin/settings?tab={$tab}");
    }

    public function testMail(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $to       = trim($this->post('test_email', ''));
        $settings = $this->settings->allFlat();

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Ungültige E-Mail-Adresse.');
            $this->redirect('/admin/settings?tab=mail');
            return;
        }

        try {
            $from     = $settings['mail_from_address'] ?? 'noreply@therapano.de';
            $fromName = $settings['mail_from_name'] ?? 'TheraPano SaaS';
            $subject  = 'Test-E-Mail von TheraPano SaaS';
            $body     = "Dies ist eine Test-E-Mail von TheraPano SaaS.\r\n\r\nDie E-Mail-Konfiguration funktioniert korrekt.\r\n\r\nZeitstempel: " . date('d.m.Y H:i:s');

            $sent = $this->sendSimpleMail($to, $subject, $body, $from, $fromName, $settings);
            if ($sent) {
                $this->session->flash('success', "Test-E-Mail erfolgreich an {$to} gesendet.");
            } else {
                $this->session->flash('error', 'E-Mail konnte nicht gesendet werden.');
            }
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Fehler: ' . $e->getMessage());
        }

        $this->redirect('/admin/settings?tab=mail');
    }

    private function sendSimpleMail(string $to, string $subject, string $body, string $from, string $fromName, array $settings): bool
    {
        $mailerPaths = [
            dirname(__DIR__, 2) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
            dirname(__DIR__, 3) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
        ];
        foreach ($mailerPaths as $path) {
            if (file_exists($path)) { require_once $path; break; }
        }

        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $settings['smtp_host'] ?? 'localhost';
            $mail->Port       = (int)($settings['smtp_port'] ?? 587);
            $mail->SMTPAuth   = !empty($settings['smtp_username']);
            $mail->Username   = $settings['smtp_username'] ?? '';
            $mail->Password   = $settings['smtp_password'] ?? '';
            $mail->SMTPSecure = $settings['smtp_encryption'] ?? 'tls';
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($from, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        }

        return mail($to, $subject, $body, "From: {$fromName} <{$from}>\r\nContent-Type: text/plain; charset=UTF-8");
    }
}
