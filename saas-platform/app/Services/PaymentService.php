<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Database;
use Saas\Repositories\NotificationRepository;

/**
 * PaymentService — Stripe + PayPal integration
 *
 * Requires saas_settings keys:
 *   stripe_enabled, stripe_secret_key, stripe_public_key, stripe_webhook_secret
 *   paypal_enabled, paypal_client_id, paypal_client_secret, paypal_sandbox
 */
class PaymentService
{
    private array $settings = [];

    public function __construct(
        private Database               $db,
        private NotificationRepository $notifRepo
    ) {
        $rows = $this->db->fetchAll("SELECT `key`, `value` FROM saas_settings");
        foreach ($rows as $r) {
            $this->settings[$r['key']] = $r['value'];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isStripeEnabled(): bool
    {
        return ($this->settings['stripe_enabled'] ?? '0') === '1'
            && !empty($this->settings['stripe_secret_key']);
    }

    public function isPayPalEnabled(): bool
    {
        return ($this->settings['paypal_enabled'] ?? '0') === '1'
            && !empty($this->settings['paypal_client_id'])
            && !empty($this->settings['paypal_client_secret']);
    }

    public function getStripePublicKey(): string
    {
        return $this->settings['stripe_public_key'] ?? '';
    }

    public function isPayPalSandbox(): bool
    {
        return ($this->settings['paypal_sandbox'] ?? '1') === '1';
    }

    // ── Stripe ────────────────────────────────────────────────────────────

    /**
     * Create a Stripe Checkout Session for a subscription plan.
     *
     * Trial-Logik (Endziel-konform):
     *   $trialDays > 0  → subscription_data[trial_period_days] an Stripe
     *                     → Stripe führt Trial autonom und bucht danach selbst ab
     *   $trialDays = 0  → keine Trial (sofortige Abbuchung)
     *
     * Price-ID-Logik:
     *   Wenn für den Billing-Cycle (monthly/yearly) eine Stripe Price ID im Plan
     *   hinterlegt ist, wird diese verwendet (Stripe-managed Preis, Steuern,
     *   Proration etc.). Fallback: inline price_data aus $amount (Legacy-Pfad,
     *   z.B. für grandfathered Prices oder wenn Admin noch keine Price ID pflegt).
     *
     * Returns the checkout URL or throws on failure.
     */
    public function createStripeCheckoutSession(
        int     $tenantId,
        string  $email,
        string  $planName,
        float   $amount,
        string  $billingCycle,
        string  $successUrl,
        string  $cancelUrl,
        int     $trialDays       = 0,
        ?string $priceIdMonthly  = null,
        ?string $priceIdYearly   = null
    ): string {
        $key = $this->settings['stripe_secret_key'] ?? '';
        if (!$key) throw new \RuntimeException('Stripe not configured');

        $interval = $billingCycle === 'yearly' ? 'year' : 'month';
        $priceId  = $billingCycle === 'yearly' ? $priceIdYearly : $priceIdMonthly;
        $priceId  = is_string($priceId) && trim($priceId) !== '' ? trim($priceId) : null;

        $fields = [
            'mode'                      => 'subscription',
            'customer_email'            => $email,
            'success_url'               => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'                => $cancelUrl,
            'client_reference_id'       => (string)$tenantId,
            'line_items[0][quantity]'   => 1,
            'metadata[tenant_id]'       => $tenantId,
            'metadata[billing_cycle]'   => $billingCycle,
            'metadata[plan_name]'       => $planName,
            'metadata[trial_days]'      => $trialDays,
            /* Metadaten auch auf der Subscription selbst — wichtig für Webhooks,
             * in denen die Session nicht wieder aufgelöst wird. */
            'subscription_data[metadata][tenant_id]'     => $tenantId,
            'subscription_data[metadata][billing_cycle]' => $billingCycle,
        ];

        if ($priceId !== null) {
            /* Plan-gebundene Price ID nutzen — Stripe hat Preis, Währung und Intervall */
            $fields['line_items[0][price]'] = $priceId;
        } else {
            /* Fallback: dynamisches price_data (Legacy / grandfathered) */
            $fields['line_items[0][price_data][currency]']             = 'eur';
            $fields['line_items[0][price_data][product_data][name]']   = 'TheraPano ' . $planName;
            $fields['line_items[0][price_data][recurring][interval]']  = $interval;
            $fields['line_items[0][price_data][unit_amount]']          = (int)round($amount * 100);
        }

        /* Trial-Dauer AUSSCHLIESSLICH aus dem Plan — niemals hardcoded */
        if ($trialDays > 0) {
            $fields['subscription_data[trial_period_days]'] = $trialDays;
        }

        $response = $this->stripeRequest('POST', '/v1/checkout/sessions', http_build_query($fields), $key);
        if (!isset($response['url'])) {
            $err = $response['error']['message'] ?? 'unknown error';
            error_log("[PaymentService] Stripe checkout creation failed: {$err}");
            throw new \RuntimeException('Stripe: no URL in response (' . $err . ')');
        }
        return $response['url'];
    }

    /**
     * Verify a completed Stripe Checkout Session and activate subscription.
     * Returns tenant_id or 0 on failure.
     */
    public function handleStripeCheckoutSuccess(string $sessionId): int
    {
        $key      = $this->settings['stripe_secret_key'] ?? '';
        $session  = $this->stripeRequest('GET', "/v1/checkout/sessions/{$sessionId}", '', $key);
        $tenantId = (int)(
            $session['metadata']['tenant_id']
            ?? $session['client_reference_id']
            ?? 0
        );
        if (!$tenantId) {
            error_log("[PaymentService] checkout.success without tenant reference (session {$sessionId})");
            return 0;
        }

        $customerId = $session['customer']     ?? null;
        $subId      = $session['subscription'] ?? null;

        /* Echten Subscription-Status aus Stripe holen — KEINE lokalen Annahmen.
         * Stripe ist Single Source of Truth für Billing-Status. */
        $stripeSubStatus = null;
        $trialEndUnix    = null;
        if ($subId) {
            $sub = $this->stripeRequest('GET', "/v1/subscriptions/{$subId}", '', $key);
            $stripeSubStatus = $sub['status']    ?? null;     // trialing | active | past_due | …
            $trialEndUnix    = $sub['trial_end'] ?? null;     // null wenn keine Trial
        }

        /* Stripe-Status → lokaler Tenant/Subscription-Status */
        [$tenantStatus, $subStatus] = $this->mapStripeStatus($stripeSubStatus);

        if ($customerId) {
            $this->db->execute(
                "UPDATE tenants
                 SET stripe_customer_id = ?, payment_provider = 'stripe', status = ?
                 WHERE id = ?",
                [$customerId, $tenantStatus, $tenantId]
            );
        }

        if ($subId) {
            /* billing_starts_at nur setzen wenn wir schon bezahlen (nicht im Trial) */
            $billingStartsSql = $subStatus === 'active'
                ? ', billing_starts_at = COALESCE(billing_starts_at, NOW())'
                : '';

            $trialEndSql    = '';
            $trialEndParams = [];
            if ($trialEndUnix) {
                $trialEndSql     = ', trial_ends_at = ?, ends_at = ?';
                $trialEndIso     = date('Y-m-d H:i:s', (int)$trialEndUnix);
                $trialEndParams  = [$trialEndIso, $trialEndIso];
            }

            $this->db->execute(
                "UPDATE subscriptions
                 SET stripe_sub_id = ?,
                     status = ?,
                     last_webhook_sync_at = NOW()"
                 . $billingStartsSql
                 . $trialEndSql .
                " WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1",
                array_merge([$subId, $subStatus], $trialEndParams, [$tenantId])
            );

            /* Payment-Record nur wenn tatsächlich bezahlt (nicht bei Trial-Start,
             * da bei Trial noch kein Geld fließt) */
            if ($subStatus === 'active') {
                $this->db->execute(
                    "INSERT INTO payments (tenant_id, amount, currency, status, method, external_id, paid_at)
                     SELECT tenant_id, amount, currency, 'paid', 'stripe', ?, NOW()
                     FROM subscriptions WHERE stripe_sub_id = ? LIMIT 1",
                    [$subId, $subId]
                );
            }
        }

        $notifTitle = $subStatus === 'trial'
            ? 'Stripe Trial gestartet'
            : 'Stripe-Zahlung eingegangen';
        $this->notifRepo->create(
            'payment',
            $notifTitle,
            "Tenant #{$tenantId} — Stripe-Status: {$stripeSubStatus} (Session: {$sessionId})"
        );

        return $tenantId;
    }

    /**
     * Mappt einen Stripe-Subscription-Status auf lokale tenant + subscription Status.
     * Ergebnis: [$tenantStatus, $subStatus]
     *
     * Bei unbekannten/null Status → sichere Defaults (trial für Tenant, trial für Sub).
     * Bewusst KONSERVATIV: lieber Tenant als „trial" markieren als fälschlich
     * volle Freischaltung (siehe Phase-6-Regel).
     */
    private function mapStripeStatus(?string $stripeStatus): array
    {
        return match ($stripeStatus) {
            'trialing'           => ['trial',     'trial'],
            'active'             => ['active',    'active'],
            'past_due'           => ['active',    'past_due'],   // Tenant bleibt nutzbar, Sub-Mahnung läuft
            'unpaid'             => ['suspended', 'past_due'],
            'canceled'           => ['cancelled', 'cancelled'],
            'incomplete_expired' => ['suspended', 'expired'],
            'incomplete'         => ['trial',     'trial'],      // noch kein Payment — wie Trial behandeln
            default              => ['trial',     'trial'],      // fail-safe
        };
    }

    /**
     * Handle Stripe webhook — called from webhook endpoint.
     */
    public function handleStripeWebhook(string $payload, string $sigHeader): bool
    {
        $secret = $this->settings['stripe_webhook_secret'] ?? '';
        if ($secret) {
            // Verify signature
            $parts     = [];
            foreach (explode(',', $sigHeader) as $part) {
                [$k, $v] = explode('=', $part, 2);
                $parts[$k] = $v;
            }
            $timestamp = $parts['t'] ?? '';
            $sig       = $parts['v1'] ?? '';
            $expected  = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
            if (!hash_equals($expected, $sig)) return false;
        }

        $event = json_decode($payload, true);
        $type  = $event['type'] ?? '';

        match($type) {
            'invoice.payment_succeeded'      => $this->onStripePaymentSucceeded($event),
            'invoice.paid'                   => $this->onStripePaymentSucceeded($event),
            'invoice.payment_failed'         => $this->onStripePaymentFailed($event),
            /* Stripe schickt bei Trial→Active, Plan-Wechsel, Cancel-at-period-end usw.
             * einen subscription.updated. Das ist der Single Source of Truth. */
            'customer.subscription.created'  => $this->onStripeSubUpdated($event),
            'customer.subscription.updated'  => $this->onStripeSubUpdated($event),
            'customer.subscription.deleted'  => $this->onStripeSubDeleted($event),
            default => null,
        };

        return true;
    }

    /**
     * customer.subscription.created / updated
     *
     * Hauptsächlich wichtig für:
     *   - Trial startet         → status = trial
     *   - Trial endet und Stripe bucht erfolgreich → status = active
     *   - Zahlung schlägt fehl  → status = past_due
     *   - Cancel at period end  → wird bei .deleted finalisiert
     */
    private function onStripeSubUpdated(array $event): void
    {
        $sub = $event['data']['object'] ?? [];
        if (!$sub) return;

        $subId        = $sub['id']       ?? null;
        $customerId   = $sub['customer'] ?? null;
        $stripeStatus = $sub['status']   ?? null;
        $trialEndUnix = $sub['trial_end'] ?? null;

        /* Tenant auflösen: erst über customer_id (stabilster Link),
         * Fallback über Metadata (für frisch erstellte Subs bevor customer_id gespeichert ist). */
        $tenant = null;
        if ($customerId) {
            $tenant = $this->db->fetch("SELECT id FROM tenants WHERE stripe_customer_id = ?", [$customerId]);
        }
        if (!$tenant && !empty($sub['metadata']['tenant_id'])) {
            $tenant = $this->db->fetch("SELECT id FROM tenants WHERE id = ?", [(int)$sub['metadata']['tenant_id']]);
            /* Customer-ID nachträglich persistieren, damit future webhooks direkt mappen */
            if ($tenant && $customerId) {
                $this->db->execute(
                    "UPDATE tenants SET stripe_customer_id = ? WHERE id = ? AND (stripe_customer_id IS NULL OR stripe_customer_id = '')",
                    [$customerId, (int)$tenant['id']]
                );
            }
        }
        if (!$tenant) {
            error_log("[PaymentService] subscription.updated: tenant nicht auflösbar (sub={$subId}, customer={$customerId})");
            return;
        }

        [$tenantStatus, $subStatus] = $this->mapStripeStatus($stripeStatus);

        $this->db->execute(
            "UPDATE tenants SET status = ? WHERE id = ?",
            [$tenantStatus, (int)$tenant['id']]
        );

        $trialEndSql    = '';
        $trialEndParams = [];
        if ($trialEndUnix) {
            $trialEndSql    = ', trial_ends_at = ?';
            $trialEndParams = [date('Y-m-d H:i:s', (int)$trialEndUnix)];
        }

        $billingStartsSql = $subStatus === 'active'
            ? ', billing_starts_at = COALESCE(billing_starts_at, NOW())'
            : '';

        $this->db->execute(
            "UPDATE subscriptions
             SET stripe_sub_id = COALESCE(stripe_sub_id, ?),
                 status = ?,
                 last_webhook_sync_at = NOW()"
             . $billingStartsSql
             . $trialEndSql .
            " WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1",
            array_merge([$subId, $subStatus], $trialEndParams, [(int)$tenant['id']])
        );
    }

    private function onStripePaymentSucceeded(array $event): void
    {
        $invoice    = $event['data']['object'];
        $customerId = $invoice['customer'] ?? null;
        $amount     = ($invoice['amount_paid'] ?? 0) / 100;
        $subId      = $invoice['subscription'] ?? null;

        if (!$customerId) return;

        $tenant = $this->db->fetch("SELECT id FROM tenants WHERE stripe_customer_id = ?", [$customerId]);
        if (!$tenant) return;

        $this->db->execute(
            "UPDATE tenants SET status = 'active' WHERE id = ?", [$tenant['id']]
        );
        $this->db->execute(
            "UPDATE subscriptions
             SET status = 'active',
                 last_payment_at = NOW(),
                 last_payment_status = 'paid',
                 billing_starts_at = COALESCE(billing_starts_at, NOW()),
                 last_webhook_sync_at = NOW()
             WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1",
            [$tenant['id']]
        );
        $this->db->execute(
            "INSERT INTO payments (tenant_id, amount, currency, status, method, external_id, paid_at)
             VALUES (?, ?, 'EUR', 'paid', 'stripe', ?, NOW())",
            [$tenant['id'], $amount, $subId]
        );

        $this->notifRepo->create('payment', 'Stripe-Zahlung erfolgreich', "Tenant #{$tenant['id']}: {$amount} €");
    }

    private function onStripePaymentFailed(array $event): void
    {
        $invoice    = $event['data']['object'];
        $customerId = $invoice['customer'] ?? null;
        if (!$customerId) return;

        $tenant = $this->db->fetch("SELECT id FROM tenants WHERE stripe_customer_id = ?", [$customerId]);
        if (!$tenant) return;

        $this->db->execute(
            "UPDATE subscriptions SET status = 'past_due', last_payment_status = 'failed'
             WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1",
            [$tenant['id']]
        );
        $this->notifRepo->create('overdue', 'Stripe-Zahlung fehlgeschlagen', "Tenant #{$tenant['id']} – Zahlung fehlgeschlagen");
    }

    private function onStripeSubDeleted(array $event): void
    {
        $sub        = $event['data']['object'];
        $customerId = $sub['customer'] ?? null;
        if (!$customerId) return;

        $tenant = $this->db->fetch("SELECT id FROM tenants WHERE stripe_customer_id = ?", [$customerId]);
        if (!$tenant) return;

        $this->db->execute("UPDATE tenants SET status = 'cancelled' WHERE id = ?", [$tenant['id']]);
        $this->db->execute(
            "UPDATE subscriptions
             SET status = 'cancelled',
                 cancelled_at = NOW(),
                 last_webhook_sync_at = NOW()
             WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1",
            [$tenant['id']]
        );
        $this->notifRepo->create('system_update', 'Stripe-Abo gekündigt', "Tenant #{$tenant['id']} hat sein Abo beendet");
    }

    // ── PayPal ────────────────────────────────────────────────────────────

    /**
     * Get PayPal access token.
     */
    public function getPayPalAccessToken(): string
    {
        $clientId     = $this->settings['paypal_client_id'] ?? '';
        $clientSecret = $this->settings['paypal_client_secret'] ?? '';
        $sandbox      = $this->isPayPalSandbox();
        $baseUrl      = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

        $ch = curl_init("{$baseUrl}/v1/oauth2/token");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => "{$clientId}:{$clientSecret}",
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
            CURLOPT_SSL_VERIFYPEER => !$sandbox,
        ]);
        $response = json_decode(curl_exec($ch) ?: '', true) ?? [];
        curl_close($ch);

        return $response['access_token'] ?? '';
    }

