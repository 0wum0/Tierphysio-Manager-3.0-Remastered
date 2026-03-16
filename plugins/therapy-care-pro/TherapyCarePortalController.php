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
                'SELECT u.*, o.first_name, o.last_name, o.email AS owner_email
                 FROM owner_portal_users u
                 JOIN owners o ON o.id = u.owner_id
                 WHERE u.id = ? LIMIT 1',
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
            'SELECT * FROM patients WHERE id = ? AND owner_id = ? LIMIT 1',
            [$patientId, (int)$portalUser['owner_id']]
        );
        $patient = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$patient) {
            $this->abort(403);
        }
        return $patient;
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

        $this->render('@therapy-care-pro/portal_progress.twig', [
            'page_title'  => 'Fortschritt — ' . $patient['name'],
            'patient'     => $patient,
            'portal_user' => $portalUser,
            'categories'  => $categories,
            'latest'      => $latest,
            'chart_data'  => $chartData,
        ]);
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
            'SELECT id FROM patient_homework WHERE id = ? AND patient_id = ? LIMIT 1',
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
            $hw = $this->db->fetch('SELECT title FROM patient_homework WHERE id=? LIMIT 1', [$homeworkId]);
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
                'INSERT INTO patient_timeline (patient_id, user_id, type, title, content, entry_date)
                 VALUES (?, NULL, "note", ?, ?, CURDATE())',
                [$patientId, $title, $content]
            );
            $timelineId = (int)$this->db->lastInsertId();
            if ($timelineId) {
                $feedbackId = (int)$this->db->lastInsertId(); // approximate
                $this->db->execute(
                    'INSERT INTO tcp_timeline_meta (timeline_id, event_type, ref_table)
                     VALUES (?, "feedback", "tcp_exercise_feedback")
                     ON DUPLICATE KEY UPDATE event_type=VALUES(event_type)',
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

        $file = (defined('STORAGE_PATH') ? STORAGE_PATH : '') . '/patients/' . $patientId . '/' . $report['filename'];
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
