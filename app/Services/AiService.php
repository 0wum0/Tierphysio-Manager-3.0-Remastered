<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Zentraler, providerunabhängiger KI-Textgenerierungs-Service.
 *
 * Unterstützt Groq (schnelles, kostengünstiges Hosting offener Modelle wie
 * Llama 3.3, Gemma 2, GPT-OSS — OpenAI-kompatible Chat-Completions-API) und
 * Google Gemini. Zugangsdaten werden vom SaaS-Admin verwaltet (siehe
 * Saas\Controllers\AiSettingsController) und als Config-Datei verteilt —
 * exakt das gleiche Muster wie bei der Google-Calendar-Integration
 * (siehe plugins/google-calendar-sync/GoogleApiService.php).
 *
 * Design-Prinzip: Diese Klasse darf NIEMALS eine Exception nach außen werfen.
 * Jede Methode gibt bei Fehlern/fehlender Konfiguration `null` zurück, damit
 * aufrufender Code (Controller) die Praxissoftware niemals wegen eines
 * KI-Ausfalls (Netzwerk, falscher Key, Rate-Limit) blockiert oder crasht.
 */
class AiService
{
    private const GROQ_API_URL            = 'https://api.groq.com/openai/v1/chat/completions';
    private const GEMINI_API_URL_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s';

    /** Kuratierte, in der SaaS-Admin-Auswahl angebotene Groq-Modelle. */
    public const GROQ_MODELS = [
        'llama-3.3-70b-versatile' => [
            'label'         => 'Llama 3.3 70B Versatile',
            'recommended'   => true,
            'description'   => 'Beste Balance aus Qualität und Geschwindigkeit. Ideal für alle Aufgaben.',
        ],
        'llama-3.1-8b-instant' => [
            'label'         => 'Llama 3.1 8B Instant',
            'recommended'   => false,
            'description'   => 'Sehr schnell und günstig — für einfache, kurze Aufgaben.',
        ],
        'openai/gpt-oss-120b' => [
            'label'         => 'GPT-OSS 120B',
            'recommended'   => false,
            'description'   => 'Großes Open-Weight-Modell für komplexere Aufgaben.',
        ],
        'openai/gpt-oss-20b' => [
            'label'         => 'GPT-OSS 20B',
            'recommended'   => false,
            'description'   => 'Kleineres Open-Weight-Modell, schnell und günstig.',
        ],
        'gemma2-9b-it' => [
            'label'         => 'Gemma 2 9B',
            'recommended'   => false,
            'description'   => 'Kompaktes Google-Modell, gut für einfache strukturierte Texte.',
        ],
        'deepseek-r1-distill-llama-70b' => [
            'label'         => 'DeepSeek R1 Distill Llama 70B',
            'recommended'   => false,
            'description'   => 'Reasoning-Modell — gründlicher bei komplexen Zusammenhängen, etwas langsamer.',
        ],
    ];

    private const DEFAULT_GROQ_MODEL   = 'llama-3.3-70b-versatile';
    private const DEFAULT_GEMINI_MODEL = 'gemini-2.0-flash';

