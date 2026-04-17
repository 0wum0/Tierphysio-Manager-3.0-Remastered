<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

/**
 * Tenant-spezifische Textbausteine und Vorlagen für die Befundung.
 * Alle Lesemethoden sind self-healing (leere Liste bei Fehler/Tabelle fehlt).
 */
class BefundTemplateRepository extends Repository
{
    protected string $table = 'befund_textbausteine';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /* ── Textbausteine ───────────────────────────────────── */

    public function allTextbausteine(?string $scope = null): array
    {
        $tbl = $this->t('befund_textbausteine');
        if (!$this->db->tableExists($tbl)) {
            return [];
        }
        if ($scope !== null && $scope !== '') {
            return $this->db->safeFetchAll(
                "SELECT * FROM `{$tbl}` WHERE scope = ? ORDER BY title ASC",
                [$scope]
            );
        }
        return $this->db->safeFetchAll(
            "SELECT * FROM `{$tbl}` ORDER BY scope ASC, title ASC"
        );
    }

    public function createTextbaustein(string $scope, string $title, string $content, ?int $userId = null): int
    {
        $tbl = $this->t('befund_textbausteine');
        $this->db->execute(
            "INSERT INTO `{$tbl}` (scope, title, content, created_by) VALUES (?, ?, ?, ?)",
            [$scope, $title, $content, $userId]
        );
        return (int)$this->db->lastInsertId();
    }

    public function deleteTextbaustein(int $id): void
    {
        $tbl = $this->t('befund_textbausteine');
        $this->db->safeExecute("DELETE FROM `{$tbl}` WHERE id = ?", [$id]);
    }

    /* ── Vorlagen ────────────────────────────────────────── */

    public function allVorlagen(?string $species = null): array
    {
        $tbl = $this->t('befund_vorlagen');
        if (!$this->db->tableExists($tbl)) {
            return [];
        }
        $rows = ($species !== null && $species !== '')
            ? $this->db->safeFetchAll(
                "SELECT * FROM `{$tbl}` WHERE species = ? OR species IS NULL ORDER BY name ASC",
                [$species]
            )
            : $this->db->safeFetchAll("SELECT * FROM `{$tbl}` ORDER BY name ASC");

        foreach ($rows as &$row) {
            $row['felder'] = $this->safeJsonDecode($row['felder'] ?? null);
        }
        return $rows;
    }

    public function findVorlage(int $id): ?array
    {
        $tbl = $this->t('befund_vorlagen');
        if (!$this->db->tableExists($tbl)) {
            return null;
        }
        $row = $this->db->safeFetch("SELECT * FROM `{$tbl}` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) return null;
        $row['felder'] = $this->safeJsonDecode($row['felder'] ?? null);
        return $row;
    }

    public function createVorlage(string $name, ?string $species, array $felder, ?int $userId = null): int
    {
        $tbl = $this->t('befund_vorlagen');
        $this->db->execute(
            "INSERT INTO `{$tbl}` (name, species, felder, created_by) VALUES (?, ?, ?, ?)",
            [$name, $species ?: null, json_encode($felder, JSON_UNESCAPED_UNICODE), $userId]
        );
        return (int)$this->db->lastInsertId();
    }

    public function deleteVorlage(int $id): void
    {
        $tbl = $this->t('befund_vorlagen');
        $this->db->safeExecute("DELETE FROM `{$tbl}` WHERE id = ?", [$id]);
    }

    /* ── Helper ──────────────────────────────────────────── */

    /**
     * Self-Heal: defekte JSON-Strings werden zu leerem Array statt zu werfen.
     */
    private function safeJsonDecode(?string $raw): array
    {
        if ($raw === null || $raw === '') return [];
        try {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) return [];
            return $decoded;
        } catch (\Throwable $e) {
            error_log('[BefundTemplateRepository JSON] ' . $e->getMessage());
            return [];
        }
    }
}
