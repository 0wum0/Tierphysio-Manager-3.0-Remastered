<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Database;

/**
 * TenantFeatureCacheInvalidator
 * ═════════════════════════════════════════════════════════════════════════
 * Löscht den lokalen Feature-Cache eines Tenants in dessen Prefix-Tabelle
 * `{db_name}settings._features_cache`.
 *
 * Hintergrund:
 *   Die Praxis-App (FeatureGateService) cached die resolved Feature-Map
 *   pro Tenant in `{prefix}settings._features_cache` für CACHE_TTL Sekunden.
 *   Wird dieser Cache nach einer Plan- oder Feature-Änderung im SaaS-Admin
 *   NICHT invalidiert, sieht der Tenant bis zum TTL-Ablauf weiterhin seine
 *   alten (höheren) Feature-Flags — ein schwerwiegender Rechte-Enforcement-Bug.
 *
 *   Diese Klasse muss NACH jeder Plan/Feature-relevanten DB-Änderung im
 *   SaaS-Admin aufgerufen werden.  Drei Scopes:
 *
 *     invalidateForTenant(int)  – ein einzelner Tenant
 *     invalidateForPlan(int)    – alle Tenants auf einem Plan
 *     invalidateAll()           – alle Tenants (Global-Kill-Switch u.ä.)
 *
 * Fail-Safe:
 *   Wirft nie. Fehler werden per error_log protokolliert, damit die
 *   eigentliche Admin-Aktion nicht durch Cache-Probleme kaputt geht.
 *
 * Multi-Tenant:
 *   Liest `tenants.db_name` als Prefix (Konvention in dieser Code-Base,
 *   siehe TenantProvisioningService: 'db_name' => $tablePrefix).
 */
final class TenantFeatureCacheInvalidator
{
    public function __construct(private readonly Database $db) {}

    /**
     * Invalidiert den Feature-Cache eines einzelnen Tenants.
     * Nach diesem Aufruf wird der NÄCHSTE Request der Praxis-App automatisch
     * ein frisches syncFromSaas() ausführen.
     */
    public function invalidateForTenant(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        try {
            $row = $this->db->fetch(
                "SELECT `db_name` FROM `tenants` WHERE `id` = ?",
                [$tenantId]
            );
            if (!$row) {
                return;
            }
            $this->deleteCacheRow((string)($row['db_name'] ?? ''));
        } catch (\Throwable $e) {
            error_log("[FeatureCacheInvalidator] tenant={$tenantId}: " . $e->getMessage());
        }
    }

    /**
     * Invalidiert alle Tenants, die auf einem bestimmten Plan laufen.
     * Nutzen: Plan-Feature-Matrix wurde geändert → alle Tenants dieses Plans
     * müssen neu syncen.
     */
    public function invalidateForPlan(int $planId): void
    {
        if ($planId <= 0) {
            return;
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT `db_name` FROM `tenants` WHERE `plan_id` = ?",
                [$planId]
            );
            foreach ($rows as $row) {
                $this->deleteCacheRow((string)($row['db_name'] ?? ''));
            }
        } catch (\Throwable $e) {
            error_log("[FeatureCacheInvalidator] plan={$planId}: " . $e->getMessage());
        }
    }

    /**
     * Invalidiert alle Tenants. Nutzen: Global-Kill-Switch oder systemweite
     * Feature-Flag-Änderungen (saas_feature_flags).
     */
    public function invalidateAll(): void
    {
        try {
            $rows = $this->db->fetchAll("SELECT `db_name` FROM `tenants`");
            foreach ($rows as $row) {
                $this->deleteCacheRow((string)($row['db_name'] ?? ''));
            }
        } catch (\Throwable $e) {
            error_log('[FeatureCacheInvalidator] all: ' . $e->getMessage());
        }
    }

    /**
     * Löscht die `_features_cache`-Zeile in {prefix}settings.
     * Prefix wird defensiv normalisiert (trailing-"_" sicherstellen).
     */
    private function deleteCacheRow(string $dbName): void
    {
        $prefix = trim($dbName);
        if ($prefix === '') {
            return;
        }
        if (!str_ends_with($prefix, '_')) {
            $prefix .= '_';
        }

        /* Tabellen-Name ist Identifier, muss backtick-quoted werden. Er kommt
         * nur aus der eigenen tenants-Tabelle — keine User-Inputs, keine SQL-
         * Injection-Fläche. Trotzdem defensiv auf zulässiges Zeichenset prüfen. */
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
            error_log("[FeatureCacheInvalidator] unsafe prefix: {$prefix}");
            return;
        }

        try {
            $this->db->execute(
                "DELETE FROM `{$prefix}settings` WHERE `key` = ?",
                ['_features_cache']
            );
        } catch (\Throwable $e) {
            /* Prefix-Settings-Tabelle existiert evtl. noch nicht (z.B. bei
             * frisch provisionierten Tenants vor dem ersten Migrations-Run).
             * Das ist kein Fehlerfall, den wir hochpropagieren müssen. */
            error_log("[FeatureCacheInvalidator] DELETE failed for {$prefix}settings: " . $e->getMessage());
        }
    }
}
