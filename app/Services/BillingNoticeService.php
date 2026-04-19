<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Baut einen Billing-/Abo-Hinweis für den Praxisinhaber aus der SaaS-DB.
 *
 * Datenquelle (read-only):
 *   - saas.tenants           → trial_ends_at, status, plan_id
 *   - saas.subscriptions     → billing_cycle, next_billing, trial_ends_at,
 *                              status, amount, currency
 *   - saas.plans             → slug, name, price_month, price_year, trial_days
 *
 * Cache: {prefix}settings._billing_cache  (10 Minuten TTL)
 *
 * Self-Heal:
 *   - SaaS DB unreachable → letzter Cache bleibt gültig; bei Nichtvorhandensein null.
 *   - Tenant nicht in SaaS gefunden → null (keine falsche Anzeige).
 *   - Inkonsistente Daten → null (lieber nichts anzeigen als Falschinfo).
 *
 * WICHTIG: Dieses Service macht KEINE Rollenprüfung — der Caller (Bootstrap)
 * ruft getNotice() nur für Admin-User auf. Mitarbeiter bekommen den Wert
 * somit nie in die Twig-Globals.
 */
final class BillingNoticeService
{
    private const CACHE_KEY = '_billing_cache';
    private const CACHE_TTL = 600; // 10 Minuten

    public function __construct(
        private readonly Database $db,
        private readonly Config   $config,
    ) {}

    /**
     * @return array{
     *   type:string,
     *   severity:string,
     *   headline:string,
     *   message:string,
     *   days_left:?int,
     *   date_formatted:?string,
     *   cycle:?string,
     *   amount:?float,
     *   currency:?string,
     *   plan_name:?string
     * }|null
     */
    public function getNotice(): ?array
    {
        $raw = $this->loadCacheOrFetch();
        if ($raw === null) {
            return null;
        }
        return $this->buildNotice($raw);
    }

    /* ═══════════════════════════════════════════════════════════════════ */

    private function loadCacheOrFetch(): ?array
    {
        $cached = $this->loadCache();
        if ($cached !== null && (time() - (int)($cached['synced_at'] ?? 0)) <= self::CACHE_TTL) {
            return $cached;
        }

        try {
            $fresh = $this->fetchFromSaas();
            if ($fresh !== null) {
                $this->writeCache($fresh);
                return $fresh;
            }
        } catch (\Throwable $e) {
            error_log('[BillingNotice] fetch failed: ' . $e->getMessage());
        }

        /* Fallback: Stale-Cache nutzen, wenn SaaS gerade down ist */
        return $cached;
    }

    private function loadCache(): ?array
    {
        try {
            $raw = $this->db->safeFetchColumn(
                "SELECT `value` FROM `{$this->db->prefix('settings')}` WHERE `key` = ?",
                [self::CACHE_KEY]
            );
            if ($raw === null || $raw === false || $raw === '') {
                return null;
            }
            $decoded = json_decode((string)$raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function writeCache(array $payload): void
    {
        try {
            $this->db->safeExecute(
                "INSERT INTO `{$this->db->prefix('settings')}` (`key`, `value`)
                     VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                [self::CACHE_KEY, json_encode($payload, JSON_UNESCAPED_UNICODE)]
            );
        } catch (\Throwable $e) {
            error_log('[BillingNotice] cache write failed: ' . $e->getMessage());
        }
    }

    /**
     * Liest die rohen Billing-Daten zum aktuellen Tenant aus der SaaS-DB.
     */
    private function fetchFromSaas(): ?array
    {
        $saasDb = $this->config->get('saas_db.database', '');
        if ($saasDb === '') {
            return null;
        }
        $prefix = $this->db->getPrefix();
        if ($prefix === '') {
            return null;
        }

        $pdo = new \PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config->get('saas_db.host', 'localhost'),
                $this->config->get('saas_db.port', 3306),
                $saasDb
            ),
            $this->config->get('saas_db.username'),
            $this->config->get('saas_db.password'),
            [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT            => 2,
            ]
        );

        $slim = rtrim($prefix, '_');
        $stmt = $pdo->prepare(
            "SELECT t.id              AS tenant_id,
                    t.status          AS tenant_status,
                    t.trial_ends_at   AS tenant_trial_ends_at,
                    p.slug            AS plan_slug,
                    p.name            AS plan_name,
                    p.price_month     AS plan_price_month,
                    p.price_year      AS plan_price_year
               FROM tenants t
               LEFT JOIN plans p ON p.id = t.plan_id
              WHERE t.db_name = ? OR t.db_name = ?
              LIMIT 1"
        );
        $stmt->execute([$prefix, $slim]);
        $tenant = $stmt->fetch();
        if (!$tenant) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT status, billing_cycle, next_billing, ends_at, trial_ends_at, amount, currency
               FROM subscriptions
              WHERE tenant_id = ?
              ORDER BY id DESC
              LIMIT 1"
        );
        $stmt->execute([(int)$tenant['tenant_id']]);
        $sub = $stmt->fetch() ?: [];

        return [
            'synced_at'            => time(),
            'tenant_status'        => (string)($tenant['tenant_status'] ?? ''),
            'tenant_trial_ends_at' => $tenant['tenant_trial_ends_at'] ?? null,
            'plan_slug'            => (string)($tenant['plan_slug'] ?? ''),
            'plan_name'            => (string)($tenant['plan_name'] ?? ''),
            'plan_price_month'     => $tenant['plan_price_month'] !== null ? (float)$tenant['plan_price_month'] : null,
            'plan_price_year'      => $tenant['plan_price_year']  !== null ? (float)$tenant['plan_price_year']  : null,
            'sub_status'           => (string)($sub['status']        ?? ''),
            'sub_billing_cycle'    => (string)($sub['billing_cycle'] ?? ''),
            'sub_next_billing'     => $sub['next_billing']  ?? null,
            'sub_ends_at'          => $sub['ends_at']       ?? null,
            'sub_trial_ends_at'    => $sub['trial_ends_at'] ?? null,
            'sub_amount'           => isset($sub['amount']) ? (float)$sub['amount'] : null,
            'sub_currency'         => (string)($sub['currency'] ?? 'EUR'),
        ];
    }

