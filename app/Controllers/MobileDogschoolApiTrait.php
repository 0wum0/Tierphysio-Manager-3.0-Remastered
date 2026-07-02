<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Mobile REST API — Hundeschul-/Hundetrainer-Modul (Phase 2).
 *
 * Alle Endpunkte liegen unter /api/mobile/dogschool/… und nutzen die
 * privaten Helfer des MobileApiController (cors, requireAuth, json, error,
 * body, input, t). Die Methoden werden per `use` in den Controller gemischt
 * und sind damit vollwertige Controller-Methoden.
 *
 * Tabellen (tenant-prefixed):
 *   dogschool_courses, dogschool_course_sessions, dogschool_enrollments,
 *   dogschool_attendance, dogschool_packages, dogschool_package_balances,
 *   dogschool_leads, dogschool_exercises, dogschool_training_plans,
 *   dogschool_plan_exercises, dogschool_plan_assignments, dogschool_homework
 */
trait MobileDogschoolApiTrait
{
    /* ══════════════════════════════════════════════════════
       DASHBOARD
    ══════════════════════════════════════════════════════ */

    public function dogschoolDashboard(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $count = function (string $sql, array $p = []): int {
            try {
                $row = $this->db->fetch($sql, $p);
                return (int)($row['c'] ?? 0);
            } catch (\Throwable) {
                return 0;
            }
        };

        $courses = $this->t('dogschool_courses');
        $sessions = $this->t('dogschool_course_sessions');
        $leads = $this->t('dogschool_leads');
        $balances = $this->t('dogschool_package_balances');
        $homework = $this->t('dogschool_homework');

        $stats = [
            'active_courses'    => $count("SELECT COUNT(*) c FROM `{$courses}` WHERE status = 'active'"),
            'upcoming_sessions' => $count("SELECT COUNT(*) c FROM `{$sessions}` WHERE status = 'planned' AND session_date >= CURDATE() AND session_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)"),
            'new_leads'         => $count("SELECT COUNT(*) c FROM `{$leads}` WHERE status IN ('new','contacted','trial_scheduled')"),
            'active_packages'   => $count("SELECT COUNT(*) c FROM `{$balances}` WHERE status = 'active'"),
            'open_homework'     => $count("SELECT COUNT(*) c FROM `{$homework}` WHERE status = 'open'"),
        ];

        $upcoming = [];
        try {
            $upcoming = $this->db->fetchAll(
                "SELECT s.id, s.course_id, s.session_number, s.session_date, s.start_time,
                        s.topic, s.status, c.name AS course_name, c.type AS course_type
                 FROM `{$sessions}` s
                 LEFT JOIN `{$courses}` c ON c.id = s.course_id
                 WHERE s.status = 'planned' AND s.session_date >= CURDATE()
                 ORDER BY s.session_date ASC, s.start_time ASC
                 LIMIT 8"
            );
        } catch (\Throwable) {
        }

        $recentLeads = [];
        try {
            $recentLeads = $this->db->fetchAll(
                "SELECT id, first_name, last_name, dog_name, interest, status, source, created_at
                 FROM `{$leads}`
                 WHERE status NOT IN ('converted','lost','archived')
                 ORDER BY created_at DESC
                 LIMIT 8"
            );
        } catch (\Throwable) {
        }

        $this->json([
            'stats'            => $stats,
            'upcoming_sessions'=> $upcoming,
            'recent_leads'     => $recentLeads,
        ]);
    }

    /* ══════════════════════════════════════════════════════
       KURSE
    ══════════════════════════════════════════════════════ */

    public function dogschoolCoursesList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $per    = min(200, max(10, (int)($_GET['per_page'] ?? 50)));
        $status = trim((string)($_GET['status'] ?? ''));
        $type   = trim((string)($_GET['type'] ?? ''));
        $search = trim((string)($_GET['search'] ?? ''));
        $offset = ($page - 1) * $per;

        $courses = $this->t('dogschool_courses');
        $enr     = $this->t('dogschool_enrollments');
        $users   = $this->t('users');

