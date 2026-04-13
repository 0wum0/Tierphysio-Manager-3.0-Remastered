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
        $tenants = [];
        try {
            $tenants = $this->db->fetchAll("SELECT id, slug, domain, status FROM tenants WHERE status = 'active' ORDER BY slug");
        } catch (\Throwable) {}

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
        $this->verifyCsrf();

        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $cronJobKey = $_POST['cron_job_key'] ?? '';
        $token = trim($_POST['token'] ?? '');

        if (!$tenantId || !$cronJobKey) {
            $this->session->flash('error', 'Ungültige Anfrage.');
            $this->redirect('/admin/praxis-cron');
        }

        // Get tenant prefix
        $tenant = $this->db->fetch("SELECT slug FROM tenants WHERE id = ?", [$tenantId]);
        if (!$tenant) {
            $this->session->flash('error', 'Tenant nicht gefunden.');
            $this->redirect('/admin/praxis-cron');
        }

        $prefix = 't_' . $tenant['slug'] . '_';
        $settingsTable = $prefix . 'settings';

        // Get cronjob config
        $cronjobs = [
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
}
