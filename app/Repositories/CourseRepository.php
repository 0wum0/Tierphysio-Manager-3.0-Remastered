<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

/**
 * Kurs-Repository für das Hundeschul-Modul.
 *
 * Bündelt Zugriff auf:
 *   dogschool_courses         – Kursdefinition
 *   dogschool_course_sessions – Einzeltermine eines Kurses
 *   dogschool_enrollments     – Teilnehmer (Hund+Halter)
 *   dogschool_waitlist        – Wartelistenpositionen
 *
 * Alle Methoden sind gegen fehlende Tabellen resilient (safe*-Methoden),
 * damit ein fehlender Migrationslauf kein Crash auslöst — stattdessen
 * leere Listen / null zurückgeben.
 */
class CourseRepository extends Repository
{
    protected string $table = 'dogschool_courses';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /* ═══════════════════════ Kurse ═══════════════════════ */

    public function listPaginated(int $page, int $perPage, string $status = '', string $search = ''): array
    {
        $conditions = [];
        $params     = [];

        if ($status !== '' && $status !== 'all') {
            $conditions[] = '`status` = ?';
            $params[]     = $status;
        }
        if ($search !== '') {
            $conditions[] = '(`name` LIKE ? OR `description` LIKE ? OR `location` LIKE ?)';
            $s            = "%{$search}%";
            $params       = array_merge($params, [$s, $s, $s]);
        }

        $where  = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $total  = (int)$this->db->safeFetchColumn(
            "SELECT COUNT(*) FROM `{$this->t()}` {$where}",
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);

        $items = $this->db->safeFetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM `{$this->t('dogschool_enrollments')}` e
                      WHERE e.course_id = c.id AND e.status = 'active') AS enrolled_count,
                    (SELECT COUNT(*) FROM `{$this->t('dogschool_waitlist')}` w
                      WHERE w.course_id = c.id AND w.status = 'waiting') AS waitlist_count
               FROM `{$this->t()}` c
               {$where}
              ORDER BY c.start_date IS NULL, c.start_date DESC, c.id DESC
              LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );

        return [
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / max(1, $perPage)),
            'has_next'  => ($page * $perPage) < $total,
            'has_prev'  => $page > 1,
        ];
    }

    public function findWithStats(int $id): array|false
    {
        $row = $this->db->safeFetch(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM `{$this->t('dogschool_enrollments')}` e
                      WHERE e.course_id = c.id AND e.status = 'active') AS enrolled_count,
                    (SELECT COUNT(*) FROM `{$this->t('dogschool_waitlist')}` w
                      WHERE w.course_id = c.id AND w.status = 'waiting') AS waitlist_count
               FROM `{$this->t()}` c
              WHERE c.id = ? LIMIT 1",
            [$id]
        );
        return $row ?: false;
    }

    /**
     * Aktualisiert den Kurs-Status automatisch:
     *   - full     wenn enrolled_count >= max_participants
     *   - active   wenn unter Kapazität und start_date in der Zukunft/heute
     *   - completed wenn end_date in der Vergangenheit
     */
    public function recalculateStatus(int $courseId): void
    {
        $c = $this->findWithStats($courseId);
        if (!$c) {
            return;
        }
        /* Manuell gesetzte Status nicht überschreiben */
        $manual = ['cancelled', 'paused', 'draft'];
        if (in_array((string)$c['status'], $manual, true)) {
            return;
        }

        $new = 'active';
        if (!empty($c['end_date']) && $c['end_date'] < date('Y-m-d')) {
            $new = 'completed';
        } elseif ((int)$c['enrolled_count'] >= (int)$c['max_participants']) {
            $new = 'full';
        }

        if ($new !== (string)$c['status']) {
            $this->db->safeExecute(
                "UPDATE `{$this->t()}` SET `status` = ? WHERE `id` = ?",
                [$new, $courseId]
            );
        }
    }

    /* ═══════════════════════ Sessions ═══════════════════════ */

    public function sessionsForCourse(int $courseId): array
    {
        return $this->db->safeFetchAll(
            "SELECT * FROM `{$this->t('dogschool_course_sessions')}`
              WHERE course_id = ?
              ORDER BY session_date ASC, start_time ASC, session_number ASC",
            [$courseId]
        );
    }

    public function createSession(array $data): string
    {
        $cols = implode('`, `', array_keys($data));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        return $this->db->insert(
            "INSERT INTO `{$this->t('dogschool_course_sessions')}` (`{$cols}`) VALUES ({$ph})",
            array_values($data)
        );
    }

    public function updateSession(int $sessionId, array $data): int
    {
        $sets = implode(' = ?, ', array_map(fn($k) => "`{$k}`", array_keys($data))) . ' = ?';
        return $this->db->execute(
            "UPDATE `{$this->t('dogschool_course_sessions')}` SET {$sets} WHERE id = ?",
            [...array_values($data), $sessionId]
        );
    }

    public function deleteSession(int $sessionId): int
    {
        return $this->db->execute(
            "DELETE FROM `{$this->t('dogschool_course_sessions')}` WHERE id = ?",
            [$sessionId]
        );
    }

    /**
     * Generiert Sessions aus Kurs-Daten (num_sessions, weekday, start_time,
     * start_date). Überspringt Wochen ohne matchenden Wochentag.
     */
    public function generateSessions(int $courseId): int
    {
        $c = $this->findById($courseId);
        if (!$c || empty($c['start_date']) || (int)$c['num_sessions'] <= 0) {
            return 0;
        }

        /* Bereits vorhandene Sessions zählen — nicht doppelt anlegen */
        $existing = (int)$this->db->safeFetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('dogschool_course_sessions')}` WHERE course_id = ?",
            [$courseId]
        );
        if ($existing > 0) {
            return 0;
        }

        $date     = new \DateTimeImmutable((string)$c['start_date']);
        $weekday  = $c['weekday'] !== null ? (int)$c['weekday'] : null;
        $total    = (int)$c['num_sessions'];
        $startT   = (string)($c['start_time'] ?? '00:00:00');
        $duration = (int)($c['duration_minutes'] ?? 60);
        $created  = 0;

        for ($n = 1; $n <= $total; $n++) {
            /* Wenn weekday gesetzt ist, auf nächsten matchenden Tag vorspringen */
            if ($weekday !== null) {
                while ((int)$date->format('w') !== $weekday) {
                    $date = $date->modify('+1 day');
                }
            }
            $this->createSession([
                'course_id'        => $courseId,
                'session_number'   => $n,
                'session_date'     => $date->format('Y-m-d'),
                'start_time'       => $startT,
                'duration_minutes' => $duration,
                'status'           => 'planned',
            ]);
            $created++;
            $date = $date->modify('+7 days');
        }

        /* end_date passend setzen */
        $lastDate = $date->modify('-7 days')->format('Y-m-d');
        $this->db->safeExecute(
            "UPDATE `{$this->t()}` SET end_date = ? WHERE id = ? AND end_date IS NULL",
            [$lastDate, $courseId]
        );

        return $created;
    }

    /* ═══════════════════════ Enrollments ═══════════════════════ */

    public function enrollmentsForCourse(int $courseId): array
    {
        return $this->db->safeFetchAll(
            "SELECT e.*,
                    p.name            AS patient_name,
                    p.breed           AS patient_breed,
                    o.first_name      AS owner_first_name,
                    o.last_name       AS owner_last_name,
                    o.email           AS owner_email,
                    o.phone           AS owner_phone
               FROM `{$this->t('dogschool_enrollments')}` e
               LEFT JOIN `{$this->t('patients')}` p ON p.id = e.patient_id
               LEFT JOIN `{$this->t('owners')}`   o ON o.id = e.owner_id
              WHERE e.course_id = ?
              ORDER BY FIELD(e.status,'active','completed','transferred','cancelled','no_show'),
                       e.enrolled_at ASC",
            [$courseId]
        );
    }

    public function enroll(int $courseId, int $patientId, int $ownerId, array $extra = []): string|false
    {
        $data = array_merge([
            'course_id'   => $courseId,
            'patient_id'  => $patientId,
            'owner_id'    => $ownerId,
            'enrolled_at' => date('Y-m-d H:i:s'),
            'status'      => 'active',
        ], $extra);

        $cols = implode('`, `', array_keys($data));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        try {
            $id = $this->db->insert(
                "INSERT INTO `{$this->t('dogschool_enrollments')}` (`{$cols}`) VALUES ({$ph})",
                array_values($data)
            );
            $this->recalculateStatus($courseId);
            return $id;
        } catch (\Throwable $e) {
            /* Unique-Constraint-Verletzung — Teilnehmer ist bereits eingeschrieben */
            error_log('[CourseRepository enroll] ' . $e->getMessage());
            return false;
        }
    }

    public function updateEnrollmentStatus(int $enrollmentId, string $status): int
    {
        $allowed = ['active','cancelled','completed','transferred','no_show'];
        if (!in_array($status, $allowed, true)) {
            return 0;
        }
        $rows = $this->db->execute(
            "UPDATE `{$this->t('dogschool_enrollments')}` SET `status` = ? WHERE id = ?",
            [$status, $enrollmentId]
        );
        /* Kurs-Status neu berechnen — Teilnehmerzahl kann sich geändert haben */
        $courseId = (int)$this->db->safeFetchColumn(
            "SELECT course_id FROM `{$this->t('dogschool_enrollments')}` WHERE id = ?",
            [$enrollmentId]
        );
        if ($courseId > 0) {
            $this->recalculateStatus($courseId);
        }
        return $rows;
    }

    public function deleteEnrollment(int $enrollmentId): int
    {
        $courseId = (int)$this->db->safeFetchColumn(
            "SELECT course_id FROM `{$this->t('dogschool_enrollments')}` WHERE id = ?",
            [$enrollmentId]
        );
        $rows = $this->db->execute(
            "DELETE FROM `{$this->t('dogschool_enrollments')}` WHERE id = ?",
            [$enrollmentId]
        );
        if ($courseId > 0) {
            $this->recalculateStatus($courseId);
        }
        return $rows;
    }

    /* ═══════════════════════ Waitlist ═══════════════════════ */

    public function waitlistForCourse(int $courseId): array
    {
        return $this->db->safeFetchAll(
            "SELECT w.*,
                    p.name        AS patient_name,
                    o.first_name  AS owner_first_name,
                    o.last_name   AS owner_last_name,
                    o.email       AS owner_email,
                    o.phone       AS owner_phone
               FROM `{$this->t('dogschool_waitlist')}` w
               LEFT JOIN `{$this->t('patients')}` p ON p.id = w.patient_id
               LEFT JOIN `{$this->t('owners')}`   o ON o.id = w.owner_id
              WHERE w.course_id = ?
              ORDER BY FIELD(w.status,'waiting','offered','accepted','declined','expired'),
                       w.position ASC, w.created_at ASC",
            [$courseId]
        );
    }

    public function addToWaitlist(int $courseId, array $data): string
    {
        $nextPos = (int)$this->db->safeFetchColumn(
            "SELECT COALESCE(MAX(position), 0) + 1
               FROM `{$this->t('dogschool_waitlist')}`
              WHERE course_id = ? AND status = 'waiting'",
            [$courseId]
        );
        $row = array_merge([
            'course_id'  => $courseId,
            'position'   => max(1, $nextPos),
            'status'     => 'waiting',
            'created_at' => date('Y-m-d H:i:s'),
        ], $data);

        $cols = implode('`, `', array_keys($row));
        $ph   = implode(', ', array_fill(0, count($row), '?'));
        return $this->db->insert(
            "INSERT INTO `{$this->t('dogschool_waitlist')}` (`{$cols}`) VALUES ({$ph})",
            array_values($row)
        );
    }

    public function updateWaitlistStatus(int $id, string $status, ?string $notifiedAt = null): int
    {
        $allowed = ['waiting','offered','accepted','declined','expired'];
        if (!in_array($status, $allowed, true)) {
            return 0;
        }
        $sets   = ['`status` = ?'];
        $params = [$status];
        if ($notifiedAt !== null) {
            $sets[]   = '`notified_at` = ?';
            $params[] = $notifiedAt;
        }
        $params[] = $id;
        return $this->db->execute(
            "UPDATE `{$this->t('dogschool_waitlist')}` SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );
    }

    public function removeFromWaitlist(int $id): int
    {
        return $this->db->execute(
            "DELETE FROM `{$this->t('dogschool_waitlist')}` WHERE id = ?",
            [$id]
        );
    }

    /* ═══════════════════════ Attendance (Phase 3) ═══════════════════════ */

    public function findSession(int $sessionId): array|false
    {
        return $this->db->safeFetch(
            "SELECT s.*, c.name AS course_name, c.max_participants
               FROM `{$this->t('dogschool_course_sessions')}` s
               LEFT JOIN `{$this->t('dogschool_courses')}` c ON c.id = s.course_id
              WHERE s.id = ? LIMIT 1",
            [$sessionId]
        );
    }

    public function attendanceForSession(int $sessionId): array
    {
        /* Liefert alle aktiven Teilnehmer des Kurses + deren Attendance-
         * Record (falls vorhanden). Durch LEFT JOIN sieht der Controller
         * auch Teilnehmer ohne bisherigen Eintrag — und zeigt sie als
         * unmarkiert an. */
        $session = $this->findSession($sessionId);
        if (!$session) {
            return [];
        }
        return $this->db->safeFetchAll(
            "SELECT e.id AS enrollment_id,
                    e.patient_id, e.owner_id,
                    p.name AS patient_name, p.breed AS patient_breed,
                    o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                    a.id AS attendance_id, a.status AS attendance_status, a.notes AS attendance_notes
               FROM `{$this->t('dogschool_enrollments')}` e
               LEFT JOIN `{$this->t('patients')}` p ON p.id = e.patient_id
               LEFT JOIN `{$this->t('owners')}`   o ON o.id = e.owner_id
               LEFT JOIN `{$this->t('dogschool_attendance')}` a
                      ON a.enrollment_id = e.id AND a.session_id = ?
              WHERE e.course_id = ? AND e.status = 'active'
              ORDER BY p.name ASC",
            [$sessionId, (int)$session['course_id']]
        );
    }

    public function saveAttendance(int $sessionId, int $enrollmentId, string $status, ?string $notes, ?int $userId): void
    {
        $allowed = ['present','absent','excused','late','left_early','no_show'];
        if (!in_array($status, $allowed, true)) {
            return;
        }
        $this->db->safeExecute(
            "INSERT INTO `{$this->t('dogschool_attendance')}`
                (session_id, enrollment_id, status, notes, marked_by_user_id)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                status            = VALUES(status),
                notes             = VALUES(notes),
                marked_by_user_id = VALUES(marked_by_user_id)",
            [$sessionId, $enrollmentId, $status, $notes, $userId]
        );
    }

    /* ═══════════════════════ Dashboard-Aggregates ═══════════════════════ */

    /**
     * Kurstermine in einem Zeitfenster — für Dashboard-Widgets.
     * Default: heute.
     */
    public function sessionsBetween(string $from, string $to): array
    {
        return $this->db->safeFetchAll(
            "SELECT s.*, c.name AS course_name, c.id AS course_id,
                    c.location, c.max_participants,
                    (SELECT COUNT(*) FROM `{$this->t('dogschool_enrollments')}` e
                      WHERE e.course_id = c.id AND e.status = 'active') AS enrolled_count
               FROM `{$this->t('dogschool_course_sessions')}` s
               LEFT JOIN `{$this->t('dogschool_courses')}` c ON c.id = s.course_id
              WHERE s.session_date BETWEEN ? AND ?
                AND s.status != 'cancelled'
              ORDER BY s.session_date ASC, s.start_time ASC",
            [$from, $to]
        );
    }

    public function openSessions(int $limit = 20): array
    {
        /* Noch nicht markierte vergangene oder heutige Sessions */
        return $this->db->safeFetchAll(
            "SELECT s.*, c.name AS course_name
               FROM `{$this->t('dogschool_course_sessions')}` s
               LEFT JOIN `{$this->t('dogschool_courses')}` c ON c.id = s.course_id
              WHERE s.session_date <= CURDATE()
                AND s.status = 'planned'
              ORDER BY s.session_date DESC, s.start_time DESC
              LIMIT ?",
            [$limit]
        );
    }

    public function countFreeSpotsTotal(): int
    {
        return (int)$this->db->safeFetchColumn(
            "SELECT COALESCE(SUM(GREATEST(0, c.max_participants -
                    (SELECT COUNT(*) FROM `{$this->t('dogschool_enrollments')}` e
                      WHERE e.course_id = c.id AND e.status = 'active'))), 0)
               FROM `{$this->t('dogschool_courses')}` c
              WHERE c.status IN ('active','draft')"
        );
    }

    public function countByStatus(string $status): int
    {
        return (int)$this->db->safeFetchColumn(
            "SELECT COUNT(*) FROM `{$this->t()}` WHERE status = ?",
            [$status]
        );
    }

    public function countWaitlistTotal(): int
    {
        return (int)$this->db->safeFetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('dogschool_waitlist')}` WHERE status = 'waiting'"
        );
    }
}
