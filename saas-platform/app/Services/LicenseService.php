<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Config;
use Saas\Repositories\LicenseRepository;
use Saas\Repositories\TenantRepository;

class LicenseService
{
    private const OFFLINE_GRACE_DAYS = 30;
    private const TOKEN_VALIDITY_DAYS = 31;

    public function __construct(
        private Config            $config,
        private LicenseRepository $licenseRepo,
        private TenantRepository  $tenantRepo
    ) {}

    /**
     * Issue a signed license token for a tenant.
     * Called when tenant activates or renews subscription.
     */
    public function issueToken(int $tenantId): string
    {
        $secret    = $this->config->get('license.secret');
        $issuedAt  = time();
        $expiresAt = $issuedAt + (self::TOKEN_VALIDITY_DAYS * 86400);

        $tenant = $this->tenantRepo->find($tenantId);
        if (!$tenant) {
            throw new \RuntimeException("Tenant {$tenantId} nicht gefunden");
        }

        $payload = [
            'tenant_id'   => $tenantId,
            'tenant_uuid' => $tenant['uuid'],
            'plan'        => $tenant['plan_slug'] ?? '',
            'issued_at'   => $issuedAt,
            'expires_at'  => $expiresAt,
            'offline_days'=> self::OFFLINE_GRACE_DAYS,
        ];

        $payloadJson = base64_encode(json_encode($payload));
        $signature   = hash_hmac('sha256', $payloadJson, $secret);
        $token       = $payloadJson . '.' . $signature;
        $tokenHash   = hash('sha256', $token);

        // Revoke old tokens and issue new one
        $this->licenseRepo->revokeAllForTenant($tenantId);
        $this->licenseRepo->create(
            $tenantId,
            $tokenHash,
            date('Y-m-d H:i:s', $expiresAt)
        );

        return $token;
    }

    /**
     * Verify a license token presented by the Praxissoftware.
     * Returns license info array or throws on failure.
     */
    public function verifyToken(string $token, string $clientIp = ''): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Ungültiges Token-Format');
        }

        [$payloadB64, $signature] = $parts;
        $secret   = $this->config->get('license.secret');
        $expected = hash_hmac('sha256', $payloadB64, $secret);

        if (!hash_equals($expected, $signature)) {
            throw new \InvalidArgumentException('Token-Signatur ungültig');
        }

        $payload = json_decode(base64_decode($payloadB64), true);
        if (!$payload) {
            throw new \InvalidArgumentException('Token-Payload ungültig');
        }

        if ($payload['expires_at'] < time()) {
            throw new \RuntimeException('Lizenz-Token abgelaufen');
        }

        $tokenHash = hash('sha256', $token);
        $record    = $this->licenseRepo->findByTokenHash($tokenHash);

        if (!$record) {
            throw new \RuntimeException('Token nicht gefunden oder widerrufen');
        }

        if ($record['revoked']) {
            throw new \RuntimeException('Lizenz widerrufen');
        }

        if ($record['tenant_status'] !== 'active') {
            throw new \RuntimeException('Praxis-Abo inaktiv (Status: ' . $record['tenant_status'] . ')');
        }

        // Update last seen
        $this->licenseRepo->updateLastSeen((int)$record['id'], $clientIp);

        $features = json_decode($record['plan_features'] ?? '[]', true);

        return [
            'valid'        => true,
            'tenant_id'    => $record['tenant_id'],
            'tenant_uuid'  => $record['tenant_uuid'],
            'plan'         => $record['plan_slug'],
            'features'     => is_array($features) ? $features : [],
            'max_users'    => (int)$record['max_users'],
            'expires_at'   => $payload['expires_at'],
            'offline_days' => self::OFFLINE_GRACE_DAYS,
        ];
    }

    /**
     * Quick status check by tenant UUID — used for fast API responses.
     */
    public function checkByUuid(string $uuid): array
    {
        $tenant = $this->tenantRepo->findByUuid($uuid);

        if (!$tenant) {
            return ['status' => 'not_found', 'valid' => false];
        }

        if ($tenant['status'] !== 'active') {
            return [
                'status'  => $tenant['status'],
                'valid'   => false,
                'message' => 'Abo nicht aktiv',
            ];
        }

        $features = json_decode($tenant['plan_features'] ?? '[]', true);

        return [
            'status'      => 'active',
            'valid'       => true,
            'plan'        => $tenant['plan_slug'],
            'features'    => is_array($features) ? $features : [],
            'max_users'   => (int)$tenant['max_users'],
            'offline_days'=> self::OFFLINE_GRACE_DAYS,
        ];
    }

    public function revokeAllTokens(int $tenantId): void
    {
        $this->licenseRepo->revokeAllForTenant($tenantId);
    }

    public function getSecret(): string
    {
        return $this->config->get('license.secret', '');
    }
}
