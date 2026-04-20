<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

/**
 * Training-Plans-Repository (TCP — Training Control Panel).
 *
 * Abdeckung:
 *   - Plan-Vorlagen (is_template=1) und individuelle Pläne
 *   - Übungs-Katalog (is_system=1 = Standard)
 *   - Plan-Zuweisungen an Hunde (assignments)
 *   - Fortschritts-Tracking (progress) mit 6-stufigem Mastery-Level
 *   - Hausaufgaben (homework) für den Halter
 */
class TrainingPlanRepository extends Repository
{
    protected string $table = 'dogschool_training_plans';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /* ═════════════════ Plan-Vorlagen ═════════════════ */

    public function listTemplates(string $audience = ''): array
    {
        $where  = 'WHERE is_template = 1 AND is_active = 1';
        $params = [];
        if ($audience !== '') {
            $where  .= ' AND target_audience = ?';
            $params[] = $audience;
        }
        return $this->db->safeFetchAll(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM `{$this->t('dogschool_plan_exercises')}` pe WHERE pe.plan_id = p.id) AS exercise_count
               FROM `{$this->t()}` p
               {$where}
              ORDER BY p.is_system DESC, p.name ASC",
            $params
        );
    }

    public function findPlanWithExercises(int $planId): array
    {
        $plan = $this->db->safeFetch(
            "SELECT * FROM `{$this->t()}` WHERE id = ? LIMIT 1",
            [$planId]
        );
        if (!$plan) {
            return [];
        }

        $exercises = $this->db->safeFetchAll(
            "SELECT pe.*,
                    e.name AS exercise_name, e.category, e.difficulty, e.description,
                    e.instructions, e.duration_minutes, e.video_url
               FROM `{$this->t('dogschool_plan_exercises')}` pe
               LEFT JOIN `{$this->t('dogschool_exercises')}` e ON e.id = pe.exercise_id
              WHERE pe.plan_id = ?
              ORDER BY pe.week_number ASC, pe.session_number ASC, pe.sort_order ASC",
            [$planId]
        );

        $plan['exercises'] = $exercises;
        /* Gruppiert nach Woche/Session für einfacheres Rendering */
        $grouped = [];
        foreach ($exercises as $ex) {
            $week    = (int)$ex['week_number'];
            $session = (int)$ex['session_number'];
            $grouped[$week][$session][] = $ex;
        }
        $plan['curriculum'] = $grouped;

        return $plan;
    }

    public function createPlan(array $data): string
    {
        return $this->db->insert(
            "INSERT INTO `{$this->t()}`
                (name, description, target_audience, duration_weeks, sessions_per_week,
                 difficulty, is_template, is_system, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)",
            [
                (string)($data['name'] ?? 'Unbenannter Plan'),
                (string)($data['description'] ?? ''),
                (string)($data['target_audience'] ?? ''),
                (int)($data['duration_weeks'] ?? 8),
                (int)($data['sessions_per_week'] ?? 1),
                (string)($data['difficulty'] ?? 'medium'),
                (int)(bool)($data['is_template'] ?? 1),
                (int)(bool)($data['is_active'] ?? 1),
            ]
        );
    }

    public function updatePlan(int $planId, array $data): int
    {
        $allowed = ['name','description','target_audience','duration_weeks',
                    'sessions_per_week','difficulty','is_template','is_active'];
        $sets    = [];
        $params  = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $sets[]   = "`{$k}` = ?";
                $params[] = $v;
            }
        }
        if (!$sets) return 0;
        $params[] = $planId;
        return $this->db->execute(
            "UPDATE `{$this->t()}` SET " . implode(', ', $sets)
            . " WHERE id = ? AND is_system = 0",
            $params
        );
    }

    public function deletePlan(int $planId): int
    {
        /* System-Pläne schützen */
        $row = $this->db->safeFetch(
            "SELECT is_system FROM `{$this->t()}` WHERE id = ?",
            [$planId]
        );
        if (!$row || (int)$row['is_system'] === 1) {
            return 0;
        }
        $this->db->safeExecute(
            "DELETE FROM `{$this->t('dogschool_plan_exercises')}` WHERE plan_id = ?",
            [$planId]
        );
        return $this->db->execute(
            "DELETE FROM `{$this->t()}` WHERE id = ? AND is_system = 0",
            [$planId]
        );
    }

    public function addPlanExercise(int $planId, int $exerciseId, int $week, int $session,
                                     ?int $targetReps = null, ?int $targetDur = null,
                                     ?string $notes = null): string
    {
        $sort = (int)$this->db->safeFetchColumn(
            "SELECT COALESCE(MAX(sort_order), 0) + 1
               FROM `{$this->t('dogschool_plan_exercises')}`
              WHERE plan_id = ? AND week_number = ? AND session_number = ?",
            [$planId, $week, $session]
        );
        return $this->db->insert(
            "INSERT INTO `{$this->t('dogschool_plan_exercises')}`
                (plan_id, exercise_id, week_number, session_number, sort_order,
                 target_repetitions, target_duration_minutes, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$planId, $exerciseId, $week, $session, $sort, $targetReps, $targetDur, $notes]
        );
    }

    public function removePlanExercise(int $planExerciseId): int
    {
        return $this->db->execute(
            "DELETE FROM `{$this->t('dogschool_plan_exercises')}` WHERE id = ?",
            [$planExerciseId]
        );
    }

    /* ═════════════════ Übungs-Katalog ═════════════════ */

    public function listExercises(string $category = '', string $search = ''): array
    {
        $conds  = ['is_active = 1'];
        $params = [];
        if ($category !== '') {
            $conds[]  = 'category = ?';
            $params[] = $category;
        }
        if ($search !== '') {
            $conds[]  = '(name LIKE ? OR description LIKE ?)';
            $s        = "%{$search}%";
            $params   = array_merge($params, [$s, $s]);
        }
        $where = 'WHERE ' . implode(' AND ', $conds);
        return $this->db->safeFetchAll(
            "SELECT * FROM `{$this->t('dogschool_exercises')}` {$where}
              ORDER BY is_system DESC, category ASC, difficulty ASC, name ASC",
            $params
        );
    }

    public function findExercise(int $id): array|false
    {
        return $this->db->safeFetch(
            "SELECT * FROM `{$this->t('dogschool_exercises')}` WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    public function createExercise(array $data): string
    {
        $slug = !empty($data['slug'])
            ? (string)$data['slug']
            : $this->generateSlug((string)($data['name'] ?? 'exercise'));
        return $this->db->insert(
            "INSERT INTO `{$this->t('dogschool_exercises')}`
                (slug, name, category, description, instructions, difficulty,
                 duration_minutes, min_age_months, required_equipment, video_url,
                 is_system, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)",
            [
                $slug,
                (string)($data['name'] ?? ''),
                (string)($data['category'] ?? 'basics'),
                (string)($data['description'] ?? ''),
                (string)($data['instructions'] ?? ''),
                (string)($data['difficulty'] ?? 'medium'),
                (int)($data['duration_minutes'] ?? 10),
                !empty($data['min_age_months']) ? (int)$data['min_age_months'] : null,
                (string)($data['required_equipment'] ?? '') ?: null,
                (string)($data['video_url'] ?? '') ?: null,
            ]
        );
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = str_replace(['ä','ö','ü','ß'], ['ae','oe','ue','ss'], $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        /* Eindeutigkeit sicherstellen */
        $base    = $slug ?: 'exercise';
        $counter = 1;
        while ((int)$this->db->safeFetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('dogschool_exercises')}` WHERE slug = ?",
            [$slug]
        ) > 0) {
            $slug = $base . '_' . $counter++;
        }
        return $slug;
    }

    /* ═════════════════ Plan-Zuweisungen ═════════════════ */

    public function assignPlanToPatient(int $planId, int $patientId, ?int $ownerId,
                                         ?int $courseId, ?int $trainerId,
                                         string $startDate, ?string $notes = null): string
    {
        /* Wenn Plan eine Vorlage ist → Kopie als individueller Plan anlegen und zuweisen */
        $plan = $this->findPlanWithExercises($planId);
        if (!$plan) {
            return '0';
        }

        $actualPlanId = $planId;
        if ((int)$plan['is_template'] === 1) {
            $actualPlanId = (int)$this->createPlan([
                'name'              => ($plan['name'] ?? 'Plan') . ' (Instanz)',
                'description'       => $plan['description'] ?? null,
                'target_audience'   => $plan['target_audience'] ?? null,
                'duration_weeks'    => (int)($plan['duration_weeks'] ?? 8),
                'sessions_per_week' => (int)($plan['sessions_per_week'] ?? 1),
                'difficulty'        => $plan['difficulty'] ?? 'medium',
                'is_template'       => 0,
                'is_active'         => 1,
            ]);
            /* Übungen kopieren */
            foreach ($plan['exercises'] as $ex) {
                $this->addPlanExercise(
                    $actualPlanId,
                    (int)$ex['exercise_id'],
                    (int)$ex['week_number'],
                    (int)$ex['session_number'],
                    !empty($ex['target_repetitions']) ? (int)$ex['target_repetitions'] : null,
                    !empty($ex['target_duration_minutes']) ? (int)$ex['target_duration_minutes'] : null,
                    $ex['notes'] ?? null
                );
            }
        }

        $targetEnd = date('Y-m-d', strtotime(
            $startDate . ' +' . (int)($plan['duration_weeks'] ?? 8) . ' weeks'
        ));

        return $this->db->insert(
            "INSERT INTO `{$this->t('dogschool_plan_assignments')}`
                (plan_id, patient_id, owner_id, course_id, trainer_user_id,
                 start_date, target_end_date, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)",
            [$actualPlanId, $patientId, $ownerId, $courseId, $trainerId,
             $startDate, $targetEnd, $notes]
        );
    }

    public function assignmentsForPatient(int $patientId): array
    {
        return $this->db->safeFetchAll(
            "SELECT a.*, p.name AS plan_name, p.duration_weeks, p.difficulty,
                    (SELECT COUNT(*) FROM `{$this->t('dogschool_plan_exercises')}` pe WHERE pe.plan_id = a.plan_id) AS total_exercises,
                    (SELECT COUNT(DISTINCT pg.exercise_id) FROM `{$this->t('dogschool_training_progress')}` pg
                      WHERE pg.assignment_id = a.id AND pg.mastery_level >= 3) AS mastered_count
               FROM `{$this->t('dogschool_plan_assignments')}` a
               LEFT JOIN `{$this->t()}` p ON p.id = a.plan_id
              WHERE a.patient_id = ?
              ORDER BY a.status = 'active' DESC, a.start_date DESC",
            [$patientId]
        );
    }

    public function findAssignment(int $id): array|false
    {
        return $this->db->safeFetch(
            "SELECT a.*, p.name AS plan_name, p.duration_weeks, p.difficulty, p.description AS plan_description,
                    pt.name AS patient_name, pt.breed AS patient_breed,
                    o.first_name AS owner_first_name, o.last_name AS owner_last_name
               FROM `{$this->t('dogschool_plan_assignments')}` a
               LEFT JOIN `{$this->t()}` p  ON p.id = a.plan_id
               LEFT JOIN `{$this->t('patients')}` pt ON pt.id = a.patient_id
               LEFT JOIN `{$this->t('owners')}`   o  ON o.id  = a.owner_id
              WHERE a.id = ? LIMIT 1",
            [$id]
        );
    }

    public function updateAssignmentStatus(int $assignmentId, string $status): int
    {
        $allowed = ['active','paused','completed','cancelled'];
        if (!in_array($status, $allowed, true)) {
            return 0;
        }
        $completedAt = $status === 'completed' ? date('Y-m-d') : null;
        return $this->db->execute(
            "UPDATE `{$this->t('dogschool_plan_assignments')}`
                SET status = ?, completed_at = ?
              WHERE id = ?",
            [$status, $completedAt, $assignmentId]
        );
    }

    /* ═════════════════ Fortschritt ═════════════════ */

    public function recordProgress(array $data): string
    {
        return $this->db->insert(
            "INSERT INTO `{$this->t('dogschool_training_progress')}`
                (assignment_id, patient_id, exercise_id, session_id,
                 mastery_level, repetitions, duration_minutes, success_rate_pct,
                 notes, recorded_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                !empty($data['assignment_id']) ? (int)$data['assignment_id'] : null,
                (int)$data['patient_id'],
                (int)$data['exercise_id'],
                !empty($data['session_id']) ? (int)$data['session_id'] : null,
                max(0, min(5, (int)($data['mastery_level'] ?? 1))),
                !empty($data['repetitions']) ? (int)$data['repetitions'] : null,
                !empty($data['duration_minutes']) ? (int)$data['duration_minutes'] : null,
                isset($data['success_rate_pct']) ? max(0, min(100, (int)$data['success_rate_pct'])) : null,
                !empty($data['notes']) ? (string)$data['notes'] : null,
                !empty($data['recorded_by_user_id']) ? (int)$data['recorded_by_user_id'] : null,
            ]
        );
    }

    public function progressForAssignment(int $assignmentId): array
    {
        return $this->db->safeFetchAll(
            "SELECT pg.*, e.name AS exercise_name, e.category, e.difficulty
               FROM `{$this->t('dogschool_training_progress')}` pg
               LEFT JOIN `{$this->t('dogschool_exercises')}` e ON e.id = pg.exercise_id
              WHERE pg.assignment_id = ?
              ORDER BY pg.recorded_at DESC",
            [$assignmentId]
        );
    }

    /**
     * Pro Übung den aktuellsten (höchsten) Mastery-Wert — für den
     * Fortschritts-Balken je Exercise auf der Assignment-Detailseite.
     */
    public function latestMasteryByExercise(int $assignmentId): array
    {
        $rows = $this->db->safeFetchAll(
            "SELECT pg.exercise_id, MAX(pg.mastery_level) AS mastery_level,
                    MAX(pg.recorded_at) AS last_recorded
               FROM `{$this->t('dogschool_training_progress')}` pg
              WHERE pg.assignment_id = ?
              GROUP BY pg.exercise_id",
            [$assignmentId]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['exercise_id']] = [
                'mastery_level'  => (int)$r['mastery_level'],
                'last_recorded'  => $r['last_recorded'],
            ];
        }
        return $map;
    }

    public function progressForPatient(int $patientId, int $limit = 50): array
    {
        return $this->db->safeFetchAll(
            "SELECT pg.*, e.name AS exercise_name, e.category
               FROM `{$this->t('dogschool_training_progress')}` pg
               LEFT JOIN `{$this->t('dogschool_exercises')}` e ON e.id = pg.exercise_id
              WHERE pg.patient_id = ?
              ORDER BY pg.recorded_at DESC
              LIMIT ?",
            [$patientId, $limit]
        );
    }

    /* ═════════════════ Hausaufgaben ═════════════════ */

    public function createHomework(array $data): string
    {
        return $this->db->insert(
            "INSERT INTO `{$this->t('dogschool_homework')}`
                (assignment_id, patient_id, exercise_id, title, description, due_date, status)
             VALUES (?, ?, ?, ?, ?, ?, 'open')",
            [
                !empty($data['assignment_id']) ? (int)$data['assignment_id'] : null,
                (int)$data['patient_id'],
                !empty($data['exercise_id']) ? (int)$data['exercise_id'] : null,
                (string)($data['title'] ?? ''),
                (string)($data['description'] ?? ''),
                !empty($data['due_date']) ? (string)$data['due_date'] : null,
            ]
        );
    }

    public function homeworkForPatient(int $patientId, string $status = ''): array
    {
        $where  = 'WHERE h.patient_id = ?';
        $params = [$patientId];
        if ($status !== '') {
            $where   .= ' AND h.status = ?';
            $params[]  = $status;
        }
        return $this->db->safeFetchAll(
            "SELECT h.*, e.name AS exercise_name
               FROM `{$this->t('dogschool_homework')}` h
               LEFT JOIN `{$this->t('dogschool_exercises')}` e ON e.id = h.exercise_id
               {$where}
              ORDER BY h.status = 'open' DESC, h.due_date ASC",
            $params
        );
    }

    public function updateHomework(int $id, array $data): int
    {
        $allowed = ['status','owner_feedback','completed_at','title','description','due_date'];
        $sets    = [];
        $params  = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $sets[]   = "`{$k}` = ?";
                $params[] = $v;
            }
        }
        if (!$sets) return 0;
        $params[] = $id;
        return $this->db->execute(
            "UPDATE `{$this->t('dogschool_homework')}` SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );
    }

    public function exerciseCategories(): array
    {
        return [
            'basics'    => ['Grundkommandos',   '#60a5fa'],
            'obedience' => ['Gehorsam',         '#3b82f6'],
            'recall'    => ['Rückruf',          '#a78bfa'],
            'leash'     => ['Leinenführung',    '#f97316'],
            'social'    => ['Sozialverhalten',  '#ec4899'],
            'tricks'    => ['Tricks',           '#8b5cf6'],
            'agility'   => ['Agility',          '#06b6d4'],
            'problem'   => ['Problemverhalten', '#ef4444'],
        ];
    }

    public function masteryLevels(): array
    {
        return [
            0 => ['label' => 'Nicht geübt',   'color' => '#64748b'],
            1 => ['label' => 'Eingeführt',    'color' => '#94a3b8'],
            2 => ['label' => 'Geübt',         'color' => '#60a5fa'],
            3 => ['label' => 'Sicher',        'color' => '#22c55e'],
            4 => ['label' => 'Gemeistert',    'color' => '#10b981'],
            5 => ['label' => 'Abgeschlossen', 'color' => '#059669'],
        ];
    }
}
