<?php

declare(strict_types=1);

namespace Plugins\TherapyCarePro;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;
use App\Repositories\SettingsRepository;

/**
 * TherapyCare Pro — Owner Portal extensions
 * All routes start with /portal/tcp/...
 * Auth is enforced via owner_portal_user_id session key (same as owner-portal plugin).
 */
class TherapyCarePortalController extends Controller
{
    private TherapyCareRepository $repo;
    private SettingsRepository $settingsRepo;
    private Database $db;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db,
        SettingsRepository $settingsRepo
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo         = new TherapyCareRepository($db);
        $this->settingsRepo = $settingsRepo;
        $this->db           = $db;
    }

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    /* ── Auth guard (mirrors owner-portal plugin) ── */
    private function requirePortalAuth(): array
    {
        $userId = $this->session->get('owner_portal_user_id');
        if (!$userId) {
            $this->redirect('/portal/login');
            exit;
        }

        try {
            $stmt = $this->db->query(
                "SELECT u.*, o.first_name, o.last_name, o.email AS owner_email
                 FROM `{$this->t('owner_portal_users')}` u
                 JOIN `{$this->t('owners')}` o ON o.id = u.owner_id
                 WHERE u.id = ? LIMIT 1",
                [(int)$userId]
            );
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $user = null;
        }

        if (!$user || !$user['is_active']) {
            $this->session->remove('owner_portal_user_id');
            $this->redirect('/portal/login');
            exit;
        }

        return $user;
    }

    /** Check if patient belongs to the authenticated owner */
    private function requirePatientOwnership(array $portalUser, int $patientId): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM `{$this->t('patients')}` WHERE id = ? AND owner_id = ? LIMIT 1",
            [$patientId, (int)$portalUser['owner_id']]
        );
        $patient = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$patient) {
            $this->abort(403);
        }
        return $patient;
    }

    /* ══════════════════════════════════════════════════════════
       GET /portal/fortschritt
       Aggregierte Übersicht aller Tiere des Halters mit direktem
       Sprung in Fortschrittsseite + Story je Tier.
       Bei genau 1 Tier → direkter Redirect zur Tier-Seite.
    ══════════════════════════════════════════════════════════ */
    public function progressOverview(array $params = []): void
    {
        $portalUser = $this->requirePortalAuth();

        $stmt = $this->db->query(
            "SELECT id, name, species, breed FROM `{$this->t('patients')}`
              WHERE owner_id = ? ORDER BY name ASC",
            [(int)$portalUser['owner_id']]
        );
        $pets = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (count($pets) === 1) {
            $this->redirect('/portal/tcp/tiere/' . (int)$pets[0]['id'] . '/fortschritt');
            return;
        }

        $isTrainer = ($this->settingsRepo->get('practice_type', 'therapeut') === 'trainer');

        /* Pro Tier: letzter Eintrag + Anzahl Medien für die Karten-Vorschau. */
        $cards = [];
        foreach ($pets as $p) {
            $petId  = (int)$p['id'];
            $latest = $this->repo->getLatestProgressForPatient($petId);
            $media  = $this->repo->getMediaForPatient($petId, 1);
            $cards[] = [
                'pet'         => $p,
                'latest'      => $latest,
                'has_media'   => !empty($media),
                'progress_url'=> '/portal/tcp/tiere/' . $petId . '/fortschritt',
                'story_url'   => '/portal/tcp/tiere/' . $petId . '/fortschritt/story',
            ];
        }

        $this->render('@therapy-care-pro/portal_progress_overview.twig', [
            'page_title'        => $isTrainer ? 'Trainings-Verlauf' : 'Therapiefortschritt',
            'active_nav'        => 'fortschritt',
            'cards'             => $cards,
            'is_trainer'        => $isTrainer,
            'portal_user'       => $portalUser,
            'is_trainer_tenant' => $isTrainer,
            'csrf_token'        => $this->session->generateCsrfToken(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       GET /portal/tcp/tiere/{id}/fortschritt
       Owner views progress chart for their pet
    ══════════════════════════════════════════════════════════ */
    public function progress(array $params = []): void
    {
        $portalUser = $this->requirePortalAuth();
        $patientId  = (int)$params['id'];
        $patient    = $this->requirePatientOwnership($portalUser, $patientId);

        $visibility = $this->repo->getPortalVisibility($patientId);
        if (!$visibility['show_progress']) {
            $this->redirect('/portal/tiere/' . $patientId);
            return;
        }

        $categories = $this->repo->getActiveProgressCategories();
        $entries    = $this->repo->getProgressEntriesForPatient($patientId);
        $latest     = $this->repo->getLatestProgressForPatient($patientId);
        $chartData  = $this->buildChartData($categories, $entries);

        /* Medien-Galerie zum Eintrag — read-only für den Halter.
         * Galerie-URLs verweisen auf die Portal-Serve-Route, die Owner-
         * Authentifizierung statt Praxis-Auth nutzt. */
        $media = $this->repo->getMediaForPatient($patientId, 60);

        $isTrainer = ($this->settingsRepo->get('practice_type', 'therapeut') === 'trainer');
        $this->render('@therapy-care-pro/portal_progress.twig', [
            'page_title'        => 'Fortschritt — ' . $patient['name'],
            'active_nav'        => 'fortschritt',
            'is_trainer_tenant' => $isTrainer,
            'is_trainer'        => $isTrainer,
            'patient'           => $patient,
            'portal_user'       => $portalUser,
            'categories'     => $categories,
            'latest'         => $latest,
            'chart_data'     => $chartData,
            'media'          => $media,
            'media_url_base' => '/portal/tcp/tiere/' . $patientId . '/fortschritt/media',
            'story_url'      => '/portal/tcp/tiere/' . $patientId . '/fortschritt/story',
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       GET /portal/tcp/tiere/{id}/fortschritt/story
       Owner views the chronological therapy story for their pet.
       Reuses the same template as the practice-side, with
       portal-scoped media URLs.
    ══════════════════════════════════════════════════════════ */
    public function progressStory(array $params = []): void
    {
        $portalUser = $this->requirePortalAuth();
        $patientId  = (int)$params['id'];
        $patient    = $this->requirePatientOwnership($portalUser, $patientId);

        /* Respect tenant-side visibility flag — wenn der Therapeut den
         * Fortschritt nicht freigegeben hat, leiten wir zurück. */
        $visibility = $this->repo->getPortalVisibility($patientId);
        if (!$visibility['show_progress']) {
            $this->redirect('/portal/tiere/' . $patientId);
            return;
        }

        $media         = $this->repo->getMediaForPatient($patientId);
        $beforeAfter   = $this->repo->getBeforeAfterPairs($patientId);
        $latestEntries = $this->repo->getLatestProgressForPatient($patientId);
        $isTrainer     = ($this->settingsRepo->get('practice_type', 'therapeut') === 'trainer');

        $this->render('@therapy-care-pro/progress_story.twig', [
            'page_title'        => ($isTrainer ? 'Trainings-Verlauf — ' : 'Therapie-Story — ') . $patient['name'],
            'active_nav'        => 'fortschritt',
            'is_trainer_tenant' => $isTrainer,
            'patient'           => $patient,
            'portal_user'       => $portalUser,
            'media'          => $media,
            'before_after'   => $beforeAfter,
            'latest'         => $latestEntries,
            'is_trainer'     => $isTrainer,
            /* Wichtig: Portal-User darf nicht /patienten/{id}/... aufrufen,
             * deshalb hier die Portal-Variante. Das Story-Template nutzt
             * `media_url_base` für alle <img>/<video>-Quellen. */
            'media_url_base' => '/portal/tcp/tiere/' . $patientId . '/fortschritt/media',
            'is_portal_view' => true,
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       GET /portal/tcp/tiere/{id}/fortschritt/media/{media_id}
       Authenticated media file delivery for the owner portal.
    ══════════════════════════════════════════════════════════ */
    public function progressMediaServe(array $params = []): void
    {
        $portalUser = $this->requirePortalAuth();
        $patientId  = (int)$params['id'];
        $mediaId    = (int)$params['media_id'];

        $this->requirePatientOwnership($portalUser, $patientId);

        $media = $this->repo->findProgressMedia($mediaId);
        if (!$media || (int)$media['patient_id'] !== $patientId) {
            $this->abort(404);
            return;
        }

        $path = $this->db->storagePath($media['file_path']);
        if (!is_file($path)) {
            $this->abort(404);
            return;
        }

        header('Content-Type: ' . $media['mime_type']);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="' .
            preg_replace('/[^a-zA-Z0-9._-]/', '_', $media['original_name'] ?: 'media') . '"');
        readfile($path);
        exit;
    }

    /* ══════════════════════════════════════════════════════════
       GET /portal/tcp/tiere/{id}/feedback
       Owner views their feedback history
    ══════════════════════════════════════════════════════════ */
    public function feedbackList(array $params = []): void
    {
        $portalUser = $this->requirePortalAuth();
        $patientId  = (int)$params['id'];
        $patient    = $this->requirePatientOwnership($portalUser, $patientId);

        $homework = $this->repo->getHomeworkForPatient($patientId);
        $feedback = $this->repo->getFeedbackForPatient($patientId, 60);

        $this->render('@therapy-care-pro/portal_feedback.twig', [
            'page_title'  => 'Übungs-Feedback — ' . $patient['name'],
            'patient'     => $patient,
            'portal_user' => $portalUser,
            'homework'    => $homework,
            'feedback'    => $feedback,
            'csrf_token'  => $this->session->generateCsrfToken(),
            'success'     => $this->session->getFlash('success'),
            'error'       => $this->session->getFlash('error'),
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       POST /portal/tcp/tiere/{id}/feedback
       Owner submits exercise feedback
    ══════════════════════════════════════════════════════════ */
    public function feedbackStore(array $params = []): void
    {
        $this->validateCsrf();
        $portalUser = $this->requirePortalAuth();
        $patientId  = (int)$params['id'];
        $patient    = $this->requirePatientOwnership($portalUser, $patientId);

        $homeworkId = (int)$this->post('homework_id', 0);
        $status     = $this->post('status', 'done');
        $comment    = $this->post('comment', '');
        $date       = $this->post('feedback_date', date('Y-m-d'));

        $allowedStatuses = ['done', 'not_done', 'pain', 'difficult'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'done';
        }

        if (!$homeworkId) {
            $this->session->flash('error', 'Bitte eine Übung auswählen.');
            $this->redirect('/portal/tcp/tiere/' . $patientId . '/feedback');
            return;
        }

        // Verify this homework belongs to this patient
        $stmt = $this->db->query(
            "SELECT id FROM `{$this->t('patient_homework')}` WHERE id = ? AND patient_id = ? LIMIT 1",
            [$homeworkId, $patientId]
        );
        if (!$stmt->fetch()) {
            $this->abort(403);
        }

        $this->repo->createFeedback([
            'homework_id'   => $homeworkId,
            'patient_id'    => $patientId,
            'owner_id'      => (int)$portalUser['owner_id'],
            'status'        => $status,
            'comment'       => $comment,
            'feedback_date' => $date,
        ]);

        // Add timeline entry in practice system
        try {
            $hw = $this->db->fetch("SELECT title FROM `{$this->t('patient_homework')}` WHERE id=? LIMIT 1", [$homeworkId]);
            $statusLabel = match($status) {
                'done'      => 'Durchgeführt',
                'not_done'  => 'Nicht durchgeführt',
                'pain'      => 'Tier hatte Schmerzen',
                'difficult' => 'Schwierig umsetzbar',
                default     => $status
            };
            $title   = 'Feedback vom Besitzer: ' . ($hw['title'] ?? 'Übung') . ' — ' . $statusLabel;
            $content = $comment ?: '';

            $this->db->execute(
                "INSERT INTO `{$this->t('patient_timeline')}` (patient_id, user_id, type, title, content, entry_date)
                 VALUES (?, NULL, 'note', ?, ?, CURDATE())",
                [$patientId, $title, $content]
            );
            $timelineId = (int)$this->db->lastInsertId();
            if ($timelineId) {
                $feedbackId = (int)$this->db->lastInsertId(); // approximate
                $this->db->execute(
                    "INSERT INTO `{$this->t('tcp_timeline_meta')}` (timeline_id, event_type, ref_table)
                     VALUES (?, 'feedback', 'tcp_exercise_feedback')
                     ON DUPLICATE KEY UPDATE event_type=VALUES(event_type)",
                    [$timelineId]
                );
            }
        } catch (\Throwable) {}

        $this->session->flash('success', 'Feedback gespeichert. Danke!');
        $this->redirect('/portal/tcp/tiere/' . $patientId . '/feedback');
    }

    /* ══════════════════════════════════════════════════════════
       GET /portal/tcp/tiere/{id}/naturheilkunde
       Owner views natural therapy entries (if visible)
    ══════════════════════════════════════════════════════════ */
    public function naturalTherapy(array $params = []): void
    {
        $portalUser = $this->requirePortalAuth();
        $patientId  = (int)$params['id'];
        $patient    = $this->requirePatientOwnership($portalUser, $patientId);

        $visibility = $this->repo->getPortalVisibility($patientId);
        if (!$visibility['show_natural']) {
            $this->redirect('/portal/tiere/' . $patientId);
            return;
        }

        $entries = $this->repo->getPublicNaturalEntriesForPatient($patientId);

        $this->render('@therapy-care-pro/portal_natural.twig', [
            'page_title'  => 'Naturheilkunde — ' . $patient['name'],
            'patient'     => $patient,
            'portal_user' => $portalUser,
            'entries'     => $entries,
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       GET /portal/tcp/tiere/{id}/berichte
       Owner views/downloads therapy reports
    ══════════════════════════════════════════════════════════ */
    public function reports(array $params = []): void
    {
        $portalUser = $this->requirePortalAuth();
        $patientId  = (int)$params['id'];
        $patient    = $this->requirePatientOwnership($portalUser, $patientId);

        $visibility = $this->repo->getPortalVisibility($patientId);
        if (!$visibility['show_reports']) {
            $this->redirect('/portal/tiere/' . $patientId);
            return;
        }

        $reports = $this->repo->getTherapyReportsForPatient($patientId);
        // Only show reports that have been generated (have a filename)
        $reports = array_filter($reports, fn($r) => !empty($r['filename']));

        $this->render('@therapy-care-pro/portal_reports.twig', [
            'page_title'  => 'Therapieberichte — ' . $patient['name'],
            'patient'     => $patient,
            'portal_user' => $portalUser,
            'reports'     => array_values($reports),
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       GET /portal/tcp/tiere/{id}/berichte/{report_id}/download
    ══════════════════════════════════════════════════════════ */
    public function reportDownload(array $params = []): void
    {
        $portalUser = $this->requirePortalAuth();
        $patientId  = (int)$params['id'];
        $this->requirePatientOwnership($portalUser, $patientId);

        $visibility = $this->repo->getPortalVisibility($patientId);
        if (!$visibility['show_reports']) { $this->abort(403); }

        $report = $this->repo->findTherapyReportById((int)$params['report_id']);
        if (!$report || $report['patient_id'] !== $patientId || !$report['filename']) {
            $this->abort(404);
        }

        $file = tenant_storage_path('patients/' . $patientId . '/' . $report['filename']);
        if (!file_exists($file)) { $this->abort(404); }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($report['filename']) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    /* ── Private helpers ── */

    private function buildChartData(array $categories, array $entries): array
    {
        $datasets = [];
        $labels   = [];
        $byDate   = [];

        foreach ($entries as $e) {
            $date = substr($e['entry_date'], 0, 10);
            if (!in_array($date, $labels, true)) { $labels[] = $date; }
            $byDate[$e['category_id']][$date] = $e['score'];
        }

        sort($labels);

        foreach ($categories as $cat) {
            $points = [];
            foreach ($labels as $date) {
                $points[] = $byDate[$cat['id']][$date] ?? null;
            }
            $datasets[] = [
                'label'           => $cat['name'],
                'data'            => $points,
                'borderColor'     => $cat['color'],
                'backgroundColor' => $cat['color'] . '33',
                'tension'         => 0.4,
                'spanGaps'        => true,
            ];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }
}
