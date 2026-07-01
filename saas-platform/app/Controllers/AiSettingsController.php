<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Database;
use Saas\Repositories\ActivityLogRepository;

class AiSettingsController extends Controller
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

        $isConfigured = !empty($flat['ai_grok_api_key']) || !empty($flat['ai_gemini_api_key']);

        $this->render('admin/ai-settings/index.twig', [
            'page_title'    => 'KI-Integration (Grok / Gemini)',
            'active_nav'    => 'ai_settings',
            'settings'      => $flat,
            'is_configured' => $isConfigured,
        ]);
    }

    public function update(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $grokKey     = trim($_POST['ai_grok_api_key'] ?? '');
        $geminiKey   = trim($_POST['ai_gemini_api_key'] ?? '');
        $provider    = in_array($_POST['ai_default_provider'] ?? '', ['grok', 'gemini'], true)
            ? $_POST['ai_default_provider'] : 'gemini';
        $grokModel   = trim($_POST['ai_grok_model'] ?? '') ?: 'grok-4';
        $geminiModel = trim($_POST['ai_gemini_model'] ?? '') ?: 'gemini-2.0-flash';

        $this->setSetting('ai_grok_api_key', $grokKey);
        $this->setSetting('ai_gemini_api_key', $geminiKey);
        $this->setSetting('ai_default_provider', $provider);
        $this->setSetting('ai_grok_model', $grokModel);
        $this->setSetting('ai_gemini_model', $geminiModel);

        // Config-Datei für Praxis-App schreiben (gleiches Muster wie google.php)
        $configPath = dirname(__DIR__, 2) . '/storage/config/ai.php';
        $configDir  = dirname($configPath);
        if (!is_dir($configDir)) {
            @mkdir($configDir, 0755, true);
        }
        $configContent  = "<?php\nreturn [\n";
        $configContent .= "    'grok_api_key'      => '" . addslashes($grokKey) . "',\n";
        $configContent .= "    'gemini_api_key'    => '" . addslashes($geminiKey) . "',\n";
        $configContent .= "    'default_provider'  => '" . addslashes($provider) . "',\n";
        $configContent .= "    'grok_model'        => '" . addslashes($grokModel) . "',\n";
        $configContent .= "    'gemini_model'      => '" . addslashes($geminiModel) . "',\n";
        $configContent .= "];\n";
        file_put_contents($configPath, $configContent);

        $actor = $this->session->get('saas_user') ?? 'admin';
        $this->log->log('settings.ai.update', $actor, 'settings', null, 'KI-API-Einstellungen aktualisiert');

        $this->session->flash('success', 'KI-Einstellungen gespeichert.');
        $this->redirect('/admin/ai-settings');
    }

    public function testGrok(array $params = []): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        try {
            $key = $this->getSetting('ai_grok_api_key');
            if (!$key) {
                echo json_encode(['ok' => false, 'message' => 'Kein Grok API-Key konfiguriert.']);
                return;
            }
            $model = $this->getSetting('ai_grok_model') ?: 'grok-4';

            $ch = curl_init('https://api.x.ai/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model'    => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Antworte nur mit "OK".'],
                    ],
                    'max_tokens' => 5,
                ]),
                CURLOPT_TIMEOUT => 15,
            ]);
            $res    = json_decode(curl_exec($ch) ?: '', true) ?? [];
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200 && isset($res['choices'])) {
                echo json_encode(['ok' => true, 'message' => 'Verbindung erfolgreich! Grok API erreichbar.']);
            } else {
                $err = $res['error']['message'] ?? ($res['error'] ?? 'Unbekannter Fehler');
                echo json_encode(['ok' => false, 'message' => "Grok Fehler (HTTP {$status}): {$err}"]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function testGemini(array $params = []): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        try {
            $key = $this->getSetting('ai_gemini_api_key');
            if (!$key) {
                echo json_encode(['ok' => false, 'message' => 'Kein Gemini API-Key konfiguriert.']);
                return;
            }
            $model = $this->getSetting('ai_gemini_model') ?: 'gemini-2.0-flash';
            $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($key);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode([
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => 'Antworte nur mit "OK".']]],
                    ],
                ]),
                CURLOPT_TIMEOUT => 15,
            ]);
            $res    = json_decode(curl_exec($ch) ?: '', true) ?? [];
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200 && isset($res['candidates'])) {
                echo json_encode(['ok' => true, 'message' => 'Verbindung erfolgreich! Gemini API erreichbar.']);
            } else {
                $err = $res['error']['message'] ?? 'Unbekannter Fehler';
                echo json_encode(['ok' => false, 'message' => "Gemini Fehler (HTTP {$status}): {$err}"]);
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
