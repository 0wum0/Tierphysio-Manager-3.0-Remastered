<?php

declare(strict_types=1);

namespace Saas\Repositories;

use Saas\Core\Database;

class LicenseRepository
{
    public function __construct(private Database $db) {}

    public function findByTokenHash(string $hash): array|false
    {
        return $this->db->fetch(
            "SELECT lt.*, t.status AS tenant_status, t.plan_id, t.uuid AS tenant_uuid,
                    p.features AS plan_features, p.slug AS plan_slug, p.max_users
             FROM license_tokens lt
             JOIN tenants t ON t.id = lt.tenant_id
             LEFT JOIN plans p ON p.id = t.plan_id
             WHERE lt.token_hash = ? AND lt.revoked = 0",
            [$hash]
        );
    }

    public function create(int $tenantId, string $tokenHash, string $expiresAt): int
    {
        return (int)$this->db->insert(
            "INSERT INTO license_tokens (tenant_id, token_hash, expires_at)
             VALUES (?, ?, ?)",
            [$tenantId, $tokenHash, $expiresAt]
        );
    }

    public function updateLastSeen(int $id, string $ip): void
    {
        $this->db->execute(
            "UPDATE license_tokens SET last_seen_at = NOW(), ip_address = ? WHERE id = ?",
            [$ip, $id]
        );
    }

    public function revokeAllForTenant(int $tenantId): void
    {
        $this->db->execute(
            "UPDATE license_tokens SET revoked = 1, revoked_at = NOW() WHERE tenant_id = ?",
            [$tenantId]
        );
    }

    public function revokeToken(int $id): void
    {
        $this->db->execute(
            "UPDATE license_tokens SET revoked = 1, revoked_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function getActiveForTenant(int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM license_tokens
             WHERE tenant_id = ? AND revoked = 0 AND expires_at > NOW()
             ORDER BY issued_at DESC",
            [$tenantId]
        );
    }
}
