<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Services\BirthdayMailService;
use App\Repositories\SettingsRepository;
use App\Core\Database;

class CronController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly BirthdayMailService $birthdayMailService,
        private readonly SettingsRepository  $settings,
        private readonly Database            $db
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    /* ─────────────────────────────────────────────────────────
       GET /cron/geburtstag
       Protected by Bearer token from settings.
    ───────────────────────────────────────────────────────── */
    public function birthday(): void
    {
        $start = hrtime(true);
        $startTime = date('Y-m-d H:i:s');
        $this->cronLog("START birthday cron at {$startTime}");

        try {
            $expectedToken = $this->settings->get('birthday_cron_token', '');

            if (empty($expectedToken)) {
                http_response_code(503);
                $this->cronLog("ERROR birthday cron: Token nicht konfiguriert");
                $this->dbLog('birthday', 'error', 'Token nicht konfiguriert.', $start);
                $this->jsonCron(['error' => 'Cron-Token nicht konfiguriert. Bitte in den Einstellungen hinterlegen.']);
                return;
            }

            $providedToken = '';
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (str_starts_with($authHeader, 'Bearer ')) {
                $providedToken = substr($authHeader, 7);
            }
            if ($providedToken === '') {
                $providedToken = $_GET['token'] ?? '';
            }

            if (!hash_equals($expectedToken, $providedToken)) {
                http_response_code(401);
                $this->cronLog("ERROR birthday cron: Ungültiger Token");
                $this->dbLog('birthday', 'error', 'Ungültiger Token.', $start);
                $this->jsonCron(['error' => 'Ungültiger Token.']);
                return;
            }

            $result = $this->birthdayMailService->runDailyCheck();

            $msg = "sent={$result['sent']}, skipped={$result['skipped']}, errors={$result['errors']}";
            $this->cronLog("SUCCESS birthday cron: {$msg}");
            $this->dbLog('birthday', 'success', $msg, $start);

            $this->jsonCron([
                'ok'      => true,
                'date'    => date('Y-m-d'),
                'sent'    => $result['sent'],
                'skipped' => $result['skipped'],
                'errors'  => $result['errors'],
            ]);

        } catch (\Throwable $e) {
            $this->cronLog("EXCEPTION birthday cron: " . $e->getMessage());
            $this->dbLog('birthday', 'error', $e->getMessage(), $start);
            http_response_code(200);
            $this->jsonCron(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function dbLog(string $jobKey, string $status, string $message, int $startHrtime): void
    {
        $ms = (int)((hrtime(true) - $startHrtime) / 1_000_000);
        \App\Controllers\CronAdminController::logRun($this->db, $jobKey, $status, $message, $ms, 'cron');
    }

    /* ─────────────────────────────────────────────────────────
       Write to logs/cron.log
    ───────────────────────────────────────────────────────── */
    private function cronLog(string $message): void
    {
        try {
            $logDir  = defined('ROOT_PATH') ? ROOT_PATH . '/logs' : dirname(__DIR__, 2) . '/logs';
            $logFile = $logDir . '/cron.log';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            @file_put_contents(
                $logFile,
                '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable) {
            /* Logging must never crash the cron */
        }
    }

    private function jsonCron(mixed $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "\nCron completed\n";
        exit;
    }
}
