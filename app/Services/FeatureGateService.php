<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;

/**
 * Zentrale Feature-Gating-Logik für die Praxis-App.
 *
 * Bezieht Feature-Status aus:
 *   1. Lokal gecachter JSON-Map in `t_{id}_settings._features_cache` (primär)
 *   2. Synchronisation aus SaaS-DB (plans.features + tenants.features_override + saas_feature_flags.global_enabled)
 *
 * Reihenfolge (Prioritätsordnung, höchste zuerst):
 *   - global_enabled = 0           → Feature AUS (Kill-Switch)
 *   - tenant_override = false      → Feature AUS (Tenant-Sperre)
 *   - tenant_override = true       → Feature AN  (Tenant-Freischaltung)
 *   - plan_features enthält key    → Feature AN
 *   - sonst                        → Feature AUS (sicherer Default)
 *
 * Self-Heal:
 *   - SaaS DB unreachable → letzter Cache bleibt gültig
 *   - kein Cache + keine Verbindung → nur Core-Features (dashboard/profile/logout)
 *   - unbekanntes Feature in isEnabled() → false
 *   - korruptes JSON → Reset → Core-Only
 */
class FeatureGateService
{
    /** Features die IMMER an sind — sonst ist der Nutzer komplett ausgesperrt */
    public const CORE_FEATURES = ['dashboard', 'profile', 'auth', 'settings'];

    /** Plugin-Feature-Keys, die der Code referenziert aber die (noch) nicht
     *  zwingend in saas_feature_flags registriert sein müssen. Für Ultra-
     *  Tier-Pläne werden diese automatisch als aktiv behandelt, damit neue
     *  Plugins ohne manuelle Admin-Konfiguration nutzbar sind. */
    private const TOP_TIER_AUTO_FEATURES = [
        'patient_invite',
        'therapy_care',
        'tax_export',
        'vet_report',
    ];

    /** Cache-Lebensdauer in Sekunden.
     *
     *  Der SaaS-Admin invalidiert den Cache bei Plan-/Feature-Änderungen
     *  explizit via TenantFeatureCacheInvalidator — der TTL ist der
     *  Defense-in-Depth-Fallback, falls die Invalidation aus irgendeinem
     *  Grund scheitert (DB-Timeout, Crash zwischen UPDATE und DELETE, …).
     *
     *  15 Sekunden: akzeptabler Kompromiss zwischen DB-Last und Zeit bis
     *  Plan-Downgrades spätestens automatisch greifen. */
    private const CACHE_TTL = 15;

    /** In-Request-Cache (1 Request = 1 DB-Hit) */
    private ?array $featuresCache = null;

    /** Plan-Slug des aktuellen Tenants, lazy geladen für isTopTierPlan() */
    private ?string $planSlugCache = null;

    /** Praxis-Typ ('therapeut'|'trainer'), lazy aus settings geladen */
    private ?string $practiceTypeCache = null;

    /** Prefix `dogschool_` identifiziert Hundeschul-exklusive Features.
     *  Diese sind nur sichtbar/nutzbar wenn practice_type='trainer'. */
    private const DOGSCHOOL_PREFIX = 'dogschool_';

    public function __construct(
        private readonly Database $db,
        private readonly Session $session,
        private readonly Config $config,
    ) {}

    /**
     * Prüft ob ein Feature nutzbar ist.
     *
     * Ist der gegebene Key ein Core-Feature (dashboard etc.), wird immer true
     * zurückgegeben, damit Nutzer nie komplett ausgesperrt sind.
     *
     * Self-Heal für Upgrade-Pläne:
     *   Wird ein NEUER Feature-Key abgefragt, der (noch) nicht in
     *   `saas_feature_flags` registriert ist — z.B. weil eine Plugin-
     *   Migration beim Kunden noch nicht gelaufen ist —, wäre das
     *   normale Verhalten `false`. Für Tenants auf einem Top-Tier-Plan
     *   (Ultra / Praxis / Enterprise / Business) wird das jedoch als
     *   „ja, du hast alles" interpretiert, damit ein Upgrade nicht durch
     *   Seed-Lücken effektiv zum Downgrade wird.
     *   Für Basic/Pro-Tenants bleibt der sichere Default `false`.
     */
    public function isEnabled(string $key): bool
    {
        if (in_array($key, self::CORE_FEATURES, true)) {
            return true;
        }

        /* Tenant-Typ-Gate: Hundeschul-Features nur für Trainer-Tenants.
         * Eine Praxis (practice_type='therapeut') darf kein dogschool_*
         * nutzen, selbst wenn der Plan es theoretisch freischaltet. */
        if (str_starts_with($key, self::DOGSCHOOL_PREFIX) && !$this->isTrainerTenant()) {
            return false;
        }

        $map = $this->all();

        /* Key ist explizit registriert → harte Antwort zurückgeben */
        if (array_key_exists($key, $map)) {
            return (bool)$map[$key];
        }

        /* Unbekannter Key → Tier-Fallback nur für Top-Tier-Pläne */
        return $this->isTopTierPlan();
    }