    /**
     * Erkennt Lifetime-Lizenzen.
     *
     * Im Bestands-System (siehe TenantController::setTrial, type='lifetime')
     * werden Lifetime-Lizenzen ausschließlich über das Sentinel-Datum
     * `2099-12-31 23:59:59` in next_billing / ends_at / trial_ends_at
     * markiert. Es gibt kein dediziertes Flag.
     *
     * Zusätzlich defensiv: jedes Datum mit Jahr >= 2099 wird als Lifetime
     * interpretiert (schützt gegen "nächste Zahlung in 26 919 Tagen"-Bug).
     */
    private function isLifetime(array $d): bool
    {
        $candidates = [
            $d['sub_next_billing']     ?? null,
            $d['sub_ends_at']          ?? null,
            $d['sub_trial_ends_at']    ?? null,
            $d['tenant_trial_ends_at'] ?? null,
        ];
        foreach ($candidates as $date) {
            if (!$date) {
                continue;
            }
            $s = (string)$date;
            if (str_starts_with($s, '2099-12-31') || str_starts_with($s, '9999-')) {
                return true;
            }
            try {
                $year = (int)(new \DateTimeImmutable($s))->format('Y');
                if ($year >= 2099) {
                    return true;
                }
            } catch (\Throwable) {
                /* ignore invalid date */
            }
        }
        /* Expliziter Marker, falls künftig eingeführt */
        $cycle = strtolower((string)($d['sub_billing_cycle'] ?? ''));
        return in_array($cycle, ['lifetime', 'perpetual', 'onetime', 'one_time'], true);
    }

