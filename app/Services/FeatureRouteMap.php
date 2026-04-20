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

        '/mailbox'                      => 'bulk_mail',
        '/api/mailbox'                  => 'bulk_mail',
        '/bulk-mail'                    => 'bulk_mail',

        '/eingangsmeldungen'            => 'patient_intake',

        '/einladungen'                  => 'patient_invite',
        // Public landing (/einladung/{token}) bleibt frei — dort werden
        // neue Besitzer angelegt, muss ohne Auth erreichbar sein.

        '/portal-admin'                 => 'patient_portal',

        '/tcp'                          => 'therapy_care',

        '/steuerexport'                 => 'tax_export',

        // ── Hundeschul-/Hundetrainer-Modul (practice_type = 'trainer') ──
        //    Alle dogschool_* Features werden zusätzlich über den
        //    Tenant-Typ-Gate im FeatureGateService::isEnabled() abgesichert:
        //    Praxis-Tenants (therapeut) bekommen 403 selbst wenn der Plan
        //    die Keys theoretisch freischaltet.

        '/hundeschule'                  => 'dogschool_dashboard',
        '/api/hundeschule'              => 'dogschool_dashboard',
        '/kurse'                        => 'dogschool_courses',
        '/api/kurse'                    => 'dogschool_courses',
        '/kurstermine'                  => 'dogschool_group_training',
        '/teilnehmer'                   => 'dogschool_courses',
        '/warteliste'                   => 'dogschool_waitlist',
        '/anwesenheit'                  => 'dogschool_attendance',
        '/api/anwesenheit'              => 'dogschool_attendance',
        '/pakete'                       => 'dogschool_packages',
        '/api/pakete'                   => 'dogschool_packages',
        '/mehrfachkarten'               => 'dogschool_packages',
        '/einwilligungen'               => 'dogschool_consents',
        '/trainer'                      => 'dogschool_trainers',
        '/trainer-team'                 => 'dogschool_trainers',
        '/events'                       => 'dogschool_events',
        '/social-walks'                 => 'dogschool_events',
        '/interessenten'                => 'dogschool_leads',
        '/leads'                        => 'dogschool_leads',
        '/buchung'                      => null,    /* öffentlich — kein Gate */
        '/anfragen'                     => 'dogschool_online_booking',
        '/fortschritt'                  => 'dogschool_progress_tracking',
        '/auswertungen'                 => 'dogschool_reports',
        '/berichte'                     => 'dogschool_reports',
        '/trainingsplaene'              => 'dogschool_training_plans',
        '/uebungen'                     => 'dogschool_exercises',
        '/kursarten'                    => 'dogschool_categories',
        '/hundeschule/rechnungen'       => 'dogschool_invoicing',
        '/steuerexport'                 => 'dogschool_datev_export',
        '/api/consents'                 => 'dogschool_consents',
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
