<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Core\Application;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Repositories\CourseRepository;
use App\Repositories\PackageRepository;
use App\Repositories\SettingsRepository;
use App\Services\DogschoolInvoiceService;

/**
 * OwnerPortalBookingController
 *
 * Erweiterung des Kundenportals für Hundeschul-Tenants:
 * Kunden können sich Kurse ansehen, sich einschreiben und Pakete kaufen —
 * alles direkt aus dem Portal heraus, ohne den Admin kontaktieren zu müssen.
 *
 * Architektur:
 *   - Trainer-only Sichtbarkeit via isTrainerTenant() — bei Praxis-Tenants
 *     liefern alle Endpoints HTTP 404 (Feature existiert dort nicht)
 *   - Nutzt bestehende CourseRepository / PackageRepository / DogschoolInvoiceService
 *     für Persistenz — keine Code-Duplikation
 *   - Enroll + Kauf legen automatisch Rechnung an (Status `open`) via
 *     DogschoolInvoiceService. Bezahlung über normale Rechnungs-Flow
 *     (Überweisung / Bar beim nächsten Termin).
 *
 * Routen (in ServiceProvider::registerRoutes):
 *   GET  /portal/kurse                        → coursesIndex
 *   GET  /portal/kurse/{id}                   → courseDetail
 *   POST /portal/kurse/{id}/einschreiben      → courseEnroll
 *   GET  /portal/pakete                       → packagesIndex
 *   POST /portal/pakete/{id}/kaufen           → packagePurchase
 */
