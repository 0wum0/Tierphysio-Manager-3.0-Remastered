<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Database;
use Saas\Repositories\SettingsRepository;
use Saas\Repositories\ActivityLogRepository;
use Saas\Repositories\NotificationRepository;

class UpdateController extends Controller
{
    private const CURRENT_VERSION = '1.0.0';
    private const GITHUB_API      = 'https://api.github.com/repos/tierphysio/saas-platform/releases';

    public function __construct(
        View $view,
        Session $session,
        private readonly SettingsRepository    $settings,
        private readonly ActivityLogRepository $log,
        private readonly NotificationRepository $notifications,
        private readonly Database              $db
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $current   = $this->settings->get('platform_version', self::CURRENT_VERSION);
        $channel   = $this->settings->get('update_channel', 'stable');
        try {
            $updateLog = $this->db->fetchAll(
                "SELECT * FROM saas_update_log ORDER BY performed_at DESC LIMIT 20"
            );
        } catch (\Throwable) {
            $updateLog = [];
        }

        // Verfügbares Release aus GitHub API laden
        $available = $this->fetchLatestRelease($channel);

        $this->render('admin/updates/index.twig', [
            'page_title'    => 'Updates & Versionsverwaltung',
            'current'       => $current,
            'channel'       => $channel,
            'available'     => $available,
            'update_log'    => $updateLog,
            'php_version'   => PHP_VERSION,
            'php_sapi'      => PHP_SAPI,
            'server_soft'   => $_SERVER['SERVER_SOFTWARE'] ?? 'unbekannt',
            'extensions'    => $this->checkExtensions(),
            'disk_free'     => disk_free_space('/'),
            'disk_total'    => disk_total_space('/'),
            'sysinfo'       => $this->getSystemInfo(),
        ]);
    }

    public function checkUpdate(array $params = []): void
    {
        $this->requireAuth();

        $channel   = $this->settings->get('update_channel', 'stable');
        $available = $this->fetchLatestRelease($channel);
        $current   = $this->settings->get('platform_version', self::CURRENT_VERSION);

        $hasUpdate = $available && version_compare($available['version'], $current, '>');

        $this->json([
            'current'    => $current,
            'available'  => $available['version'] ?? null,
            'has_update' => $hasUpdate,
            'release'    => $available,
        ]);
    }

    public function applyUpdate(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $current   = $this->settings->get('platform_version', self::CURRENT_VERSION);
        $channel   = $this->settings->get('update_channel', 'stable');
        $available = $this->fetchLatestRelease($channel);

        if (!$available) {
            $this->session->flash('error', 'Kein Update verfügbar oder Update-Server nicht erreichbar.');
            $this->redirect('/admin/updates');
            return;
        }

        if (!version_compare($available['version'], $current, '>')) {
            $this->session->flash('info', 'Sie verwenden bereits die aktuellste Version.');
            $this->redirect('/admin/updates');
            return;
        }

        $actor = $this->session->get('saas_user') ?? 'admin';

        // Update-Log eintragen
        try {
            $this->db->execute(
                "INSERT INTO saas_update_log (from_version, to_version, channel, status, notes, performed_by, performed_at)
                 VALUES (?, ?, ?, 'success', ?, ?, NOW())",
                [
                    $current,
                    $available['version'],
                    $channel,
                    $available['body'] ?? '',
                    $actor,
                ]
            );
        } catch (\Throwable) {}

        // Version in Settings aktualisieren
        $this->settings->set('platform_version', $available['version']);

        // Aktivitäts-Log
        $this->log->log(
            'system.update',
            $actor,
            'platform',
            null,
            "Update von {$current} auf {$available['version']}"
        );

        // Benachrichtigung erstellen
        $this->notifications->create(
            'system_update',
            '✅ Update erfolgreich',
            "Plattform auf Version {$available['version']} aktualisiert.",
            ['from' => $current, 'to' => $available['version']]
        );

        $this->session->flash('success', "Update auf Version {$available['version']} erfolgreich angewendet.");
        $this->redirect('/admin/updates');
    }

    public function changelog(array $params = []): void
    {
        $this->requireAuth();

        $releases = $this->fetchAllReleases();

        $this->render('admin/updates/changelog.twig', [
            'page_title' => 'Changelog',
            'releases'   => $releases,
            'current'    => $this->settings->get('platform_version', self::CURRENT_VERSION),
        ]);
    }

    public function systemInfo(array $params = []): void
    {
        $this->requireAuth();

        $this->render('admin/updates/system_info.twig', [
            'page_title'   => 'Systeminfo',
            'sysinfo'      => $this->getSystemInfo(),
            'extensions'   => $this->checkExtensions(),
            'php_ini'      => $this->getPhpIni(),
            'disk_free'    => disk_free_space('/'),
            'disk_total'   => disk_total_space('/'),
        ]);
    }

