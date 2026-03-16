<?php

declare(strict_types=1);

namespace Plugins\TherapyCarePro;

use App\Core\Database;
use PDO;

class TherapyCareRepository
{
    public function __construct(private readonly Database $db) {}

    /* ══════════════════════════════════════════════════════════
       MODULE 1 — PROGRESS TRACKING
    ══════════════════════════════════════════════════════════ */

    public function getAllProgressCategories(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM tcp_progress_categories ORDER BY sort_order ASC, name ASC'
        );
    }

    public function getActiveProgressCategories(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM tcp_progress_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
        );
    }

    public function findProgressCategoryById(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM tcp_progress_categories WHERE id = ? LIMIT 1', [$id]);
        return $row ?: null;
    }

    public function createProgressCategory(array $data): int
    {
        $this->db->execute(
            'INSERT INTO tcp_progress_categories (name, description, scale_min, scale_max, scale_label_min, scale_label_max, color, icon, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'],
                $data['description'] ?? null,
                (int)($data['scale_min'] ?? 1),
                (int)($data['scale_max'] ?? 10),
                $data['scale_label_min'] ?? null,
                $data['scale_label_max'] ?? null,
                $data['color'] ?? '#4f7cff',
                $data['icon'] ?? null,
                (int)($data['sort_order'] ?? 0),
                (int)($data['is_active'] ?? 1),
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateProgressCategory(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE tcp_progress_categories SET name=?, description=?, scale_min=?, scale_max=?,
             scale_label_min=?, scale_label_max=?, color=?, icon=?, sort_order=?, is_active=? WHERE id=?',
            [
                $data['name'],
                $data['description'] ?? null,
                (int)($data['scale_min'] ?? 1),
                (int)($data['scale_max'] ?? 10),
                $data['scale_label_min'] ?? null,
                $data['scale_label_max'] ?? null,
                $data['color'] ?? '#4f7cff',
                $data['icon'] ?? null,
                (int)($data['sort_order'] ?? 0),
                (int)($data['is_active'] ?? 1),
                $id,
            ]
        );
    }

    public function deleteProgressCategory(int $id): void
    {
        $this->db->execute('DELETE FROM tcp_progress_categories WHERE id = ?', [$id]);
    }

    public function getProgressEntriesForPatient(int $patientId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql    = 'SELECT e.*, c.name AS category_name, c.color AS category_color,
                          c.scale_min, c.scale_max, u.name AS recorded_by_name
                   FROM tcp_progress_entries e
                   JOIN tcp_progress_categories c ON c.id = e.category_id
                   LEFT JOIN users u ON u.id = e.recorded_by
                   WHERE e.patient_id = ?';
        $params = [$patientId];
        if ($dateFrom) { $sql .= ' AND e.entry_date >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $sql .= ' AND e.entry_date <= ?'; $params[] = $dateTo;   }
        $sql .= ' ORDER BY e.entry_date ASC, e.category_id ASC';
        return $this->db->fetchAll($sql, $params);
    }

    public function getProgressEntriesByCategory(int $patientId, int $categoryId): array
    {
        return $this->db->fetchAll(
            'SELECT e.*, u.name AS recorded_by_name
             FROM tcp_progress_entries e
             LEFT JOIN users u ON u.id = e.recorded_by
             WHERE e.patient_id = ? AND e.category_id = ?
             ORDER BY e.entry_date ASC',
            [$patientId, $categoryId]
        );
    }

    public function getLatestProgressForPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            'SELECT e.*, c.name AS category_name, c.color AS category_color, c.scale_min, c.scale_max
             FROM tcp_progress_entries e
             JOIN tcp_progress_categories c ON c.id = e.category_id
             WHERE e.id IN (
                 SELECT MAX(id) FROM tcp_progress_entries WHERE patient_id = ? GROUP BY category_id
             )
             ORDER BY c.sort_order ASC',
            [$patientId]
        );
    }

    public function createProgressEntry(array $data): int
    {
        $this->db->execute(
            'INSERT INTO tcp_progress_entries (patient_id, category_id, appointment_id, score, notes, recorded_by, entry_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                (int)$data['patient_id'],
                (int)$data['category_id'],
                isset($data['appointment_id']) ? (int)$data['appointment_id'] : null,
                (int)$data['score'],
                $data['notes'] ?? null,
                isset($data['recorded_by']) ? (int)$data['recorded_by'] : null,
                $data['entry_date'],
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function deleteProgressEntry(int $id): void
    {
        $this->db->execute('DELETE FROM tcp_progress_entries WHERE id = ?', [$id]);
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 2 — EXERCISE FEEDBACK
    ══════════════════════════════════════════════════════════ */

    public function getFeedbackForHomework(int $homeworkId): array
    {
        return $this->db->fetchAll(
            'SELECT f.*, o.first_name, o.last_name
             FROM tcp_exercise_feedback f
             JOIN owners o ON o.id = f.owner_id
             WHERE f.homework_id = ?
             ORDER BY f.feedback_date DESC',
            [$homeworkId]
        );
    }

    public function getFeedbackForPatient(int $patientId, ?int $days = null): array
    {
        $sql    = 'SELECT f.*, ph.title AS homework_title, o.first_name, o.last_name
                   FROM tcp_exercise_feedback f
                   JOIN patient_homework ph ON ph.id = f.homework_id
                   JOIN owners o ON o.id = f.owner_id
                   WHERE f.patient_id = ?';
        $params = [$patientId];
        if ($days) {
            $sql .= ' AND f.feedback_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)';
            $params[] = $days;
        }
        $sql .= ' ORDER BY f.feedback_date DESC, f.created_at DESC';
        return $this->db->fetchAll($sql, $params);
    }

    public function getFeedbackSummaryForPatient(int $patientId): array
    {
        $row = $this->db->fetch(
            'SELECT
                COUNT(*) AS total,
                SUM(status = "done") AS done_count,
                SUM(status = "not_done") AS not_done_count,
                SUM(status = "pain") AS pain_count,
                SUM(status = "difficult") AS difficult_count
             FROM tcp_exercise_feedback WHERE patient_id = ?',
            [$patientId]
        );
        return $row ?: [];
    }

    public function getProblematicFeedback(int $days = 7): array
    {
        return $this->db->fetchAll(
            'SELECT f.*, ph.title AS homework_title, p.name AS patient_name,
                    o.first_name, o.last_name
             FROM tcp_exercise_feedback f
             JOIN patient_homework ph ON ph.id = f.homework_id
             JOIN patients p ON p.id = f.patient_id
             JOIN owners o ON o.id = f.owner_id
             WHERE f.status IN ("pain","difficult")
               AND f.feedback_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             ORDER BY f.feedback_date DESC',
            [$days]
        );
    }

    public function createFeedback(array $data): int
    {
        $this->db->execute(
            'INSERT INTO tcp_exercise_feedback (homework_id, patient_id, owner_id, status, comment, feedback_date)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                (int)$data['homework_id'],
                (int)$data['patient_id'],
                (int)$data['owner_id'],
                $data['status'],
                $data['comment'] ?? null,
                $data['feedback_date'],
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 3 — REMINDERS
    ══════════════════════════════════════════════════════════ */

    public function getAllReminderTemplates(): array
    {
        return $this->db->fetchAll('SELECT * FROM tcp_reminder_templates ORDER BY type ASC, name ASC');
    }

    public function getActiveReminderTemplates(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM tcp_reminder_templates WHERE is_active = 1 ORDER BY type ASC'
        );
    }

    public function findReminderTemplateById(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM tcp_reminder_templates WHERE id = ? LIMIT 1', [$id]);
        return $row ?: null;
    }

    public function createReminderTemplate(array $data): int
    {
        $this->db->execute(
            'INSERT INTO tcp_reminder_templates (type, name, subject, body, trigger_hours, is_active)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['type'],
                $data['name'],
                $data['subject'],
                $data['body'],
                (int)$data['trigger_hours'],
                (int)($data['is_active'] ?? 1),
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateReminderTemplate(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE tcp_reminder_templates SET type=?, name=?, subject=?, body=?, trigger_hours=?, is_active=? WHERE id=?',
            [
                $data['type'],
                $data['name'],
                $data['subject'],
                $data['body'],
                (int)$data['trigger_hours'],
                (int)($data['is_active'] ?? 1),
                $id,
            ]
        );
    }

    public function deleteReminderTemplate(int $id): void
    {
        $this->db->execute('DELETE FROM tcp_reminder_templates WHERE id = ?', [$id]);
    }

    public function getPendingReminderQueue(): array
    {
        return $this->db->fetchAll(
            'SELECT q.*, o.email AS owner_email, o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                    p.name AS patient_name
             FROM tcp_reminder_queue q
             JOIN owners o ON o.id = q.owner_id
             LEFT JOIN patients p ON p.id = q.patient_id
             WHERE q.status = "pending" AND q.send_at <= NOW()
             ORDER BY q.send_at ASC
             LIMIT 50'
        );
    }

    public function createReminderQueueEntry(array $data): int
    {
        $this->db->execute(
            'INSERT INTO tcp_reminder_queue (template_id, type, patient_id, owner_id, appointment_id, subject, body, send_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending")',
            [
                isset($data['template_id']) ? (int)$data['template_id'] : null,
                $data['type'],
                isset($data['patient_id']) ? (int)$data['patient_id'] : null,
                (int)$data['owner_id'],
                isset($data['appointment_id']) ? (int)$data['appointment_id'] : null,
                $data['subject'],
                $data['body'],
                $data['send_at'],
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function markReminderSent(int $queueId): void
    {
        $this->db->execute(
            'UPDATE tcp_reminder_queue SET status="sent", sent_at=NOW() WHERE id=?',
            [$queueId]
        );
    }

    public function markReminderFailed(int $queueId, string $error): void
    {
        $this->db->execute(
            'UPDATE tcp_reminder_queue SET status="failed", error_message=? WHERE id=?',
            [$error, $queueId]
        );
    }

    public function getReminderLogs(int $limit = 100): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM tcp_reminder_logs ORDER BY sent_at DESC LIMIT ' . (int)$limit
        );
    }

    public function logReminder(array $data): void
    {
        $this->db->execute(
            'INSERT INTO tcp_reminder_logs (queue_id, type, recipient, subject, status, error, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                isset($data['queue_id']) ? (int)$data['queue_id'] : null,
                $data['type'],
                $data['recipient'],
                $data['subject'],
                $data['status'],
                $data['error'] ?? null,
            ]
        );
    }

    public function reminderAlreadyQueued(int $ownerId, ?int $appointmentId, string $type): bool
    {
        $count = (int)$this->db->fetchColumn(
            'SELECT COUNT(*) FROM tcp_reminder_queue
             WHERE owner_id=? AND appointment_id=? AND type=? AND status IN ("pending","sent")',
            [$ownerId, $appointmentId, $type]
        );
        return $count > 0;
    }

    public function getQueuedRemindersForPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM tcp_reminder_queue WHERE patient_id = ? ORDER BY send_at DESC LIMIT 20',
            [$patientId]
        );
    }

    public function cancelRemindersByAppointment(int $appointmentId): void
    {
        $this->db->execute(
            'UPDATE tcp_reminder_queue SET status="cancelled" WHERE appointment_id=? AND status="pending"',
            [$appointmentId]
        );
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 4 — THERAPY REPORTS
    ══════════════════════════════════════════════════════════ */

    public function getTherapyReportsForPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            'SELECT r.*, u.name AS created_by_name
             FROM tcp_therapy_reports r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.patient_id = ?
             ORDER BY r.created_at DESC',
            [$patientId]
        );
    }

    public function findTherapyReportById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT r.*, u.name AS created_by_name
             FROM tcp_therapy_reports r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.id = ? LIMIT 1',
            [$id]
        );
        return $row ?: null;
    }

    public function createTherapyReport(array $data): int
    {
        $this->db->execute(
            'INSERT INTO tcp_therapy_reports
             (patient_id, created_by, title, diagnosis, therapies_used, recommendations,
              followup_recommendation, include_progress, include_homework, include_natural, include_timeline, filename)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int)$data['patient_id'],
                isset($data['created_by']) ? (int)$data['created_by'] : null,
                $data['title'],
                $data['diagnosis'] ?? null,
                $data['therapies_used'] ?? null,
                $data['recommendations'] ?? null,
                $data['followup_recommendation'] ?? null,
                (int)($data['include_progress'] ?? 1),
                (int)($data['include_homework'] ?? 1),
                (int)($data['include_natural'] ?? 1),
                (int)($data['include_timeline'] ?? 1),
                $data['filename'] ?? null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateTherapyReportFilename(int $id, string $filename): void
    {
        $this->db->execute('UPDATE tcp_therapy_reports SET filename=? WHERE id=?', [$filename, $id]);
    }

    public function markTherapyReportSent(int $id, string $sentTo): void
    {
        $this->db->execute(
            'UPDATE tcp_therapy_reports SET sent_at=NOW(), sent_to=? WHERE id=?',
            [$sentTo, $id]
        );
    }

    public function deleteTherapyReport(int $id): void
    {
        $this->db->execute('DELETE FROM tcp_therapy_reports WHERE id = ?', [$id]);
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 5 — EXERCISE LIBRARY
    ══════════════════════════════════════════════════════════ */

    public function getExerciseLibrary(?string $category = null, ?string $search = null): array
    {
        $sql    = 'SELECT e.*, u.name AS created_by_name FROM tcp_exercise_library e LEFT JOIN users u ON u.id = e.created_by WHERE e.is_active = 1';
        $params = [];
        if ($category) { $sql .= ' AND e.category = ?';                                   $params[] = $category; }
        if ($search)   { $sql .= ' AND (e.title LIKE ? OR e.description LIKE ?)';          $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
        $sql .= ' ORDER BY e.category ASC, e.title ASC';
        return $this->db->fetchAll($sql, $params);
    }

    public function findExerciseById(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM tcp_exercise_library WHERE id = ? LIMIT 1', [$id]);
        return $row ?: null;
    }

    public function createExercise(array $data): int
    {
        $this->db->execute(
            'INSERT INTO tcp_exercise_library
             (title, category, description, instructions, contraindications, frequency, duration,
              species_tags, therapy_tags, has_image, image_file, has_video, video_file, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['title'],
                $data['category'] ?? 'sonstiges',
                $data['description'] ?? '',
                $data['instructions'] ?? null,
                $data['contraindications'] ?? null,
                $data['frequency'] ?? null,
                $data['duration'] ?? null,
                $data['species_tags'] ?? null,
                $data['therapy_tags'] ?? null,
                (int)($data['has_image'] ?? 0),
                $data['image_file'] ?? null,
                (int)($data['has_video'] ?? 0),
                $data['video_file'] ?? null,
                (int)($data['is_active'] ?? 1),
                isset($data['created_by']) ? (int)$data['created_by'] : null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateExercise(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE tcp_exercise_library SET title=?, category=?, description=?, instructions=?,
             contraindications=?, frequency=?, duration=?, species_tags=?, therapy_tags=?,
             has_image=?, image_file=?, has_video=?, video_file=?, is_active=? WHERE id=?',
            [
                $data['title'],
                $data['category'] ?? 'sonstiges',
                $data['description'] ?? '',
                $data['instructions'] ?? null,
                $data['contraindications'] ?? null,
                $data['frequency'] ?? null,
                $data['duration'] ?? null,
                $data['species_tags'] ?? null,
                $data['therapy_tags'] ?? null,
                (int)($data['has_image'] ?? 0),
                $data['image_file'] ?? null,
                (int)($data['has_video'] ?? 0),
                $data['video_file'] ?? null,
                (int)($data['is_active'] ?? 1),
                $id,
            ]
        );
    }

    public function deleteExercise(int $id): void
    {
        $this->db->execute('DELETE FROM tcp_exercise_library WHERE id = ?', [$id]);
    }

    public function getExerciseCategories(): array
    {
        return $this->db->fetchAll(
            'SELECT DISTINCT category FROM tcp_exercise_library WHERE is_active=1 ORDER BY category ASC'
        );
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 6 — NATURAL THERAPY
    ══════════════════════════════════════════════════════════ */

    public function getAllNaturalTherapyTypes(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM tcp_natural_therapy_types WHERE is_active=1 ORDER BY sort_order ASC, name ASC'
        );
    }

    public function getNaturalTherapyTypesByCategory(): array
    {
        $rows = $this->getAllNaturalTherapyTypes();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['category']][] = $row;
        }
        return $grouped;
    }

    public function createNaturalTherapyType(array $data): int
    {
        $this->db->execute(
            'INSERT INTO tcp_natural_therapy_types (name, category, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?)',
            [$data['name'], $data['category'] ?? 'Sonstiges', $data['description'] ?? null, (int)($data['sort_order'] ?? 0), (int)($data['is_active'] ?? 1)]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateNaturalTherapyType(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE tcp_natural_therapy_types SET name=?, category=?, description=?, sort_order=?, is_active=? WHERE id=?',
            [$data['name'], $data['category'] ?? 'Sonstiges', $data['description'] ?? null, (int)($data['sort_order'] ?? 0), (int)($data['is_active'] ?? 1), $id]
        );
    }

    public function deleteNaturalTherapyType(int $id): void
    {
        $this->db->execute('DELETE FROM tcp_natural_therapy_types WHERE id=?', [$id]);
    }

    public function getNaturalEntriesForPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            'SELECT e.*, t.name AS type_name, t.category AS type_category, u.name AS recorded_by_name
             FROM tcp_natural_therapy_entries e
             LEFT JOIN tcp_natural_therapy_types t ON t.id = e.type_id
             LEFT JOIN users u ON u.id = e.recorded_by
             WHERE e.patient_id = ?
             ORDER BY e.entry_date DESC, e.created_at DESC',
            [$patientId]
        );
    }

    public function getPublicNaturalEntriesForPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            'SELECT e.*, t.name AS type_name
             FROM tcp_natural_therapy_entries e
             LEFT JOIN tcp_natural_therapy_types t ON t.id = e.type_id
             WHERE e.patient_id = ? AND e.show_in_portal = 1
             ORDER BY e.entry_date DESC',
            [$patientId]
        );
    }

    public function createNaturalEntry(array $data): int
    {
        $this->db->execute(
            'INSERT INTO tcp_natural_therapy_entries
             (patient_id, type_id, therapy_type, agent, dosage, frequency, duration, notes, show_in_portal, recorded_by, entry_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int)$data['patient_id'],
                isset($data['type_id']) && $data['type_id'] ? (int)$data['type_id'] : null,
                $data['therapy_type'],
                $data['agent'] ?? null,
                $data['dosage'] ?? null,
                $data['frequency'] ?? null,
                $data['duration'] ?? null,
                $data['notes'] ?? null,
                (int)($data['show_in_portal'] ?? 0),
                isset($data['recorded_by']) ? (int)$data['recorded_by'] : null,
                $data['entry_date'],
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateNaturalEntry(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE tcp_natural_therapy_entries SET therapy_type=?, agent=?, dosage=?, frequency=?,
             duration=?, notes=?, show_in_portal=?, entry_date=? WHERE id=?',
            [
                $data['therapy_type'],
                $data['agent'] ?? null,
                $data['dosage'] ?? null,
                $data['frequency'] ?? null,
                $data['duration'] ?? null,
                $data['notes'] ?? null,
                (int)($data['show_in_portal'] ?? 0),
                $data['entry_date'],
                $id,
            ]
        );
    }

    public function deleteNaturalEntry(int $id): void
    {
        $this->db->execute('DELETE FROM tcp_natural_therapy_entries WHERE id=?', [$id]);
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 7 — TIMELINE META
    ══════════════════════════════════════════════════════════ */

    public function getTimelineMetaForPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            'SELECT m.* FROM tcp_timeline_meta m
             JOIN patient_timeline t ON t.id = m.timeline_id
             WHERE t.patient_id = ?',
            [$patientId]
        );
    }

    public function setTimelineMeta(int $timelineId, string $eventType, ?int $refId = null, ?string $refTable = null): void
    {
        $this->db->execute(
            'INSERT INTO tcp_timeline_meta (timeline_id, event_type, ref_id, ref_table)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE event_type=VALUES(event_type), ref_id=VALUES(ref_id), ref_table=VALUES(ref_table)',
            [$timelineId, $eventType, $refId, $refTable]
        );
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 8 — PORTAL VISIBILITY
    ══════════════════════════════════════════════════════════ */

    public function getPortalVisibility(int $patientId): array
    {
        $row = $this->db->fetch('SELECT * FROM tcp_portal_visibility WHERE patient_id = ?', [$patientId]);
        return $row ?: [
            'patient_id'    => $patientId,
            'show_progress' => 0,
            'show_natural'  => 0,
            'show_reports'  => 0,
        ];
    }

    public function savePortalVisibility(int $patientId, array $data): void
    {
        $this->db->execute(
            'INSERT INTO tcp_portal_visibility (patient_id, show_progress, show_natural, show_reports)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE show_progress=VALUES(show_progress),
                                     show_natural=VALUES(show_natural),
                                     show_reports=VALUES(show_reports)',
            [
                $patientId,
                (int)($data['show_progress'] ?? 0),
                (int)($data['show_natural'] ?? 0),
                (int)($data['show_reports'] ?? 0),
            ]
        );
    }

    /* ══════════════════════════════════════════════════════════
       HELPERS — cross-module queries
    ══════════════════════════════════════════════════════════ */

    public function getPatientWithOwner(int $patientId): ?array
    {
        $row = $this->db->fetch(
            'SELECT p.*, o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                    o.email AS owner_email, o.phone AS owner_phone
             FROM patients p
             JOIN owners o ON o.id = p.owner_id
             WHERE p.id = ? LIMIT 1',
            [$patientId]
        );
        return $row ?: null;
    }

    public function getRecentTimeline(int $patientId, int $limit = 5): array
    {
        return $this->db->fetchAll(
            'SELECT t.*, m.event_type AS tcp_event_type, m.icon AS tcp_icon, m.badge_color AS tcp_badge_color
             FROM patient_timeline t
             LEFT JOIN tcp_timeline_meta m ON m.timeline_id = t.id
             WHERE t.patient_id = ?
             ORDER BY t.entry_date DESC, t.created_at DESC
             LIMIT ' . (int)$limit,
            [$patientId]
        );
    }

    public function getEnrichedTimeline(int $patientId): array
    {
        return $this->db->fetchAll(
            'SELECT t.*, u.name AS user_name,
                    m.event_type AS tcp_event_type, m.icon AS tcp_icon, m.badge_color AS tcp_badge_color, m.ref_id AS tcp_ref_id
             FROM patient_timeline t
             LEFT JOIN users u ON u.id = t.user_id
             LEFT JOIN tcp_timeline_meta m ON m.timeline_id = t.id
             WHERE t.patient_id = ?
             ORDER BY t.entry_date DESC, t.created_at DESC',
            [$patientId]
        );
    }

    public function getUpcomingAppointments(int $patientId, int $limit = 5): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT a.id, a.title, a.start_at, a.end_at, a.status, o.first_name, o.last_name, o.email
                 FROM appointments a
                 JOIN patients p ON p.id = a.patient_id
                 JOIN owners o ON o.id = p.owner_id
                 WHERE a.patient_id = ? AND a.start_at >= NOW()
                   AND a.status NOT IN ("cancelled","noshow")
                 ORDER BY a.start_at ASC LIMIT ' . (int)$limit,
                [$patientId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getHomeworkForPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM patient_homework WHERE patient_id = ? ORDER BY created_at DESC',
            [$patientId]
        );
    }

    public function getProgressDashboardStats(): array
    {
        $totalEntries = (int)$this->db->fetchColumn('SELECT COUNT(*) FROM tcp_progress_entries');
        $totalFeedback = (int)$this->db->fetchColumn('SELECT COUNT(*) FROM tcp_exercise_feedback');
        $painFeedback  = (int)$this->db->fetchColumn('SELECT COUNT(*) FROM tcp_exercise_feedback WHERE status="pain" AND feedback_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)');
        $pendingReminders = (int)$this->db->fetchColumn('SELECT COUNT(*) FROM tcp_reminder_queue WHERE status="pending"');
        $reports       = (int)$this->db->fetchColumn('SELECT COUNT(*) FROM tcp_therapy_reports');
        return compact('totalEntries', 'totalFeedback', 'painFeedback', 'pendingReminders', 'reports');
    }
}
