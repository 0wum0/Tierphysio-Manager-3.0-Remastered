<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Core\Database;
use PDO;

class OwnerPortalRepository
{
    public function __construct(private readonly Database $db) {}

    /* ─── Portal Users ─── */

    public function findUserByEmail(string $email): ?array
    {
        $stmt = $this->db->query(
            'SELECT u.*, o.first_name, o.last_name, o.phone, o.street, o.zip, o.city
             FROM owner_portal_users u
             JOIN owners o ON o.id = u.owner_id
             WHERE u.email = ? LIMIT 1',
            [$email]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findUserById(int $id): ?array
    {
        $stmt = $this->db->query(
            'SELECT u.*, o.first_name, o.last_name, o.phone, o.street, o.zip, o.city
             FROM owner_portal_users u
             JOIN owners o ON o.id = u.owner_id
             WHERE u.id = ? LIMIT 1',
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findUserByInviteToken(string $token): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM owner_portal_users WHERE invite_token = ? LIMIT 1',
            [$token]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findUserByOwnerId(int $ownerId): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM owner_portal_users WHERE owner_id = ? LIMIT 1',
            [$ownerId]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createUser(array $data): int
    {
        $this->db->execute(
            'INSERT INTO owner_portal_users (owner_id, email, password_hash, is_active, invite_token, invite_expires)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['owner_id'],
                $data['email'],
                $data['password_hash'] ?? null,
                $data['is_active'] ?? 1,
                $data['invite_token'] ?? null,
                $data['invite_expires'] ?? null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateUser(int $id, array $data): void
    {
        $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $id;
        $this->db->execute("UPDATE owner_portal_users SET {$sets} WHERE id = ?", $values);
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->execute(
            'UPDATE owner_portal_users SET last_login = NOW() WHERE id = ?',
            [$id]
        );
    }

    public function getAllPortalUsers(): array
    {
        $stmt = $this->db->query(
            'SELECT u.*, o.first_name, o.last_name
             FROM owner_portal_users u
             JOIN owners o ON o.id = u.owner_id
             ORDER BY o.last_name ASC, o.first_name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ─── Rate Limiting ─── */

    public function countRecentAttempts(string $email, string $ip, int $minutes = 15): int
    {
        $stmt = $this->db->query(
            'SELECT COUNT(*) FROM owner_portal_login_attempts
             WHERE (email = ? OR ip = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$email, $ip, $minutes]
        );
        return (int)$stmt->fetchColumn();
    }

    public function logLoginAttempt(string $email, string $ip): void
    {
        $this->db->execute(
            'INSERT INTO owner_portal_login_attempts (email, ip) VALUES (?, ?)',
            [$email, $ip]
        );
    }

    public function cleanOldAttempts(): void
    {
        $this->db->execute(
            'DELETE FROM owner_portal_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
    }

    /* ─── Pets (patients belonging to owner) ─── */

    public function getPetsByOwnerId(int $ownerId): array
    {
        $stmt = $this->db->query(
            'SELECT p.*,
                    (SELECT MAX(t.entry_date) FROM patient_timeline t WHERE t.patient_id = p.id) AS last_treatment
             FROM patients p
             WHERE p.owner_id = ?
             ORDER BY p.name ASC',
            [$ownerId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPetByIdAndOwner(int $patientId, int $ownerId): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM patients WHERE id = ? AND owner_id = ? LIMIT 1',
            [$patientId, $ownerId]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPetTimeline(int $patientId): array
    {
        $stmt = $this->db->query(
            'SELECT t.*, u.name AS user_name
             FROM patient_timeline t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.patient_id = ?
             ORDER BY t.entry_date DESC',
            [$patientId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ─── Invoices ─── */

    public function getInvoicesByOwnerId(int $ownerId): array
    {
        $stmt = $this->db->query(
            'SELECT i.*, p.name AS patient_name
             FROM invoices i
             LEFT JOIN patients p ON p.id = i.patient_id
             WHERE i.owner_id = ?
             ORDER BY i.issue_date DESC',
            [$ownerId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInvoiceByIdAndOwner(int $invoiceId, int $ownerId): ?array
    {
        $stmt = $this->db->query(
            'SELECT i.*, p.name AS patient_name
             FROM invoices i
             LEFT JOIN patients p ON p.id = i.patient_id
             WHERE i.id = ? AND i.owner_id = ? LIMIT 1',
            [$invoiceId, $ownerId]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* ─── Appointments ─── */

    public function getAppointmentsByOwnerId(int $ownerId): array
    {
        try {
            $stmt = $this->db->query(
                'SELECT a.*, p.name AS patient_name,
                        tt.name AS treatment_type_name, tt.color AS treatment_type_color
                 FROM appointments a
                 LEFT JOIN patients p ON p.id = a.patient_id
                 LEFT JOIN treatment_types tt ON tt.id = a.treatment_type_id
                 WHERE a.owner_id = ?
                 ORDER BY a.start_at DESC',
                [$ownerId]
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /* ─── Exercises ─── */

    public function getExercisesByPatient(int $patientId): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM pet_exercises WHERE patient_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC',
            [$patientId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExerciseById(int $id): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM pet_exercises WHERE id = ? LIMIT 1',
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createExercise(array $data): int
    {
        $this->db->execute(
            'INSERT INTO pet_exercises (patient_id, title, description, video_url, image, sort_order, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['patient_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['video_url'] ?? null,
                $data['image'] ?? null,
                $data['sort_order'] ?? 0,
                $data['created_by'] ?? null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateExercise(int $id, array $data): void
    {
        $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $id;
        $this->db->execute("UPDATE pet_exercises SET {$sets} WHERE id = ?", $values);
    }

    public function deleteExercise(int $id): void
    {
        $this->db->execute('DELETE FROM pet_exercises WHERE id = ?', [$id]);
    }

    public function getAllExercisesForPatients(array $patientIds): array
    {
        if (empty($patientIds)) return [];
        $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
        $stmt = $this->db->query(
            "SELECT e.*, p.name AS patient_name
             FROM pet_exercises e
             JOIN patients p ON p.id = e.patient_id
             WHERE e.patient_id IN ({$placeholders}) AND e.is_active = 1
             ORDER BY p.name ASC, e.sort_order ASC",
            $patientIds
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ─── Homework Plans ─── */

    public function getHomeworkPlansByPatient(int $patientId): array
    {
        $stmt = $this->db->query(
            'SELECT hp.*, u.name AS created_by_name
             FROM portal_homework_plans hp
             LEFT JOIN users u ON u.id = hp.created_by
             WHERE hp.patient_id = ?
             ORDER BY hp.plan_date DESC, hp.id DESC',
            [$patientId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHomeworkPlanById(int $id): ?array
    {
        $stmt = $this->db->query(
            'SELECT hp.*, u.name AS created_by_name
             FROM portal_homework_plans hp
             LEFT JOIN users u ON u.id = hp.created_by
             WHERE hp.id = ? LIMIT 1',
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getHomeworkPlansByOwner(int $ownerId): array
    {
        $stmt = $this->db->query(
            'SELECT hp.*, p.name AS patient_name
             FROM portal_homework_plans hp
             JOIN patients p ON p.id = hp.patient_id
             WHERE hp.owner_id = ? AND hp.status = \'active\'
             ORDER BY hp.plan_date DESC',
            [$ownerId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createHomeworkPlan(array $data): int
    {
        $this->db->execute(
            'INSERT INTO portal_homework_plans
             (patient_id, owner_id, plan_date, physio_principles, short_term_goals,
              long_term_goals, therapy_means, general_notes, next_appointment, therapist_name,
              status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['patient_id'],
                $data['owner_id'],
                $data['plan_date'],
                $data['physio_principles'] ?? null,
                $data['short_term_goals'] ?? null,
                $data['long_term_goals'] ?? null,
                $data['therapy_means'] ?? null,
                $data['general_notes'] ?? null,
                $data['next_appointment'] ?? null,
                $data['therapist_name'] ?? null,
                $data['status'] ?? 'active',
                $data['created_by'] ?? null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateHomeworkPlan(int $id, array $data): void
    {
        $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $id;
        $this->db->execute("UPDATE portal_homework_plans SET {$sets} WHERE id = ?", $values);
    }

    public function deleteHomeworkPlan(int $id): void
    {
        $this->db->execute('DELETE FROM portal_homework_plans WHERE id = ?', [$id]);
    }

    /* ─── Homework Plan Tasks ─── */

    public function getTasksByPlan(int $planId): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM portal_homework_plan_tasks WHERE plan_id = ? ORDER BY sort_order ASC, id ASC',
            [$planId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveTasksForPlan(int $planId, array $tasks): void
    {
        $this->db->execute('DELETE FROM portal_homework_plan_tasks WHERE plan_id = ?', [$planId]);
        foreach ($tasks as $i => $task) {
            if (empty(trim($task['title'] ?? ''))) continue;
            $this->db->execute(
                'INSERT INTO portal_homework_plan_tasks
                 (plan_id, template_id, title, description, frequency, duration, therapist_notes, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $planId,
                    $task['template_id'] ?? null,
                    trim($task['title']),
                    $task['description'] ?? null,
                    $task['frequency'] ?? null,
                    $task['duration'] ?? null,
                    $task['therapist_notes'] ?? null,
                    $i,
                ]
            );
        }
    }

    public function getAllHomeworkTemplates(): array
    {
        try {
            $stmt = $this->db->query(
                'SELECT * FROM homework_templates WHERE is_active = 1 ORDER BY category ASC, title ASC'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }
}
