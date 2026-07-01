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
    /**
     * Kuratierte Groq-Modelle für die Auswahl im SaaS-Admin.
     *
     * WICHTIG: saas-platform und die Praxis-App (`app/`) sind zwei getrennte
     * Composer-Projekte mit eigenem Autoloading (Saas\ vs. App\) — diese
     * Liste ist deshalb bewusst dupliziert zu `App\Services\self::GROQ_MODELS`
     * und muss bei Änderungen dort synchron gehalten werden.
     */
    public const GROQ_MODELS = [
        'llama-3.3-70b-versatile' => [
            'label'       => 'Llama 3.3 70B Versatile',
            'recommended' => true,
            'description' => 'Beste Balance aus Qualität und Geschwindigkeit. Ideal für alle Aufgaben.',
        ],
        'llama-3.1-8b-instant' => [
            'label'       => 'Llama 3.1 8B Instant',
            'recommended' => false,
            'description' => 'Sehr schnell und günstig — für einfache, kurze Aufgaben.',
        ],
        'openai/gpt-oss-120b' => [
            'label'       => 'GPT-OSS 120B',
            'recommended' => false,
            'description' => 'Großes Open-Weight-Modell für komplexere Aufgaben.',
        ],
        'openai/gpt-oss-20b' => [
            'label'       => 'GPT-OSS 20B',
            'recommended' => false,
            'description' => 'Kleineres Open-Weight-Modell, schnell und günstig.',
        ],
        'gemma2-9b-it' => [
            'label'       => 'Gemma 2 9B',
            'recommended' => false,
            'description' => 'Kompaktes Google-Modell, gut für einfache strukturierte Texte.',
        ],
        'deepseek-r1-distill-llama-70b' => [
            'label'       => 'DeepSeek R1 Distill Llama 70B',
            'recommended' => false,
            'description' => 'Reasoning-Modell — gründlicher bei komplexen Zusammenhängen, etwas langsamer.',
        ],
    ];

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

        $isConfigured = !empty($flat['ai_groq_api_key']) || !empty($flat['ai_gemini_api_key']);

        $this->render('admin/ai-settings/index.twig', [
            'page_title'    => 'KI-Integration (Groq / Gemini)',
            'active_nav'    => 'ai_settings',
            'settings'      => $flat,
            'is_configured' => $isConfigured,
            'groq_models'   => self::GROQ_MODELS,
        ]);
    }

    public function update(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $groqKey     = trim($_POST['ai_groq_api_key'] ?? '');
        $geminiKey   = trim($_POST['ai_gemini_api_key'] ?? '');
        $provider    = in_array($_POST['ai_default_provider'] ?? '', ['groq', 'gemini'], true)
            ? $_POST['ai_default_provider'] : 'gemini';

        // Modell nur aus der kuratierten Liste übernehmen — verhindert ungültige/veraltete
        // Modell-Slugs durch manuelle Manipulation des POST-Bodys.
        $groqModelInput = trim($_POST['ai_groq_model'] ?? '');
        $groqModel      = array_key_exists($groqModelInput, self::GROQ_MODELS)
            ? $groqModelInput : 'llama-3.3-70b-versatile';
        $geminiModel    = trim($_POST['ai_gemini_model'] ?? '') ?: 'gemini-2.0-flash';

        $this->setSetting('ai_groq_api_key', $groqKey);
        $this->setSetting('ai_gemini_api_key', $geminiKey);
        $this->setSetting('ai_default_provider', $provider);
        $this->setSetting('ai_groq_model', $groqModel);
        $this->setSetting('ai_gemini_model', $geminiModel);

        // Config-Datei für Praxis-App schreiben (gleiches Muster wie google.php)
        $configPath = dirname(__DIR__, 2) . '/storage/config/ai.php';
        $configDir  = dirname($configPath);
        if (!is_dir($configDir)) {
            @mkdir($configDir, 0755, true);
        }
        $configContent  = "<?php\nreturn [\n";
        $configContent .= "    'groq_api_key'      => '" . addslashes($groqKey) . "',\n";
        $configContent .= "    'gemini_api_key'    => '" . addslashes($geminiKey) . "',\n";
        $configContent .= "    'default_provider'  => '" . addslashes($provider) . "',\n";
        $configContent .= "    'groq_model'        => '" . addslashes($groqModel) . "',\n";
        $configContent .= "    'gemini_model'      => '" . addslashes($geminiModel) . "',\n";
        $configContent .= "];\n";
        file_put_contents($configPath, $configContent);

        $actor = $this->session->get('saas_user') ?? 'admin';
        $this->log->log('settings.ai.update', $actor, 'settings', null, 'KI-API-Einstellungen aktualisiert');

        $this->session->flash('success', 'KI-Einstellungen gespeichert.');
        $this->redirect('/admin/ai-settings');
    }

    public function testGroq(array $params = []): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        try {
            $key = $this->getSetting('ai_groq_api_key');
            if (!$key) {
                echo json_encode(['ok' => false, 'message' => 'Kein Groq API-Key konfiguriert.']);
                return;
            }
            $model = $this->getSetting('ai_groq_model') ?: 'llama-3.3-70b-versatile';

            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
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
                echo json_encode(['ok' => true, 'message' => 'Verbindung erfolgreich! Groq API erreichbar.']);
            } else {
                $err = $res['error']['message'] ?? ($res['error'] ?? 'Unbekannter Fehler');
                echo json_encode(['ok' => false, 'message' => "Groq Fehler (HTTP {$status}): {$err}"]);
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
