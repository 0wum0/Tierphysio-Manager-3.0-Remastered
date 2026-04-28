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
    /**
     * Tracks which tokens were auto-generated during this request.
     * Used to allow first-run execution when no token has been provided yet.
     *
     * @var array<string, true>
     */
    private array $newlyCreatedTokens = [];

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
       Self-Healing Token Management
    ───────────────────────────────────────────────────────── */

    /**
     * Ensures a cron token exists in the tenant settings table.
     *
     * - If token already exists  → returns it unchanged (NEVER overwrites).
     * - If token is missing      → generates a cryptographically secure token,
     *                              persists it, logs it, and returns it.
     *
     * Execution always continues regardless of storage success.
     */
    private function ensureCronToken(string $key): string
    {
        $existing = $this->settings->get($key, '');

        // A valid token is exactly 64 lowercase hex characters (bin2hex(random_bytes(32))).
        // If the stored value is missing OR structurally invalid, regenerate it.
        // A valid token that simply doesn't match the caller's value is still a 401 –
        // only DB corruption or absence triggers a new token here.
        if ($existing !== '' && $existing !== null && $this->isValidCronToken((string)$existing)) {
            return (string)$existing;
        }

        // Token missing or corrupted – generate, persist, and track
        $reason = ($existing !== '' && $existing !== null) ? 'corrupted' : 'missing';
        $newToken = bin2hex(random_bytes(32));

        try {
            $this->settings->set($key, $newToken);
            $this->newlyCreatedTokens[$key] = true;
            $tid = (string)($_GET['tid'] ?? '');
            $this->cronLog("[CRON TOKEN] {$reason}, regenerated: {$key} for tenant {$tid}");
        } catch (\Throwable $e) {
            // Storage failure must not stop cron execution
            $this->cronLog("[CRON TOKEN] WARNING: could not persist {$key}: " . $e->getMessage());
        }

        return $newToken;
    }

    /**
     * A cron token is valid if it is exactly 64 lowercase hex characters.
     * This is the format produced by bin2hex(random_bytes(32)).
     * Any other value (empty, truncated, wrong encoding, null bytes, etc.) is considered corrupted.
     */
    private function isValidCronToken(string $token): bool
    {
        return strlen($token) === 64 && ctype_xdigit($token);
    }

    /**
     * Ensures ALL known cron tokens exist for the current tenant.
     * Call this once after the tenant prefix has been set.
     */
    private function ensureAllCronTokens(): void
    {
        $keys = [
            'cron_dispatcher_token',
            'birthday_cron_token',
            'calendar_cron_secret',
            'google_sync_cron_secret',
            'tcp_cron_token',
            'cron_secret',
            'portal_smart_reminder_token',
        ];

        foreach ($keys as $key) {
            $this->ensureCronToken($key);
        }

        if (!empty($this->newlyCreatedTokens)) {
            $tid = (string)($_GET['tid'] ?? '');
            $this->cronLog(sprintf(
                '[CRON TOKEN] tenant: %s – auto-created %d token(s): %s',
                $tid,
                count($this->newlyCreatedTokens),
                implode(', ', array_keys($this->newlyCreatedTokens))
            ));
        }
    }

    /* ─────────────────────────────────────────────────────────
       GET /cron/geburtstag
       Protected by Bearer token from settings.
       Self-healing: token is auto-created on first run.
    ───────────────────────────────────────────────────────── */
    public function birthday(): void
    {
        $start = hrtime(true);
        $startTime = date('Y-m-d H:i:s');
        $this->cronLog("START birthday cron at {$startTime}");

        try {
            // Tenant-Identifikation über tid-Parameter
            $tid = (string)($_GET['tid'] ?? '');
            if ($tid !== '') {
                $prefix = $this->prefixFromTid($tid);
                $this->db->setPrefix($prefix);
                $this->cronLog(sprintf(
                    'BIRTHDAY tenant context: tid="%s" prefix="%s" table="%s"',
                    $tid,
                    $prefix,
                    $this->db->prefix('settings')
                ));
            }

            // Self-healing: ensure token exists, generate if missing
            $expectedToken = $this->ensureCronToken('birthday_cron_token');

            $providedToken = '';
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (str_starts_with($authHeader, 'Bearer ')) {
                $providedToken = substr($authHeader, 7);
            }
            if ($providedToken === '') {
                $providedToken = (string)($_GET['token'] ?? '');
            }

            // Security:
            // - Token was JUST auto-created (first run) → allow execution unconditionally.
            // - Token already existed → validate strictly via hash_equals.
            if (!isset($this->newlyCreatedTokens['birthday_cron_token']) && !hash_equals($expectedToken, $providedToken)) {
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
       Self-healing: all tokens are auto-created on first run.
       Läuft alle 10 Minuten via Hosting-Panel.
    ───────────────────────────────────────────────────────── */
    public function dispatcher(): void
    {
        $start = hrtime(true);
        $startTime = date('Y-m-d H:i:s');
        $this->cronLog("START dispatcher at {$startTime}");

        try {
            // Tenant-Identifikation über tid-Parameter
            $tid = (string)($_GET['tid'] ?? '');
            if ($tid !== '') {
                // Prefix aus tid setzen (z.B. praxis-wenzel -> t_praxis_wenzel_)
                $prefix = $this->prefixFromTid($tid);
                $this->db->setPrefix($prefix);

                $this->cronLog(sprintf(
                    'DISPATCHER tenant context: tid="%s" prefix="%s" table="%s"',
                    $tid,
                    $prefix,
                    $this->db->prefix('settings')
                ));
            }

            // Self-healing: auto-create ALL cron tokens for this tenant if missing
            $this->ensureAllCronTokens();

            $expectedToken = $this->settings->get('cron_dispatcher_token', '');

            $providedToken = '';
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (str_starts_with($authHeader, 'Bearer ')) {
                $providedToken = substr($authHeader, 7);
            }
            if ($providedToken === '') {
                $providedToken = (string)($_GET['token'] ?? '');
            }

            // Security:
            // - Token was JUST auto-created (first run)   → allow unconditionally.
            // - No token in request at all (empty string)  → self-heal: allow + log (cron URL not yet configured).
            // - Token provided but does not match          → reject (genuine mismatch).
            $tokenJustCreated = isset($this->newlyCreatedTokens['cron_dispatcher_token']);
            $noTokenInRequest = ($providedToken === '');

            if (!$tokenJustCreated && !$noTokenInRequest && !hash_equals((string)$expectedToken, $providedToken)) {
                http_response_code(401);
                $this->cronLog("ERROR dispatcher: Ungültiger Token (Token vorhanden, stimmt nicht überein)");
                $this->dispatcherLog('dispatcher', 'error', 'Ungültiger Token.', $start);
                $this->jsonCron(['error' => 'Ungültiger Token.']);
                return;
            }

            if ($noTokenInRequest && !$tokenJustCreated) {
                $this->cronLog(sprintf(
                    '[CRON] Dispatcher: kein Token im Request (Self-Healing-Modus). Cron-URL bitte mit ?token=%s&tid=... konfigurieren.',
                    $expectedToken
                ));
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
                ],
                'smart_reminders' => [
                    'schedule' => '0 9 * * *', // Täglich um 09:00
                    'interval_seconds' => 86400,
                    'endpoint' => '/portal/cron/smart-erinnerungen'
                ]
            ];

            $results = [];

            foreach ($jobs as $jobKey => $config) {
                $jobStart = hrtime(true);

                try {
                    // Prüfen, ob Job fällig ist
                    $lastRun = $this->db->fetchColumn(
                        "SELECT created_at FROM cron_dispatcher_log WHERE job_key = ? ORDER BY created_at DESC LIMIT 1",
                        [$jobKey]
                    );

                    $isDue = true;
                    if ($lastRun) {
                        $lastRunTime = strtotime((string)$lastRun);
                        $nextRunTime = $lastRunTime + (int)$config['interval_seconds'];
                        $isDue = time() >= $nextRunTime;
                    }

                    if (!$isDue) {
                        $results[$jobKey] = ['status' => 'skipped', 'reason' => 'Not due yet'];
                        $this->dispatcherLog($jobKey, 'skipped', 'Not due yet', $jobStart);
                        continue;
                    }

                    // Job ausführen via internen Aufruf
                    $this->cronLog("EXECUTING job: {$jobKey}");
                    $jobResult = $this->executeJob($jobKey, (string)$config['endpoint'], $tid);
                    $results[$jobKey] = $jobResult;

                    $status = !empty($jobResult['success']) ? 'success' : 'error';
                    $message = $jobResult['message'] ?? (!empty($jobResult['success']) ? 'Executed successfully' : 'Execution failed');
                    $this->dispatcherLog($jobKey, $status, $message, $jobStart);
                } catch (\Throwable $e) {
                    $errorMessage = 'Job failed: ' . $e->getMessage();
                    $results[$jobKey] = [
                        'success' => false,
                        'status' => 'error',
                        'message' => $errorMessage,
                    ];
                    $this->cronLog("[JOB EXCEPTION] {$jobKey}: " . $e->getMessage());
                    $this->dispatcherLog($jobKey, 'error', $errorMessage, $jobStart);
                }
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

    private function executeJob(string $jobKey, string $endpoint, string $tid = ''): array
    {
        $start = hrtime(true);
        $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $url = $baseUrl . $endpoint;

        // Token für den spezifischen Job – self-healing: auto-create if missing
        $tokenKeys = [
            'birthday' => 'birthday_cron_token',
            'calendar_reminders' => 'calendar_cron_secret',
            'google_sync' => 'google_sync_cron_secret',
            'tcp_reminders' => 'tcp_cron_token',
            'holiday_greetings' => 'cron_secret',
            'smart_reminders'   => 'portal_smart_reminder_token'
        ];

        $tokenKey = $tokenKeys[$jobKey] ?? '';
        $token = $tokenKey !== '' ? $this->ensureCronToken($tokenKey) : '';

        $query = [];
        if ($token !== '') {
            $query['token'] = $token;
        }
        if ($tid !== '') {
            $query['tid'] = $tid;
        }

        if (!empty($query)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query);
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
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $duration = (int)((hrtime(true) - $start) / 1_000_000);

        if ($error) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'CURL Error: ' . $error,
                'duration_ms' => $duration
            ];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error',
            'message' => "HTTP {$httpCode}",
            'duration_ms' => $duration,
            'response' => substr((string)$response, 0, 500)
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
}