        $where  = [];
        $args   = [];
        if ($status !== '') { $where[] = 'c.status = ?'; $args[] = $status; }
        if ($type !== '')   { $where[] = 'c.type = ?';   $args[] = $type; }
        if ($search !== '') { $where[] = '(c.name LIKE ? OR c.location LIKE ?)'; $args[] = "%{$search}%"; $args[] = "%{$search}%"; }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = 0;
        try {
            $row = $this->db->fetch("SELECT COUNT(*) c FROM `{$courses}` c {$whereSql}", $args);
            $total = (int)($row['c'] ?? 0);
        } catch (\Throwable) {
        }

        $items = [];
        try {
            $items = $this->db->fetchAll(
                "SELECT c.*, u.name AS trainer_name,
                        (SELECT COUNT(*) FROM `{$enr}` e WHERE e.course_id = c.id AND e.status = 'active') AS enrolled_count
                 FROM `{$courses}` c
                 LEFT JOIN `{$users}` u ON u.id = c.trainer_user_id
                 {$whereSql}
                 ORDER BY c.start_date DESC, c.id DESC
                 LIMIT {$per} OFFSET {$offset}",
                $args
            );
        } catch (\Throwable $e) {
            $this->error('Kurse konnten nicht geladen werden: ' . $e->getMessage(), 500);
        }