    /**
     * Create a PayPal subscription (billing plan).
     * Returns approval URL for redirect.
     */
    public function createPayPalSubscription(
        int    $tenantId,
        string $planName,
        float  $amount,
        string $billingCycle,
        string $returnUrl,
        string $cancelUrl
    ): string {
        $accessToken = $this->getPayPalAccessToken();
        if (!$accessToken) throw new \RuntimeException('PayPal auth failed');

        $sandbox = $this->isPayPalSandbox();
        $baseUrl = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

        // First create a product
        $product = $this->paypalRequest('POST', "{$baseUrl}/v1/catalogs/products", [
            'name'        => 'TheraPano ' . $planName,
            'type'        => 'SERVICE',
            'category'    => 'SOFTWARE',
        ], $accessToken);

        $productId = $product['id'] ?? null;
        if (!$productId) throw new \RuntimeException('PayPal: product creation failed');

        // Create billing plan
        $interval = $billingCycle === 'yearly' ? 'YEAR' : 'MONTH';
        $plan = $this->paypalRequest('POST', "{$baseUrl}/v1/billing/plans", [
            'product_id'          => $productId,
            'name'                => 'TheraPano ' . $planName . ' ' . ucfirst($billingCycle),
            'status'              => 'ACTIVE',
            'billing_cycles'      => [[
                'frequency'       => ['interval_unit' => $interval, 'interval_count' => 1],
                'tenure_type'     => 'REGULAR',
                'sequence'        => 1,
                'total_cycles'    => 0,
                'pricing_scheme'  => ['fixed_price' => ['value' => number_format($amount, 2, '.', ''), 'currency_code' => 'EUR']],
            ]],
            'payment_preferences' => ['auto_bill_outstanding' => true, 'setup_fee_failure_action' => 'CONTINUE', 'payment_failure_threshold' => 3],
        ], $accessToken);

        $planId = $plan['id'] ?? null;
        if (!$planId) throw new \RuntimeException('PayPal: plan creation failed');

        // Create subscription
        $sub = $this->paypalRequest('POST', "{$baseUrl}/v1/billing/subscriptions", [
            'plan_id'             => $planId,
            'application_context' => [
                'return_url'      => $returnUrl . "?tenant_id={$tenantId}",
                'cancel_url'      => $cancelUrl,
                'user_action'     => 'SUBSCRIBE_NOW',
            ],
            'custom_id'           => (string)$tenantId,
        ], $accessToken);

        foreach ($sub['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') return $link['href'];
        }
        throw new \RuntimeException('PayPal: no approval URL');
    }

    /**
     * Capture/verify a PayPal subscription after redirect.
     */
    public function handlePayPalReturn(int $tenantId, string $subscriptionId): void
    {
        $this->db->execute(
            "UPDATE tenants SET paypal_customer_id = ?, payment_provider = 'paypal', status = 'active' WHERE id = ?",
            [$subscriptionId, $tenantId]
        );
        $this->db->execute(
            "UPDATE subscriptions SET paypal_sub_id = ?, status = 'active', last_payment_at = NOW(), last_payment_status = 'paid'
             WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1",
            [$subscriptionId, $tenantId]
        );
        $this->notifRepo->create('payment', 'PayPal-Abo aktiviert', "Tenant #{$tenantId}: PayPal Subscription {$subscriptionId}");
    }

    // ── Private HTTP helpers ───────────────────────────────────────────────

    private function stripeRequest(string $method, string $path, string $body, string $key): array
    {
        $ch = curl_init("https://api.stripe.com{$path}");
        $headers = ["Authorization: Bearer {$key}"];
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($method === 'POST' && $body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res ?: '', true) ?? [];
    }

    private function paypalRequest(string $method, string $url, array $body, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res ?: '', true) ?? [];
    }
}
