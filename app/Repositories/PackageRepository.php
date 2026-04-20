<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

/**
 * Pakete / Mehrfachkarten für Hundeschulen.
 *
 * Die Paket-Logik kennt 3 Ebenen:
 *   1. dogschool_packages            – Katalog/Vorlage
 *   2. dogschool_package_balances    – Instanz pro Halter (Kontostand)
 *   3. dogschool_package_redemptions – Einlösungen pro Termin/Einschreibung
 */
class PackageRepository extends Repository
{
    protected string $table = 'dogschool_packages';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /* ═══════════════════ Katalog ═══════════════════ */

    public function listActive(): array
    {
        return $this->db->safeFetchAll(
            "SELECT * FROM `{$this->t()}` WHERE is_active = 1 ORDER BY price_cents ASC"
        );
    }

    public function listAll(): array
    {
        return $this->db->safeFetchAll(
            "SELECT * FROM `{$this->t()}` ORDER BY is_active DESC, price_cents ASC"
        );
    }

    /* ═══════════════════ Balances ═══════════════════ */

    public function balancesForOwner(int $ownerId): array
    {
        return $this->db->safeFetchAll(
            "SELECT b.*, p.name AS package_name, p.type AS package_type, p.total_units,
                    (b.units_total - b.units_used) AS units_left,
                    pt.name AS patient_name
               FROM `{$this->t('dogschool_package_balances')}` b
               LEFT JOIN `{$this->t('dogschool_packages')}` p ON p.id = b.package_id
               LEFT JOIN `{$this->t('patients')}` pt ON pt.id = b.patient_id
              WHERE b.owner_id = ?
              ORDER BY b.status = 'active' DESC, b.purchased_at DESC",
            [$ownerId]
        );
    }

    public function listBalances(int $page = 1, int $perPage = 50, string $status = 'active'): array
    {
        $conditions = [];
        $params     = [];
        if ($status !== '' && $status !== 'all') {
            $conditions[] = 'b.status = ?';
            $params[]     = $status;
        }
        $where  = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $offset = max(0, ($page - 1) * $perPage);

        $total = (int)$this->db->safeFetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('dogschool_package_balances')}` b {$where}",
            $params
        );
        $items = $this->db->safeFetchAll(
            "SELECT b.*, p.name AS package_name, p.type AS package_type,
                    (b.units_total - b.units_used) AS units_left,
                    o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                    pt.name AS patient_name
               FROM `{$this->t('dogschool_package_balances')}` b
               LEFT JOIN `{$this->t('dogschool_packages')}` p ON p.id = b.package_id
               LEFT JOIN `{$this->t('owners')}`   o  ON o.id  = b.owner_id
               LEFT JOIN `{$this->t('patients')}` pt ON pt.id = b.patient_id
               {$where}
              ORDER BY b.purchased_at DESC
              LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );

        return [
            'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage,
            'last_page' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    public function createBalance(int $packageId, int $ownerId, ?int $patientId, ?string $notes = null): string|false
    {
        $pkg = $this->findById($packageId);
        if (!$pkg) {
            return false;
        }
        $purchased = date('Y-m-d');
        $expires   = null;
        if (!empty($pkg['valid_days'])) {
            $expires = date('Y-m-d', strtotime("+{$pkg['valid_days']} days"));
        }

        return $this->db->insert(
            "INSERT INTO `{$this->t('dogschool_package_balances')}`
                (package_id, owner_id, patient_id, units_total, units_used, purchased_at, expires_at, status, notes)
             VALUES (?, ?, ?, ?, 0, ?, ?, 'active', ?)",
            [
                $packageId, $ownerId, $patientId, (int)$pkg['total_units'],
                $purchased, $expires, $notes,
            ]
        );
    }

    public function findBalance(int $balanceId): array|false
    {
        return $this->db->safeFetch(
            "SELECT * FROM `{$this->t('dogschool_package_balances')}` WHERE id = ? LIMIT 1",
            [$balanceId]
        );
    }

    /**
     * Löst 1 Einheit (oder n) von einem Balance ein.
     * Markiert Balance automatisch als used_up wenn erschöpft.
     */
    public function redeem(int $balanceId, int $units, ?int $enrollmentId, ?int $sessionId, ?int $userId, ?string $notes = null): bool
    {
        $b = $this->findBalance($balanceId);
        if (!$b || $b['status'] !== 'active') {
            return false;
        }
        $left = (int)$b['units_total'] - (int)$b['units_used'];
        if ($left < $units) {
            return false;
        }

        $this->db->safeExecute(
            "INSERT INTO `{$this->t('dogschool_package_redemptions')}`
                (balance_id, enrollment_id, session_id, units, redeemed_by_user_id, notes)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$balanceId, $enrollmentId, $sessionId, $units, $userId, $notes]
        );

        $newUsed   = (int)$b['units_used'] + $units;
        $newStatus = ($newUsed >= (int)$b['units_total']) ? 'used_up' : 'active';

        $this->db->safeExecute(
            "UPDATE `{$this->t('dogschool_package_balances')}`
                SET units_used = ?, status = ?
              WHERE id = ?",
            [$newUsed, $newStatus, $balanceId]
        );

        return true;
    }

    public function expireOutdated(): int
    {
        return (int)$this->db->safeExecute(
            "UPDATE `{$this->t('dogschool_package_balances')}`
                SET status = 'expired'
              WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < CURDATE()"
        );
    }

    public function redemptionsForBalance(int $balanceId): array
    {
        return $this->db->safeFetchAll(
            "SELECT r.*, s.session_date, s.start_time, c.name AS course_name
               FROM `{$this->t('dogschool_package_redemptions')}` r
               LEFT JOIN `{$this->t('dogschool_course_sessions')}` s ON s.id = r.session_id
               LEFT JOIN `{$this->t('dogschool_enrollments')}` e     ON e.id = r.enrollment_id
               LEFT JOIN `{$this->t('dogschool_courses')}` c         ON c.id = e.course_id
              WHERE r.balance_id = ?
              ORDER BY r.redeemed_at DESC",
            [$balanceId]
        );
    }
}
