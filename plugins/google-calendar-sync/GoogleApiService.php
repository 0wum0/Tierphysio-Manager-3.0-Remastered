<?php

declare(strict_types=1);

namespace Plugins\GoogleCalendarSync;

/**
 * Handles all Google OAuth2 and Calendar API HTTP calls.
 * No external SDK required — pure cURL / stream_context.
 *
 * Google Calendar Event colorId for Lila/Grape = "3"
 * Reference: https://developers.google.com/calendar/api/v3/reference/colors/get
 *   1 = Lavender, 2 = Sage, 3 = Grape, 4 = Flamingo,
 *   5 = Banana, 6 = Tangerine, 7 = Peacock, 8 = Blueberry,
 *   9 = Basil, 10 = Tomato, 11 = Flamingo
 */
class GoogleApiService
{
    /* Google Calendar Event colorId: Grape / Lila */
    public const COLOR_ID_GRAPE = '3';

    private const TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    private const AUTH_URL    = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct(
        private readonly GoogleCalendarRepository $repo
    ) {
        $this->clientId     = defined('GOOGLE_CLIENT_ID')     ? GOOGLE_CLIENT_ID     : '';
        $this->clientSecret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
        $this->redirectUri  = defined('GOOGLE_REDIRECT_URI')  ? GOOGLE_REDIRECT_URI  : '';
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /* ─── OAuth2 Flow ─── */

    public function getAuthUrl(string $state = ''): string
    {
        $params = [
            'client_id'             => $this->clientId,
            'redirect_uri'          => $this->redirectUri,
            'response_type'         => 'code',
            'scope'                 => 'https://www.googleapis.com/auth/calendar openid email profile',
            'access_type'           => 'offline',
            'prompt'                => 'consent',
            'include_granted_scopes'=> 'true',
        ];
        if ($state) $params['state'] = $state;
        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public function exchangeCodeForTokens(string $code): array
    {
        $response = $this->httpPost(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException('Token exchange failed: ' . ($response['error_description'] ?? json_encode($response)));
        }

        return $response;
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = $this->httpPost(self::TOKEN_URL, [
            'refresh_token' => $refreshToken,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'refresh_token',
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException('Token refresh failed: ' . ($response['error_description'] ?? json_encode($response)));
        }

        return $response;
    }

    public function getUserInfo(string $accessToken): array
    {
        return $this->httpGet(self::USERINFO_URL, [], $accessToken);
    }

    /* ─── Token Management ─── */

    public function getValidAccessToken(array $connection): string
    {
        /* Check if token is still valid (5 min buffer) */
        if ($connection['token_expires_at'] && strtotime($connection['token_expires_at']) > time() + 300) {
            return $connection['access_token'];
        }

        /* Refresh the token */
        if (empty($connection['refresh_token'])) {
            throw new \RuntimeException('No refresh token available. Please reconnect Google Calendar.');
        }

        $tokens = $this->refreshAccessToken($connection['refresh_token']);

        $expiresAt = date('Y-m-d H:i:s', time() + (int)($tokens['expires_in'] ?? 3600));
        $this->repo->updateConnection((int)$connection['id'], [
            'access_token'    => $tokens['access_token'],
            'token_expires_at'=> $expiresAt,
        ]);

        return $tokens['access_token'];
    }

    /* ─── Calendar List ─── */

    public function listCalendars(array $connection): array
    {
        $token    = $this->getValidAccessToken($connection);
        $response = $this->httpGet(self::CALENDAR_API . '/users/me/calendarList', [], $token);
        return $response['items'] ?? [];
    }

    /* ─── Events CRUD ─── */

    public function createEvent(array $connection, string $calendarId, array $eventData): array
    {
        $token    = $this->getValidAccessToken($connection);
        $url      = self::CALENDAR_API . '/calendars/' . rawurlencode($calendarId) . '/events';
        $response = $this->httpPostJson($url, $eventData, $token);

        if (empty($response['id'])) {
            throw new \RuntimeException('Create event failed: ' . json_encode($response));
        }

        return $response;
    }

    public function updateEvent(array $connection, string $calendarId, string $eventId, array $eventData): array
    {
        $token    = $this->getValidAccessToken($connection);
        $url      = self::CALENDAR_API . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId);
        $response = $this->httpPutJson($url, $eventData, $token);

        if (empty($response['id'])) {
            throw new \RuntimeException('Update event failed: ' . json_encode($response));
        }

        return $response;
    }

    public function deleteEvent(array $connection, string $calendarId, string $eventId): void
    {
        $token = $this->getValidAccessToken($connection);
        $url   = self::CALENDAR_API . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId);
        $this->httpDelete($url, $token);
    }

    public function getEvent(array $connection, string $calendarId, string $eventId): ?array
    {
        try {
            $token    = $this->getValidAccessToken($connection);
            $url      = self::CALENDAR_API . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId);
            $response = $this->httpGet($url, [], $token);
            return isset($response['id']) ? $response : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /* ─── Build Event Payload ─── */

    public function buildEventPayload(array $appointment): array
    {
        $patientName  = $appointment['patient_name'] ?? null;
        $ownerName    = trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? ''));
        $treatmentType= $appointment['treatment_type_name'] ?? null;

        /* Title: "Tiername – Behandlungsart" or fallback to appointment title */
        $title = $appointment['title'];
        if ($patientName && $treatmentType) {
            $title = $patientName . ' – ' . $treatmentType;
        } elseif ($patientName) {
            $title = $patientName;
        }

        /* Description */
        $descParts = [];
        if ($patientName)   $descParts[] = '🐾 Tier: ' . $patientName;
        if ($ownerName)     $descParts[] = '👤 Besitzer: ' . $ownerName;
        if ($treatmentType) $descParts[] = '💉 Behandlung: ' . $treatmentType;
        if (!empty($appointment['description'])) $descParts[] = "\n" . $appointment['description'];
        $descParts[] = "\n📋 Termin-ID: " . $appointment['id'];

        /* Dates */
        $start = $this->formatDateTime($appointment['start_at']);
        $end   = $this->formatDateTime($appointment['end_at']);

        $payload = [
            'summary'     => $title,
            'description' => implode("\n", $descParts),
            'colorId'     => self::COLOR_ID_GRAPE,
            'start'       => ['dateTime' => $start, 'timeZone' => 'Europe/Berlin'],
            'end'         => ['dateTime' => $end,   'timeZone' => 'Europe/Berlin'],
            'extendedProperties' => [
                'private' => [
                    'tierphysio_appointment_id' => (string)$appointment['id'],
                    'tierphysio_source'         => 'tierphysio-manager',
                ],
            ],
        ];

        /* Optional: add reminder */
        if (!empty($appointment['reminder_minutes'])) {
            $payload['reminders'] = [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'popup', 'minutes' => (int)$appointment['reminder_minutes']],
                ],
            ];
        }

        return $payload;
    }

    private function formatDateTime(string $datetime): string
    {
        return (new \DateTime($datetime))->format(\DateTime::RFC3339);
    }

    /* ─── HTTP Helpers ─── */

    private function httpPost(string $url, array $data): array
    {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true],
        ]);
        $body = file_get_contents($url, false, $ctx);
        return json_decode($body ?: '{}', true) ?? [];
    }

    private function httpGet(string $url, array $params = [], string $token = ''): array
    {
        if ($params) $url .= '?' . http_build_query($params);
        $headers = "Accept: application/json\r\n";
        if ($token) $headers .= "Authorization: Bearer {$token}\r\n";

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => $headers,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true],
        ]);
        $body = file_get_contents($url, false, $ctx);
        return json_decode($body ?: '{}', true) ?? [];
    }

    private function httpPostJson(string $url, array $data, string $token): array
    {
        $json = json_encode($data);
        $ctx  = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
                'content' => $json,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true],
        ]);
        $body = file_get_contents($url, false, $ctx);
        return json_decode($body ?: '{}', true) ?? [];
    }

    private function httpPutJson(string $url, array $data, string $token): array
    {
        $json = json_encode($data);
        $ctx  = stream_context_create([
            'http' => [
                'method'  => 'PUT',
                'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
                'content' => $json,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true],
        ]);
        $body = file_get_contents($url, false, $ctx);
        return json_decode($body ?: '{}', true) ?? [];
    }

    private function httpDelete(string $url, string $token): void
    {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'DELETE',
                'header'  => "Authorization: Bearer {$token}\r\n",
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true],
        ]);
        file_get_contents($url, false, $ctx);
    }
}
