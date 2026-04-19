<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Mappt URL-Pfad-Präfixe auf Feature-Keys.
 *
 * Wird vom Router VOR jedem Handler-Aufruf ausgewertet: passt ein Pfad auf
 * einen Präfix, muss das zugehörige Feature aktiv sein — sonst 403.
 *
 * ZWECK:
 *   - Defense-in-Depth: auch ungeschützte Routen (keine feature:-Middleware,
 *     kein requireFeature() im Controller) sind automatisch abgesichert.
 *   - Zentrale Stelle: neue URLs erben das Gating ohne Code-Duplikation.
 *
 * REGELN:
 *   - Der längste passende Präfix gewinnt (specific wins over generic).
 *   - Immer-erreichbare Pfade (Auth, Dashboard, Profil, Assets) fehlen bewusst
 *     in der Map und bleiben frei.
 */
final class FeatureRouteMap
{
    /**
     * URL-Präfix → Feature-Key.
     * Reihenfolge egal — match() sortiert nach Länge.
     *
     * @var array<string, string>
     */
    private const MAP = [
        '/patienten'                    => 'patients',
        '/api/patienten'                => 'patients',
        '/api/tierhalter'               => 'patients',
        '/api/global-search'            => 'patients',

        '/tierhalter'                   => 'owners',

        '/rechnungen'                   => 'invoices',
        '/api/rechnungen'               => 'invoices',
        '/api/invoice-form-data'        => 'invoices',

        '/ausgaben'                     => 'expenses',

        '/mahnwesen/erinnerungen'       => 'reminders',
        '/mahnwesen/mahnungen'          => 'dunning',
        '/api/mahnwesen'                => 'reminders',

        '/api/befund'                   => 'befunde',
        '/portal-admin/befunde'         => 'befunde',
        // patienten/{id}/befunde ist bereits über /patienten gekappt — Gating dort durch feature:patients
        // Zusätzlicher defensiver Check im Controller mit requireFeature('befunde').

        '/api/homework'                 => 'homework',
        '/api/patients'                 => 'patients',   // englische Variante

        '/api/mobile'                   => 'mobile_api',

        '/kalender'                     => 'calendar',
        '/api/kalender'                 => 'calendar',
        '/api/appointments'             => 'appointments',
        '/termine'                      => 'appointments',

        // ── Plugin-Routen: ohne diese Einträge könnten Tenants ohne
        //    Berechtigung per Direkt-URL auf gesperrte Module zugreifen,
        //    selbst wenn die Sidebar-Links ausgeblendet sind.

        '/mailbox'                      => 'messaging',
        '/api/mailbox'                  => 'messaging',
        '/bulk-mail'                    => 'messaging',

        '/eingangsmeldungen'            => 'intake',

        '/einladungen'                  => 'patient_invite',
        // Public landing (/einladung/{token}) bleibt frei — dort werden
        // neue Besitzer angelegt, muss ohne Auth erreichbar sein.

        '/portal-admin'                 => 'owner_portal',

        '/tcp'                          => 'therapy_care',

        '/steuerexport'                 => 'tax_export',
    ];

    /**
     * Gibt den Feature-Key zurück, dem ein URL-Pfad zugeordnet ist, oder null.
     */
    public static function match(string $uri): ?string
    {
        $uri = '/' . ltrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');

        /* Längsten Präfix-Match finden */
        $best    = null;
        $bestLen = 0;
        foreach (self::MAP as $prefix => $feature) {
            if ($prefix === $uri || str_starts_with($uri, rtrim($prefix, '/') . '/')) {
                $len = strlen($prefix);
                if ($len > $bestLen) {
                    $best    = $feature;
                    $bestLen = $len;
                }
            }
        }

        return $best;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::MAP;
    }
}
