<?php

declare(strict_types=1);

namespace Saas\Repositories;

use Saas\Core\Database;

class LegalRepository
{
    public function __construct(private Database $db) {}

    public function allActive(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM legal_documents WHERE is_active = 1 ORDER BY id ASC"
        );
    }

    public function all(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM legal_documents ORDER BY id ASC"
        );
    }

    public function find(int $id): array|false
    {
        return $this->db->fetch("SELECT * FROM legal_documents WHERE id = ?", [$id]);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetch("SELECT * FROM legal_documents WHERE slug = ?", [$slug]);
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE legal_documents SET title = ?, content = ?, version = ?, updated_at = NOW() WHERE id = ?",
            [$data['title'], $data['content'], $data['version'], $id]
        );
    }

    public function recordAcceptance(int $tenantId, int $documentId, string $version, string $ip): void
    {
        $this->db->execute(
            "INSERT INTO legal_acceptances (tenant_id, document_id, version, ip_address)
             VALUES (?, ?, ?, ?)",
            [$tenantId, $documentId, $version, $ip]
        );
    }

    public function getAcceptances(int $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT la.*, ld.title, ld.slug
             FROM legal_acceptances la
             JOIN legal_documents ld ON ld.id = la.document_id
             WHERE la.tenant_id = ?
             ORDER BY la.accepted_at DESC",
            [$tenantId]
        );
    }
}
