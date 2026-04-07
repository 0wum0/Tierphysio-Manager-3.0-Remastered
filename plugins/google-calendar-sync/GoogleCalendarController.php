<?php

declare(strict_types=1);

namespace Plugins\GoogleCalendarSync;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;

class GoogleCalendarController extends Controller
{
    private GoogleCalendarRepository $repo;
    private GoogleApiService         $api;
    private GoogleSyncService        $sync;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo = new GoogleCalendarRepository($db);
        $this->api  = new GoogleApiService($this->repo);
        $this->sync = new GoogleSyncService($this->repo, $this->api, $db);
    }

    /* ── GET /google-kalender ── */
    public function index(array $params = []): void
    {
        $connection  = $this->repo->getConnection();
        $recentLogs  = $this->repo->getRecentLogs(15);
        $lastSuccess = $this->repo->getLastSuccessfulSync();
        $lastError   = $this->repo->getLastError();
        $syncedCount = $this->repo->getRecentSyncedCount(24);

        $calendars = [];
        if ($connection && !empty($connection['access_token'])) {
            try {
                $calendars = $this->api->listCalendars($connection);
            } catch (\Throwable) {}
        }

        $this->render('@google-calendar-sync/admin_index.twig', [
            'page_title'   => 'Google Kalender Sync',
            'connection'   => $connection,
            'calendars'    => $calendars,
            'recent_logs'  => $recentLogs,
            'last_success' => $lastSuccess,
            'last_error'   => $lastError,
            'synced_count' => $syncedCount,
            'is_configured'=> $this->api->isConfigured(),
            'csrf_token'   => $this->session->generateCsrfToken(),
            'success'      => $this->session->getFlash('success'),
            'error'        => $this->session->getFlash('error'),
        ]);
    }

    /* ── POST /google-kalender/pull ── */
    public function pullFromGoogle(array $params = []): void
    {
        $this->validateCsrf();
        $result = $this->sync->pullFromGoogle();
        if ($result['success']) {
            $this->session->flash('success', $result['message']);
        } else {
            $this->session->flash('error', $result['message']);
        }
        $this->redirect('/google-kalender');
    }

    /* ── GET /google-kalender/verbinden ── */
    public function connect(array $params = []): void
    {
        if (!$this->api->isConfigured()) {
            $this->session->flash('error', 'Google API Zugangsdaten nicht konfiguriert. Bitte GOOGLE_CLIENT_ID und GOOGLE_CLIENT_SECRET in der .env setzen.');
            $this->redirect('/google-kalender');
            return;
        }

        $state   = bin2hex(random_bytes(16));
        $this->session->set('google_oauth_state', $state);
        $authUrl = $this->api->getAuthUrl($state);
        $this->redirect($authUrl);
    }

    /* ── GET /google-kalender/callback ── */
    public function oauthCallback(array $params = []): void
    {
        $code  = $_GET['code']  ?? '';
        $state = $_GET['state'] ?? '';
        $error = $_GET['error'] ?? '';

        if ($error) {
            $this->session->flash('error', 'Google hat die Verbindung abgelehnt: ' . htmlspecialchars($error));
            $this->redirect('/google-kalender');
            return;
        }

        $savedState = $this->session->get('google_oauth_state');
        if (!$state || !$savedState || !hash_equals($savedState, $state)) {
            $this->session->flash('error', 'Ungültiger OAuth-State. Bitte erneut versuchen.');
            $this->redirect('/google-kalender');
            return;
        }

        $this->session->remove('google_oauth_state');

        try {
            $tokens   = $this->api->exchangeCodeForTokens($code);
            $userInfo = $this->api->getUserInfo($tokens['access_token']);

            $expiresAt = date('Y-m-d H:i:s', time() + (int)($tokens['expires_in'] ?? 3600));

            $this->repo->upsertConnection([
                'google_email'    => $userInfo['email'] ?? null,
                'access_token'    => $tokens['access_token'],
                'refresh_token'   => $tokens['refresh_token'] ?? null,
                'token_expires_at'=> $expiresAt,
                'calendar_id'     => 'primary',
                'sync_enabled'    => 1,
                'auto_sync'       => 1,
                'skip_waitlist'   => 1,
            ]);

            $connection = $this->repo->getConnection();
            if ($connection) {
                $this->repo->log((int)$connection['id'], 'auth', true, 'Google Konto verbunden: ' . ($userInfo['email'] ?? ''));
            }

            $this->session->flash('success', 'Google Konto erfolgreich verbunden: ' . ($userInfo['email'] ?? ''));
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Verbindung fehlgeschlagen: ' . $e->getMessage());
        }

        $this->redirect('/google-kalender');
    }

    /* ── POST /google-kalender/trennen ── */
    public function disconnect(array $params = []): void
    {
        $this->validateCsrf();
        $connection = $this->repo->getConnection();
        if ($connection) {
            $this->repo->log((int)$connection['id'], 'auth', true, 'Google Verbindung getrennt.');
            $this->repo->deleteConnection((int)$connection['id']);
        }
        $this->session->flash('success', 'Google Konto wurde getrennt.');
        $this->redirect('/google-kalender');
    }

    /* ── POST /google-kalender/einstellungen ── */
    public function saveSettings(array $params = []): void
    {
        $this->validateCsrf();
        $connection = $this->repo->getConnection();
        if (!$connection) {
            $this->session->flash('error', 'Kein Google Konto verbunden.');
            $this->redirect('/google-kalender');
            return;
        }

        $this->repo->updateConnection((int)$connection['id'], [
            'calendar_id'              => $this->post('calendar_id', 'primary'),
            'sync_enabled'             => (int)(bool)$this->post('sync_enabled', 0),
            'auto_sync'                => (int)(bool)$this->post('auto_sync', 0),
            'skip_waitlist'            => (int)(bool)$this->post('skip_waitlist', 1),
            'default_reminder_minutes' => ($this->post('default_reminder_minutes') !== '' && $this->post('default_reminder_minutes') !== null)
                                          ? (int)$this->post('default_reminder_minutes')
                                          : null,
        ]);

        $this->session->flash('success', 'Einstellungen gespeichert.');
        $this->redirect('/google-kalender');
    }

    /* ── POST /google-kalender/test-sync ── */
    public function testSync(array $params = []): void
    {
        $this->validateCsrf();
        $result = $this->sync->testSync();
        if ($result['success']) {
            $this->session->flash('success', $result['message']);
        } else {
            $this->session->flash('error', $result['message']);
        }
        $this->redirect('/google-kalender');
    }

    /* ── POST /google-kalender/bulk-sync ── */
    public function bulkSync(array $params = []): void
    {
        $this->validateCsrf();
        $result = $this->sync->bulkSyncAll();
        $this->session->flash(
            'success',
            "Bulk-Sync abgeschlossen: {$result['success']} synchronisiert, {$result['failed']} Fehler."
        );
        $this->redirect('/google-kalender');
    }

    /* ── POST /google-kalender/kalender-auswaehlen ── */
    public function selectCalendar(array $params = []): void
    {
        $this->validateCsrf();
        $connection = $this->repo->getConnection();
        if (!$connection) {
            $this->redirect('/google-kalender');
            return;
        }

        $calendarId   = $this->post('calendar_id', 'primary');
        $calendarName = $this->post('calendar_name', '');

        $this->repo->updateConnection((int)$connection['id'], [
            'calendar_id'   => $calendarId,
            'calendar_name' => $calendarName,
        ]);

        $this->session->flash('success', 'Kalender ausgewählt: ' . htmlspecialchars($calendarName ?: $calendarId));
        $this->redirect('/google-kalender');
    }

    /* ── POST /google-kalender/sync/termin/{id} (internal JS bridge) ── */
    public function syncAppointment(array $params = []): void
    {
        $this->validateCsrf();
        $id     = (int)($params['id'] ?? 0);
        $action = $this->post('action', 'upsert'); /* create | update | delete | upsert */

        if (!$id) {
            $this->json(['success' => false, 'error' => 'Missing id'], 422);
            return;
        }

        try {
            match ($action) {
                'create' => $this->sync->syncCreated($id),
                'update' => $this->sync->syncUpdated($id),
                'delete' => $this->sync->syncDeleted($id),
                default  => $this->sync->syncUpdated($id),
            };
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /* ── GET /google-kalender/cron (secured by token) ── */
    public function cron(array $params = []): void
    {
        $start  = hrtime(true);
        $token  = $_GET['token'] ?? ($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
        $secret = defined('GOOGLE_SYNC_CRON_SECRET') ? GOOGLE_SYNC_CRON_SECRET : '';

        if (empty($secret) || !hash_equals($secret, $token)) {
            http_response_code(403);
            $this->googleDbLog('google_calendar', 'error', 'Unauthorized token.', $start);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        try {
            $pushResult = $this->sync->bulkSyncAll();
            $pullResult = $this->sync->pullFromGoogle();
            $msg = 'push=' . json_encode($pushResult) . ' pull=' . json_encode($pullResult);
            $this->googleDbLog('google_calendar', 'success', $msg, $start);
            header('Content-Type: application/json');
            echo json_encode([
                'ok'   => true,
                'time' => date('c'),
                'push' => $pushResult,
                'pull' => $pullResult,
            ]);
        } catch (\Throwable $e) {
            $this->googleDbLog('google_calendar', 'error', $e->getMessage(), $start);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function googleDbLog(string $jobKey, string $status, string $message, int $startHrtime): void
    {
        $ms = (int)((hrtime(true) - $startHrtime) / 1_000_000);
        try {
            $db = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $db->query(
                "INSERT INTO `{$db->prefix('cron_job_log')}` (job_key, status, message, duration_ms, triggered_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [$jobKey, $status, mb_substr($message, 0, 2000), $ms, 'cron']
            );
        } catch (\Throwable) {}
    }
}
