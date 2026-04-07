<?php

namespace App\Repositories;

use App\Core\Database;

class HomeworkRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    public function findAllTemplates(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM `{$this->t('homework_templates')}` WHERE is_active = 1 ORDER BY category, title"
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function findTemplateById(int $id): ?array
    {
        try {
            $template = $this->db->fetch(
                "SELECT * FROM `{$this->t('homework_templates')}` WHERE id = ? AND is_active = 1",
                [$id]
            );
            return $template ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function findPatientHomework(int $patientId): array
    {
        error_log('HomeworkRepository::findPatientHomework - Patient ID: ' . $patientId);
        
        try {
            $result = $this->db->fetchAll(
                "SELECT ph.*, u.name AS assigned_by_name,
                        CASE 
                            WHEN ph.frequency = 'daily' THEN 'Täglich'
                            WHEN ph.frequency = 'twice_daily' THEN '2x täglich'
                            WHEN ph.frequency = 'three_times_daily' THEN '3x täglich'
                            WHEN ph.frequency = 'weekly' THEN 'Wöchentlich'
                            WHEN ph.frequency = 'as_needed' THEN 'Bei Bedarf'
                            ELSE ph.frequency
                        END as frequency_display,
                        CONCAT(ph.duration_value, ' ', 
                            CASE ph.duration_unit
                                WHEN 'minutes' THEN 'Minuten'
                                WHEN 'hours' THEN 'Stunden'
                                WHEN 'days' THEN 'Tage'
                                WHEN 'weeks' THEN 'Wochen'
                                ELSE ph.duration_unit
                            END
                        ) as duration_display
                FROM `{$this->t('patient_homework')}` ph
                LEFT JOIN `{$this->t('users')}` u ON ph.assigned_by = u.id
                WHERE ph.patient_id = ? AND ph.status != 'cancelled'
                ORDER BY ph.created_at DESC",
                [$patientId]
            );
            
            error_log('HomeworkRepository::findPatientHomework - Result count: ' . count($result));
            
            return $result;
        } catch (\Exception $e) {
            error_log('HomeworkRepository::findPatientHomework - Exception: ' . $e->getMessage());
            error_log('HomeworkRepository::findPatientHomework - Exception trace: ' . $e->getTraceAsString());
            return [];
        }
    }

    public function createPatientHomework(array $data): int
    {
        error_log('HomeworkRepository::createPatientHomework - Data: ' . print_r($data, true));
        
        $sql = "INSERT INTO `{$this->t('patient_homework')}` (
            patient_id, homework_template_id, title, description, 
            category, category_emoji, frequency, duration_value, duration_unit,
            start_date, end_date, therapist_notes, status, assigned_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $data['patient_id'],
            $data['homework_template_id'] ?? null,
            $data['title'],
            $data['description'],
            $data['category'],
            $data['category_emoji'],
            $data['frequency'],
            $data['duration_value'],
            $data['duration_unit'],
            $data['start_date'],
            $data['end_date'] ?? null,
            $data['therapist_notes'] ?? null,
            $data['status'] ?? 'pending',
            $data['assigned_by']
        ];

        error_log('HomeworkRepository::createPatientHomework - SQL: ' . $sql);
        error_log('HomeworkRepository::createPatientHomework - Params: ' . print_r($params, true));

        $this->db->query($sql, $params);
        $id = $this->db->lastInsertId();
        
        error_log('HomeworkRepository::createPatientHomework - Inserted ID: ' . $id);
        
        return $id;
    }

    public function updatePatientHomework(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        foreach ($data as $field => $value) {
            if ($value !== null) {
                $fields[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE `{$this->t('patient_homework')}` SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $this->db->query($sql, $params);
        return $this->db->rowCount() > 0;
    }

    public function deletePatientHomework(int $id, int $patientId = null): bool
    {
        if ($patientId) {
            $this->db->query("DELETE FROM `{$this->t('patient_homework')}` WHERE id = ? AND patient_id = ?", [$id, $patientId]);
        } else {
            $this->db->query("DELETE FROM `{$this->t('patient_homework')}` WHERE id = ?", [$id]);
        }
        return $this->db->rowCount() > 0;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $this->db->query(
            "UPDATE `{$this->t('patient_homework')}` SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$status, $id]
        );
        return $this->db->rowCount() > 0;
    }

    public function findHomeworkById(int $id): ?array
    {
        $homework = $this->db->fetch(
            "SELECT * FROM `{$this->t('patient_homework')}` WHERE id = ?",
            [$id]
        );
        return $homework ?: null;
    }

    // ── Plan-Meta (PDF-relevante Felder pro Patient) ──────────────────────

    public function getPatientPlanMeta(int $patientId): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM `{$this->t('homework_plan_meta')}` WHERE patient_id = ?",
            [$patientId]
        );
        return $row ?: null;
    }

    public function savePatientPlanMeta(int $patientId, array $data): void
    {
        $existing = $this->getPatientPlanMeta($patientId);

        if ($existing) {
            $this->db->query(
                "UPDATE `{$this->t('homework_plan_meta')}` SET
                    physiotherapeutische_grundsaetze = ?,
                    kurzfristige_ziele = ?,
                    langfristige_ziele = ?,
                    therapiemittel = ?,
                    beachte_hinweise = ?,
                    wiedervorstellung_date = ?,
                    therapist_name = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE patient_id = ?",
                [
                    $data['physiotherapeutische_grundsaetze'] ?? null,
                    $data['kurzfristige_ziele'] ?? null,
                    $data['langfristige_ziele'] ?? null,
                    $data['therapiemittel'] ?? null,
                    $data['beachte_hinweise'] ?? null,
                    !empty($data['wiedervorstellung_date']) ? $data['wiedervorstellung_date'] : null,
                    $data['therapist_name'] ?? null,
                    $patientId,
                ]
            );
        } else {
            $this->db->query(
                "INSERT INTO `{$this->t('homework_plan_meta')}`
                    (patient_id, physiotherapeutische_grundsaetze, kurzfristige_ziele,
                     langfristige_ziele, therapiemittel, beachte_hinweise,
                     wiedervorstellung_date, therapist_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $patientId,
                    $data['physiotherapeutische_grundsaetze'] ?? null,
                    $data['kurzfristige_ziele'] ?? null,
                    $data['langfristige_ziele'] ?? null,
                    $data['therapiemittel'] ?? null,
                    $data['beachte_hinweise'] ?? null,
                    !empty($data['wiedervorstellung_date']) ? $data['wiedervorstellung_date'] : null,
                    $data['therapist_name'] ?? null,
                ]
            );
        }
    }

    // ── Template-Verwaltung (Admin) ───────────────────────────────────────

    public function findAllTemplatesAdmin(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->t('homework_templates')}` ORDER BY category, title"
        );
    }

    public function createTemplate(array $data): int
    {
        $this->db->query(
            "INSERT INTO `{$this->t('homework_templates')}`
                (title, description, category, category_emoji, frequency,
                 duration_value, duration_unit, therapist_notes, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [
                $data['title'],
                $data['description'],
                $data['category'],
                $data['category_emoji'],
                $data['frequency'],
                (int)$data['duration_value'],
                $data['duration_unit'],
                $data['therapist_notes'] ?? null,
            ]
        );
        return $this->db->lastInsertId();
    }

    public function updateTemplate(int $id, array $data): bool
    {
        $affected = $this->db->execute(
            "UPDATE `{$this->t('homework_templates')}` SET
                title = ?, description = ?, category = ?, category_emoji = ?,
                frequency = ?, duration_value = ?, duration_unit = ?,
                therapist_notes = ?, is_active = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [
                $data['title'],
                $data['description'],
                $data['category'],
                $data['category_emoji'],
                $data['frequency'],
                (int)$data['duration_value'],
                $data['duration_unit'],
                $data['therapist_notes'] ?? null,
                isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
                $id,
            ]
        );
        return $affected > 0;
    }

    public function deleteTemplate(int $id): bool
    {
        return $this->db->execute("DELETE FROM `{$this->t('homework_templates')}` WHERE id = ?", [$id]) > 0;
    }
}
