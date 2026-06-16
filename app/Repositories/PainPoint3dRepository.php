<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

class PainPoint3dRepository extends Repository
{
    protected string $table = 'patient_3d_pain_points';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function findByPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->t()}` WHERE patient_id = ? ORDER BY created_at DESC",
            [$patientId]
        );
    }

    public function findByPatientAndType(int $patientId, string $animalType): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->t()}` WHERE patient_id = ? AND animal_type = ? ORDER BY created_at DESC",
            [$patientId, $animalType]
        );
    }

    public function upsert(int $patientId, string $animalType, string $muscleGroupId, string $side, array $data): int
    {
        $existing = $this->db->fetch(
            "SELECT id FROM `{$this->t()}` WHERE patient_id = ? AND animal_type = ? AND muscle_group_id = ? AND side = ?",
            [$patientId, $animalType, $muscleGroupId, $side]
        );

        if ($existing) {
            $this->db->execute(
                "UPDATE `{$this->t()}` SET
                    muscle_group_label = ?,
                    region             = ?,
                    pain_level         = ?,
                    pain_type          = ?,
                    notes              = ?,
                    updated_at         = NOW()
                 WHERE id = ?",
                [
                    $data['muscle_group_label'] ?? '',
                    $data['region']             ?? '',
                    (int)($data['pain_level']   ?? 0),
                    $data['pain_type']           ?? '',
                    $data['notes']               ?? null,
                    $existing['id'],
                ]
            );
            return (int)$existing['id'];
        }

        $this->db->execute(
            "INSERT INTO `{$this->t()}`
                (patient_id, animal_type, muscle_group_id, muscle_group_label, region, side, pain_level, pain_type, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $patientId,
                $animalType,
                $muscleGroupId,
                $data['muscle_group_label'] ?? '',
                $data['region']             ?? '',
                $side,
                (int)($data['pain_level']   ?? 0),
                $data['pain_type']           ?? '',
                $data['notes']               ?? null,
                $data['created_by']          ?? null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function deleteById(int $id, int $patientId): bool
    {
        $affected = $this->db->execute(
            "DELETE FROM `{$this->t()}` WHERE id = ? AND patient_id = ?",
            [$id, $patientId]
        );
        return $affected > 0;
    }

    public function deleteByMuscleGroup(int $patientId, string $animalType, string $muscleGroupId, string $side): void
    {
        $this->db->execute(
            "DELETE FROM `{$this->t()}` WHERE patient_id = ? AND animal_type = ? AND muscle_group_id = ? AND side = ?",
            [$patientId, $animalType, $muscleGroupId, $side]
        );
    }

    public function ensureSchema(): void
    {
        try {
            $t = $this->t();
            $this->db->execute("
                CREATE TABLE IF NOT EXISTS `{$t}` (
                    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `patient_id`        INT UNSIGNED NOT NULL,
                    `animal_type`       VARCHAR(20)  NOT NULL DEFAULT 'dog',
                    `muscle_group_id`   VARCHAR(100) NOT NULL,
                    `muscle_group_label` VARCHAR(255) NOT NULL DEFAULT '',
                    `region`            VARCHAR(100) NOT NULL DEFAULT '',
                    `side`              VARCHAR(20)  NOT NULL DEFAULT 'midline',
                    `pain_level`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    `pain_type`         VARCHAR(50)  NOT NULL DEFAULT '',
                    `notes`             TEXT NULL,
                    `created_by`        INT UNSIGNED NULL,
                    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    INDEX `idx_patient_id` (`patient_id`),
                    INDEX `idx_animal_type` (`animal_type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable) {
        }
    }
}
