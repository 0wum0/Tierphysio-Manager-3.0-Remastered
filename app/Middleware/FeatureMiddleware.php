<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Services\FeatureGateService;

/**
 * Route-Level Feature-Gate.
 *
 * Wird vom Router bei Middleware-Einträgen der Form "feature:patients" instanziiert.
 * Blockiert die Route wenn das Feature nicht aktiv ist — hart, bevor der Controller
 * überhaupt erreicht wird.
 *
 * Verwendung in Routes:
 *   $router->get('/patienten', [PatientController::class, 'index'], ['auth', 'feature:patients']);
 */
class FeatureMiddleware
{
    public function __construct(
        private readonly FeatureGateService $gate,
        private readonly Database $db,
    ) {}

    private string $featureKey = '';

    public function setFeatureKey(string $key): void
    {
        $this->featureKey = $key;
    }

    public function handle(callable $next): void
    {
        if ($this->featureKey === '') {
            /* Kein Key → durchlassen (defensiver Default) */
            $next();
            return;
        }

        /* Internal cron requests (X-Internal-Cron: true) are token-secured.
         * Never block them with a session-dependent feature check. */
        if (($_SERVER['HTTP_X_INTERNAL_CRON'] ?? '') === 'true') {
            $next();
            return;
        }

        /* Stateless Bearer-token API requests (e.g. /api/mobile/*) arrive without
         * a PHP session, so the tenant prefix cannot be resolved at bootstrap time.
         * The DB prefix is therefore empty at this point — any feature check here
         * would use a prefix-less FeatureGateService that has no cache and cannot
         * sync from SaaS, causing false `feature_disabled` 403 responses.
         *
         * The MobileApiController::requireAuth() resolves the correct prefix and
         * performs the mobile_api feature check inline after setting the prefix.
         * We therefore defer all feature checks for prefixless Bearer-token requests. */
        if ($this->db->getPrefix() === ''
            && isset($_SERVER['HTTP_AUTHORIZATION'])
            && stripos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ') === 0
        ) {
            $next();
            return;
        }

        /* Delegiert an den zentralen Gate-Service — stoppt bei Bedarf mit 403/Redirect */
        $this->gate->requireFeature($this->featureKey);
        $next();
    }
}
