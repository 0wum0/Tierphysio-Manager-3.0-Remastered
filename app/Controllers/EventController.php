<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * EventController
 *
 * Events sind technisch Kurse mit bestimmten `type`-Werten (event, workshop,
 * seminar, social_walk). Damit ist keine eigene Tabelle nötig — wir nutzen
 * die `dogschool_courses`-Infrastruktur und filtern.
 */
class EventController extends Controller
{
    /* Kurs-Typen, die als "Event" gelten */
    private const EVENT_TYPES = ['event', 'workshop', 'seminar', 'social_walk'];

    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        private readonly Database $db,
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_events');

        $types     = self::EVENT_TYPES;
        $in        = implode(',', array_fill(0, count($types), '?'));
        $filter    = (string)$this->get('filter', 'upcoming'); /* upcoming | past | all */

        $dateCondition = match($filter) {
            'past'     => 'AND c.start_date < CURDATE()',
            'all'      => '',
            'upcoming' => 'AND (c.start_date IS NULL OR c.start_date >= CURDATE())',
            default    => 'AND (c.start_date IS NULL OR c.start_date >= CURDATE())',
        };

        $events = $this->db->safeFetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM `{$this->db->prefix('dogschool_enrollments')}` e
                      WHERE e.course_id = c.id AND e.status = 'active') AS enrolled_count
               FROM `{$this->db->prefix('dogschool_courses')}` c
              WHERE c.type IN ({$in})
                {$dateCondition}
              ORDER BY c.start_date ASC, c.start_time ASC",
            $types
        );

        $this->render('dogschool/events/index.twig', [
            'page_title' => 'Events & Workshops',
            'active_nav' => 'events',
            'events'     => $events,
            'filter'     => $filter,
            'type_labels' => [
                'event'       => 'Event',
                'workshop'    => 'Workshop',
                'seminar'     => 'Seminar',
                'social_walk' => 'Social Walk',
            ],
        ]);
    }
}
