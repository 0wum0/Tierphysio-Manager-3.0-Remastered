<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Services\LicenseService;
use Saas\Repositories\TenantRepository;

class LicenseApiController extends Controller
{
    public function __construct(
        View                      $view,
        Session                   $session,
        private LicenseService    $licenseService,
        private TenantRepository  $tenantRepo
    ) {
        parent::__construct($view, $session);
    }

    /**
     * POST /api/license/verify
     * Body: { "token": "..." }
     * Used by Praxissoftware to verify a stored token (offline-safe).
     */
    public function verify(array $params = []): void
    {
        $input = $this->getJsonInput();
        $token = trim($input['token'] ?? '');

        if (!$token) {
            $this->json(['valid' => false, 'error' => 'Token fehlt'], 400);
        }

        try {
            $result = $this->licenseService->verifyToken($token, $this->getClientIp());
            $this->json($result);
        } catch (\InvalidArgumentException $e) {
            $this->json(['valid' => false, 'error' => $e->getMessage()], 401);
        } catch (\RuntimeException $e) {
            $this->json(['valid' => false, 'error' => $e->getMessage()], 403);
        }
    }

    /**
     * GET /api/license/check?uuid={tenant_uuid}
     * Lightweight real-time check — used when online.
     */
    public function check(array $params = []): void
    {
        $uuid = trim($_GET['uuid'] ?? '');

        if (!$uuid) {
            $this->json(['valid' => false, 'error' => 'UUID fehlt'], 400);
        }

        $result = $this->licenseService->checkByUuid($uuid);
        $status = $result['valid'] ? 200 : 403;
        $this->json($result, $status);
    }

    /**
     * POST /api/license/token
     * Body: { "uuid": "...", "api_key": "..." }
     * Issues a fresh token — called by Praxissoftware on first setup or renewal.
     */
    public function token(array $params = []): void
    {
        $input  = $this->getJsonInput();
        $uuid   = trim($input['uuid'] ?? '');
        $apiKey = trim($input['api_key'] ?? '');

        // Validate via bearer or api_key param
        $bearer = $this->getBearerToken();
        if (!$bearer && !$apiKey) {
            $this->json(['error' => 'Authentifizierung erforderlich'], 401);
        }

        // For simplicity: api_key must match LICENSE_SECRET
        // In production this should be a per-tenant API key
        $secret = $this->licenseService->getSecret();
        $keyToCheck = $bearer ?: $apiKey;

        if (!hash_equals($secret, $keyToCheck)) {
            $this->json(['error' => 'Ungültiger API-Key'], 401);
        }

        if (!$uuid) {
            $this->json(['error' => 'UUID fehlt'], 400);
        }

        try {
            $checkResult = $this->licenseService->checkByUuid($uuid);
            if (!$checkResult['valid']) {
                $this->json(['error' => $checkResult['message'] ?? 'Lizenz inaktiv'], 403);
            }

            // Look up tenant to get numeric ID for token issuance
            $tenant = $this->tenantRepo->findByUuid($uuid);
            if (!$tenant) {
                $this->json(['error' => 'Tenant nicht gefunden'], 404);
            }

            $token = $this->licenseService->issueToken((int)$tenant['id']);

            $this->json([
                'token'       => $token,
                'plan'        => $checkResult['plan'],
                'features'    => $checkResult['features'],
                'offline_days'=> $checkResult['offline_days'],
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
    }

    private function getBearerToken(): string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return '';
    }
}
