<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\CourseRepository;
use App\Repositories\OwnerRepository;
use App\Repositories\PatientRepository;

/**
 * CourseController — Hundeschul-Kurs-Verwaltung
 *
 * Abgesichert über FeatureRouteMap → FeatureMiddleware auf `dogschool_courses`,
 * `dogschool_waitlist`, `dogschool_group_training`. Zusätzliche Defense-in-Depth
 * per requireFeature() im Controller.
 *
 * Multi-Tenant: alle Queries über CourseRepository (tenant-prefixed).
 */
class CourseController extends Controller
{
    private CourseRepository $courses;
    private PatientRepository $patients;
    private OwnerRepository $owners;

    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        CourseRepository $courses,
        PatientRepository $patients,
        OwnerRepository $owners,
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->courses  = $courses;
        $this->patients = $patients;
        $this->owners   = $owners;
    }

    /* ═════════════════════════ Liste ═════════════════════════ */

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');

        $page    = max(1, (int)$this->get('page', 1));
        $perPage = 20;
        $status  = (string)$this->get('status', '');
        $search  = trim((string)$this->get('q', ''));

        $pagination = $this->courses->listPaginated($page, $perPage, $status, $search);

        $this->render('dogschool/courses/index.twig', [
            'page_title'   => 'Kurse',
            'active_nav'   => 'courses',
            'pagination'   => $pagination,
            'filter_status'=> $status,
            'filter_q'     => $search,
            'status_list'  => [
                ''          => 'Alle',
                'active'    => 'Aktiv',
                'full'      => 'Voll',
                'draft'     => 'Entwurf',
                'paused'    => 'Pausiert',
                'completed' => 'Abgeschlossen',
                'cancelled' => 'Abgesagt',
            ],
            'type_list'    => $this->courseTypes(),
        ]);
    }

    /* ═════════════════════════ Detail ═════════════════════════ */

    public function show(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');

        $id     = (int)($params['id'] ?? 0);
        $course = $this->courses->findWithStats($id);
        if (!$course) {
            $this->flash('error', 'Kurs nicht gefunden.');
            $this->redirect('/kurse');
            return;
        }

        $sessions    = $this->courses->sessionsForCourse($id);
        $enrollments = $this->courses->enrollmentsForCourse($id);
        $waitlist    = $this->courses->waitlistForCourse($id);

        $this->render('dogschool/courses/show.twig', [
            'page_title'   => $course['name'] ?? 'Kurs',
            'active_nav'   => 'courses',
            'course'       => $course,
            'sessions'     => $sessions,
            'enrollments'  => $enrollments,
            'waitlist'     => $waitlist,
            'type_list'    => $this->courseTypes(),
            'free_spots'   => max(0, (int)$course['max_participants'] - (int)$course['enrolled_count']),
        ]);
    }

    /* ═════════════════════════ Formular ═════════════════════════ */

    public function create(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');

        $this->render('dogschool/courses/form.twig', [
            'page_title' => 'Neuer Kurs',
            'active_nav' => 'courses',
            'course'     => [
                'id'               => 0,
                'name'             => '',
                'type'             => 'group',
                'description'      => '',
                'location'         => '',
                'start_date'       => '',
                'weekday'          => '',
                'start_time'       => '18:00',
                'duration_minutes' => 60,
                'max_participants' => 8,
                'price_cents'      => 0,
                'num_sessions'     => 8,
                'status'           => 'draft',
                'notes'            => '',
            ],
            'type_list' => $this->courseTypes(),
            'is_new'    => true,
        ]);
    }

    public function edit(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');

        $id = (int)($params['id'] ?? 0);
        $c  = $this->courses->findById($id);
        if (!$c) {
            $this->flash('error', 'Kurs nicht gefunden.');
            $this->redirect('/kurse');
            return;
        }

        $this->render('dogschool/courses/form.twig', [
            'page_title' => 'Kurs bearbeiten',
            'active_nav' => 'courses',
            'course'     => $c,
            'type_list'  => $this->courseTypes(),
            'is_new'     => false,
        ]);
    }

    public function store(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');
        $this->validateCsrf();

        $data = $this->collectCourseData();
        if ($data['name'] === '') {
            $this->flash('error', 'Kursname darf nicht leer sein.');
            $this->redirect('/kurse/neu');
            return;
        }
        $id = (int)$this->courses->create($data);

        /* Sessions automatisch generieren wenn möglich */
        if (!empty($data['start_date']) && (int)$data['num_sessions'] > 0) {
            $this->courses->generateSessions($id);
        }

        $this->flash('success', 'Kurs angelegt.');
        $this->redirect("/kurse/{$id}");
    }

    public function update(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');
        $this->validateCsrf();

        $id = (int)($params['id'] ?? 0);
        $c  = $this->courses->findById($id);
        if (!$c) {
            $this->flash('error', 'Kurs nicht gefunden.');
            $this->redirect('/kurse');
            return;
        }

        $data = $this->collectCourseData();
        $this->courses->update($id, $data);
        $this->courses->recalculateStatus($id);

        $this->flash('success', 'Kurs aktualisiert.');
        $this->redirect("/kurse/{$id}");
    }

    public function delete(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');
        $this->validateCsrf();

        $id = (int)($params['id'] ?? 0);
        $this->courses->delete($id);
        $this->flash('success', 'Kurs gelöscht.');
        $this->redirect('/kurse');
    }

    /* ═════════════════════════ Enrollments ═════════════════════════ */

    public function enroll(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');
        $this->validateCsrf();

        $courseId = (int)($params['id'] ?? 0);
        $course   = $this->courses->findWithStats($courseId);
        if (!$course) {
            $this->flash('error', 'Kurs nicht gefunden.');
            $this->redirect('/kurse');
            return;
        }

        $patientId = (int)$this->post('patient_id', 0);
        $ownerId   = (int)$this->post('owner_id', 0);
        $notes     = trim((string)$this->post('notes', ''));

        /* Owner aus Patient ableiten, falls nicht explizit übergeben */
        if ($ownerId === 0 && $patientId > 0) {
            $p = $this->patients->findById($patientId);
            if ($p && !empty($p['owner_id'])) {
                $ownerId = (int)$p['owner_id'];
            }
        }

        if ($patientId === 0 || $ownerId === 0) {
            $this->flash('error', 'Hund oder Halter fehlt.');
            $this->redirect("/kurse/{$courseId}");
            return;
        }

        /* Kurs voll → automatisch auf Warteliste */
        if ((int)$course['enrolled_count'] >= (int)$course['max_participants']) {
            $this->courses->addToWaitlist($courseId, [
                'patient_id' => $patientId,
                'owner_id'   => $ownerId,
                'notes'      => $notes,
            ]);
            $this->flash('warning', 'Kurs ist voll — auf Warteliste gesetzt.');
            $this->redirect("/kurse/{$courseId}");
            return;
        }

        $extra = $notes !== '' ? ['notes' => $notes] : [];
        $ok = $this->courses->enroll($courseId, $patientId, $ownerId, $extra);
        if ($ok === false) {
            $this->flash('warning', 'Hund ist bereits für diesen Kurs eingeschrieben.');
        } else {
            $this->flash('success', 'Hund eingeschrieben.');
        }
        $this->redirect("/kurse/{$courseId}");
    }

    public function unenroll(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');
        $this->validateCsrf();

        $courseId     = (int)($params['id'] ?? 0);
        $enrollmentId = (int)($params['enrollment_id'] ?? 0);
        $this->courses->deleteEnrollment($enrollmentId);

        $this->flash('success', 'Einschreibung entfernt.');
        $this->redirect("/kurse/{$courseId}");
    }

    public function setEnrollmentStatus(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');
        $this->validateCsrf();

        $courseId     = (int)($params['id'] ?? 0);
        $enrollmentId = (int)($params['enrollment_id'] ?? 0);
        $status       = (string)$this->post('status', 'active');

        $this->courses->updateEnrollmentStatus($enrollmentId, $status);
        $this->flash('success', 'Teilnehmer-Status aktualisiert.');
        $this->redirect("/kurse/{$courseId}");
    }

    /* ═════════════════════════ Waitlist ═════════════════════════ */

    public function waitlistAdd(array $params = []): void
    {
        $this->requireFeature('dogschool_waitlist');
        $this->validateCsrf();

        $courseId = (int)($params['id'] ?? 0);
        $this->courses->addToWaitlist($courseId, [
            'patient_id' => ((int)$this->post('patient_id', 0)) ?: null,
            'owner_id'   => ((int)$this->post('owner_id', 0)) ?: null,
            'lead_name'  => trim((string)$this->post('lead_name', '')) ?: null,
            'lead_email' => trim((string)$this->post('lead_email', '')) ?: null,
            'lead_phone' => trim((string)$this->post('lead_phone', '')) ?: null,
            'notes'      => trim((string)$this->post('notes', '')) ?: null,
        ]);
        $this->flash('success', 'Auf Warteliste gesetzt.');
        $this->redirect("/kurse/{$courseId}");
    }

    public function waitlistRemove(array $params = []): void
    {
        $this->requireFeature('dogschool_waitlist');
        $this->validateCsrf();

        $courseId = (int)($params['id'] ?? 0);
        $waitId   = (int)($params['wait_id'] ?? 0);
        $this->courses->removeFromWaitlist($waitId);

        $this->flash('success', 'Von Warteliste entfernt.');
        $this->redirect("/kurse/{$courseId}");
    }

    /* ═════════════════════════ Gesamtübersicht Warteliste ═════════════════════════ */

    public function waitlistIndex(array $params = []): void
    {
        $this->requireFeature('dogschool_waitlist');

        /* Alle wartenden Einträge aller Kurse aggregiert */
        $pagination = $this->courses->listPaginated(1, 100, '', '');
        $entries    = [];
        foreach ($pagination['items'] as $c) {
            foreach ($this->courses->waitlistForCourse((int)$c['id']) as $w) {
                $w['course_name'] = $c['name'];
                $w['course_id']   = $c['id'];
                $entries[] = $w;
            }
        }

        $this->render('dogschool/waitlist/index.twig', [
            'page_title' => 'Warteliste',
            'active_nav' => 'waitlist',
            'entries'    => $entries,
        ]);
    }

    /* ═════════════════════════ Helpers ═════════════════════════ */

    private function collectCourseData(): array
    {
        $weekday = $this->post('weekday', '');
        return [
            'name'             => trim((string)$this->post('name', '')),
            'type'             => (string)$this->post('type', 'group'),
            'description'      => trim((string)$this->post('description', '')),
            'level'            => trim((string)$this->post('level', '')) ?: null,
            'trainer_user_id'  => ((int)$this->post('trainer_user_id', 0)) ?: null,
            'location'         => trim((string)$this->post('location', '')),
            'start_date'       => (string)$this->post('start_date', '') ?: null,
            'end_date'         => (string)$this->post('end_date', '')   ?: null,
            'weekday'          => ($weekday === '' || $weekday === null) ? null : (int)$weekday,
            'start_time'       => (string)$this->post('start_time', '') ?: null,
            'duration_minutes' => max(1, (int)$this->post('duration_minutes', 60)),
            'max_participants' => max(1, (int)$this->post('max_participants', 8)),
            'price_cents'      => max(0, (int)round(((float)$this->post('price_eur', 0)) * 100)),
            'num_sessions'     => max(1, (int)$this->post('num_sessions', 1)),
            'status'           => (string)$this->post('status', 'draft'),
            'notes'            => trim((string)$this->post('notes', '')) ?: null,
        ];
    }

    /**
     * Kurstypen-Katalog. Wird später durch konfigurierbare Leistungen
     * (Phase 5/6) ergänzt/ersetzt.
     */
    private function courseTypes(): array
    {
        return [
            'group'              => 'Gruppentraining',
            'welpen'             => 'Welpenkurs',
            'junghunde'          => 'Junghundekurs',
            'alltag'             => 'Alltagstraining',
            'rueckruf'           => 'Rückruftraining',
            'leinenfuehrigkeit'  => 'Leinenführigkeit',
            'begegnung'          => 'Begegnungstraining',
            'social_walk'        => 'Social Walk',
            'problem'            => 'Problemhundetraining',
            'agility'            => 'Agility / Fun-Sport',
            'beschaeftigung'     => 'Beschäftigung / Tricks',
            'workshop'           => 'Workshop',
            'seminar'            => 'Seminar / Vortrag',
            'event'              => 'Event',
        ];
    }
}
