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

    public function findAllTemplates(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM homework_templates WHERE is_active = 1 ORDER BY category, title"
        );
    }

    public function findTemplateById(int $id): ?array
    {
        $template = $this->db->fetch(
            "SELECT * FROM homework_templates WHERE id = ? AND is_active = 1",
            [$id]
        );
        return $template ?: null;
    }

    public function findPatientHomework(int $patientId): array
    {
        error_log('HomeworkRepository::findPatientHomework - Patient ID: ' . $patientId);
        
        try {
            $result = $this->db->fetchAll(
                "SELECT ph.*, u.first_name, u.last_name,
                        CASE 
                            WHEN ph.frequency = 'daily' THEN 'Täglich'
                            WHEN ph.frequency = 'twice_daily' THEN '2x täglich'
                            WHEN ph.frequency = 'three_times_daily' THEN '3x täglich'
                            WHEN ph.frequency = 'weekly' THEN 'Wöchentlich'
                            WHEN ph.frequency = 'as_needed' THEN 'Bei Bedarf'
                        END as frequency_display,
                        CONCAT(ph.duration_value, ' ', 
                            CASE ph.duration_unit
                                WHEN 'minutes' THEN 'Minuten'
                                WHEN 'hours' THEN 'Stunden'
                                WHEN 'days' THEN 'Tage'
                                WHEN 'weeks' THEN 'Wochen'
                            END
                        ) as duration_display
                FROM patient_homework ph
                LEFT JOIN users u ON ph.assigned_by = u.id
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
        
        $sql = "INSERT INTO patient_homework (
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
        $sql = "UPDATE patient_homework SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $this->db->query($sql, $params);
        return $this->db->rowCount() > 0;
    }

    public function deletePatientHomework(int $id, int $patientId = null): bool
    {
        if ($patientId) {
            $this->db->query("DELETE FROM patient_homework WHERE id = ? AND patient_id = ?", [$id, $patientId]);
        } else {
            $this->db->query("DELETE FROM patient_homework WHERE id = ?", [$id]);
        }
        return $this->db->rowCount() > 0;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $this->db->query(
            "UPDATE patient_homework SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$status, $id]
        );
        return $this->db->rowCount() > 0;
    }

    public function findHomeworkById(int $id): ?array
    {
        $homework = $this->db->fetch(
            "SELECT * FROM patient_homework WHERE id = ?",
            [$id]
        );
        return $homework ?: null;
    }
}
