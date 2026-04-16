<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Database;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\SubscriptionRepository;
use Saas\Repositories\PlanRepository;

/**
 * SubscriptionService — Central subscription lifecycle manager.
 *
 * Responsibilities:
 *  - hasFeature()               : plan-based feature access check (fails closed, never throws)
 *  - getEffectiveSubscription() : current subscription with self-healing fallback
 *  - selfHealSubscription()     : create a safe default subscription if none exists
 *  - assignPlan()               : safely change a tenant's plan with full audit trail
 *  - setGrandfatheredPrice()    : lock in a special early-adopter or legacy price
 *  - getEffectivePrice()        : returns grandfathered price when set, plan price otherwise
 *  - startTrial() / activateFromTrial() / expireSubscription() : state machine transitions
 *  - logEvent()                 : append to subscription_events audit log
 */
class SubscriptionService
{
    private const FALLBACK_PLAN_SLUG  = 'basic';
    private const DEFAULT_TRIAL_DAYS  = 14;
    private const FALLBACK_FEATURES   = ['invoices', 'appointments', 'patients', 'owners'];

    public function __construct(
        private Database               $db,
        private TenantRepository       $tenantRepo,
        private SubscriptionRepository $subRepo,
        private PlanRepository         $planRepo
    ) {}

    // ── Feature Access ─────────────────────────────────────────────────────