        $this->json([
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per,
        ]);
    }

    public function dogschoolCourseShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $courses = $this->t('dogschool_courses');
        $sessions = $this->t('dogschool_course_sessions');
        $enr = $this->t('dogschool_enrollments');
        $patients = $this->t('patients');
        $owners = $this->t('owners');
        $users = $this->t('users');

        $course = $this->db->fetch(
            "SELECT c.*, u.name AS trainer_name
             FROM `{$courses}` c
             LEFT JOIN `{$users}` u ON u.id = c.trainer_user_id
             WHERE c.id = ? LIMIT 1",
            [$id]
        );
        if (!$course) {
            $this->error('Kurs nicht gefunden.', 404);
        }

        $course['sessions'] = $this->db->fetchAll(
            "SELECT * FROM `{$sessions}` WHERE course_id = ? ORDER BY session_number ASC, session_date ASC",
            [$id]
        );
        $course['enrollments'] = $this->db->fetchAll(
            "SELECT e.*, p.name AS patient_name, o.first_name AS owner_first_name, o.last_name AS owner_last_name
             FROM `{$enr}` e
             LEFT JOIN `{$patients}` p ON p.id = e.patient_id
             LEFT JOIN `{$owners}` o ON o.id = e.owner_id
             WHERE e.course_id = ?
             ORDER BY e.enrolled_at ASC",
            [$id]
        );

        $this->json($course);
    }

    public function dogschoolCourseCreate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data = $this->body();
        if (trim((string)($data['name'] ?? '')) === '') {
            $this->error("Feld 'name' ist erforderlich.");
        }

        $courses = $this->t('dogschool_courses');
        $id = $this->db->insert(
            "INSERT INTO `{$courses}`
                (name, type, description, level, trainer_user_id, location, start_date, end_date,
                 weekday, start_time, duration_minutes, max_participants, price_cents, num_sessions, status, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                trim((string)$data['name']),
                trim((string)($data['type'] ?? 'group')),
                $this->nullableStr($data['description'] ?? null),
                $this->nullableStr($data['level'] ?? null),
                $this->nullableInt($data['trainer_user_id'] ?? null),
                $this->nullableStr($data['location'] ?? null),
                $this->nullableStr($data['start_date'] ?? null),
                $this->nullableStr($data['end_date'] ?? null),
                $this->nullableInt($data['weekday'] ?? null),
                $this->nullableStr($data['start_time'] ?? null),
                (int)($data['duration_minutes'] ?? 60),
                (int)($data['max_participants'] ?? 8),
                (int)($data['price_cents'] ?? 0),
                (int)($data['num_sessions'] ?? 1),
                trim((string)($data['status'] ?? 'draft')),
                $this->nullableStr($data['notes'] ?? null),
            ]
        );

        $this->json($this->db->fetch("SELECT * FROM `{$courses}` WHERE id = ?", [(int)$id]), 201);
    }

    public function dogschoolCourseUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $courses = $this->t('dogschool_courses');
        $existing = $this->db->fetch("SELECT * FROM `{$courses}` WHERE id = ? LIMIT 1", [$id]);
        if (!$existing) {
            $this->error('Kurs nicht gefunden.', 404);
        }

        $data = $this->body();
        $fields = [
            'name', 'type', 'description', 'level', 'trainer_user_id', 'location',
            'start_date', 'end_date', 'weekday', 'start_time', 'duration_minutes',
            'max_participants', 'price_cents', 'num_sessions', 'status', 'notes',
        ];
        $intFields = ['trainer_user_id', 'weekday', 'duration_minutes', 'max_participants', 'price_cents', 'num_sessions'];
        $nullableFields = ['description', 'level', 'trainer_user_id', 'location', 'start_date', 'end_date', 'weekday', 'start_time', 'notes'];

        $set = [];
        $args = [];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $val = $data[$f];
            if (in_array($f, $nullableFields, true) && ($val === '' || $val === null)) {
                $val = null;
            } elseif (in_array($f, $intFields, true)) {
                $val = (int)$val;
            } else {
                $val = is_string($val) ? trim($val) : $val;
            }
            $set[] = "`{$f}` = ?";
            $args[] = $val;
        }
        if ($set) {
            $args[] = $id;
            $this->db->execute("UPDATE `{$courses}` SET " . implode(', ', $set) . " WHERE id = ?", $args);
        }

        $this->json($this->db->fetch("SELECT * FROM `{$courses}` WHERE id = ?", [$id]));
    }

    public function dogschoolCourseEnroll(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $courseId = (int)($params['id'] ?? 0);
        $data = $this->body();
        $patientId = (int)($data['patient_id'] ?? 0);
        $ownerId = (int)($data['owner_id'] ?? 0);
        if ($courseId <= 0 || $patientId <= 0 || $ownerId <= 0) {
            $this->error("Felder 'patient_id' und 'owner_id' sind erforderlich.");
        }

        $enr = $this->t('dogschool_enrollments');
        $exists = $this->db->fetch(
            "SELECT id FROM `{$enr}` WHERE course_id = ? AND patient_id = ? LIMIT 1",
            [$courseId, $patientId]
        );
        if ($exists) {
            $this->error('Dieser Hund ist bereits in den Kurs eingeschrieben.', 409);
        }

        $id = $this->db->insert(
            "INSERT INTO `{$enr}` (course_id, patient_id, owner_id, status, price_cents, notes)
             VALUES (?,?,?,?,?,?)",
            [
                $courseId,
                $patientId,
                $ownerId,
                trim((string)($data['status'] ?? 'active')),
                $this->nullableInt($data['price_cents'] ?? null),
                $this->nullableStr($data['notes'] ?? null),
            ]
        );

        $this->json($this->db->fetch("SELECT * FROM `{$enr}` WHERE id = ?", [(int)$id]), 201);
    }

    public function dogschoolEnrollmentStatus(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $enrollmentId = (int)($params['enrollment_id'] ?? 0);
        $data = $this->body();
        $status = trim((string)($data['status'] ?? ''));
        $allowed = ['active', 'cancelled', 'completed', 'transferred', 'no_show'];
        if (!in_array($status, $allowed, true)) {
            $this->error('Ungültiger Status.');
        }

        $enr = $this->t('dogschool_enrollments');
        $existing = $this->db->fetch("SELECT id FROM `{$enr}` WHERE id = ? LIMIT 1", [$enrollmentId]);
        if (!$existing) {
            $this->error('Einschreibung nicht gefunden.', 404);
        }
        $this->db->execute("UPDATE `{$enr}` SET status = ? WHERE id = ?", [$status, $enrollmentId]);

        $this->json($this->db->fetch("SELECT * FROM `{$enr}` WHERE id = ?", [$enrollmentId]));
    }

    /* ══════════════════════════════════════════════════════
       ANWESENHEIT
    ══════════════════════════════════════════════════════ */

    public function dogschoolSessionsList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $sessions = $this->t('dogschool_course_sessions');
        $courses = $this->t('dogschool_courses');
        $att = $this->t('dogschool_attendance');

        $items = [];
        try {
            $items = $this->db->fetchAll(
                "SELECT s.id, s.course_id, s.session_number, s.session_date, s.start_time,
                        s.topic, s.status, c.name AS course_name, c.type AS course_type,
                        (SELECT COUNT(*) FROM `{$att}` a WHERE a.session_id = s.id) AS marked_count
                 FROM `{$sessions}` s
                 LEFT JOIN `{$courses}` c ON c.id = s.course_id
                 WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 ORDER BY s.session_date DESC, s.start_time DESC
                 LIMIT 100"
            );
        } catch (\Throwable $e) {
            $this->error('Termine konnten nicht geladen werden: ' . $e->getMessage(), 500);
        }

        $this->json(['items' => $items]);
    }

    public function dogschoolAttendanceMatrix(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $sessionId = (int)($params['session_id'] ?? 0);
        $sessions = $this->t('dogschool_course_sessions');
        $courses = $this->t('dogschool_courses');
        $enr = $this->t('dogschool_enrollments');
        $att = $this->t('dogschool_attendance');
        $patients = $this->t('patients');
        $owners = $this->t('owners');

        $session = $this->db->fetch(
            "SELECT s.*, c.name AS course_name FROM `{$sessions}` s
             LEFT JOIN `{$courses}` c ON c.id = s.course_id
             WHERE s.id = ? LIMIT 1",
            [$sessionId]
        );
        if (!$session) {
            $this->error('Termin nicht gefunden.', 404);
        }

        $rows = $this->db->fetchAll(
            "SELECT e.id AS enrollment_id, p.name AS patient_name,
                    o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                    a.status AS attendance_status, a.notes AS attendance_notes
             FROM `{$enr}` e
             LEFT JOIN `{$patients}` p ON p.id = e.patient_id
             LEFT JOIN `{$owners}` o ON o.id = e.owner_id
             LEFT JOIN `{$att}` a ON a.enrollment_id = e.id AND a.session_id = ?
             WHERE e.course_id = ? AND e.status = 'active'
             ORDER BY p.name ASC",
            [$sessionId, (int)$session['course_id']]
        );

        $this->json(['session' => $session, 'rows' => $rows]);
    }

    public function dogschoolAttendanceSave(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();

        $sessionId = (int)($params['session_id'] ?? 0);
        $data = $this->body();
        $entries = $data['entries'] ?? [];
        if (!is_array($entries)) {
            $this->error("Feld 'entries' muss ein Array sein.");
        }

        $sessions = $this->t('dogschool_course_sessions');
        $att = $this->t('dogschool_attendance');
        $session = $this->db->fetch("SELECT id FROM `{$sessions}` WHERE id = ? LIMIT 1", [$sessionId]);
        if (!$session) {
            $this->error('Termin nicht gefunden.', 404);
        }

        $allowed = ['present', 'absent', 'excused', 'late', 'left_early', 'no_show'];
        $userId = (int)($user['user_id'] ?? 0);
        $saved = 0;
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $enrollmentId = (int)($entry['enrollment_id'] ?? 0);
            $status = trim((string)($entry['status'] ?? ''));
            if ($enrollmentId <= 0 || !in_array($status, $allowed, true)) {
                continue;
            }
            $notes = $this->nullableStr($entry['notes'] ?? null);
            $this->db->execute(
                "INSERT INTO `{$att}` (session_id, enrollment_id, status, notes, marked_by_user_id)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes),
                    marked_by_user_id = VALUES(marked_by_user_id), marked_at = CURRENT_TIMESTAMP",
                [$sessionId, $enrollmentId, $status, $notes, $userId ?: null]
            );
            $saved++;
        }

        $this->db->execute("UPDATE `{$sessions}` SET status = 'held' WHERE id = ? AND status = 'planned'", [$sessionId]);

        $this->json(['saved' => $saved]);
    }

    /* ══════════════════════════════════════════════════════
       PAKETE / MEHRFACHKARTEN
    ══════════════════════════════════════════════════════ */

    public function dogschoolPackagesList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $packages = $this->t('dogschool_packages');
        $items = [];
        try {
            $items = $this->db->fetchAll(
                "SELECT * FROM `{$packages}` ORDER BY is_active DESC, name ASC"
            );
        } catch (\Throwable $e) {
            $this->error('Pakete konnten nicht geladen werden: ' . $e->getMessage(), 500);
        }
        $this->json(['items' => $items]);
    }

    public function dogschoolPackageBalances(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $status = trim((string)($_GET['status'] ?? ''));
        $balances = $this->t('dogschool_package_balances');
        $packages = $this->t('dogschool_packages');
        $owners = $this->t('owners');
        $patients = $this->t('patients');

        $where = '';
        $args = [];
        if ($status !== '') {
            $where = 'WHERE b.status = ?';
            $args[] = $status;
        }

        $items = [];
        try {
            $items = $this->db->fetchAll(
                "SELECT b.*, pk.name AS package_name, pk.type AS package_type,
                        o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                        p.name AS patient_name,
                        (b.units_total - b.units_used) AS units_remaining
                 FROM `{$balances}` b
                 LEFT JOIN `{$packages}` pk ON pk.id = b.package_id
                 LEFT JOIN `{$owners}` o ON o.id = b.owner_id
                 LEFT JOIN `{$patients}` p ON p.id = b.patient_id
                 {$where}
                 ORDER BY b.created_at DESC
                 LIMIT 200",
                $args
            );
        } catch (\Throwable $e) {
            $this->error('Paket-Konten konnten nicht geladen werden: ' . $e->getMessage(), 500);
        }
        $this->json(['items' => $items]);
    }

    public function dogschoolPackageCreate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data = $this->body();
        if (trim((string)($data['name'] ?? '')) === '') {
            $this->error("Feld 'name' ist erforderlich.");
        }

        $packages = $this->t('dogschool_packages');
        $id = $this->db->insert(
            "INSERT INTO `{$packages}`
                (name, description, type, total_units, valid_days, price_cents, applies_to_types, is_active)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                trim((string)$data['name']),
                $this->nullableStr($data['description'] ?? null),
                trim((string)($data['type'] ?? 'multi')),
                (int)($data['total_units'] ?? 1),
                $this->nullableInt($data['valid_days'] ?? null),
                (int)($data['price_cents'] ?? 0),
                $this->nullableStr($data['applies_to_types'] ?? null),
                isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            ]
        );
        $this->json($this->db->fetch("SELECT * FROM `{$packages}` WHERE id = ?", [(int)$id]), 201);
    }

    public function dogschoolPackageSell(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data = $this->body();
        $packageId = (int)($data['package_id'] ?? 0);
        $ownerId = (int)($data['owner_id'] ?? 0);
        if ($packageId <= 0 || $ownerId <= 0) {
            $this->error("Felder 'package_id' und 'owner_id' sind erforderlich.");
        }

        $packages = $this->t('dogschool_packages');
        $balances = $this->t('dogschool_package_balances');
        $package = $this->db->fetch("SELECT * FROM `{$packages}` WHERE id = ? LIMIT 1", [$packageId]);
        if (!$package) {
            $this->error('Paket nicht gefunden.', 404);
        }

        $totalUnits = (int)($data['units_total'] ?? $package['total_units'] ?? 1);
        $validDays = $package['valid_days'] !== null ? (int)$package['valid_days'] : null;
        $expiresAt = $validDays !== null ? date('Y-m-d', strtotime("+{$validDays} days")) : null;

        $id = $this->db->insert(
            "INSERT INTO `{$balances}`
                (package_id, owner_id, patient_id, units_total, units_used, purchased_at, expires_at, status, notes)
             VALUES (?,?,?,?,0,CURDATE(),?, 'active', ?)",
            [
                $packageId,
                $ownerId,
                $this->nullableInt($data['patient_id'] ?? null),
                $totalUnits,
                $expiresAt,
                $this->nullableStr($data['notes'] ?? null),
            ]
        );
        $this->json($this->db->fetch("SELECT * FROM `{$balances}` WHERE id = ?", [(int)$id]), 201);
    }

    public function dogschoolPackageRedeem(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();

        $balanceId = (int)($params['balance_id'] ?? 0);
        $data = $this->body();
        $units = max(1, (int)($data['units'] ?? 1));

        $balances = $this->t('dogschool_package_balances');
        $redemptions = $this->t('dogschool_package_redemptions');
        $balance = $this->db->fetch("SELECT * FROM `{$balances}` WHERE id = ? LIMIT 1", [$balanceId]);
        if (!$balance) {
            $this->error('Paket-Konto nicht gefunden.', 404);
        }
        $remaining = (int)$balance['units_total'] - (int)$balance['units_used'];
        if ($units > $remaining) {
            $this->error('Nicht genügend Einheiten übrig.', 409);
        }

        $this->db->execute(
            "UPDATE `{$balances}` SET units_used = units_used + ? WHERE id = ?",
            [$units, $balanceId]
        );
        $this->db->insert(
            "INSERT INTO `{$redemptions}` (balance_id, enrollment_id, session_id, units, redeemed_by_user_id, notes)
             VALUES (?,?,?,?,?,?)",
            [
                $balanceId,
                $this->nullableInt($data['enrollment_id'] ?? null),
                $this->nullableInt($data['session_id'] ?? null),
                $units,
                (int)($user['user_id'] ?? 0) ?: null,
                $this->nullableStr($data['notes'] ?? null),
            ]
        );

        $updated = $this->db->fetch("SELECT * FROM `{$balances}` WHERE id = ?", [$balanceId]);
        if ($updated && (int)$updated['units_used'] >= (int)$updated['units_total']) {
            $this->db->execute("UPDATE `{$balances}` SET status = 'used_up' WHERE id = ?", [$balanceId]);
            $updated['status'] = 'used_up';
        }
        $this->json($updated);
    }

    /* ══════════════════════════════════════════════════════
       INTERESSENTEN / LEADS
    ══════════════════════════════════════════════════════ */

    public function dogschoolLeadsList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $status = trim((string)($_GET['status'] ?? ''));
        $search = trim((string)($_GET['search'] ?? ''));
        $leads = $this->t('dogschool_leads');

        $where = [];
        $args = [];
        if ($status !== '') { $where[] = 'status = ?'; $args[] = $status; }
        if ($search !== '') {
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR dog_name LIKE ? OR email LIKE ?)';
            $args[] = "%{$search}%"; $args[] = "%{$search}%"; $args[] = "%{$search}%"; $args[] = "%{$search}%";
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $items = [];
        try {
            $items = $this->db->fetchAll(
                "SELECT * FROM `{$leads}` {$whereSql} ORDER BY
                    FIELD(status,'new','contacted','trial_scheduled','trial_done','converted','lost','archived'),
                    created_at DESC
                 LIMIT 300",
                $args
            );
        } catch (\Throwable $e) {
            $this->error('Interessenten konnten nicht geladen werden: ' . $e->getMessage(), 500);
        }
        $this->json(['items' => $items]);
    }

    public function dogschoolLeadShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $leads = $this->t('dogschool_leads');
        $lead = $this->db->fetch("SELECT * FROM `{$leads}` WHERE id = ? LIMIT 1", [$id]);
        if (!$lead) {
            $this->error('Interessent nicht gefunden.', 404);
        }
        $this->json($lead);
    }

    public function dogschoolLeadCreate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data = $this->body();
        if (trim((string)($data['first_name'] ?? '')) === '' && trim((string)($data['last_name'] ?? '')) === '') {
            $this->error("Mindestens ein Name (Vor- oder Nachname) ist erforderlich.");
        }

        $leads = $this->t('dogschool_leads');
        $id = $this->db->insert(
            "INSERT INTO `{$leads}`
                (source, first_name, last_name, email, phone, dog_name, dog_breed, dog_age_months,
                 interest, message, status, next_followup_at, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $this->nullableStr($data['source'] ?? null),
                trim((string)($data['first_name'] ?? '')),
                trim((string)($data['last_name'] ?? '')),
                $this->nullableStr($data['email'] ?? null),
                $this->nullableStr($data['phone'] ?? null),
                $this->nullableStr($data['dog_name'] ?? null),
                $this->nullableStr($data['dog_breed'] ?? null),
                $this->nullableInt($data['dog_age_months'] ?? null),
                $this->nullableStr($data['interest'] ?? null),
                $this->nullableStr($data['message'] ?? null),
                trim((string)($data['status'] ?? 'new')),
                $this->nullableStr($data['next_followup_at'] ?? null),
                $this->nullableStr($data['notes'] ?? null),
            ]
        );
        $this->json($this->db->fetch("SELECT * FROM `{$leads}` WHERE id = ?", [(int)$id]), 201);
    }

    public function dogschoolLeadUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $leads = $this->t('dogschool_leads');
        $existing = $this->db->fetch("SELECT id FROM `{$leads}` WHERE id = ? LIMIT 1", [$id]);
        if (!$existing) {
            $this->error('Interessent nicht gefunden.', 404);
        }

        $data = $this->body();
        $fields = [
            'source', 'first_name', 'last_name', 'email', 'phone', 'dog_name', 'dog_breed',
            'dog_age_months', 'interest', 'message', 'status', 'next_followup_at', 'notes',
        ];
        $intFields = ['dog_age_months'];
        $nullableFields = ['source', 'email', 'phone', 'dog_name', 'dog_breed', 'dog_age_months', 'interest', 'message', 'next_followup_at', 'notes'];

        $set = [];
        $args = [];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $val = $data[$f];
            if (in_array($f, $nullableFields, true) && ($val === '' || $val === null)) {
                $val = null;
            } elseif (in_array($f, $intFields, true)) {
                $val = (int)$val;
            } else {
                $val = is_string($val) ? trim($val) : $val;
            }
            $set[] = "`{$f}` = ?";
            $args[] = $val;
        }
        if ($set) {
            $args[] = $id;
            $this->db->execute("UPDATE `{$leads}` SET " . implode(', ', $set) . " WHERE id = ?", $args);
        }

        $this->json($this->db->fetch("SELECT * FROM `{$leads}` WHERE id = ?", [$id]));
    }

    public function dogschoolLeadConvert(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $leads = $this->t('dogschool_leads');
        $owners = $this->t('owners');
        $patients = $this->t('patients');

        $lead = $this->db->fetch("SELECT * FROM `{$leads}` WHERE id = ? LIMIT 1", [$id]);
        if (!$lead) {
            $this->error('Interessent nicht gefunden.', 404);
        }
        if (!empty($lead['converted_owner_id'])) {
            $this->error('Interessent wurde bereits konvertiert.', 409);
        }

        $ownerId = $this->db->insert(
            "INSERT INTO `{$owners}` (first_name, last_name, email, phone)
             VALUES (?,?,?,?)",
            [
                (string)($lead['first_name'] ?? ''),
                (string)($lead['last_name'] ?? ''),
                $this->nullableStr($lead['email'] ?? null),
                $this->nullableStr($lead['phone'] ?? null),
            ]
        );

        $patientId = null;
        if (!empty($lead['dog_name'])) {
            $patientId = (int)$this->db->insert(
                "INSERT INTO `{$patients}` (name, species, breed, owner_id, status)
                 VALUES (?, 'Hund', ?, ?, 'active')",
                [
                    (string)$lead['dog_name'],
                    $this->nullableStr($lead['dog_breed'] ?? null),
                    (int)$ownerId,
                ]
            );
        }

        $this->db->execute(
            "UPDATE `{$leads}` SET status = 'converted', converted_owner_id = ?, converted_patient_id = ? WHERE id = ?",
            [(int)$ownerId, $patientId, $id]
        );

        $this->json([
            'lead_id'    => $id,
            'owner_id'   => (int)$ownerId,
            'patient_id' => $patientId,
        ]);
    }

    /* ══════════════════════════════════════════════════════
       TRAININGSPLÄNE / ÜBUNGEN
    ══════════════════════════════════════════════════════ */

    public function dogschoolTrainingPlansList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $search = trim((string)($_GET['search'] ?? ''));
        $plans = $this->t('dogschool_training_plans');
        $planEx = $this->t('dogschool_plan_exercises');

        $where = [];
        $args = [];
        if ($search !== '') {
            $where[] = '(name LIKE ? OR target_audience LIKE ?)';
            $args[] = "%{$search}%"; $args[] = "%{$search}%";
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $items = [];
        try {
            $items = $this->db->fetchAll(
                "SELECT tp.*,
                        (SELECT COUNT(*) FROM `{$planEx}` pe WHERE pe.plan_id = tp.id) AS exercise_count
                 FROM `{$plans}` tp
                 {$whereSql}
                 ORDER BY tp.is_active DESC, tp.name ASC
                 LIMIT 200",
                $args
            );
        } catch (\Throwable $e) {
            $this->error('Trainingspläne konnten nicht geladen werden: ' . $e->getMessage(), 500);
        }
        $this->json(['items' => $items]);
    }

    public function dogschoolTrainingPlanShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $plans = $this->t('dogschool_training_plans');
        $planEx = $this->t('dogschool_plan_exercises');
        $exercises = $this->t('dogschool_exercises');

        $plan = $this->db->fetch("SELECT * FROM `{$plans}` WHERE id = ? LIMIT 1", [$id]);
        if (!$plan) {
            $this->error('Trainingsplan nicht gefunden.', 404);
        }

        $plan['exercises'] = $this->db->fetchAll(
            "SELECT pe.*, ex.name AS exercise_name, ex.category, ex.difficulty, ex.duration_minutes AS exercise_duration
             FROM `{$planEx}` pe
             LEFT JOIN `{$exercises}` ex ON ex.id = pe.exercise_id
             WHERE pe.plan_id = ?
             ORDER BY pe.week_number ASC, pe.session_number ASC, pe.sort_order ASC",
            [$id]
        );

        $this->json($plan);
    }

    public function dogschoolExercisesList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $category = trim((string)($_GET['category'] ?? ''));
        $search = trim((string)($_GET['search'] ?? ''));
        $exercises = $this->t('dogschool_exercises');

        $where = ['is_active = 1'];
        $args = [];
        if ($category !== '') { $where[] = 'category = ?'; $args[] = $category; }
        if ($search !== '') { $where[] = '(name LIKE ? OR description LIKE ?)'; $args[] = "%{$search}%"; $args[] = "%{$search}%"; }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $items = [];
        try {
            $items = $this->db->fetchAll(
                "SELECT * FROM `{$exercises}` {$whereSql} ORDER BY category ASC, name ASC LIMIT 300",
                $args
            );
        } catch (\Throwable $e) {
            $this->error('Übungen konnten nicht geladen werden: ' . $e->getMessage(), 500);
        }
        $this->json(['items' => $items]);
    }

    public function dogschoolTrainingPlanCreate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data = $this->body();
        if (trim((string)($data['name'] ?? '')) === '') {
            $this->error("Feld 'name' ist erforderlich.");
        }

        $plans = $this->t('dogschool_training_plans');
        $id = $this->db->insert(
            "INSERT INTO `{$plans}`
                (name, description, target_audience, duration_weeks, sessions_per_week, difficulty, is_template, is_active)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                trim((string)$data['name']),
                $this->nullableStr($data['description'] ?? null),
                $this->nullableStr($data['target_audience'] ?? null),
                (int)($data['duration_weeks'] ?? 8),
                (int)($data['sessions_per_week'] ?? 1),
                trim((string)($data['difficulty'] ?? 'easy')),
                isset($data['is_template']) ? (int)(bool)$data['is_template'] : 1,
                isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            ]
        );
        $this->json($this->db->fetch("SELECT * FROM `{$plans}` WHERE id = ?", [(int)$id]), 201);
    }

    public function dogschoolPlanAssign(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();

        $planId = (int)($params['id'] ?? 0);
        $data = $this->body();
        $patientId = (int)($data['patient_id'] ?? 0);
        if ($planId <= 0 || $patientId <= 0) {
            $this->error("Feld 'patient_id' ist erforderlich.");
        }

        $plans = $this->t('dogschool_training_plans');
        $assignments = $this->t('dogschool_plan_assignments');
        $plan = $this->db->fetch("SELECT * FROM `{$plans}` WHERE id = ? LIMIT 1", [$planId]);
        if (!$plan) {
            $this->error('Trainingsplan nicht gefunden.', 404);
        }

        $startDate = trim((string)($data['start_date'] ?? '')) ?: date('Y-m-d');
        $weeks = (int)($plan['duration_weeks'] ?? 8);
        $targetEnd = date('Y-m-d', strtotime("{$startDate} +{$weeks} weeks"));

        $id = $this->db->insert(
            "INSERT INTO `{$assignments}`
                (plan_id, patient_id, owner_id, course_id, trainer_user_id, start_date, target_end_date, status, notes)
             VALUES (?,?,?,?,?,?,?, 'active', ?)",
            [
                $planId,
                $patientId,
                $this->nullableInt($data['owner_id'] ?? null),
                $this->nullableInt($data['course_id'] ?? null),
                (int)($user['user_id'] ?? 0) ?: null,
                $startDate,
                $targetEnd,
                $this->nullableStr($data['notes'] ?? null),
            ]
        );
        $this->json($this->db->fetch("SELECT * FROM `{$assignments}` WHERE id = ?", [(int)$id]), 201);
    }

    /* ══════════════════════════════════════════════════════
       HELPER
    ══════════════════════════════════════════════════════ */

    private function nullableStr(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }

    private function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        return (int)$v;
    }
}
