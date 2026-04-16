<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;

/**
 * Feature flag system.
 *
 * Reads feature flags from the tenant settings table.
 * Key format: "{feature_name}" → "1" / "0" / "true" / "false"
 *
 * Priority (highest wins):
 *  1. Explicit tenant setting in the settings table
 *  2. Plan-level override (passed via constructor)
 *  3. System-wide default (defined in DEFAULTS constant)
 *
 * Self-healing: missing flags fall back to defaults without erroring.
 *
 * Feature #5: Feature Flag System
 */
class FeatureFlagService
{
    /**
     * System-wide defaults.
     * true  = feature is on unless explicitly disabled.
     * false = feature is off unless explicitly enabled.
     */
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
     * @param SettingsRepository   $settings      Tenant settings repository (prefix already set).
     * @param array<string, bool>  $planFeatures  Optional plan-level feature overrides.
     */
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly array              $planFeatures = []
    ) {}

    /* ──────────────────────────────────────────────────────────
       Public API
    ────────────────────────────────────────────────────────── */

    /**
     * Check whether a feature is enabled for the current tenant.
     *
     * @param string $feature  Feature key, e.g. "calendar_enabled"
     */
    public function hasFeature(string $feature): bool
    {
        // 1. Tenant explicit setting (highest priority)
        $tenantValue = $this->settings->get($feature);
        if ($tenantValue !== null && $tenantValue !== '') {
            return $this->isTruthy($tenantValue);
        }

        // 2. Plan-level override
        if (array_key_exists($feature, $this->planFeatures)) {
            return (bool)$this->planFeatures[$feature];
        }

        // 3. System default (unknown flags default to enabled)
        return self::DEFAULTS[$feature] ?? true;
    }

    /**
     * Resolve all known feature flags and return them as a map.
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
     * Enable or disable a feature for the current tenant.
     * Writes directly to the settings table.
     */
    public function setFeature(string $feature, bool $enabled): void
    {
        $this->settings->set($feature, $enabled ? '1' : '0');
    }

    /**
     * Check multiple features at once.
     * Returns a map of feature → bool.
     *
     * @param  list<string> $features
     * @return array<string, bool>
     */
    public function checkMany(array $features): array
    {
        $result = [];
        foreach ($features as $feature) {
            $result[$feature] = $this->hasFeature($feature);
        }
        return $result;
    }

    /**
     * Return only the features that are currently enabled.
     *
     * @return list<string>
     */
    public function getEnabled(): array
    {
        return array_keys(array_filter($this->getAll()));
    }

    /* ──────────────────────────────────────────────────────────
       Internal helpers
    ────────────────────────────────────────────────────────── */

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}
