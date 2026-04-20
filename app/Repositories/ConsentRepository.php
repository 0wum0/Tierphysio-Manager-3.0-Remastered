<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

class ConsentRepository extends Repository
{
    protected string $table = 'dogschool_consents';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function listAll(): array
    {
        return $this->db->safeFetchAll(
            "SELECT * FROM `{$this->t()}` ORDER BY is_active DESC, name ASC"
        );
    }

    public function signaturesForConsent(int $consentId): array
    {
        return $this->db->safeFetchAll(
            "SELECT s.*,
                    o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                    p.name AS patient_name
               FROM `{$this->t('dogschool_consent_signatures')}` s
               LEFT JOIN `{$this->t('owners')}`   o ON o.id = s.owner_id
               LEFT JOIN `{$this->t('patients')}` p ON p.id = s.patient_id
              WHERE s.consent_id = ?
              ORDER BY s.status = 'pending' DESC, s.created_at DESC",
            [$consentId]
        );
    }

    public function createSignature(int $consentId, int $ownerId, ?int $patientId): string
    {
        return $this->db->insert(
            "INSERT INTO `{$this->t('dogschool_consent_signatures')}`
                (consent_id, owner_id, patient_id, status)
             VALUES (?, ?, ?, 'pending')",
            [$consentId, $ownerId, $patientId]
        );
    }

    public function sign(int $signatureId, string $name, ?string $signatureData, string $ip, string $ua): int
    {
        return $this->db->safeExecute(
            "UPDATE `{$this->t('dogschool_consent_signatures')}`
                SET status = 'signed', signed_at = NOW(),
                    signature_name = ?, signature_data = ?,
                    ip_address = ?, user_agent = ?
              WHERE id = ? AND status = 'pending'",
            [$name, $signatureData, $ip, $ua, $signatureId]
        );
    }

    public function revoke(int $signatureId): int
    {
        return $this->db->safeExecute(
            "UPDATE `{$this->t('dogschool_consent_signatures')}`
                SET status = 'revoked', revoked_at = NOW()
              WHERE id = ?",
            [$signatureId]
        );
    }
}
