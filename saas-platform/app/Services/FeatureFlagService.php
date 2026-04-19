<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Database;

/**
 * Feature flag service (SaaS platform).
 *
 * Reads feature flags from a tenant's settings table.
 * The SaaS admin can inspect or override flags for any tenant.
 *
 * Priority (highest wins):
 *  1. Tenant setting in {prefix}settings
 *  2. Plan-level override (passed via constructor)
 *  3. System-wide default
 *
 * Feature #5: Feature Flag System
 */
class FeatureFlagService
{
    private const DEFAULTS = [
        'calendar_enabled'            => true,
        'google_sync_enabled'         => true,
        'birthday_cron_enabled'       => true,
        'tcp_enabled'                 => true,
        'holiday_greetings_enabled'   => true,
        'invoice_enabled'             => true,
        'patient_portal_enabled'      => false,
        'api_enabled'                 => false,
        'sms_notifications_enabled'   => false,
        'multi_location_enabled'      => false,
        'advanced_reporting_enabled'  => false,
        'data_export_enabled'         => true,
    ];

    /**
     * @param Database             $db            SaaS platform DB (has access to all tenant tables)
     * @param string               $prefix        Tenant table prefix, e.g. "t_therapano_2eff77_"
     * @param array<string, bool>  $planFeatures  Optional plan-level overrides
     */
    public function __construct(
        private readonly Database $db,
        private readonly string   $prefix,
        private readonly array    $planFeatures = []
    ) {}

    /**
     * Check whether a feature is enabled for the tenant.
     */
    public function hasFeature(string $feature): bool
    {
        // 1. Tenant setting
        try {
            $row = $this->db->fetch(
                "SELECT `value` FROM `{$this->prefix}settings` WHERE `key` = ?",
                [$feature]
            );
            if ($row !== false && $row['value'] !== null && $row['value'] !== '') {
                return $this->isTruthy($row['value']);
            }
        } catch (\Throwable) {
            // Fall through to plan/default
        }

        // 2. Plan override
        if (array_key_exists($feature, $this->planFeatures)) {
            return (bool)$this->planFeatures[$feature];
        }

        // 3. System default — unbekannte Features sind IMMER gesperrt (safe default).
        return self::DEFAULTS[$feature] ?? false;
    }

    /**
     * Resolve all known feature flags for this tenant.
     *
     * @return array<string, bool>
     */
    public function getAll(): array
    {
        $flags = [];
        foreach (array_keys(self::DEFAULTS) as $feature) {
            $flags[$feature] = $this->hasFeature($feature);
        }
        return $flags;
    }

    /**
     * Override a feature flag directly in the tenant's settings table.
     */
    public function setFeature(string $feature, bool $enabled): void
    {
        $this->db->execute(
            "INSERT INTO `{$this->prefix}settings` (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$feature, $enabled ? '1' : '0']
        );
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}
