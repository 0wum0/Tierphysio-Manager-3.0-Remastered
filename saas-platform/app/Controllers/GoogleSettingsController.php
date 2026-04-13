<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Database;
use Saas\Repositories\ActivityLogRepository;

class GoogleSettingsController extends Controller
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

        $flat = [];
        try {
            $rows = $this->db->fetchAll("SELECT `key`, `value` FROM saas_settings");
            foreach ($rows as $r) {
                $flat[$r['key']] = $r['value'];
            }
        } catch (\Throwable) {}

        $isConfigured = !empty($flat['google_client_id']) && !empty($flat['google_client_secret']);

        $this->render('admin/google-settings/index.twig', [
            'page_title'    => 'Google Kalender API',
            'active_nav'    => 'google_settings',
            'settings'      => $flat,
            'is_configured' => $isConfigured,
        ]);
    }

    public function update(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $this->setSetting('google_client_id', trim($_POST['google_client_id'] ?? ''));
        $this->setSetting('google_client_secret', trim($_POST['google_client_secret'] ?? ''));
        $this->setSetting('google_redirect_uri', trim($_POST['google_redirect_uri'] ?? ''));
        $this->setSetting('google_cron_secret', trim($_POST['google_cron_secret'] ?? ''));

        // Write to config file for plugin access
        $configPath = dirname(__DIR__, 2) . '/storage/config/google.php';
        $configDir = dirname($configPath);
        if (!is_dir($configDir)) {
            @mkdir($configDir, 0755, true);
        }
        $configContent = "<?php\nreturn [\n";
        $configContent .= "    'client_id'     => '" . addslashes(trim($_POST['google_client_id'] ?? '')) . "',\n";
        $configContent .= "    'client_secret' => '" . addslashes(trim($_POST['google_client_secret'] ?? '')) . "',\n";
        $configContent .= "    'redirect_uri'  => '" . addslashes(trim($_POST['google_redirect_uri'] ?? '')) . "',\n";
        $configContent .= "    'cron_secret'   => '" . addslashes(trim($_POST['google_cron_secret'] ?? '')) . "',\n";
        $configContent .= "];\n";
        file_put_contents($configPath, $configContent);

        $actor = $this->session->get('saas_user') ?? 'admin';
        $this->log->log('settings.google.update', $actor, 'settings', null, 'Google API Einstellungen aktualisiert');

        $this->session->flash('success', 'Google API Einstellungen gespeichert.');
        $this->redirect('/admin/google-settings');
    }

    public function testConnection(array $params = []): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        try {
            $clientId = $this->getSetting('google_client_id');
            $clientSecret = $this->getSetting('google_client_secret');

            if (!$clientId || !$clientSecret) {
                echo json_encode(['ok' => false, 'message' => 'Client ID oder Secret fehlt.']);
                return;
            }

            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'grant_type'    => 'authorization_code',
                    'code'          => 'test',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri'  => 'https://example.com',
                ]),
                CURLOPT_TIMEOUT        => 10,
            ]);
            $res    = json_decode(curl_exec($ch) ?: '', true) ?? [];
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $error = $res['error'] ?? '';
            $errorDesc = $res['error_description'] ?? '';

            if ($error === 'invalid_client') {
                echo json_encode(['ok' => false, 'message' => 'Client ID oder Secret ungültig.']);
            } elseif ($error === 'invalid_grant' || $error === 'redirect_uri_mismatch') {
                echo json_encode(['ok' => true, 'message' => 'Credentials sind gültig! Google API erreichbar.']);
            } else {
                echo json_encode(['ok' => false, 'message' => 'Verbindung fehlgeschlagen: ' . $errorDesc]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

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
}
