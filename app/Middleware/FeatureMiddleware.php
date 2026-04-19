<?php

declare(strict_types=1);

namespace App\Middleware;

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
        private readonly FeatureGateService $gate
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

        /* Delegiert an den zentralen Gate-Service — stoppt bei Bedarf mit 403/Redirect */
        $this->gate->requireFeature($this->featureKey);
        $next();
    }
}
