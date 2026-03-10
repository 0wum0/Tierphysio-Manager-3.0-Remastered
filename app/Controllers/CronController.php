<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\BirthdayMailService;
use App\Repositories\SettingsRepository;

class CronController extends Controller
{
    public function __construct(
        private readonly BirthdayMailService $birthdayMailService,
        private readonly SettingsRepository  $settings
    ) {}

    /* ─────────────────────────────────────────────────────────
       GET /cron/geburtstag
       Protected by Bearer token from settings.
    ───────────────────────────────────────────────────────── */
    public function birthday(): void
    {
        $expectedToken = $this->settings->get('birthday_cron_token', '');

        /* Token validation */
        if (empty($expectedToken)) {
            http_response_code(503);
            $this->json(['error' => 'Cron-Token nicht konfiguriert. Bitte in den Einstellungen hinterlegen.']);
            return;
        }

        $providedToken = '';
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $providedToken = substr($authHeader, 7);
        }
        /* Also allow ?token=... as fallback for simple cron setups */
        if ($providedToken === '') {
            $providedToken = $_GET['token'] ?? '';
        }

        if (!hash_equals($expectedToken, $providedToken)) {
            http_response_code(401);
            $this->json(['error' => 'Ungültiger Token.']);
            return;
        }

        $result = $this->birthdayMailService->runDailyCheck();

        $this->json([
            'ok'      => true,
            'date'    => date('Y-m-d'),
            'sent'    => $result['sent'],
            'skipped' => $result['skipped'],
            'errors'  => $result['errors'],
        ]);
    }

    private function json(mixed $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