    /**
     * Wandelt die rohen Daten in einen anzuzeigenden Hinweis um.
     * Gibt null zurück, wenn keine belastbare Aussage möglich ist.
     */
    private function buildNotice(array $d): ?array
    {
        $status = strtolower((string)($d['sub_status'] ?: $d['tenant_status']));
        $cycle  = strtolower((string)$d['sub_billing_cycle']);
        $cycleLabel = match ($cycle) {
            'yearly', 'annual' => 'jährlich',
            'monthly'          => 'monatlich',
            default            => null,
        };
        $amount   = $d['sub_amount']
            ?? ($cycle === 'yearly' ? $d['plan_price_year'] : $d['plan_price_month']);
        $currency = $d['sub_currency'] ?: 'EUR';
        $planName = $d['plan_name'] ?: null;

        /* ───────── Lifetime (MUSS vor Trial/Active geprüft werden) ─────────
         * Lifetime wird im SaaS-Admin über das Sentinel-Datum 2099-12-31
         * in next_billing / ends_at / trial_ends_at markiert. Ohne diesen
         * Early-Exit würde der Service "nächste Zahlung in 26919 Tagen"
         * anzeigen, weil der Tenant-Status dabei auf 'active' steht. */
        if ($this->isLifetime($d)) {
            /* past_due schlägt Lifetime — falls jemand die Lizenz gesperrt hat */
            if (!in_array($status, ['past_due', 'suspended', 'unpaid'], true)) {
                return [
                    'type'           => 'lifetime',
                    'severity'       => 'success',
                    'headline'       => 'Lifetime-Lizenz aktiv.',
                    'message'        => $planName
                        ? sprintf('Ihre Praxis nutzt eine dauerhafte Freischaltung (%s). Es fallen keine wiederkehrenden Zahlungen an.', $planName)
                        : 'Ihre Praxis nutzt eine dauerhafte Freischaltung. Es fallen keine wiederkehrenden Zahlungen an.',
                    'days_left'      => null,
                    'date_formatted' => null,
                    'cycle'          => null,
                    'amount'         => null,
                    'currency'       => $currency,
                    'plan_name'      => $planName,
                ];
            }
        }

        /* ───────── Trial ───────── */
        if ($status === 'trial') {
            $trialEnd = $d['sub_trial_ends_at'] ?: $d['tenant_trial_ends_at'];
            $days     = $this->daysUntil($trialEnd);
            $fmt      = $this->formatDate($trialEnd);
            if ($days === null || $fmt === null) {
                return null; // keine Falschinfo
            }

            $severity = $days <= 3 ? 'warning' : 'info';
            $amountText = ($amount !== null && $cycleLabel !== null)
                ? sprintf(' Danach wird Ihr %s-Tarif (%s) automatisch %s mit %s %s abgerechnet.',
                    $planName ?? 'Abo', $planName ?? '', $cycleLabel,
                    number_format($amount, 2, ',', '.'), $currency)
                : '';

            return [
                'type'           => 'trial',
                'severity'       => $severity,
                'headline'       => $days > 0
                    ? sprintf('Ihre Testphase läuft noch %d %s.', $days, $days === 1 ? 'Tag' : 'Tage')
                    : 'Ihre Testphase endet heute.',
                'message'        => sprintf('Die Testversion endet am %s.', $fmt) . $amountText,
                'days_left'      => $days,
                'date_formatted' => $fmt,
                'cycle'          => $cycleLabel,
                'amount'         => $amount,
                'currency'       => $currency,
                'plan_name'      => $planName,
            ];
        }

        /* ───────── Aktives Abo ───────── */
        if ($status === 'active') {
            $next = $d['sub_next_billing'];
            $days = $this->daysUntil($next);
            $fmt  = $this->formatDate($next);
            if ($days === null || $fmt === null) {
                return null;
            }

            $severity = $days <= 3 ? 'warning' : 'info';
            $amountText = ($amount !== null)
                ? sprintf(' (%s %s)', number_format($amount, 2, ',', '.'), $currency)
                : '';
            $cycleText = $cycleLabel ? ' ' . $cycleLabel : '';

            return [
                'type'           => 'active',
                'severity'       => $severity,
                'headline'       => $days > 0
                    ? sprintf('Ihre nächste Zahlung ist in %d %s fällig.', $days, $days === 1 ? 'Tag' : 'Tagen')
                    : 'Ihre nächste Zahlung ist heute fällig.',
                'message'        => sprintf('Die nächste%s Abbuchung erfolgt am %s%s.', $cycleText, $fmt, $amountText),
                'days_left'      => $days,
                'date_formatted' => $fmt,
                'cycle'          => $cycleLabel,
                'amount'         => $amount,
                'currency'       => $currency,
                'plan_name'      => $planName,
            ];
        }

        /* ───────── Zahlung fehlgeschlagen / überfällig ───────── */
        if (in_array($status, ['past_due', 'suspended', 'unpaid'], true)) {
            return [
                'type'           => 'past_due',
                'severity'       => 'danger',
                'headline'       => 'Ihre Zahlung ist fehlgeschlagen.',
                'message'        => 'Bitte aktualisieren Sie Ihre Zahlungsmethode, um den Zugang aufrechtzuerhalten.',
                'days_left'      => null,
                'date_formatted' => null,
                'cycle'          => $cycleLabel,
                'amount'         => $amount,
                'currency'       => $currency,
                'plan_name'      => $planName,
            ];
        }

        /* ───────── Gekündigt ───────── */
        if (in_array($status, ['cancelled', 'canceled', 'expired'], true)) {
            $fmt = $this->formatDate($d['sub_next_billing'] ?: $d['tenant_trial_ends_at']);
            return [
                'type'           => 'canceled',
                'severity'       => 'warning',
                'headline'       => 'Ihr Abonnement wurde gekündigt.',
                'message'        => $fmt
                    ? sprintf('Zugang verfügbar bis %s.', $fmt)
                    : 'Bitte kontaktieren Sie den Support, wenn Sie weitermachen möchten.',
                'days_left'      => null,
                'date_formatted' => $fmt,
                'cycle'          => $cycleLabel,
                'amount'         => $amount,
                'currency'       => $currency,
                'plan_name'      => $planName,
            ];
        }

        return null;
    }

    private function daysUntil(?string $date): ?int
    {
        if (!$date) {
            return null;
        }
        try {
            $end   = new \DateTimeImmutable($date);
            $today = new \DateTimeImmutable('today');
            return (int)$today->diff($end)->format('%r%a');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($date))->format('d.m.Y');
        } catch (\Throwable) {
            return null;
        }
    }
}
