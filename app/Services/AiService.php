<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Zentraler, providerunabhängiger KI-Textgenerierungs-Service.
 *
 * Unterstützt xAI Grok und Google Gemini. Zugangsdaten werden vom SaaS-Admin
 * verwaltet (siehe Saas\Controllers\AiSettingsController) und als Config-Datei
 * verteilt — exakt das gleiche Muster wie bei der Google-Calendar-Integration
 * (siehe plugins/google-calendar-sync/GoogleApiService.php).
 *
 * Design-Prinzip: Diese Klasse darf NIEMALS eine Exception nach außen werfen.
 * Jede Methode gibt bei Fehlern/fehlender Konfiguration `null` zurück, damit
 * aufrufender Code (Controller) die Praxissoftware niemals wegen eines
 * KI-Ausfalls (Netzwerk, falscher Key, Rate-Limit) blockiert oder crasht.
 */
class AiService
{
    private const GROK_API_URL            = 'https://api.x.ai/v1/chat/completions';
    private const GEMINI_API_URL_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s';

    private const DEFAULT_GROK_MODEL   = 'grok-4';
    private const DEFAULT_GEMINI_MODEL = 'gemini-2.0-flash';

    private string $grokApiKey = '';
    private string $geminiApiKey = '';
    private string $defaultProvider = 'gemini';
    private string $grokModel = self::DEFAULT_GROK_MODEL;
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
            $this->grokApiKey      = (string)($config['grok_api_key'] ?? '');
            $this->geminiApiKey    = (string)($config['gemini_api_key'] ?? '');
            $this->defaultProvider = in_array($config['default_provider'] ?? '', ['grok', 'gemini'], true)
                ? $config['default_provider']
                : 'gemini';
            $this->grokModel   = trim((string)($config['grok_model'] ?? '')) !== ''
                ? (string)$config['grok_model'] : self::DEFAULT_GROK_MODEL;
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
        return $this->grokApiKey !== '' || $this->geminiApiKey !== '';
    }

    public function isProviderConfigured(string $provider): bool
    {
        return match ($provider) {
            'grok'   => $this->grokApiKey !== '',
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
            $fallback = $provider === 'grok' ? 'gemini' : 'grok';
            if (!$this->isProviderConfigured($fallback)) {
                return null;
            }
            $provider = $fallback;
        }

        try {
            $result = match ($provider) {
                'grok'   => $this->callGrok($systemPrompt, $userPrompt),
                'gemini' => $this->callGemini($systemPrompt, $userPrompt),
                default  => null,
            };
        } catch (\Throwable $e) {
            error_log('[AiService generateText] ' . $e->getMessage());
            return null;
        }

        return $result !== null ? trim($result) : null;
    }

    private function callGrok(string $systemPrompt, string $userPrompt): ?string
    {
        $payload = [
            'model'       => $this->grokModel,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.4,
        ];

        $ch = curl_init(self::GROK_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->grokApiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT    => 30,
        ]);
        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            error_log('[AiService callGrok] cURL: ' . $err);
            return null;
        }
        if ($status < 200 || $status >= 300) {
            error_log('[AiService callGrok] HTTP ' . $status . ': ' . substr((string)$raw, 0, 500));
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