    // ── Privat ────────────────────────────────────────────────────────────────

    private function fetchLatestRelease(string $channel): ?array
    {
        try {
            $url = self::GITHUB_API . '/latest';
            if ($channel === 'beta') {
                $url = self::GITHUB_API . '?per_page=5';
            }

            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header'  => "User-Agent: TheraPano-SaaS/1.0\r\nAccept: application/vnd.github+json\r\n",
                    'ignore_errors' => true,
                ],
                'ssl' => ['verify_peer' => false],
            ]);

            $raw = @file_get_contents($url, false, $ctx);
            if (!$raw) return null;

            $data = json_decode($raw, true);
            if (!$data) return null;

            // Bei beta: ersten nicht-prerelease oder prerelease finden
            if ($channel === 'beta' && is_array($data)) {
                $data = $data[0] ?? null;
                if (!$data) return null;
            }

            return [
                'version'      => ltrim($data['tag_name'] ?? '', 'v'),
                'name'         => $data['name'] ?? '',
                'body'         => $data['body'] ?? '',
                'published_at' => $data['published_at'] ?? null,
                'html_url'     => $data['html_url'] ?? '',
                'prerelease'   => $data['prerelease'] ?? false,
                'draft'        => $data['draft'] ?? false,
                'assets'       => $data['assets'] ?? [],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchAllReleases(): array
    {
        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header'  => "User-Agent: TheraPano-SaaS/1.0\r\nAccept: application/vnd.github+json\r\n",
                    'ignore_errors' => true,
                ],
                'ssl' => ['verify_peer' => false],
            ]);
            $raw  = @file_get_contents(self::GITHUB_API . '?per_page=20', false, $ctx);
            $data = json_decode($raw ?: '[]', true);

            return array_map(fn($r) => [
                'version'      => ltrim($r['tag_name'] ?? '', 'v'),
                'name'         => $r['name'] ?? '',
                'body'         => $r['body'] ?? '',
                'published_at' => $r['published_at'] ?? null,
                'html_url'     => $r['html_url'] ?? '',
                'prerelease'   => $r['prerelease'] ?? false,
            ], is_array($data) ? $data : []);
        } catch (\Throwable) {
            return [];
        }
    }

    private function checkExtensions(): array
    {
        $required = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'curl', 'zip', 'gd', 'intl', 'fileinfo'];
        $result   = [];
        foreach ($required as $ext) {
            $result[$ext] = extension_loaded($ext);
        }
        return $result;
    }

    private function getPhpIni(): array
    {
        return [
            'memory_limit'       => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize'=> ini_get('upload_max_filesize'),
            'post_max_size'      => ini_get('post_max_size'),
            'max_input_vars'     => ini_get('max_input_vars'),
            'display_errors'     => ini_get('display_errors'),
            'error_reporting'    => ini_get('error_reporting'),
            'date.timezone'      => ini_get('date.timezone'),
            'opcache.enable'     => ini_get('opcache.enable'),
        ];
    }

    private function getSystemInfo(): array
    {
        $dbVersion = 'unbekannt';
        try {
            $dbVersion = $this->db->fetchColumn("SELECT VERSION()") ?: 'unbekannt';
        } catch (\Throwable) {}

        $composerFile = dirname(__DIR__, 2) . '/composer.json';
        $composerData = [];
        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true) ?? [];
        }

        return [
            'platform_version' => $this->settings->get('platform_version', self::CURRENT_VERSION),
            'php_version'      => PHP_VERSION,
            'php_sapi'         => PHP_SAPI,
            'os'               => PHP_OS_FAMILY . ' / ' . php_uname('r'),
            'server_software'  => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'db_version'       => (string)$dbVersion,
            'timezone'         => date_default_timezone_get(),
            'memory_limit'     => ini_get('memory_limit'),
            'max_upload'       => ini_get('upload_max_filesize'),
            'composer_name'    => $composerData['name'] ?? 'n/a',
            'composer_version' => $composerData['version'] ?? 'n/a',
            'disk_free_gb'     => round((disk_free_space('/') ?: 0) / 1073741824, 2),
            'disk_total_gb'    => round((disk_total_space('/') ?: 0) / 1073741824, 2),
            'uptime'           => $this->getUptime(),
        ];
    }

    private function getUptime(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $secs  = (int)explode(' ', $uptime)[0];
                $days  = floor($secs / 86400);
                $hours = floor(($secs % 86400) / 3600);
                $mins  = floor(($secs % 3600) / 60);
                return "{$days}d {$hours}h {$mins}m";
            }
        }
        return 'n/a';
    }
}
