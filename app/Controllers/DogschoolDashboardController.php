<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\CourseRepository;
use App\Services\DogschoolSeedService;

/**
 * Hundeschul-Startseite mit Tagesübersicht.
 *
 * Zeigt:
 *   - Heutige Kurstermine
 *   - Nächste 7 Tage
 *   - Offene (unmarkierte) Sessions
 *   - Freie Plätze Gesamt
 *   - Warteliste Gesamt
 *   - Aktive Kurse
 */
class DogschoolDashboardController extends Controller
{
    private CourseRepository $courses;
    private DogschoolSeedService $seed;

    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        CourseRepository $courses,
        DogschoolSeedService $seed,
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->courses = $courses;
        $this->seed    = $seed;
    }

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_dashboard');

        /* Bei erstem Besuch des Hundeschul-Dashboards Standard-Inhalte
         * einspielen (Kursarten, Übungen-Katalog, Consent-Vorlagen,
         * Trainingsplan-Vorlagen). Idempotent — kostet nach erstem Run 0ms. */
        $this->seed->seed();

        $today    = date('Y-m-d');
        $nextWeek = date('Y-m-d', strtotime('+7 days'));

        $stats = [
            'active_courses'    => $this->courses->countByStatus('active'),
            'free_spots_total'  => $this->courses->countFreeSpotsTotal(),
            'waitlist_total'    => $this->courses->countWaitlistTotal(),
            'full_courses'      => $this->courses->countByStatus('full'),
        ];

        $todaySessions   = $this->courses->sessionsBetween($today, $today);
        $upcomingSessions = $this->courses->sessionsBetween($today, $nextWeek);
        $openSessions    = $this->courses->openSessions(10);

        $this->render('dogschool/dashboard/index.twig', [
            'page_title'        => 'Hundeschul-Übersicht',
            'active_nav'        => 'dashboard',
            'stats'             => $stats,
            'today_sessions'    => $todaySessions,
            'upcoming_sessions' => $upcomingSessions,
            'open_sessions'     => $openSessions,
        ]);
    }
}
