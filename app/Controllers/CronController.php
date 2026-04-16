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

    /* ─────────────────────────────────────────────────────────
       GET /cron/dispatcher
       Zentraler Dispatcher - führt fällige Jobs basierend auf Zeitplänen aus
       Protected by cron_dispatcher_token from settings.
       Läuft alle 10 Minuten via Hosting-Panel.
    ───────────────────────────────────────────────────────── */
    public function dispatcher(): void
    {
        $start = hrtime(true);
        $startTime = date('Y-m-d H:i:s');
        $this->cronLog("START dispatcher at {$startTime}");

        try {
            // Tenant-Identifikation über tid-Parameter
            $tid = $_GET['tid'] ?? '';
            if ($tid) {
                // Prefix aus tid setzen (z.B. praxis-wenzel -> t_praxis_wenzel_)
                $prefix = $this->prefixFromTid((string)$tid);
                $this->db->setPrefix($prefix);
                $this->cronLog(sprintf(
                    'DISPATCHER tenant context: tid="%s" prefix="%s" table="%s"',
                    (string)$tid,
                    $prefix,
                    $this->db->prefix('settings')
                ));
            }

            $expectedToken = $this->settings->get('cron_dispatcher_token', '');

            if (empty($expectedToken)) {
                http_response_code(503);
                $this->cronLog("ERROR dispatcher: Token nicht konfiguriert");
                $this->dispatcherLog('dispatcher', 'error', 'Token nicht konfiguriert.', $start);
                $this->jsonCron(['error' => 'Dispatcher-Token nicht konfiguriert.']);
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
                $this->cronLog("ERROR dispatcher: Ungültiger Token");
                $this->dispatcherLog('dispatcher', 'error', 'Ungültiger Token.', $start);
                $this->jsonCron(['error' => 'Ungültiger Token.']);
                return;
            }

            // Job-Konfiguration mit Zeitplänen
            $jobs = [
                'birthday' => [
                    'schedule' => '0 8 * * *', // Täglich um 08:00
                    'interval_seconds' => 86400, // 24 Stunden
                    'endpoint' => '/cron/geburtstag'
                ],
                'calendar_reminders' => [
                    'schedule' => '*/15 * * * *', // Alle 15 Minuten
                    'interval_seconds' => 900,
                    'endpoint' => '/kalender/cron/erinnerungen'
                ],
                'google_sync' => [
                    'schedule' => '0 * * * *', // Stündlich
                    'interval_seconds' => 3600,
                    'endpoint' => '/google-kalender/cron'
                ],
                'tcp_reminders' => [
                    'schedule' => '*/15 * * * *', // Alle 15 Minuten
                    'interval_seconds' => 900,
                    'endpoint' => '/tcp/cron/erinnerungen'
                ],
                'holiday_greetings' => [
                    'schedule' => '0 8 * * *', // Täglich um 08:00
                    'interval_seconds' => 86400,
                    'endpoint' => '/api/holiday-cron'
                ]
            ];

            $results = [];

            foreach ($jobs as $jobKey => $config) {
                $jobStart = hrtime(true);

                // Prüfen, ob Job fällig ist
                $lastRun = $this->db->fetchColumn(
                    "SELECT created_at FROM cron_dispatcher_log WHERE job_key = ? ORDER BY created_at DESC LIMIT 1",
                    [$jobKey]
                );

                $isDue = true;
                if ($lastRun) {
                    $lastRunTime = strtotime($lastRun);
                    $nextRunTime = $lastRunTime + $config['interval_seconds'];
                    $isDue = time() >= $nextRunTime;
                }

                if (!$isDue) {
                    $results[$jobKey] = ['status' => 'skipped', 'reason' => 'Not due yet'];
                    $this->dispatcherLog($jobKey, 'skipped', 'Not due yet', $jobStart);
                    continue;
                }

                // Job ausführen via internen Aufruf
                $this->cronLog("EXECUTING job: {$jobKey}");
                $jobResult = $this->executeJob($jobKey, $config['endpoint']);
                $results[$jobKey] = $jobResult;

                $status = $jobResult['success'] ? 'success' : 'error';
                $message = $jobResult['message'] ?? ($jobResult['success'] ? 'Executed successfully' : 'Execution failed');
                $this->dispatcherLog($jobKey, $status, $message, $jobStart);
            }

            $executedCount = count(array_filter($results, fn($r) => isset($r['status']) && $r['status'] !== 'skipped'));
            $skippedCount = count(array_filter($results, fn($r) => isset($r['status']) && $r['status'] === 'skipped'));

            $msg = "executed={$executedCount}, skipped={$skippedCount}";
            $this->cronLog("SUCCESS dispatcher: {$msg}");
            $this->dispatcherLog('dispatcher', 'success', $msg, $start);

            $this->jsonCron([
                'ok' => true,
                'timestamp' => $startTime,
                'executed' => $executedCount,
                'skipped' => $skippedCount,
                'results' => $results
            ]);

        } catch (\Throwable $e) {
            $this->cronLog("EXCEPTION dispatcher: " . $e->getMessage());
            $this->dispatcherLog('dispatcher', 'error', $e->getMessage(), $start);
            http_response_code(200);
            $this->jsonCron(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function executeJob(string $jobKey, string $endpoint): array
    {
        $start = hrtime(true);
        $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $url = $baseUrl . $endpoint;

        // Token für den spezifischen Job holen
        $tokenKeys = [
            'birthday' => 'birthday_cron_token',
            'calendar_reminders' => 'calendar_cron_secret',
            'google_sync' => 'google_sync_cron_secret',
            'tcp_reminders' => 'tcp_cron_token',
            'holiday_greetings' => 'cron_secret'
        ];

        $tokenKey = $tokenKeys[$jobKey] ?? '';
        $token = $tokenKey ? $this->settings->get($tokenKey, '') : '';

        if ($token) {
            $url .= '?token=' . $token;
        }

        // Internen Aufruf via curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Internal-Cron: true']);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $duration = (int)((hrtime(true) - $start) / 1_000_000);

        if ($error) {
            return [
                'success' => false,
                'message' => 'CURL Error: ' . $error,
                'duration_ms' => $duration
            ];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'message' => "HTTP {$httpCode}",
            'duration_ms' => $duration,
            'response' => substr($response, 0, 500)
        ];
    }

    private function dispatcherLog(string $jobKey, string $status, string $message, int $startHrtime): void
    {
        $ms = (int)((hrtime(true) - $startHrtime) / 1_000_000);
        try {
            $this->db->query(
                "INSERT INTO cron_dispatcher_log (job_key, status, message, duration_ms) VALUES (?, ?, ?, ?)",
                [$jobKey, $status, $message, $ms]
            );
        } catch (\Throwable) {
            // Logging must never crash the dispatcher
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

    private function prefixFromTid(string $tid): string
    {
        $normalized = strtolower(trim($tid));
        $normalized = preg_replace('/[^a-z0-9]/', '_', $normalized) ?? $normalized;
        $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;
        $normalized = trim($normalized, '_');
        return 't_' . $normalized . '_';
    }
}        $startTime = date('Y-m-d H:i:s');
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

    /* ─────────────────────────────────────────────────────────
       GET /cron/dispatcher
       Zentraler Dispatcher - führt fällige Jobs basierend auf Zeitplänen aus
       Protected by cron_dispatcher_token from settings.
       Läuft alle 10 Minuten via Hosting-Panel.
    ───────────────────────────────────────────────────────── */
    public function dispatcher(): void
    {
        $start = hrtime(true);
        $startTime = date('Y-m-d H:i:s');
        $this->cronLog("START dispatcher at {$startTime}");

        try {
            // Tenant-Identifikation über tid-Parameter
            $tid = $_GET['tid'] ?? '';
            if ($tid) {
                // Prefix aus tid setzen (z.B. praxis-wenzel -> t_praxis-wenzel_)
                $prefix = 't_' . $tid . '_';
                $this->db->setPrefix($prefix);
            }

            $expectedToken = $this->settings->get('cron_dispatcher_token', '');

            if (empty($expectedToken)) {
                http_response_code(503);
                $this->cronLog("ERROR dispatcher: Token nicht konfiguriert");
                $this->dispatcherLog('dispatcher', 'error', 'Token nicht konfiguriert.', $start);
                $this->jsonCron(['error' => 'Dispatcher-Token nicht konfiguriert.']);
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
                $this->cronLog("ERROR dispatcher: Ungültiger Token");
                $this->dispatcherLog('dispatcher', 'error', 'Ungültiger Token.', $start);
                $this->jsonCron(['error' => 'Ungültiger Token.']);
                return;
            }

            // Job-Konfiguration mit Zeitplänen
            $jobs = [
                'birthday' => [
                    'schedule' => '0 8 * * *', // Täglich um 08:00
                    'interval_seconds' => 86400, // 24 Stunden
                    'endpoint' => '/cron/geburtstag'
                ],
                'calendar_reminders' => [
                    'schedule' => '*/15 * * * *', // Alle 15 Minuten
                    'interval_seconds' => 900,
                    'endpoint' => '/kalender/cron/erinnerungen'
                ],
                'google_sync' => [
                    'schedule' => '0 * * * *', // Stündlich
                    'interval_seconds' => 3600,
                    'endpoint' => '/google-kalender/cron'
                ],
                'tcp_reminders' => [
                    'schedule' => '*/15 * * * *', // Alle 15 Minuten
                    'interval_seconds' => 900,
                    'endpoint' => '/tcp/cron/erinnerungen'
                ],
                'holiday_greetings' => [
                    'schedule' => '0 8 * * *', // Täglich um 08:00
                    'interval_seconds' => 86400,
                    'endpoint' => '/api/holiday-cron'
                ]
            ];

            $results = [];

            foreach ($jobs as $jobKey => $config) {
                $jobStart = hrtime(true);

                // Prüfen, ob Job fällig ist
                $lastRun = $this->db->fetchColumn(
                    "SELECT created_at FROM cron_dispatcher_log WHERE job_key = ? ORDER BY created_at DESC LIMIT 1",
                    [$jobKey]
                );

                $isDue = true;
                if ($lastRun) {
                    $lastRunTime = strtotime($lastRun);
                    $nextRunTime = $lastRunTime + $config['interval_seconds'];
                    $isDue = time() >= $nextRunTime;
                }

                if (!$isDue) {
                    $results[$jobKey] = ['status' => 'skipped', 'reason' => 'Not due yet'];
                    $this->dispatcherLog($jobKey, 'skipped', 'Not due yet', $jobStart);
                    continue;
                }

                // Job ausführen via internen Aufruf
                $this->cronLog("EXECUTING job: {$jobKey}");
                $jobResult = $this->executeJob($jobKey, $config['endpoint']);
                $results[$jobKey] = $jobResult;

                $status = $jobResult['success'] ? 'success' : 'error';
                $message = $jobResult['message'] ?? ($jobResult['success'] ? 'Executed successfully' : 'Execution failed');
                $this->dispatcherLog($jobKey, $status, $message, $jobStart);
            }

            $executedCount = count(array_filter($results, fn($r) => isset($r['status']) && $r['status'] !== 'skipped'));
            $skippedCount = count(array_filter($results, fn($r) => isset($r['status']) && $r['status'] === 'skipped'));

            $msg = "executed={$executedCount}, skipped={$skippedCount}";
            $this->cronLog("SUCCESS dispatcher: {$msg}");
            $this->dispatcherLog('dispatcher', 'success', $msg, $start);

            $this->jsonCron([
                'ok' => true,
                'timestamp' => $startTime,
                'executed' => $executedCount,
                'skipped' => $skippedCount,
                'results' => $results
            ]);

        } catch (\Throwable $e) {
            $this->cronLog("EXCEPTION dispatcher: " . $e->getMessage());
            $this->dispatcherLog('dispatcher', 'error', $e->getMessage(), $start);
            http_response_code(200);
            $this->jsonCron(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function executeJob(string $jobKey, string $endpoint): array
    {
        $start = hrtime(true);
        $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $url = $baseUrl . $endpoint;

        // Token für den spezifischen Job holen
        $tokenKeys = [
            'birthday' => 'birthday_cron_token',
            'calendar_reminders' => 'calendar_cron_secret',
            'google_sync' => 'google_sync_cron_secret',
            'tcp_reminders' => 'tcp_cron_token',
            'holiday_greetings' => 'cron_secret'
        ];

        $tokenKey = $tokenKeys[$jobKey] ?? '';
        $token = $tokenKey ? $this->settings->get($tokenKey, '') : '';

        if ($token) {
            $url .= '?token=' . $token;
        }

        // Internen Aufruf via curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Internal-Cron: true']);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $duration = (int)((hrtime(true) - $start) / 1_000_000);

        if ($error) {
            return [
                'success' => false,
                'message' => 'CURL Error: ' . $error,
                'duration_ms' => $duration
            ];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'message' => "HTTP {$httpCode}",
            'duration_ms' => $duration,
            'response' => substr($response, 0, 500)
        ];
    }

    private function dispatcherLog(string $jobKey, string $status, string $message, int $startHrtime): void
    {
        $ms = (int)((hrtime(true) - $startHrtime) / 1_000_000);
        try {
            $this->db->query(
                "INSERT INTO cron_dispatcher_log (job_key, status, message, duration_ms) VALUES (?, ?, ?, ?)",
                [$jobKey, $status, $message, $ms]
            );
        } catch (\Throwable) {
            // Logging must never crash the dispatcher
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