class OwnerPortalBookingController extends Controller
{
    private OwnerPortalRepository $repo;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly Database $db,
        private readonly CourseRepository $courses,
        private readonly PackageRepository $packages,
        private readonly SettingsRepository $settingsRepository,
        private readonly DogschoolInvoiceService $dogschoolInvoices,
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo = new OwnerPortalRepository($db);
    }

    /* ═══════════════════ Auth + Guards ═══════════════════ */

    /**
     * Gibt den eingeloggten Portal-User zurück oder leitet auf Login um.
     * @return array<string,mixed>
     */
    private function requireOwnerAuth(): array
    {
        $userId = $this->session->get('owner_portal_user_id');
        if (!$userId) {
            $this->redirect('/portal/login');
            exit;
        }
        $user = $this->repo->findUserById((int)$userId);
        if (!$user || !$user['is_active']) {
            $this->session->remove('owner_portal_user_id');
            $this->session->remove('owner_portal_owner_id');
            $this->redirect('/portal/login');
            exit;
        }
        return $user;
    }

    /**
     * Prüft ob der aktuelle Tenant eine Hundeschule ist. Nur dann macht
     * Kurs-/Paket-Buchung im Portal Sinn — Praxen nutzen stattdessen das
     * klassische Termin-System.
     */
    private function requireTrainerTenant(): void
    {
        $type = strtolower((string)$this->settingsRepository->get('practice_type', 'therapeut'));
        if ($type !== 'trainer') {
            $this->abort(404);
            exit;
        }
    }

    /**
     * Gemeinsame Twig-Variablen für alle Portal-Seiten dieses Controllers.
     * Gleiches Schema wie OwnerPortalController — duplizieren hier, um nicht
     * per public-API-Methode auf den anderen Controller zugreifen zu müssen.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function portalBase(array $user): array
    {
        $ownerId = (int)$user['owner_id'];
        $unread  = 0;
        try {
            $msgRepo = new MessagingRepository($this->db);
            $unread  = $msgRepo->countUnreadForOwner($ownerId);
        } catch (\Throwable) {}

        return [
            'portal_user'         => $user,
            'portal_unread_count' => $unread,
            'csrf_token'          => $this->session->generateCsrfToken(),
            'show_homework_nav'   => ($this->settingsRepository->get('portal_show_homework', '1') === '1'),
            'is_trainer_tenant'   => true,
        ];
    }

    /* ═══════════════════ Kurse ═══════════════════ */

    /** GET /portal/kurse — Katalog aller buchbaren Kurse. */
    public function coursesIndex(array $params = []): void
    {
        $this->requireTrainerTenant();
        $user = $this->requireOwnerAuth();

        /* Öffentliche Katalog-Sicht: nur aktive Kurse, die mindestens einen
         * freien Platz haben ODER noch nicht gestartet sind. Volle Kurse
         * werden angezeigt aber mit "Warteliste"-Hinweis markiert. */
        $courses = $this->db->safeFetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM `{$this->db->prefix('dogschool_enrollments')}` e
                      WHERE e.course_id = c.id AND e.status = 'active') AS enrolled_count
               FROM `{$this->db->prefix('dogschool_courses')}` c
              WHERE c.status IN ('active', 'full')
                AND (c.end_date IS NULL OR c.end_date >= CURDATE())
              ORDER BY c.start_date ASC, c.name ASC"
        );

        /* Schon eingeschrieben? Für UX-Marker. */
        $enrolledCourseIds = array_map(
            fn($r) => (int)$r['course_id'],
            $this->db->safeFetchAll(
                "SELECT DISTINCT course_id FROM `{$this->db->prefix('dogschool_enrollments')}`
                  WHERE owner_id = ? AND status = 'active'",
                [(int)$user['owner_id']]
            )
        );

        $this->render('@owner-portal/owner_courses.twig', array_merge(
            $this->portalBase($user),
            [
                'page_title'          => 'Kurse',
                'active_nav'          => 'kurse',
                'courses'             => $courses,
                'enrolled_course_ids' => $enrolledCourseIds,
            ]
        ));
    }

    /** GET /portal/kurse/{id} — Kurs-Detail mit Einschreibe-Formular. */
    public function courseDetail(array $params = []): void
    {
        $this->requireTrainerTenant();
        $user = $this->requireOwnerAuth();

        $courseId = (int)($params['id'] ?? 0);
        $course   = $this->courses->findWithStats($courseId);
        if (!$course) {
            $this->abort(404);
            return;
        }

        /* Verfügbare Hunde des Halters (für das Auswahl-Dropdown beim Einschreiben). */
        $pets = $this->repo->getPetsByOwnerId((int)$user['owner_id']);

        /* Schon für diesen Kurs eingeschrieben (pro Patient)? */
        $existing = $this->db->safeFetchAll(
            "SELECT patient_id FROM `{$this->db->prefix('dogschool_enrollments')}`
              WHERE course_id = ? AND owner_id = ? AND status = 'active'",
            [$courseId, (int)$user['owner_id']]
        );
        $enrolledPetIds = array_map(fn($r) => (int)$r['patient_id'], $existing);

        /* Sessions des Kurses für die Detail-Anzeige. */
        $sessions = $this->courses->sessionsForCourse($courseId);

        $this->render('@owner-portal/owner_course_detail.twig', array_merge(
            $this->portalBase($user),
            [
                'page_title'        => $course['name'] ?? 'Kurs',
                'active_nav'        => 'kurse',
                'course'            => $course,
                'sessions'          => $sessions,
                'pets'              => $pets,
                'enrolled_pet_ids'  => $enrolledPetIds,
                'free_spots'        => max(0, (int)$course['max_participants'] - (int)$course['enrolled_count']),
            ]
        ));
    }

    /** POST /portal/kurse/{id}/einschreiben — einen Hund in den Kurs einschreiben. */
    public function courseEnroll(array $params = []): void
    {
        $this->requireTrainerTenant();
        $user = $this->requireOwnerAuth();
        $this->validateCsrf();

        $courseId  = (int)($params['id'] ?? 0);
        $patientId = (int)$this->post('patient_id', 0);

        if ($courseId <= 0 || $patientId <= 0) {
            $this->session->flash('error', 'Bitte wähle einen Hund für die Einschreibung aus.');
            $this->redirect('/portal/kurse/' . $courseId);
            return;
        }

        /* Patient muss dem eingeloggten Halter gehören — Tenant-Scope-Schutz. */
        $patient = $this->db->safeFetch(
            "SELECT id, owner_id, name FROM `{$this->db->prefix('patients')}`
              WHERE id = ? AND owner_id = ? LIMIT 1",
            [$patientId, (int)$user['owner_id']]
        );
        if (!$patient) {
            $this->session->flash('error', 'Dieser Hund gehört nicht zu deinem Konto.');
            $this->redirect('/portal/kurse/' . $courseId);
            return;
        }

        $course = $this->courses->findWithStats($courseId);
        if (!$course || !in_array($course['status'], ['active', 'full'], true)) {
            $this->session->flash('error', 'Dieser Kurs ist aktuell nicht buchbar.');
            $this->redirect('/portal/kurse');
            return;
        }

        /* Duplicate-Enrollment-Schutz. */
        $already = $this->db->safeFetchColumn(
            "SELECT id FROM `{$this->db->prefix('dogschool_enrollments')}`
              WHERE course_id = ? AND patient_id = ? AND status = 'active'
              LIMIT 1",
            [$courseId, $patientId]
        );
        if ($already) {
            $this->session->flash('info', 'Dieser Hund ist bereits in diesem Kurs eingeschrieben.');
            $this->redirect('/portal/kurse/' . $courseId);
            return;
        }

        /* Kapazitäts-Check: bei vollem Kurs auf Warteliste, sonst direkt einschreiben. */
        $enrolled = (int)$course['enrolled_count'];
        $maxPart  = (int)$course['max_participants'];

        if ($maxPart > 0 && $enrolled >= $maxPart) {
            /* Warteliste */
            try {
                $this->courses->addToWaitlist($courseId, [
                    'patient_id' => $patientId,
                    'owner_id'   => (int)$user['owner_id'],
                    'status'     => 'waiting',
                    'joined_at'  => date('Y-m-d H:i:s'),
                ]);
                $this->session->flash('success', 'Du stehst jetzt auf der Warteliste — wir melden uns, sobald ein Platz frei wird.');
            } catch (\Throwable $e) {
                error_log('[PortalBooking] waitlist failed: ' . $e->getMessage());
                $this->session->flash('error', 'Warteliste konnte nicht aktualisiert werden.');
            }
            $this->redirect('/portal/kurse/' . $courseId);
            return;
        }

        /* Enrollment anlegen */
        $enrollmentId = (int)$this->courses->enroll($courseId, $patientId, (int)$user['owner_id']);
        if ($enrollmentId <= 0) {
            $this->session->flash('error', 'Einschreibung fehlgeschlagen — bitte kontaktiere die Hundeschule.');
            $this->redirect('/portal/kurse/' . $courseId);
            return;
        }

        /* Rechnung automatisch erzeugen (nicht fatal bei Fehler —
         * Hundeschule kann sie nachträglich erstellen). */
        try {
            $this->dogschoolInvoices->createForEnrollment($enrollmentId);
        } catch (\Throwable $e) {
            error_log('[PortalBooking] auto-invoice failed: ' . $e->getMessage());
        }

        $this->session->flash(
            'success',
            'Einschreibung erfolgreich! Die Rechnung findest du unter "Rechnungen" — bitte begleiche sie innerhalb von 14 Tagen.'
        );
        $this->redirect('/portal/kurse/' . $courseId);
    }

    /* ═══════════════════ Pakete ═══════════════════ */

    /** GET /portal/pakete — Katalog buchbarer Pakete. */
    public function packagesIndex(array $params = []): void
    {
        $this->requireTrainerTenant();
        $user = $this->requireOwnerAuth();

        $packages     = $this->packages->listActive();
        $ownedBalances = $this->packages->balancesForOwner((int)$user['owner_id']);

        $this->render('@owner-portal/owner_packages.twig', array_merge(
            $this->portalBase($user),
            [
                'page_title'     => 'Pakete',
                'active_nav'     => 'pakete',
                'packages'       => $packages,
                'owned_balances' => $ownedBalances,
            ]
        ));
    }

    /** POST /portal/pakete/{id}/kaufen — ein Paket kaufen (erzeugt Balance + Rechnung). */
    public function packagePurchase(array $params = []): void
    {
        $this->requireTrainerTenant();
        $user = $this->requireOwnerAuth();
        $this->validateCsrf();

        $packageId = (int)($params['id'] ?? 0);
        $patientId = (int)$this->post('patient_id', 0) ?: null;

        $pkg = $this->packages->findById($packageId);
        if (!$pkg || (int)$pkg['is_active'] !== 1) {
            $this->session->flash('error', 'Dieses Paket ist aktuell nicht verfügbar.');
            $this->redirect('/portal/pakete');
            return;
        }

        /* Falls Patient angegeben: er muss dem Halter gehören. */
        if ($patientId !== null) {
            $owns = $this->db->safeFetchColumn(
                "SELECT id FROM `{$this->db->prefix('patients')}`
                  WHERE id = ? AND owner_id = ? LIMIT 1",
                [$patientId, (int)$user['owner_id']]
            );
            if (!$owns) {
                $this->session->flash('error', 'Dieser Hund gehört nicht zu deinem Konto.');
                $this->redirect('/portal/pakete');
                return;
            }
        }

        $balanceId = (int)$this->packages->createBalance(
            $packageId,
            (int)$user['owner_id'],
            $patientId,
            'Gekauft über Kundenportal am ' . date('d.m.Y H:i')
        );

        if ($balanceId <= 0) {
            $this->session->flash('error', 'Paketkauf fehlgeschlagen — bitte kontaktiere die Hundeschule.');
            $this->redirect('/portal/pakete');
            return;
        }

        /* Automatische Rechnung. */
        try {
            $this->dogschoolInvoices->createForPackage($balanceId);
        } catch (\Throwable $e) {
            error_log('[PortalBooking] auto-invoice (package) failed: ' . $e->getMessage());
        }

        $this->session->flash(
            'success',
            'Paket erfolgreich gebucht! Die Rechnung findest du unter "Rechnungen".'
        );
        $this->redirect('/portal/pakete');
    }
}
