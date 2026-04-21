<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Repositories\CourseRepository;
use App\Services\DogschoolInvoiceService;
use App\Services\DogschoolSeedService;
use App\Services\FeatureGateService;

/**
 * Hundeschul-Startseite mit Tagesübersicht und Finanz-/Lead-KPIs.
 *
 * Zeigt:
 *   - Heutige Kurstermine, Nächste 7 Tage, Offene (unmarkierte) Sessions
 *   - Kurs-KPIs:     aktive Kurse, freie Plätze, volle Kurse, Warteliste
 *   - Rechnungs-KPIs: offen, überfällig, bezahlt Monat, bezahlt Jahr
 *     (gegated auf `dogschool_invoicing`)
 *   - Anfragen-/Lead-KPIs: neue Buchungsanfragen, aktive Leads
 *     (gegated auf `dogschool_online_booking` / `dogschool_leads`)
 *
 * Die KPI-Karten sind im Template klickbar und führen direkt auf den
 * jeweiligen Listen-Screen mit passendem Filter — keine Sackgassen.
 */
class DogschoolDashboardController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        private readonly CourseRepository $courses,
        private readonly DogschoolSeedService $seed,
        private readonly DogschoolInvoiceService $dsInvoices,
        private readonly FeatureGateService $featureGate,
        private readonly Database $db,
    ) {
        parent::__construct($view, $session, $config, $translator);
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

        $todaySessions    = $this->courses->sessionsBetween($today, $today);
        $upcomingSessions = $this->courses->sessionsBetween($today, $nextWeek);
        $openSessions     = $this->courses->openSessions(10);

        /* ── Finanz-KPIs (Rechnungen) ──
         * Nur laden wenn dogschool_invoicing-Feature aktiv. Self-healing:
         * getStats() liefert alle-0-Array bei jedem Fehler — kein Blocker. */
        $invoiceStats = null;
        if ($this->featureGate->isEnabled('dogschool_invoicing')) {
            $invoiceStats = $this->dsInvoices->getStats();
        }

        /* ── Anfragen / Leads ──
         * Counts per safeFetchColumn, fallback 0 wenn Tabelle noch nicht
         * migriert ist (erste Installation ohne 050-Migration). */
        $newBookingRequests = 0;
        if ($this->featureGate->isEnabled('dogschool_online_booking')) {
            $newBookingRequests = (int)$this->db->safeFetchColumn(
                "SELECT COUNT(*) FROM `{$this->db->prefix('dogschool_booking_requests')}`
                  WHERE status = 'pending'"
            );
        }

        $activeLeads = 0;
        if ($this->featureGate->isEnabled('dogschool_leads')) {
            $activeLeads = (int)$this->db->safeFetchColumn(
                "SELECT COUNT(*) FROM `{$this->db->prefix('dogschool_leads')}`
                  WHERE status IN ('new', 'contacted')"
            );
        }

        $this->render('dogschool/dashboard/index.twig', [
            'page_title'          => 'Hundeschul-Übersicht',
            'active_nav'          => 'dashboard',
            'stats'               => $stats,
            'today_sessions'      => $todaySessions,
            'upcoming_sessions'   => $upcomingSessions,
            'open_sessions'       => $openSessions,
            'invoice_stats'       => $invoiceStats,
            'new_booking_requests'=> $newBookingRequests,
            'active_leads'        => $activeLeads,
        ]);
    }
}