    /**
     * Check whether a tenant's active plan grants the given feature.
     * Always fails closed (returns false) on missing data or errors.
     */
    public function hasFeature(string $feature, int $tenantId): bool
    {
        try {
            $sub = $this->getEffectiveSubscription($tenantId);
            if (!$sub) {
                return in_array($feature, self::FALLBACK_FEATURES, true);
            }

            if (in_array($sub['status'], ['suspended', 'expired', 'cancelled'], true)) {
                return false;
            }

            $plan = $this->planRepo->find((int)$sub['plan_id']);
            if (!$plan) {
                return in_array($feature, self::FALLBACK_FEATURES, true);
            }

            $features = json_decode($plan['features'] ?? '[]', true);
            if (!is_array($features)) {
                error_log("SubscriptionService::hasFeature – invalid features_json for plan #{$plan['id']}");
                return false;
            }

            return in_array($feature, $features, true);
        } catch (\Throwable $e) {
            error_log("SubscriptionService::hasFeature error for tenant {$tenantId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Return all feature keys currently available to a tenant.
     */
    public function getFeaturesForTenant(int $tenantId): array
    {
        try {
            $sub = $this->getEffectiveSubscription($tenantId);
            if (!$sub) {
                return self::FALLBACK_FEATURES;
            }
            if (in_array($sub['status'], ['suspended', 'expired', 'cancelled'], true)) {
                return [];
            }
            $plan = $this->planRepo->find((int)$sub['plan_id']);
            if (!$plan) {
                return self::FALLBACK_FEATURES;
            }
            $features = json_decode($plan['features'] ?? '[]', true);
            return is_array($features) ? $features : self::FALLBACK_FEATURES;
        } catch (\Throwable) {
            return self::FALLBACK_FEATURES;
        }
    }

    // ── Subscription Retrieval & Self-Healing ──────────────────────────────

    /**
     * Return the most recent subscription for a tenant.
     * If none exists, self-heals by creating a safe default record.
     */
    public function getEffectiveSubscription(int $tenantId): array|false
    {
        $sub = $this->subRepo->findByTenant($tenantId);
        if ($sub) {
            return $sub;
        }
        return $this->selfHealSubscription($tenantId);
    }

    /**
     * Self-heal: create a safe default subscription for a tenant that has none.
     * Uses the tenant's current plan_id or falls back to the configured default plan.
     * Does NOT fail – returns false only if the tenant itself does not exist.
     */
    public function selfHealSubscription(int $tenantId): array|false
    {
        try {
            $tenant = $this->tenantRepo->find($tenantId);
            if (!$tenant) {
                return false;
            }

            $planId = (int)($tenant['plan_id'] ?? 0);
            if (!$planId) {
                $plan = $this->planRepo->findBySlug(self::FALLBACK_PLAN_SLUG);
                if (!$plan) {
                    error_log("SubscriptionService::selfHeal – no fallback plan found for tenant #{$tenantId}");
                    return false;
                }
                $planId = (int)$plan['id'];
            }

            $plan = $this->planRepo->find($planId);
            if (!$plan) {
                error_log("SubscriptionService::selfHeal – plan #{$planId} not found for tenant #{$tenantId}");
                return false;
            }

            $trialDays = (int)($plan['trial_days'] ?? self::DEFAULT_TRIAL_DAYS);
            $now       = date('Y-m-d H:i:s');
            $trialEnd  = $tenant['trial_ends_at']
                ?? date('Y-m-d H:i:s', strtotime("+{$trialDays} days"));

            $tenantStatus = $tenant['status'] ?? 'active';
            $subStatus    = match ($tenantStatus) {
                'trial'     => 'trial',
                'suspended' => 'suspended',
                'cancelled' => 'cancelled',
                default     => 'active',
            };

            $this->subRepo->create([
                'tenant_id'       => $tenantId,
                'plan_id'         => $planId,
                'billing_cycle'   => 'monthly',
                'status'          => $subStatus,
                'started_at'      => $tenant['created_at'] ?? $now,
                'ends_at'         => $trialEnd,
                'next_billing'    => $trialEnd,
                'amount'          => (float)$plan['price_month'],
                'currency'        => 'EUR',
                'payment_method'  => null,
                'external_id'     => null,
                'trial_starts_at' => $tenant['created_at'] ?? $now,
                'trial_ends_at'   => $trialEnd,
                'billing_starts_at' => null,
            ]);

            $this->logEvent($tenantId, 'self_healed', [
                'plan_id'     => $planId,
                'plan_slug'   => $plan['slug'],
                'status'      => $subStatus,
                'trial_end'   => $trialEnd,
            ], 'system');

            return $this->subRepo->findByTenant($tenantId);
        } catch (\Throwable $e) {
            error_log("SubscriptionService::selfHealSubscription failed for tenant {$tenantId}: " . $e->getMessage());
            return false;
        }
    }

    // ── Plan Assignment ────────────────────────────────────────────────────

    /**
     * Safely assign a new plan to a tenant.
     * Optionally locks in a grandfathered price (e.g. early-adopter pricing).
     * Creates a subscription record if none exists.
     */
    public function assignPlan(
        int     $tenantId,
        int     $planId,
        ?float  $grandfatheredPrice = null,
        ?string $grandfatheredReason = null,
        ?string $pricingNote = null,
        string  $actor = 'admin'
    ): void {
        $plan = $this->planRepo->find($planId);
        if (!$plan) {
            throw new \RuntimeException("Plan #{$planId} nicht gefunden.");
        }

        $previousPlanId = null;

        $this->tenantRepo->update($tenantId, ['plan_id' => $planId]);

        $sub = $this->subRepo->findByTenant($tenantId);

        $effectiveAmount = $grandfatheredPrice ?? (float)$plan['price_month'];

        $updates = [
            'plan_id' => $planId,
            'amount'  => $effectiveAmount,
        ];

        if ($grandfatheredPrice !== null) {
            $updates['grandfathered_price']  = $grandfatheredPrice;
            $updates['grandfathered_reason'] = $grandfatheredReason ?? 'admin-override';
        }

        if ($pricingNote !== null) {
            $updates['pricing_note'] = $pricingNote;
        }

        if ($sub) {
            $previousPlanId = (int)$sub['plan_id'];
            $this->subRepo->update((int)$sub['id'], $updates);
        } else {
            $trialDays = (int)($plan['trial_days'] ?? self::DEFAULT_TRIAL_DAYS);
            $now       = date('Y-m-d H:i:s');
            $trialEnd  = date('Y-m-d H:i:s', strtotime("+{$trialDays} days"));
            $this->subRepo->create(array_merge([
                'tenant_id'       => $tenantId,
                'billing_cycle'   => 'monthly',
                'status'          => 'trial',
                'started_at'      => $now,
                'ends_at'         => $trialEnd,
                'next_billing'    => $trialEnd,
                'currency'        => 'EUR',
                'payment_method'  => null,
                'external_id'     => null,
                'trial_starts_at' => $now,
                'trial_ends_at'   => $trialEnd,
                'billing_starts_at' => null,
            ], $updates));
        }

        $event = $previousPlanId !== null && $previousPlanId !== $planId
            ? 'plan_changed'
            : 'plan_assigned';

        $this->logEvent($tenantId, $event, [
            'new_plan_id'         => $planId,
            'new_plan_slug'       => $plan['slug'],
            'previous_plan_id'    => $previousPlanId,
            'grandfathered_price' => $grandfatheredPrice,
            'grandfathered_reason'=> $grandfatheredReason,
        ], $actor);
    }

    // ── Grandfathered / Special Pricing ───────────────────────────────────

    /**
     * Lock in a special price for a tenant's subscription without changing the plan.
     * Used for early-adopter pricing, negotiated contracts, etc.
     * The price is stored in subscriptions.grandfathered_price and is fully traceable.
     */
    public function setGrandfatheredPrice(
        int     $tenantId,
        float   $price,
        string  $reason = 'early-adopter',
        ?string $note = null,
        string  $actor = 'admin'
    ): void {
        $sub = $this->getEffectiveSubscription($tenantId);
        if (!$sub) {
            throw new \RuntimeException("Kein Abonnement für Tenant #{$tenantId} gefunden.");
        }

        $updates = [
            'grandfathered_price'  => $price,
            'grandfathered_reason' => $reason,
            'amount'               => $price,
        ];

        if ($note !== null) {
            $updates['pricing_note'] = $note;
        }

        $this->subRepo->update((int)$sub['id'], $updates);

        $this->logEvent($tenantId, 'grandfathered', [
            'price'         => $price,
            'reason'        => $reason,
            'note'          => $note,
            'previous_amount' => (float)$sub['amount'],
        ], $actor);
    }

    /**
     * Remove grandfathered pricing and restore the plan's standard price.
     */
    public function removeGrandfatheredPrice(int $tenantId, string $actor = 'admin'): void
    {
        $sub = $this->subRepo->findByTenant($tenantId);
        if (!$sub) {
            return;
        }

        $plan = $this->planRepo->find((int)$sub['plan_id']);
        $standardPrice = $plan ? (float)$plan['price_month'] : (float)$sub['amount'];

        $this->subRepo->update((int)$sub['id'], [
            'grandfathered_price'  => null,
            'grandfathered_reason' => null,
            'amount'               => $standardPrice,
        ]);

        $this->logEvent($tenantId, 'grandfathered_removed', [
            'restored_price' => $standardPrice,
        ], $actor);
    }

    /**
     * Return the effective monthly price for a tenant.
     * Grandfathered price takes precedence over the plan's standard price.
     */
    public function getEffectivePrice(int $tenantId): float
    {
        try {
            $sub = $this->subRepo->findByTenant($tenantId);
            if (!$sub) {
                return 0.0;
            }
            if (!empty($sub['grandfathered_price'])) {
                return (float)$sub['grandfathered_price'];
            }
            return (float)$sub['amount'];
        } catch (\Throwable) {
            return 0.0;
        }
    }

    // ── Subscription State Machine ─────────────────────────────────────────

    /**
     * Transition a tenant's subscription into 'trial' status and record timestamps.
     * Idempotent: safe to call multiple times.
     */
    public function startTrial(int $tenantId, ?int $trialDays = null, string $actor = 'system'): void
    {
        $sub = $this->getEffectiveSubscription($tenantId);
        if (!$sub) {
            return;
        }

        $plan = $this->planRepo->find((int)$sub['plan_id']);
        $days = $trialDays ?? (int)($plan['trial_days'] ?? self::DEFAULT_TRIAL_DAYS);

        $now      = date('Y-m-d H:i:s');
        $trialEnd = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        $this->subRepo->update((int)$sub['id'], [
            'status'          => 'trial',
            'trial_starts_at' => $now,
            'trial_ends_at'   => $trialEnd,
            'ends_at'         => $trialEnd,
        ]);

        $this->tenantRepo->update($tenantId, [
            'status'        => 'trial',
            'trial_ends_at' => $trialEnd,
        ]);

        $this->logEvent($tenantId, 'trial_started', [
            'trial_days' => $days,
            'ends_at'    => $trialEnd,
        ], $actor);
    }

    /**
     * Transition subscription from 'trial' or 'trialing' → 'active'.
     * Records billing_starts_at and optional Stripe subscription ID.
     */
    public function activateFromTrial(
        int     $tenantId,
        ?string $stripeSubId = null,
        string  $actor = 'system'
    ): void {
        $sub = $this->getEffectiveSubscription($tenantId);
        if (!$sub) {
            return;
        }

        $now         = date('Y-m-d H:i:s');
        $nextBilling = date('Y-m-d H:i:s', strtotime('+1 month'));

        $updates = [
            'status'            => 'active',
            'billing_starts_at' => $now,
            'next_billing'      => $nextBilling,
            'ends_at'           => $nextBilling,
            'last_webhook_sync_at' => $now,
        ];

        if ($stripeSubId !== null) {
            $updates['stripe_sub_id'] = $stripeSubId;
        }

        $this->subRepo->update((int)$sub['id'], $updates);
        $this->tenantRepo->update($tenantId, ['status' => 'active']);

        $this->logEvent($tenantId, 'activated', [
            'stripe_sub_id'     => $stripeSubId,
            'billing_starts_at' => $now,
            'was_status'        => $sub['status'],
        ], $actor);
    }

    /**
     * Mark a subscription as expired (trial ended, no payment).
     * Transitions tenant to 'suspended'.
     */
    public function expireSubscription(int $tenantId, string $actor = 'cron'): void
    {
        $sub = $this->subRepo->findByTenant($tenantId);
        if (!$sub) {
            return;
        }

        $wasStatus = $sub['status'];
        $this->subRepo->update((int)$sub['id'], ['status' => 'expired']);
        $this->tenantRepo->update($tenantId, ['status' => 'suspended']);

        $this->logEvent($tenantId, 'trial_ended', [
            'was_status' => $wasStatus,
        ], $actor);
    }

    /**
     * Cancel a subscription and transition tenant to 'cancelled'.
     */
    public function cancelSubscription(int $tenantId, string $actor = 'admin'): void
    {
        $sub = $this->subRepo->findByTenant($tenantId);
        if ($sub) {
            $this->subRepo->update((int)$sub['id'], [
                'status'       => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->tenantRepo->update($tenantId, ['status' => 'cancelled']);

        $this->logEvent($tenantId, 'canceled', [], $actor);
    }

    /**
     * Update the Stripe webhook sync timestamp on a subscription.
     */
    public function markStripeSynced(int $tenantId, array $details = []): void
    {
        $sub = $this->subRepo->findByTenant($tenantId);
        if (!$sub) {
            return;
        }
        try {
            $this->subRepo->update((int)$sub['id'], [
                'last_webhook_sync_at' => date('Y-m-d H:i:s'),
            ]);
            $this->logEvent($tenantId, 'stripe_sync', $details, 'stripe');
        } catch (\Throwable) {}
    }

    // ── Audit Logging ──────────────────────────────────────────────────────

    /**
     * Append a subscription lifecycle event to subscription_events.
     * Silent on failure – logging must never crash the main flow.
     */
    public function logEvent(
        int    $tenantId,
        string $event,
        array  $details = [],
        string $actor = 'system'
    ): void {
        try {
            $this->db->execute(
                "INSERT INTO subscription_events (tenant_id, event, details, actor, created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $event, json_encode($details, JSON_UNESCAPED_UNICODE), $actor]
            );
        } catch (\Throwable $e) {
            error_log("SubscriptionService::logEvent failed for tenant {$tenantId} / {$event}: " . $e->getMessage());
        }
    }

    /**
     * Return the most recent subscription events for a tenant.
     */
    public function getEventsForTenant(int $tenantId, int $limit = 25): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM subscription_events WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ?",
                [$tenantId, $limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Count tenants with active grandfathered pricing (for admin dashboard).
     */
    public function countGrandfathered(): int
    {
        try {
            return (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM subscriptions WHERE grandfathered_price IS NOT NULL AND status = 'active'"
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}
