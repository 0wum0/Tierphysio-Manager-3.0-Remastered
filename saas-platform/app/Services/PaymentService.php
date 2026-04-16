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
     * Returns the checkout URL or throws on failure.
     */
    public function createStripeCheckoutSession(
        int    $tenantId,
        string $email,
        string $planName,
        float  $amount,
        string $billingCycle,
        string $successUrl,
        string $cancelUrl
    ): string {
        $key = $this->settings['stripe_secret_key'] ?? '';
        if (!$key) throw new \RuntimeException('Stripe not configured');

        $interval = $billingCycle === 'yearly' ? 'year' : 'month';

        $payload = http_build_query([
            'mode'                                  => 'subscription',
            'customer_email'                        => $email,
            'success_url'                           => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'                            => $cancelUrl,
            'line_items[0][price_data][currency]'                     => 'eur',
            'line_items[0][price_data][product_data][name]'           => 'TheraPano ' . $planName,
            'line_items[0][price_data][recurring][interval]'          => $interval,
            'line_items[0][price_data][unit_amount]'                  => (int)round($amount * 100),
            'line_items[0][quantity]'                                 => 1,
            'metadata[tenant_id]'                                     => $tenantId,
            'metadata[billing_cycle]'                                 => $billingCycle,
        ]);

        $response = $this->stripeRequest('POST', '/v1/checkout/sessions', $payload, $key);
        return $response['url'] ?? throw new \RuntimeException('Stripe: no URL in response');
    }

    /**
     * Verify a completed Stripe Checkout Session and activate subscription.
     * Returns tenant_id or 0 on failure.
     */
    public function handleStripeCheckoutSuccess(string $sessionId): int
    {
        $key      = $this->settings['stripe_secret_key'] ?? '';
        $session  = $this->stripeRequest('GET', "/v1/checkout/sessions/{$sessionId}", '', $key);
        $tenantId = (int)($session['metadata']['tenant_id'] ?? 0);
        if (!$tenantId) return 0;

        $customerId = $session['customer'] ?? null;
        $subId      = $session['subscription'] ?? null;

        if ($customerId) {
            $this->db->execute(
                "UPDATE tenants SET stripe_customer_id = ?, payment_provider = 'stripe', status = 'active' WHERE id = ?",
                [$customerId, $tenantId]
            );
        }
        if ($subId) {
            $this->db->execute(
                "UPDATE subscriptions
                 SET stripe_sub_id = ?,
                     status = 'active',
                     last_payment_at = NOW(),
                     last_payment_status = 'paid',
                     billing_starts_at = COALESCE(billing_starts_at, NOW()),
                     last_webhook_sync_at = NOW()
                 WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1",
                [$subId, $tenantId]
            );
            $this->db->execute(
                "INSERT INTO payments (tenant_id, amount, currency, status, method, external_id, paid_at)
                 SELECT tenant_id, amount, currency, 'paid', 'stripe', ?, NOW()
                 FROM subscriptions WHERE stripe_sub_id = ? LIMIT 1",
                [$subId, $subId]
            );
        }

        $this->notifRepo->create(
            'payment',
            'Stripe-Zahlung eingegangen',
            "Tenant #{$tenantId} hat per Stripe bezahlt (Session: {$sessionId})"
        );

        return $tenantId;
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
            'invoice.payment_succeeded' => $this->onStripePaymentSucceeded($event),
            'invoice.payment_failed'    => $this->onStripePaymentFailed($event),
            'customer.subscription.deleted' => $this->onStripeSubDeleted($event),
            default => null,
        };

        return true;
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
