<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Repositories\SettingsRepository;

class CronAdminController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly SettingsRepository $settings,
        private readonly Database $db
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    /* ─────────────────────────────────────────────────────────
       GET /admin/cronjobs
       Dashboard: show all jobs, last run, status, log
    ───────────────────────────────────────────────────────── */
    public function index(array $params = []): void
    {
        $this->requireAdmin();

        $appUrl = rtrim($this->settings->get('app_url', ''), '/');

        /* Fetch last run for each job */
        $jobs = $this->buildJobDefinitions($appUrl);
        foreach ($jobs as &$job) {
            $job['last_run'] = $this->getLastRun($job['key']);
        }
        unset($job);

        /* Recent log (last 50 entries across all jobs) */
        $log = $this->getRecentLog(50);

        $this->render('settings/cronjobs.twig', [
            'jobs'    => $jobs,
            'log'     => $log,
            'app_url' => $appUrl,
            'success' => $this->session->getFlash('success'),
            'error'   => $this->session->getFlash('error'),
        ]);
    }

    /* ─────────────────────────────────────────────────────────
       POST /admin/cronjobs/{key}/trigger
       Manually trigger a cron job via internal HTTP call
    ───────────────────────────────────────────────────────── */
    public function trigger(array $params = []): void
    {
        $this->requireAdmin();
        $this->validateCsrf();

        $key    = $params['key'] ?? '';
        $appUrl = rtrim($this->settings->get('app_url', ''), '/');
        $jobs   = $this->buildJobDefinitions($appUrl);

        $job = null;
        foreach ($jobs as $j) {
            if ($j['key'] === $key) { $job = $j; break; }
        }

        if (!$job) {
            $this->session->flash('error', 'Unbekannter Cron-Job: ' . htmlspecialchars($key));
            $this->redirect('/admin/cronjobs');
            return;
        }

        if (empty($job['token'])) {
            $this->session->flash('error', 'Cron-Job "' . $job['label'] . '": Kein Token konfiguriert. Bitte zuerst in den Einstellungen hinterlegen.');
            $this->redirect('/admin/cronjobs');
            return;
        }

        $url      = $job['url'] . '?token=' . urlencode($job['token']);
        $start    = hrtime(true);
        $response = $this->httpGet($url);
        $ms       = (int)(( hrtime(true) - $start ) / 1_000_000);

        $status  = ($response['http_code'] >= 200 && $response['http_code'] < 300) ? 'success' : 'error';
        $message = mb_substr(trim($response['body'] ?? ''), 0, 2000);

        $this->logRunInstance($key, $status, $message, $ms, 'manual');

        if ($status === 'success') {
            $this->session->flash('success', 'Cron-Job "' . $job['label'] . '" erfolgreich ausgefuehrt (' . $ms . ' ms).');
        } else {
            $this->session->flash('error', 'Cron-Job "' . $job['label'] . '" fehlgeschlagen (HTTP ' . $response['http_code'] . '): ' . mb_substr($message, 0, 200));
        }

        $this->redirect('/admin/cronjobs');
    }

    /* ─────────────────────────────────────────────────────────
       GET /admin/cronjobs/log  (JSON — for live reload)
    ───────────────────────────────────────────────────────── */
    public function logJson(array $params = []): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');
        echo json_encode($this->getRecentLog(100));
        exit;
    }

    /* ── Job definitions ──────────────────────────────────── */
    private function buildJobDefinitions(string $appUrl): array
    {
        $s = $this->settings;

        $jobs = [];

        /* 1 — Geburtstagsmail */
        $jobs[] = [
            'key'      => 'birthday',
            'label'    => 'Geburtstagsmail',
            'icon'     => '🎂',
            'desc'     => 'Versendet automatische Geburtstagsmails an Tierhalter.',
            'url'      => $appUrl . '/cron/geburtstag',
            'token'    => $s->get('birthday_cron_token', ''),
            'schedule' => '0 8 * * *',
            'schedule_label' => 'täglich 08:00 Uhr',
            'color'    => 'rgba(124,46,248,0.15)',
            'border'   => 'rgba(180,100,255,0.3)',
        ];

        /* 2 — Kalender-Erinnerungen */
        $jobs[] = [
            'key'      => 'calendar_reminders',
            'label'    => 'Kalender-Erinnerungen',
            'icon'     => '📅',
            'desc'     => 'Sendet ausstehende Terminerinnerungen per E-Mail.',
            'url'      => $appUrl . '/kalender/cron/erinnerungen',
            'token'    => $s->get('calendar_cron_secret', ''),
            'schedule' => '*/15 * * * *',
            'schedule_label' => 'alle 15 Minuten',
            'color'    => 'rgba(14,165,233,0.12)',
            'border'   => 'rgba(14,165,233,0.3)',
        ];

        /* 3 — Google Kalender Sync */
        $googleToken = $s->get('google_sync_cron_secret', '');
        if (empty($googleToken) && defined('GOOGLE_SYNC_CRON_SECRET')) {
            $googleToken = GOOGLE_SYNC_CRON_SECRET;
        }
        $jobs[] = [
            'key'      => 'google_calendar',
            'label'    => 'Google Kalender Sync',
            'icon'     => '🔄',
            'desc'     => 'Synchronisiert Termine mit Google Kalender (Push + Pull).',
            'url'      => $appUrl . '/google-kalender/cron',
            'token'    => $googleToken,
            'schedule' => '0 * * * *',
            'schedule_label' => 'stündlich',
            'color'    => 'rgba(234,179,8,0.1)',
            'border'   => 'rgba(234,179,8,0.25)',
        ];

        /* 4 — TherapyCare Erinnerungen */
        $jobs[] = [
            'key'      => 'tcp_reminders',
            'label'    => 'TherapyCare Erinnerungen',
            'icon'     => '💉',
            'desc'     => 'Verarbeitet die TherapyCare Erinnerungswarteschlange.',
            'url'      => $appUrl . '/tcp/cron/erinnerungen',
            'token'    => $s->get('tcp_cron_token', ''),
            'schedule' => '*/15 * * * *',
            'schedule_label' => 'alle 15 Minuten',
            'color'    => 'rgba(34,197,94,0.1)',
            'border'   => 'rgba(34,197,94,0.25)',
        ];

        /* 5 — Feiertagsgrüße */
        $jobs[] = [
            'key'      => 'holiday_greetings',
            'label'    => 'Feiertagsgrüße',
            'icon'     => '🎉',
            'desc'     => 'Prüft täglich ob ein Feiertags-Gruß fällig ist und sendet ihn automatisch per E-Mail.',
            'url'      => $appUrl . '/api/holiday-cron',
            'token'    => $s->get('cron_secret', ''),
            'schedule' => '0 8 * * *',
            'schedule_label' => 'täglich 08:00 Uhr',
            'color'    => 'rgba(255,165,0,0.12)',
            'border'   => 'rgba(255,165,0,0.3)',
        ];

        return $jobs;
    }

    /* ── DB helpers ───────────────────────────────────────── */
    public static function logRun(
        \App\Core\Database $db,
        string $jobKey,
        string $status,
        string $message,
        int $durationMs,
        string $triggeredBy = 'cron'
    ): void {
        try {
            $db->query(
                "INSERT INTO `{$db->prefix('cron_job_log')}` (job_key, status, message, duration_ms, triggered_by, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$jobKey, $status, mb_substr($message, 0, 2000), $durationMs, $triggeredBy]
            );
        } catch (\Throwable) {
            /* Never crash the cron over a log write */
        }
    }

    private function logRunInstance(
        string $jobKey,
        string $status,
        string $message,
        int $durationMs,
        string $triggeredBy = 'cron'
    ): void {
        self::logRun($this->db, $jobKey, $status, $message, $durationMs, $triggeredBy);
    }

    private function getLastRun(string $jobKey): ?array
    {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM `{$this->t('cron_job_log')}` WHERE job_key = ? ORDER BY created_at DESC LIMIT 1",
                [$jobKey]
            );
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getRecentLog(int $limit = 50): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM `{$this->t('cron_job_log')}` ORDER BY created_at DESC LIMIT " . $limit
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /* ── Internal HTTP GET ────────────────────────────────── */
    private function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'TierphysioManager-CronAdmin/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['http_code' => $code, 'body' => (string)$body];
    }
}