    private string $groqApiKey = '';
    private string $geminiApiKey = '';
    private string $defaultProvider = 'gemini';
    private string $groqModel = self::DEFAULT_GROQ_MODEL;
    private string $geminiModel = self::DEFAULT_GEMINI_MODEL;
    private bool $configLoaded = false;

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Liest die zentral vom SaaS-Admin verteilte Config-Datei.
     * Fehlt die Datei (z. B. weil noch keine Keys hinterlegt wurden) bleibt
     * der Service einfach "nicht konfiguriert" — kein Fehler, kein Absturz.
     */
    private function loadConfig(): void
    {
        try {
            $configPath = dirname(__DIR__, 2) . '/saas-platform/storage/config/ai.php';
            if (!file_exists($configPath)) {
                return;
            }
            $config = require $configPath;
            if (!is_array($config)) {
                return;
            }
            $this->groqApiKey      = (string)($config['groq_api_key'] ?? '');
            $this->geminiApiKey    = (string)($config['gemini_api_key'] ?? '');
            $this->defaultProvider = in_array($config['default_provider'] ?? '', ['groq', 'gemini'], true)
                ? $config['default_provider']
                : 'gemini';
            $this->groqModel   = trim((string)($config['groq_model'] ?? '')) !== ''
                ? (string)$config['groq_model'] : self::DEFAULT_GROQ_MODEL;
            $this->geminiModel = trim((string)($config['gemini_model'] ?? '')) !== ''
                ? (string)$config['gemini_model'] : self::DEFAULT_GEMINI_MODEL;
            $this->configLoaded = true;
        } catch (\Throwable $e) {
            error_log('[AiService loadConfig] ' . $e->getMessage());
        }
    }

    /** True, wenn mindestens ein Provider mit API-Key hinterlegt ist. */
    public function isConfigured(): bool
    {
        return $this->groqApiKey !== '' || $this->geminiApiKey !== '';
    }

    public function isProviderConfigured(string $provider): bool
    {
        return match ($provider) {
            'groq'   => $this->groqApiKey !== '',
            'gemini' => $this->geminiApiKey !== '',
            default  => false,
        };
    }

    public function getDefaultProvider(): string
    {
        return $this->defaultProvider;
    }

    /**
     * Generiert Text aus System- und User-Prompt.
     *
     * Nutzt den konfigurierten Standard-Provider; ist dieser nicht
     * eingerichtet, wird automatisch auf den jeweils anderen Provider
     * ausgewichen (sofern konfiguriert). Gibt `null` zurück, wenn kein
     * Provider verfügbar ist oder der Aufruf fehlschlägt — wirft nie.
     */
    public function generateText(string $systemPrompt, string $userPrompt, ?string $provider = null): ?string
    {
        $provider = $provider ?? $this->defaultProvider;

        if (!$this->isProviderConfigured($provider)) {
            $fallback = $provider === 'groq' ? 'gemini' : 'groq';
            if (!$this->isProviderConfigured($fallback)) {
                return null;
            }
            $provider = $fallback;
        }

        try {
            $result = match ($provider) {
                'groq'   => $this->callGroq($systemPrompt, $userPrompt),
                'gemini' => $this->callGemini($systemPrompt, $userPrompt),
                default  => null,
            };
        } catch (\Throwable $e) {
            error_log('[AiService generateText] ' . $e->getMessage());
            return null;
        }

        return $result !== null ? trim($result) : null;
    }

    private function callGroq(string $systemPrompt, string $userPrompt): ?string
    {
        $payload = [
            'model'       => $this->groqModel,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.4,
        ];

        $ch = curl_init(self::GROQ_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->groqApiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT    => 30,
        ]);
        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            error_log('[AiService callGroq] cURL: ' . $err);
            return null;
        }
        if ($status < 200 || $status >= 300) {
            error_log('[AiService callGroq] HTTP ' . $status . ': ' . substr((string)$raw, 0, 500));
            return null;
        }

        $data = json_decode((string)$raw, true);
        $text = $data['choices'][0]['message']['content'] ?? null;
        return is_string($text) ? $text : null;
    }

    private function callGemini(string $systemPrompt, string $userPrompt): ?string
    {
        $url = sprintf(self::GEMINI_API_URL_TEMPLATE, $this->geminiModel, $this->geminiApiKey);

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
            ],
            'generationConfig' => ['temperature' => 0.4],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            error_log('[AiService callGemini] cURL: ' . $err);
            return null;
        }
        if ($status < 200 || $status >= 300) {
            error_log('[AiService callGemini] HTTP ' . $status . ': ' . substr((string)$raw, 0, 500));
            return null;
        }

        $data = json_decode((string)$raw, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        return is_string($text) ? $text : null;
    }
}