    /**
     * True, wenn der Tenant auf "trainer" gesetzt ist (Hundeschule/
     * Hundetrainer). Liest `settings.practice_type` lazily und cached.
     */
    public function isTrainerTenant(): bool
    {
        if ($this->practiceTypeCache === null) {
            try {
                $val = $this->db->safeFetchColumn(
                    "SELECT `value` FROM `{$this->db->prefix('settings')}` WHERE `key` = ?",
                    ['practice_type']
                );
                $this->practiceTypeCache = strtolower(trim((string)($val ?? 'therapeut')));
            } catch (\Throwable $e) {
                $this->practiceTypeCache = 'therapeut'; // sicherer Default
            }
        }
        return $this->practiceTypeCache === 'trainer';
    }

    /**
     * True, wenn der aktuelle Tenant auf einem "Alles-inklusive"-Plan
     * läuft. Wird benutzt, um unbekannte Feature-Keys (Plugin noch nicht
     * in saas_feature_flags registriert) für Ultra-Tenants durchzulassen.
     */
    private function isTopTierPlan(): bool
    {
        $slug = strtolower((string)($this->planSlugCache ?? ''));
        if ($slug === '') {
            /* Cache noch nicht geladen → über loadFromTenantCache() nachziehen */
            $cache = $this->loadFromTenantCache();
            $slug  = strtolower((string)($cache['plan_slug'] ?? ''));
            $this->planSlugCache = $slug;
        }

        return str_contains($slug, 'ultra')
            || str_contains($slug, 'praxis')
            || str_contains($slug, 'enterprise')
            || str_contains($slug, 'business');
    }

    /**
     * Gibt die komplette Feature-Map zurück (key => bool).
     * Core-Features sind immer true mit enthalten.
     */
    public function all(): array
    {
        if ($this->featuresCache !== null) {
            return $this->featuresCache;
        }

        $map = $this->loadFromTenantCache();

        /* Opportunistische Re-Sync wenn abgelaufen — darf nie blockieren */
        if ($this->needsSync($map)) {
            try {
                $synced = $this->syncFromSaas();
                if ($synced !== null) {
                    $map = $synced;
                }
            } catch (\Throwable $e) {
                error_log('[FeatureGate] sync failed: ' . $e->getMessage());
                /* Fallback: bestehender Cache bleibt aktiv */
            }
        }

        /* Plan-Slug aus dem geladenen/gesyncten Cache mitnehmen —
         * verhindert einen zweiten DB-Hit in isTopTierPlan(). */
        if (isset($map['plan_slug'])) {
            $this->planSlugCache = strtolower((string)$map['plan_slug']);
        }

        $flags = $map['flags'] ?? [];
        foreach (self::CORE_FEATURES as $core) {
            $flags[$core] = true;
        }

        /* Tenant-Typ-Gate in der Map:
         *   Alle `dogschool_*`-Keys werden für Nicht-Trainer-Tenants hart
         *   auf false gesetzt. Damit sind Hundeschul-Funktionen in der
         *   Praxis-App (Therapeut) in Sidebar, Modalen, Buttons einheitlich
         *   versteckt — nicht nur via isEnabled() gegated. */
        $isTrainer = $this->isTrainerTenant();
        if (!$isTrainer) {
            foreach ($flags as $k => $_v) {
                if (str_starts_with((string)$k, self::DOGSCHOOL_PREFIX)) {
                    $flags[$k] = false;
                }
            }
        }

        /* Ultra-Self-Heal: Plugin-Keys die im Code verwendet werden, aber
         * (noch) nicht in saas_feature_flags registriert sind, für Top-
         * Tier-Tenants automatisch als aktiv injizieren. Damit sieht die
         * Sidebar diese Features als freigeschaltet, und die Middleware
         * lässt Zugriffe durch — konsistent zwischen UI und Backend. */
        if ($this->isTopTierPlan()) {
            foreach (self::TOP_TIER_AUTO_FEATURES as $key) {
                if (!array_key_exists($key, $flags)) {
                    $flags[$key] = true;
                }
            }
        }

        return $this->featuresCache = $flags;
    }

