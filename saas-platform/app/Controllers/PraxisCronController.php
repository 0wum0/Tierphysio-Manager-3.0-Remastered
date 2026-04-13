<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Database;
use Saas\Repositories\ActivityLogRepository;

class PraxisCronController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        private Database $db,
        private ActivityLogRepository $log
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        // Get all tenants
        $tenants = $this->db->fetchAll("SELECT id, uuid, practice_name, email, status FROM tenants ORDER BY practice_name");

        // Define available cronjobs
        $cronjobs = [
            'birthday' => [
                'name' => 'Geburtstagsmail',
                'description' => 'Sendet automatisch Glückwunschmails an Tierhalter, deren Tier heute Geburtstag hat.',
                'schedule' => '0 8 * * *',
                'schedule_readable' => 'Täglich um 08:00 Uhr',
                'endpoint' => '/cron/geburtstag',
                'token_field' => 'birthday_cron_token',
                'icon' => '🎂'
            ],
            'calendar_reminders' => [
                'name' => 'Kalender-Erinnerungen',
                'description' => 'Sendet automatische E-Mail-Erinnerungen für anstehende Termine.',
                'schedule' => '*/15 * * * *',
                'schedule_readable' => 'Alle 15 Minuten',
                'endpoint' => '/kalender/cron/erinnerungen',
                'token_field' => 'calendar_cron_secret',
                'icon' => '📅'
            ],
            'google_calendar' => [
                'name' => 'Google Kalender Sync',
                'description' => 'Synchronisiert Tierphysio-Termine in Google Kalender.',
                'schedule' => '0 * * * *',
                'schedule_readable' => 'Stündlich',
                'endpoint' => '/google-kalender/cron',
                'token_field' => 'google_sync_cron_secret',
                'icon' => '🔄'
            ],
            'tcp_reminders' => [
                'name' => 'TherapyCare Erinnerungen',
                'description' => 'Erinnerungen für TherapyCare Integration.',
                'schedule' => '*/15 * * * *',
                'schedule_readable' => 'Alle 15 Minuten',
                'endpoint' => '/tcp/cron/erinnerungen',
                'token_field' => 'tcp_cron_token',
                'icon' => '💉'
            ],
            'holiday_greetings' => [
                'name' => 'Feiertagsgrüße',
                'description' => 'Sendet automatische Feiertagsgrüße.',
                'schedule' => '0 8 * * *',
                'schedule_readable' => 'Täglich um 08:00 Uhr',
                'endpoint' => '/api/holiday-cron',
                'token_field' => 'cron_secret',
                'icon' => '🎉'
            ]
        ];

        $this->render('admin/praxis-cron/index.twig', [
            'page_title' => 'Praxis Cronjobs',
            'active_nav' => 'praxis_cron',
            'tenants' => $tenants,
            'cronjobs' => $cronjobs,
        ]);
    }

    public function updateToken(array $params = []): void
    {
        $this->requireAuth();
        // CSRF deaktiviert für API-Endpunkt

        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $cronJobKey = $_POST['cron_job_key'] ?? '';
        $token = trim($_POST['token'] ?? '');

        if (!$tenantId || !$cronJobKey) {
            $this->session->flash('error', 'Ungültige Anfrage.');
            $this->redirect('/admin/praxis-cron');
        }

        // Get tenant prefix
        $tenant = $this->db->fetch("SELECT uuid FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenant) {
            $this->session->flash('error', 'Tenant nicht gefunden.');
            $this->redirect('/admin/praxis-cron');
        }

        $prefix = 't_' . $tenant['uuid'] . '_';
        $settingsTable = $prefix . 'settings';

        // Get cronjob config
        $cronjobs = [
            'dispatcher' => 'cron_dispatcher_token',
            'birthday' => 'birthday_cron_token',
            'calendar_reminders' => 'calendar_cron_secret',
            'google_calendar' => 'google_sync_cron_secret',
            'tcp_reminders' => 'tcp_cron_token',
            'holiday_greetings' => 'cron_secret'
        ];

        if (!isset($cronjobs[$cronJobKey])) {
            $this->session->flash('error', 'Ungültiger Cronjob.');
            $this->redirect('/admin/praxis-cron');
        }

        $settingKey = $cronjobs[$cronJobKey];

        // Update token in tenant settings
        try {
            $this->db->query("INSERT INTO {$settingsTable} (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?", [$settingKey, $token, $token]);

            $actor = $this->session->get('saas_user') ?? 'admin';
            $this->log->log('praxis.cron.update_token', $actor, 'tenant', $tenantId, "Cron token updated for {$cronJobKey} in tenant {$tenant['slug']}");

            $this->session->flash('success', 'Cron-Token aktualisiert.');
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Fehler beim Speichern: ' . $e->getMessage());
        }

        $this->redirect('/admin/praxis-cron');
    }

    public function runNow(array $params = []): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $tenantId = (int)($params['tenant_id'] ?? $_GET['tenant_id'] ?? 0);
        $cronJobKey = $params['cron_job_key'] ?? $_GET['cron_job_key'] ?? '';

        if (!$tenantId || !$cronJobKey) {
            echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage.']);
            exit;
        }

        // Get tenant
        $tenant = $this->db->fetch("SELECT uuid, email FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenant) {
            echo json_encode(['success' => false, 'error' => 'Tenant nicht gefunden.']);
            exit;
        }

        // Get cronjob endpoint
        $cronjobs = [
            'dispatcher' => '/cron/dispatcher',
            'birthday' => '/cron/geburtstag',
            'calendar_reminders' => '/kalender/cron/erinnerungen',
            'google_calendar' => '/google-kalender/cron',
            'tcp_reminders' => '/tcp/cron/erinnerungen',
            'holiday_greetings' => '/api/holiday-cron'
        ];

        if (!isset($cronjobs[$cronJobKey])) {
            echo json_encode(['success' => false, 'error' => 'Ungültiger Cronjob.']);
            exit;
        }

        $endpoint = $cronjobs[$cronJobKey];
        // Extract domain from email
        $emailParts = explode('@', $tenant['email']);
        $domain = isset($emailParts[1]) ? $emailParts[1] : 'example.com';
        $url = 'https://' . $domain . $endpoint;

        // Get token from tenant settings
        $prefix = 't_' . $tenant['uuid'] . '_';
        $settingsTable = $prefix . 'settings';

        $tokenFields = [
            'dispatcher' => 'cron_dispatcher_token',
            'birthday' => 'birthday_cron_token',
            'calendar_reminders' => 'calendar_cron_secret',
            'google_calendar' => 'google_sync_cron_secret',
            'tcp_reminders' => 'tcp_cron_token',
            'holiday_greetings' => 'cron_secret'
        ];

        $tokenField = $tokenFields[$cronJobKey];
        $token = $this->db->fetchColumn("SELECT `value` FROM {$settingsTable} WHERE `key` = ?", [$tokenField]);

        if ($token) {
            $url .= '?token=' . $token;
        }

        // Execute request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            echo json_encode([
                'success' => false,
                'error' => 'CURL Fehler: ' . $error,
                'url' => $url
            ]);
            exit;
        }

        $actor = $this->session->get('saas_user') ?? 'admin';
        $this->log->log('praxis.cron.run_now', $actor, 'tenant', $tenantId, "Cronjob {$cronJobKey} executed for tenant {$tenant['slug']} - HTTP {$httpCode}");

        echo json_encode([
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => substr($response, 0, 500),
            'url' => $url
        ]);
        exit;
    }
}
