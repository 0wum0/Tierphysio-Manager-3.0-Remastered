<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * Hundeschul-Reports.
 *
 * Aggregierte Auswertungen über Kurse, Teilnehmer, Anwesenheit und
 * Paket-Verkäufe. Rein lesend — nutzt nur SELECT-Queries.
 */
class DogschoolReportController extends Controller
{
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
        $this->requireFeature('dogschool_reports');

        $prefix = fn(string $t) => $this->db->prefix($t);

        /* 1) Kurs-Auslastung */
        $utilization = $this->db->safeFetchAll(
            "SELECT c.id, c.name, c.status, c.max_participants,
                    (SELECT COUNT(*) FROM `{$prefix('dogschool_enrollments')}` e
                      WHERE e.course_id = c.id AND e.status = 'active') AS enrolled,
                    ROUND(100 * (SELECT COUNT(*) FROM `{$prefix('dogschool_enrollments')}` e
                                  WHERE e.course_id = c.id AND e.status = 'active')
                               / NULLIF(c.max_participants, 0), 1) AS pct
               FROM `{$prefix('dogschool_courses')}` c
              WHERE c.status IN ('active','full','completed')
              ORDER BY pct DESC NULLS LAST"
        );

        /* 2) Anwesenheits-Quoten je Kurs */
        $attendance = $this->db->safeFetchAll(
            "SELECT c.id, c.name,
                    SUM(a.status = 'present')  AS present,
                    SUM(a.status = 'absent')   AS absent,
                    SUM(a.status = 'excused')  AS excused,
                    SUM(a.status = 'late')     AS late,
                    COUNT(a.id) AS total,
                    ROUND(100 * SUM(a.status = 'present') / NULLIF(COUNT(a.id), 0), 1) AS pct_present
               FROM `{$prefix('dogschool_courses')}` c
               JOIN `{$prefix('dogschool_course_sessions')}` s ON s.course_id = c.id
               JOIN `{$prefix('dogschool_attendance')}` a     ON a.session_id = s.id
              GROUP BY c.id, c.name
              ORDER BY pct_present DESC"
        );

        /* 3) Umsatz aus Kurs-Preisen (active enrollments) */
        $revenueCourses = (int)$this->db->safeFetchColumn(
            "SELECT COALESCE(SUM(COALESCE(e.price_cents, c.price_cents)), 0)
               FROM `{$prefix('dogschool_enrollments')}` e
               JOIN `{$prefix('dogschool_courses')}` c ON c.id = e.course_id
              WHERE e.status = 'active'"
        );

        /* 4) Paket-Verkäufe Gesamt */
        $revenuePackages = (int)$this->db->safeFetchColumn(
            "SELECT COALESCE(SUM(p.price_cents), 0)
               FROM `{$prefix('dogschool_package_balances')}` b
               JOIN `{$prefix('dogschool_packages')}` p ON p.id = b.package_id
              WHERE b.status IN ('active','used_up')"
        );

        /* 5) Lead-Konversionsrate */
        $leadTotal = (int)$this->db->safeFetchColumn(
            "SELECT COUNT(*) FROM `{$prefix('dogschool_leads')}`"
        );
        $leadConverted = (int)$this->db->safeFetchColumn(
            "SELECT COUNT(*) FROM `{$prefix('dogschool_leads')}` WHERE status = 'converted'"
        );
        $conversionPct = $leadTotal > 0 ? round($leadConverted * 100 / $leadTotal, 1) : 0;

        $this->render('dogschool/reports/index.twig', [
            'page_title'       => 'Auswertungen',
            'active_nav'       => 'reports',
            'utilization'      => $utilization,
            'attendance'       => $attendance,
            'revenue_courses'  => $revenueCourses,
            'revenue_packages' => $revenuePackages,
            'lead_total'       => $leadTotal,
            'lead_converted'   => $leadConverted,
            'conversion_pct'   => $conversionPct,
        ]);
    }
}
