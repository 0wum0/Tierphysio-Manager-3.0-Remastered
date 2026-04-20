<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\CourseRepository;

/**
 * AttendanceController
 *
 * Öffnet die Anwesenheits-Matrix einer Session und persistiert Markierungen.
 * Die Liste der offenen Sessions dient als Einstieg für tägliches Doku-Häkeln.
 */
class AttendanceController extends Controller
{
    private CourseRepository $courses;

    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        CourseRepository $courses,
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->courses = $courses;
    }

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_attendance');

        $today          = date('Y-m-d');
        $nextWeek       = date('Y-m-d', strtotime('+7 days'));
        $openSessions   = $this->courses->openSessions(50);
        $upcoming       = $this->courses->sessionsBetween($today, $nextWeek);

        $this->render('dogschool/attendance/index.twig', [
            'page_title'    => 'Anwesenheit',
            'active_nav'    => 'attendance',
            'open_sessions' => $openSessions,
            'upcoming'      => $upcoming,
        ]);
    }

    public function sessionMatrix(array $params = []): void
    {
        $this->requireFeature('dogschool_attendance');

        $sessionId = (int)($params['session_id'] ?? 0);
        $session   = $this->courses->findSession($sessionId);
        if (!$session) {
            $this->flash('error', 'Termin nicht gefunden.');
            $this->redirect('/anwesenheit');
            return;
        }

        $rows = $this->courses->attendanceForSession($sessionId);

        $this->render('dogschool/attendance/session.twig', [
            'page_title' => 'Anwesenheit: ' . ($session['course_name'] ?? ''),
            'active_nav' => 'attendance',
            'session'    => $session,
            'rows'       => $rows,
            'statuses'   => [
                'present'    => 'Anwesend',
                'absent'     => 'Abwesend',
                'excused'    => 'Entschuldigt',
                'late'       => 'Verspätet',
                'left_early' => 'Früher gegangen',
                'no_show'    => 'Nicht erschienen',
            ],
        ]);
    }

    public function saveMatrix(array $params = []): void
    {
        $this->requireFeature('dogschool_attendance');
        $this->validateCsrf();

        $sessionId = (int)($params['session_id'] ?? 0);
        $session   = $this->courses->findSession($sessionId);
        if (!$session) {
            $this->flash('error', 'Termin nicht gefunden.');
            $this->redirect('/anwesenheit');
            return;
        }

        $attendances = $_POST['attendance'] ?? [];
        $notesMap    = $_POST['notes']      ?? [];
        $userId      = (int)($this->session->getUser()['id'] ?? 0) ?: null;

        if (is_array($attendances)) {
            foreach ($attendances as $enrollmentId => $status) {
                $eid    = (int)$enrollmentId;
                $stat   = (string)$status;
                $note   = (string)($notesMap[$eid] ?? '') ?: null;
                if ($eid > 0 && $stat !== '') {
                    $this->courses->saveAttendance($sessionId, $eid, $stat, $note, $userId);
                }
            }
        }

        /* Session als "gehalten" markieren, wenn sie heute/vergangen ist */
        if (($session['status'] ?? '') === 'planned'
            && ($session['session_date'] ?? '9999-12-31') <= date('Y-m-d')) {
            $this->courses->updateSession($sessionId, ['status' => 'held']);
        }

        $this->flash('success', 'Anwesenheit gespeichert.');
        $this->redirect('/anwesenheit/session/' . $sessionId);
    }
}