    /**
     * Stoppt die Request-Verarbeitung wenn Feature nicht aktiv.
     *
     * Response:
     *   - AJAX/API → 403 JSON { error: 'feature_disabled', feature: ... }
     *   - HTML     → Flash + Redirect zum Dashboard
     *
     * Wird in Controllern als defense-in-depth neben dem Middleware genutzt.
     */
    public function requireFeature(string $key): void
    {
        if ($this->isEnabled($key)) {
            return;
        }

        $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
               || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
               || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');

        http_response_code(403);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error'   => 'feature_disabled',
                'feature' => $key,
                'message' => 'Diese Funktion ist im aktuellen Tarif nicht verfügbar.',
            ]);
            exit;
        }

        $this->session->flash('error', 'Diese Funktion ist im aktuellen Tarif nicht verfügbar.');
        header('Location: /dashboard');
        exit;
    }

    /**
     * Liest den lokalen Tenant-Cache aus settings._features_cache.
     * Gibt ['flags' => [...], 'synced_at' => int] zurück oder leeres Array.
     */
    private function loadFromTenantCache(): array
    {
        try {
            $raw = $this->db->safeFetchColumn(
                "SELECT `value` FROM `{$this->db->prefix('settings')}` WHERE `key` = ?",
                ['_features_cache']
            );
            if ($raw === null || $raw === false || $raw === '') {
                return [];
            }
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded) || !isset($decoded['flags']) || !is_array($decoded['flags'])) {
                return [];
            }
            return $decoded;
        } catch (\Throwable $e) {
            error_log('[FeatureGate] loadFromTenantCache: ' . $e->getMessage());
            return [];
        }
    }

    private function needsSync(array $cache): bool
    {
        if (empty($cache)) {
            return true;
        }
        $synced = (int)($cache['synced_at'] ?? 0);
        return (time() - $synced) > self::CACHE_TTL;
    }

    /**
     * Synchronisiert die Feature-Map aus der SaaS-DB und persistiert sie lokal.
     * Gibt die frisch synchronisierte Map zurück oder null bei Fehler.
     */
    public function syncFromSaas(): ?array
    {
        $saasDb = $this->config->get('saas_db.database', '');
        if ($saasDb === '') {
            return null;
        }

        $prefix = $this->db->getPrefix();
        if ($prefix === '') {
            return null;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config->get('saas_db.host', 'localhost'),
                $this->config->get('saas_db.port', 3306),
                $saasDb
            );
            $pdo = new \PDO(
                $dsn,
                $this->config->get('saas_db.username'),
                $this->config->get('saas_db.password'),
                [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_TIMEOUT            => 2,
                ]
            );

            /* 1. Tenant-Zeile anhand des Prefixes finden */
            $stmt = $pdo->prepare(
                "SELECT t.id, t.plan_id, t.features_override, p.features AS plan_features, p.slug AS plan_slug
                   FROM tenants t
                   LEFT JOIN plans p ON p.id = t.plan_id
                  WHERE t.db_name = ? OR t.db_name = ?
                  LIMIT 1"
            );
            $slimPrefix = rtrim($prefix, '_');
            $stmt->execute([$prefix, $slimPrefix]);
            $tenant = $stmt->fetch();

            /* 2. Globale Kill-Switches + required_plan pro Feature */
            $flagsByKey = [];
            foreach ($pdo->query("SELECT feature_key, required_plan, global_enabled FROM saas_feature_flags") as $row) {
                $flagsByKey[$row['feature_key']] = [
                    'required_plan'  => $row['required_plan'],
                    'global_enabled' => (int)$row['global_enabled'] === 1,
                ];
            }

            if (empty($flagsByKey)) {
                /* saas_feature_flags leer → Migration noch nicht durchgelaufen → nichts tun */
                return null;
            }

            $planFeatures = [];
            if ($tenant && !empty($tenant['plan_features'])) {
                $decoded = json_decode((string)$tenant['plan_features'], true);
                if (is_array($decoded)) {
                    $planFeatures = array_values($decoded);
                }
            }

            $planSlug = strtolower((string)($tenant['plan_slug'] ?? 'basic'));
            /* Map Plan-Slug auf Tier-Rang — unbekannte Slugs → basic (sicherster
             * kleinster Umfang). Wird nur als Fallback genutzt, wenn der Plan
             * keine explizite Feature-Liste hat. */
            $planRank = match (true) {
                str_contains($planSlug, 'ultra')     => 3,
                str_contains($planSlug, 'pro')       => 2,
                str_contains($planSlug, 'plus')      => 2,
                str_contains($planSlug, 'business')  => 3,
                str_contains($planSlug, 'enterprise')=> 3,
                str_contains($planSlug, 'praxis')    => 3,
                default                              => 1,
            };
            $requiredRank = ['basic' => 1, 'pro' => 2, 'ultra' => 3];

            $override = [];
            if ($tenant && !empty($tenant['features_override'])) {
                $decoded = json_decode((string)$tenant['features_override'], true);
                if (is_array($decoded)) {
                    $override = $decoded;
                }
            }

            /* Nur bekannte (registrierte) Feature-Keys aus der Plan-Liste ziehen —
             * veraltete oder unbekannte Keys werden verworfen. */
            $validPlanFeatures = array_values(array_intersect(
                $planFeatures,
                array_keys($flagsByKey)
            ));
            $hasExplicitPlanList = $validPlanFeatures !== [];

            /* 3. Finale Resolution je Feature
             *
             *    feature_usable =
             *        global_enabled
             *      AND ( tenant_override bestimmt  (wenn gesetzt)
             *            ODER  plan_explicit_list  (wenn gesetzt)
             *            ODER  tier_rank_fallback  (nur wenn KEINE Plan-Liste) )
             *
             *    Sicherer Default: unbekanntes Feature / fehlende Zuordnung → AUS.
             */
            $resolved = [];
            foreach ($flagsByKey as $key => $meta) {
                /* Harter globaler Kill-Switch */
                if (!$meta['global_enabled']) {
                    $resolved[$key] = false;
                    continue;
                }

                /* Per-Tenant-Override hat Vorrang (force on/off) */
                if (array_key_exists($key, $override)) {
                    $resolved[$key] = (bool)$override[$key];
                    continue;
                }

                if ($hasExplicitPlanList) {
                    /* Plan-Matrix aus dem SaaS-Admin ist AUTHORITATIVE —
                     * nur explizit freigeschaltete Keys sind an. */
                    $resolved[$key] = in_array($key, $validPlanFeatures, true);
                    continue;
                }

                /* Kein expliziter Plan-Eintrag → Tier-Rang entscheidet.
                 * Unbekannter required_plan → höchste Stufe (= aus). */
                $needed = $requiredRank[$meta['required_plan']] ?? 99;
                $resolved[$key] = $planRank >= $needed;
            }

            $payload = [
                'flags'      => $resolved,
                'plan_slug'  => $planSlug,
                'synced_at'  => time(),
            ];

            $this->writeTenantCache($payload);
            $this->featuresCache = null; // re-materialize on next all()
            $this->planSlugCache = strtolower($planSlug);
            return $payload;
        } catch (\Throwable $e) {
            error_log('[FeatureGate] syncFromSaas: ' . $e->getMessage());
            return null;
        }
    }

    private function writeTenantCache(array $payload): void
    {
        try {
            $table = $this->db->prefix('settings');
            $this->db->safeExecute(
                "INSERT INTO `{$table}` (`key`, `value`) VALUES ('_features_cache', ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                [json_encode($payload)]
            );
        } catch (\Throwable $e) {
            error_log('[FeatureGate] writeTenantCache: ' . $e->getMessage());
        }
    }

    /**
     * Erzwingt einen Sync — ignoriert den Cache. Für Admin-Aktionen (SaaS → Praxis).
     */
    public function forceSync(): ?array
    {
        $this->featuresCache     = null;
        $this->planSlugCache     = null;
        $this->practiceTypeCache = null;
        return $this->syncFromSaas();
    }
}
