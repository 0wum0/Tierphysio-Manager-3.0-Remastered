<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Database;
use Saas\Repositories\ActivityLogRepository;

class PaymentSettingsController extends Controller
{
    private array $paymentKeys = [
        'stripe_enabled',
        'stripe_public_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
        'paypal_enabled',
        'paypal_client_id',
        'paypal_client_secret',
        'paypal_sandbox',
    ];

    public function __construct(
        View                          $view,
        Session                       $session,
        private Database              $db,
        private ActivityLogRepository $log
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $flat = [];
        try {
            $rows = $this->db->fetchAll("SELECT `key`, `value` FROM saas_settings");
            foreach ($rows as $r) {
                $flat[$r['key']] = $r['value'];
            }
        } catch (\Throwable) {}

        // Cron status: last run times from a simple log table / file
        $cronStatus = $this->getCronStatus();

        $this->render('admin/payment-settings/index.twig', [
            'page_title'  => 'Zahlungs-Einstellungen',
            'settings'    => $flat,
            'cron_status' => $cronStatus,
            'cron_path'   => realpath(__DIR__ . '/../../cron/cron_runner.php') ?: '/pfad/zu/saas-platform/cron/cron_runner.php',
        ]);
    }

    public function update(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        foreach ($this->paymentKeys as $key) {
            $val = $_POST[$key] ?? null;
            if ($val === null) {
                $this->setSetting($key, '0');
            } else {
                $this->setSetting($key, trim((string)$val));
            }
        }

        $actor = $this->session->get('saas_user') ?? 'admin';
        $this->log->log('settings.payment.update', $actor, 'settings', null, 'Zahlungseinstellungen aktualisiert');

        $this->session->flash('success', 'Zahlungseinstellungen gespeichert.');
        $this->redirect('/admin/payment-settings');
    }

    public function testStripe(array $params = []): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        try {
            $key = $this->getSetting('stripe_secret_key');
            if (!$key) {
                echo json_encode(['ok' => false, 'message' => 'Kein Secret Key konfiguriert.']);
                return;
            }
            $ch = curl_init('https://api.stripe.com/v1/balance');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$key}"],
                CURLOPT_TIMEOUT        => 10,
            ]);
            $res    = json_decode(curl_exec($ch) ?: '', true) ?? [];
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200 && isset($res['available'])) {
                echo json_encode(['ok' => true, 'message' => 'Verbindung erfolgreich! Stripe API erreichbar.']);
            } else {
                $err = $res['error']['message'] ?? 'Unbekannter Fehler';
                echo json_encode(['ok' => false, 'message' => "Stripe Fehler: {$err}"]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function testPayPal(array $params = []): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        try {
            $clientId     = $this->getSetting('paypal_client_id');
            $clientSecret = $this->getSetting('paypal_client_secret');
            $sandbox      = $this->getSetting('paypal_sandbox') === '1';

            if (!$clientId || !$clientSecret) {
                echo json_encode(['ok' => false, 'message' => 'Client ID oder Secret fehlt.']);
                return;
            }

            $baseUrl = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
            $ch      = curl_init("{$baseUrl}/v1/oauth2/token");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_USERPWD        => "{$clientId}:{$clientSecret}",
                CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_TIMEOUT        => 10,
            ]);
            $res    = json_decode(curl_exec($ch) ?: '', true) ?? [];
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200 && isset($res['access_token'])) {
                $mode = $sandbox ? 'Sandbox' : 'Live';
                echo json_encode(['ok' => true, 'message' => "PayPal {$mode} Verbindung erfolgreich!"]);
            } else {
                $err = $res['error_description'] ?? ($res['error'] ?? 'Unbekannter Fehler');
                echo json_encode(['ok' => false, 'message' => "PayPal Fehler: {$err}"]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function runCron(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $mode    = in_array($_POST['mode'] ?? '', ['hourly', 'daily'], true) ? $_POST['mode'] : 'hourly';
        $cronPHP = PHP_BINARY;
        $script  = realpath(__DIR__ . '/../../cron/cron_runner.php');

        if (!$script || !file_exists($script)) {
            $this->session->flash('error', 'Cron-Skript nicht gefunden.');
            $this->redirect('/admin/payment-settings#cron');
            return;
        }

        $output = [];
        $code   = 0;
        exec(escapeshellcmd("{$cronPHP} {$script} {$mode}") . ' 2>&1', $output, $code);

        if ($code === 0) {
            $this->session->flash('success', "Cron ({$mode}) erfolgreich ausgeführt.<br><pre style='margin:.5rem 0 0;font-size:.75rem;opacity:.7;'>" . htmlspecialchars(implode("\n", array_slice($output, 0, 15))) . '</pre>');
        } else {
            $this->session->flash('error', 'Cron fehlgeschlagen (Exit ' . $code . '):<br><pre style="font-size:.75rem;">' . htmlspecialchars(implode("\n", $output)) . '</pre>');
        }

        $this->redirect('/admin/payment-settings#cron');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function getSetting(string $key): string
    {
        try {
            return (string)($this->db->fetchColumn(
                "SELECT `value` FROM saas_settings WHERE `key` = ?", [$key]
            ) ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private function setSetting(string $key, string $value): void
    {
        try {
            $this->db->execute(
                "INSERT INTO saas_settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = ?",
                [$key, $value, $value]
            );
        } catch (\Throwable) {}
    }

    private function getCronStatus(): array
    {
        $logFile = __DIR__ . '/../../storage/logs/cron.log';
        $status  = [
            'last_run'    => null,
            'last_mode'   => null,
            'last_lines'  => [],
            'log_exists'  => false,
        ];

        if (!file_exists($logFile)) {
            return $status;
        }

        $status['log_exists'] = true;
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $last  = array_slice($lines, -30);

        foreach (array_reverse($last) as $line) {
            if (str_contains($line, 'TheraPano Cron')) {
                if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*\((\w+)\)/', $line, $m)) {
                    $status['last_run']  = $m[1];
                    $status['last_mode'] = $m[2];
                }
                break;
            }
        }

        $status['last_lines'] = array_slice($last, -15);
        return $status;
    }
}
