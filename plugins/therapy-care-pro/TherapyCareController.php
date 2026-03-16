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
use App\Services\MailService;

class TherapyCareController extends Controller
{
    private TherapyCareRepository $repo;
    private SettingsRepository $settingsRepo;

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
    }

    /* ══════════════════════════════════════════════════════════
       DASHBOARD WIDGET DATA
    ══════════════════════════════════════════════════════════ */

    /* ══════════════════════════════════════════════════════════
       MODULE 1 — PROGRESS TRACKING (Practice-side)
    ══════════════════════════════════════════════════════════ */

    public function progressIndex(array $params = []): void
    {
        $patientId = (int)$params['id'];
        $patient   = $this->repo->getPatientWithOwner($patientId);
        if (!$patient) { $this->abort(404); }

        $dateFrom = $this->get('date_from', date('Y-m-d', strtotime('-90 days')));
        $dateTo   = $this->get('date_to',   date('Y-m-d'));

        $categories = $this->repo->getActiveProgressCategories();
        $entries    = $this->repo->getProgressEntriesForPatient($patientId, $dateFrom, $dateTo);
        $latest     = $this->repo->getLatestProgressForPatient($patientId);

        $chartData = $this->buildChartData($categories, $entries);

        $this->render('@therapy-care-pro/progress_index.twig', [
            'page_title'  => 'Therapiefortschritt — ' . $patient['name'],
            'patient'     => $patient,
            'categories'  => $categories,
            'entries'     => $entries,
            'latest'      => $latest,
            'chart_data'  => $chartData,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'csrf_token'  => $this->session->generateCsrfToken(),
            'success'     => $this->session->getFlash('success'),
            'error'       => $this->session->getFlash('error'),
        ]);
    }

    public function progressStore(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)$params['id'];

        $entries = $_POST['entries'] ?? [];
        $date    = $this->post('entry_date', date('Y-m-d'));
        $userId  = (int)($this->session->get('user_id') ?? 0);

        $saved = 0;
        foreach ($entries as $categoryId => $score) {
            if ($score === '' || $score === null) continue;
            $this->repo->createProgressEntry([
                'patient_id'     => $patientId,
                'category_id'    => (int)$categoryId,
                'appointment_id' => $this->post('appointment_id') ?: null,
                'score'          => (int)$score,
                'notes'          => $this->post('notes_' . $categoryId, ''),
                'recorded_by'    => $userId,
                'entry_date'     => $date,
            ]);
            $saved++;
        }

        if ($saved > 0) {
            $this->addTimelineEntry($patientId, $userId, 'progress',
                'Fortschritt dokumentiert (' . $saved . ' Kategorien)', '', 'progress');
        }

        $this->session->flash('success', $saved . ' Fortschrittswerte gespeichert.');
        $this->redirect("/patienten/{$patientId}/fortschritt");
    }

    public function progressDeleteEntry(array $params = []): void
    {
        $this->validateCsrf();
        $this->repo->deleteProgressEntry((int)$params['entry_id']);
        $this->session->flash('success', 'Eintrag gelöscht.');
        $this->redirect("/patienten/{$params['id']}/fortschritt");
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 2 — EXERCISE FEEDBACK (Practice-side view)
    ══════════════════════════════════════════════════════════ */

    public function feedbackIndex(array $params = []): void
    {
        $patientId = (int)$params['id'];
        $patient   = $this->repo->getPatientWithOwner($patientId);
        if (!$patient) { $this->abort(404); }

        $days     = (int)$this->get('days', 30);
        $feedback = $this->repo->getFeedbackForPatient($patientId, $days);
        $summary  = $this->repo->getFeedbackSummaryForPatient($patientId);
        $homework = $this->repo->getHomeworkForPatient($patientId);

        $this->render('@therapy-care-pro/feedback_index.twig', [
            'page_title' => 'Übungs-Feedback — ' . $patient['name'],
            'patient'    => $patient,
            'feedback'   => $feedback,
            'summary'    => $summary,
            'homework'   => $homework,
            'days'       => $days,
            'csrf_token' => $this->session->generateCsrfToken(),
            'success'    => $this->session->getFlash('success'),
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 3 — REMINDERS (Admin/settings management)
    ══════════════════════════════════════════════════════════ */

    public function remindersAdmin(array $params = []): void
    {
        $this->requireAdmin();
        $templates = $this->repo->getAllReminderTemplates();
        $logs      = $this->repo->getReminderLogs(50);

        $this->render('@therapy-care-pro/admin_reminders.twig', [
            'page_title' => 'TherapyCare Pro — Erinnerungen',
            'templates'  => $templates,
            'logs'       => $logs,
            'csrf_token' => $this->session->generateCsrfToken(),
            'success'    => $this->session->getFlash('success'),
            'error'      => $this->session->getFlash('error'),
        ]);
    }

    public function reminderTemplateStore(array $params = []): void
    {
        $this->validateCsrf();
        $this->requireAdmin();

        $data = [
            'type'          => $this->post('type', 'appointment'),
            'name'          => $this->sanitize($this->post('name', '')),
            'subject'       => $this->sanitize($this->post('subject', '')),
            'body'          => $this->post('body', ''),
            'trigger_hours' => (int)$this->post('trigger_hours', 24),
            'is_active'     => (int)(bool)$this->post('is_active'),
        ];

        if (empty($data['name']) || empty($data['subject'])) {
            $this->session->flash('error', 'Name und Betreff sind Pflichtfelder.');
            $this->redirect('/tcp/admin/erinnerungen');
            return;
        }

        $this->repo->createReminderTemplate($data);
        $this->session->flash('success', 'Erinnerungsvorlage erstellt.');
        $this->redirect('/tcp/admin/erinnerungen');
    }

    public function reminderTemplateUpdate(array $params = []): void
    {
        $this->validateCsrf();
        $this->requireAdmin();

        $data = [
            'type'          => $this->post('type', 'appointment'),
            'name'          => $this->sanitize($this->post('name', '')),
            'subject'       => $this->sanitize($this->post('subject', '')),
            'body'          => $this->post('body', ''),
            'trigger_hours' => (int)$this->post('trigger_hours', 24),
            'is_active'     => (int)(bool)$this->post('is_active'),
        ];

        $this->repo->updateReminderTemplate((int)$params['id'], $data);
        $this->session->flash('success', 'Vorlage aktualisiert.');
        $this->redirect('/tcp/admin/erinnerungen');
    }

    public function reminderTemplateDelete(array $params = []): void
    {
        $this->validateCsrf();
        $this->requireAdmin();
        $this->repo->deleteReminderTemplate((int)$params['id']);
        $this->session->flash('success', 'Vorlage gelöscht.');
        $this->redirect('/tcp/admin/erinnerungen');
    }

    public function reminderQueue(array $params = []): void
    {
        $patientId = (int)$params['id'];
        $patient   = $this->repo->getPatientWithOwner($patientId);
        if (!$patient) { $this->abort(404); }

        $queued   = $this->repo->getQueuedRemindersForPatient($patientId);
        $templates = $this->repo->getActiveReminderTemplates();

        $this->render('@therapy-care-pro/reminder_queue.twig', [
            'page_title' => 'Erinnerungen — ' . $patient['name'],
            'patient'    => $patient,
            'queued'     => $queued,
            'templates'  => $templates,
            'csrf_token' => $this->session->generateCsrfToken(),
            'success'    => $this->session->getFlash('success'),
        ]);
    }

    public function reminderQueueStore(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)$params['id'];
        $patient   = $this->repo->getPatientWithOwner($patientId);
        if (!$patient) { $this->abort(404); }

        $templateId = (int)$this->post('template_id', 0);
        $sendAt     = $this->post('send_at', date('Y-m-d H:i:s'));

        $subject = $this->sanitize($this->post('subject', ''));
        $body    = $this->post('body', '');

        if ($templateId) {
            $tpl = $this->repo->findReminderTemplateById($templateId);
            if ($tpl) {
                $subject = $this->applyPlaceholders($tpl['subject'], $patient);
                $body    = $this->applyPlaceholders($tpl['body'],    $patient);
            }
        }

        $this->repo->createReminderQueueEntry([
            'template_id'    => $templateId ?: null,
            'type'           => $this->post('type', 'custom'),
            'patient_id'     => $patientId,
            'owner_id'       => (int)$patient['owner_id'],
            'appointment_id' => null,
            'subject'        => $subject,
            'body'           => $body,
            'send_at'        => $sendAt,
        ]);

        $this->session->flash('success', 'Erinnerung eingeplant.');
        $this->redirect("/patienten/{$patientId}/erinnerungen");
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 4 — THERAPY REPORTS
    ══════════════════════════════════════════════════════════ */

    public function reportIndex(array $params = []): void
    {
        $patientId = (int)$params['id'];
        $patient   = $this->repo->getPatientWithOwner($patientId);
        if (!$patient) { $this->abort(404); }

        $reports    = $this->repo->getTherapyReportsForPatient($patientId);
        $categories = $this->repo->getActiveProgressCategories();

        $this->render('@therapy-care-pro/report_index.twig', [
            'page_title' => 'Therapieberichte — ' . $patient['name'],
            'patient'    => $patient,
            'reports'    => $reports,
            'categories' => $categories,
            'csrf_token' => $this->session->generateCsrfToken(),
            'success'    => $this->session->getFlash('success'),
            'error'      => $this->session->getFlash('error'),
        ]);
    }

    public function reportCreate(array $params = []): void
    {
        $patientId = (int)$params['id'];
        $patient   = $this->repo->getPatientWithOwner($patientId);
        if (!$patient) { $this->abort(404); }

        $categories = $this->repo->getActiveProgressCategories();
        $homework   = $this->repo->getHomeworkForPatient($patientId);
        $natural    = $this->repo->getNaturalEntriesForPatient($patientId);

        $this->render('@therapy-care-pro/report_create.twig', [
            'page_title' => 'Therapiebericht erstellen — ' . $patient['name'],
            'patient'    => $patient,
            'categories' => $categories,
            'homework'   => $homework,
            'natural'    => $natural,
            'csrf_token' => $this->session->generateCsrfToken(),
        ]);
    }

    public function reportStore(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)$params['id'];
        $patient   = $this->repo->getPatientWithOwner($patientId);
        if (!$patient) { $this->abort(404); }

        $userId = (int)($this->session->get('user_id') ?? 0);

        $data = [
            'patient_id'              => $patientId,
            'created_by'              => $userId,
            'title'                   => $this->sanitize($this->post('title', 'Therapiebericht')),
            'diagnosis'               => $this->post('diagnosis', ''),
            'therapies_used'          => $this->post('therapies_used', ''),
            'recommendations'         => $this->post('recommendations', ''),
            'followup_recommendation' => $this->post('followup_recommendation', ''),
            'include_progress'        => (int)(bool)$this->post('include_progress'),
            'include_homework'        => (int)(bool)$this->post('include_homework'),
            'include_natural'         => (int)(bool)$this->post('include_natural'),
            'include_timeline'        => (int)(bool)$this->post('include_timeline'),
        ];

        $reportId = $this->repo->createTherapyReport($data);

        $report = $this->repo->findTherapyReportById($reportId);
        $service = new TherapyCareReportService($this->settingsRepo);

        $timeline    = $this->repo->getEnrichedTimeline($patientId);
        $latest      = $data['include_progress'] ? $this->repo->getLatestProgressForPatient($patientId) : [];
        $homework    = $data['include_homework']  ? $this->repo->getHomeworkForPatient($patientId) : [];
        $natural     = $data['include_natural']   ? $this->repo->getNaturalEntriesForPatient($patientId) : [];

        $ownerRow = null;
        try {
            $ownerRow = \App\Core\Application::getInstance()->getContainer()
                ->get(\App\Core\Database::class)
                ->fetch('SELECT * FROM owners WHERE id=? LIMIT 1', [(int)$patient['owner_id']]);
            $ownerRow = $ownerRow ?: null;
        } catch (\Throwable) {}

        $pdf = $service->generate($report, $patient, $ownerRow, $latest, $homework, $natural,
            $data['include_timeline'] ? $timeline : []);

        $filename = 'therapiebericht-' . $patientId . '-' . date('Ymd-His') . '.pdf';
        $storageDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/patients/' . $patientId : '';
        if ($storageDir && !is_dir($storageDir)) { @mkdir($storageDir, 0755, true); }
        if ($storageDir) { file_put_contents($storageDir . '/' . $filename, $pdf); }

        $this->repo->updateTherapyReportFilename($reportId, $filename);

        $this->addTimelineEntry($patientId, $userId, 'document',
            'Therapiebericht erstellt: ' . $data['title'], '', 'therapy_report', $reportId);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    public function reportDownload(array $params = []): void
    {
        $patientId = (int)$params['id'];
        $reportId  = (int)$params['report_id'];
        $report    = $this->repo->findTherapyReportById($reportId);

        if (!$report || $report['patient_id'] !== $patientId) { $this->abort(404); }
        if (!$report['filename']) { $this->abort(404); }

        $file = (defined('STORAGE_PATH') ? STORAGE_PATH : '') . '/patients/' . $patientId . '/' . $report['filename'];
        if (!file_exists($file)) {
            $this->session->flash('error', 'Datei nicht gefunden. Bitte erneut generieren.');
            $this->redirect("/patienten/{$patientId}/berichte");
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $report['filename'] . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    public function reportSend(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)$params['id'];
        $reportId  = (int)$params['report_id'];
        $report    = $this->repo->findTherapyReportById($reportId);
        $patient   = $this->repo->getPatientWithOwner($patientId);

        if (!$report || !$patient) { $this->abort(404); }

        $emailTo = $this->sanitize($this->post('email_to', $patient['owner_email'] ?? ''));
        if (!$emailTo) {
            $this->session->flash('error', 'Keine E-Mail-Adresse angegeben.');
            $this->redirect("/patienten/{$patientId}/berichte");
            return;
        }

        $file = (defined('STORAGE_PATH') ? STORAGE_PATH : '') . '/patients/' . $patientId . '/' . ($report['filename'] ?? '');
        if (!file_exists($file)) {
            $this->session->flash('error', 'PDF nicht gefunden.');
            $this->redirect("/patienten/{$patientId}/berichte");
            return;
        }

        $pdfContent = file_get_contents($file);
        $settings   = $this->settingsRepo->all();
        $mailer     = \App\Core\Application::getInstance()->getContainer()->get(\App\Services\MailService::class);

        $ok = $mailer->sendRaw(
            $emailTo,
            $emailTo,
            'Therapiebericht: ' . $patient['name'],
            $this->settingsRepo->get('company_name', '') . "\n\nAnbei finden Sie den Therapiebericht für " . $patient['name'] . ".",
            [['content' => $pdfContent, 'name' => $report['filename'], 'mime' => 'application/pdf']]
        );

        if ($ok) {
            $this->repo->markTherapyReportSent($reportId, $emailTo);
            $this->session->flash('success', 'Bericht an ' . $emailTo . ' gesendet.');
        } else {
            $this->session->flash('error', 'Fehler beim Senden. ' . $mailer->getLastError());
        }

        $this->redirect("/patienten/{$patientId}/berichte");
    }

    public function reportDelete(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)$params['id'];
        $reportId  = (int)$params['report_id'];
        $report    = $this->repo->findTherapyReportById($reportId);

        if ($report && $report['patient_id'] === $patientId) {
            if ($report['filename']) {
                $file = (defined('STORAGE_PATH') ? STORAGE_PATH : '') . '/patients/' . $patientId . '/' . $report['filename'];
                if (file_exists($file)) { @unlink($file); }
            }
            $this->repo->deleteTherapyReport($reportId);
            $this->session->flash('success', 'Bericht gelöscht.');
        }
        $this->redirect("/patienten/{$patientId}/berichte");
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 5 — EXERCISE LIBRARY
    ══════════════════════════════════════════════════════════ */

    public function exerciseLibraryIndex(array $params = []): void
    {
        $category   = $this->get('category', '');
        $search     = $this->get('search', '');
        $exercises  = $this->repo->getExerciseLibrary($category ?: null, $search ?: null);
        $categories = $this->repo->getExerciseCategories();

        $this->render('@therapy-care-pro/exercise_library.twig', [
            'page_title' => 'TherapyCare Pro — Übungsbibliothek',
            'exercises'  => $exercises,
            'categories' => $categories,
            'category'   => $category,
            'search'     => $search,
            'csrf_token' => $this->session->generateCsrfToken(),
            'success'    => $this->session->getFlash('success'),
            'error'      => $this->session->getFlash('error'),
        ]);
    }

    public function exerciseLibraryCreate(array $params = []): void
    {
        $this->render('@therapy-care-pro/exercise_library_form.twig', [
            'page_title' => 'Neue Übung erstellen',
            'exercise'   => null,
            'csrf_token' => $this->session->generateCsrfToken(),
        ]);
    }

    public function exerciseLibraryStore(array $params = []): void
    {
        $this->validateCsrf();
        $userId = (int)($this->session->get('user_id') ?? 0);

        $data = [
            'title'           => $this->sanitize($this->post('title', '')),
            'category'        => $this->sanitize($this->post('category', 'sonstiges')),
            'description'     => $this->post('description', ''),
            'instructions'    => $this->post('instructions', ''),
            'contraindications' => $this->post('contraindications', ''),
            'frequency'       => $this->sanitize($this->post('frequency', '')),
            'duration'        => $this->sanitize($this->post('duration', '')),
            'species_tags'    => $this->sanitize($this->post('species_tags', '')),
            'therapy_tags'    => $this->sanitize($this->post('therapy_tags', '')),
            'is_active'       => 1,
            'created_by'      => $userId,
        ];

        if (empty($data['title'])) {
            $this->session->flash('error', 'Titel ist ein Pflichtfeld.');
            $this->redirect('/tcp/bibliothek/neu');
            return;
        }

        $id = $this->repo->createExercise($data);
        $this->session->flash('success', 'Übung erstellt.');
        $this->redirect('/tcp/bibliothek');
    }

    public function exerciseLibraryEdit(array $params = []): void
    {
        $exercise = $this->repo->findExerciseById((int)$params['id']);
        if (!$exercise) { $this->abort(404); }

        $this->render('@therapy-care-pro/exercise_library_form.twig', [
            'page_title' => 'Übung bearbeiten',
            'exercise'   => $exercise,
            'csrf_token' => $this->session->generateCsrfToken(),
        ]);
    }

    public function exerciseLibraryUpdate(array $params = []): void
    {
        $this->validateCsrf();
        $exercise = $this->repo->findExerciseById((int)$params['id']);
        if (!$exercise) { $this->abort(404); }

        $data = [
            'title'           => $this->sanitize($this->post('title', '')),
            'category'        => $this->sanitize($this->post('category', 'sonstiges')),
            'description'     => $this->post('description', ''),
            'instructions'    => $this->post('instructions', ''),
            'contraindications' => $this->post('contraindications', ''),
            'frequency'       => $this->sanitize($this->post('frequency', '')),
            'duration'        => $this->sanitize($this->post('duration', '')),
            'species_tags'    => $this->sanitize($this->post('species_tags', '')),
            'therapy_tags'    => $this->sanitize($this->post('therapy_tags', '')),
            'is_active'       => (int)(bool)$this->post('is_active', '1'),
        ];

        $this->repo->updateExercise((int)$params['id'], $data);
        $this->session->flash('success', 'Übung aktualisiert.');
        $this->redirect('/tcp/bibliothek');
    }

    public function exerciseLibraryDelete(array $params = []): void
    {
        $this->validateCsrf();
        $this->repo->deleteExercise((int)$params['id']);
        $this->session->flash('success', 'Übung gelöscht.');
        $this->redirect('/tcp/bibliothek');
    }

    public function exerciseLibraryDuplicate(array $params = []): void
    {
        $this->validateCsrf();
        $exercise = $this->repo->findExerciseById((int)$params['id']);
        if (!$exercise) { $this->abort(404); }

        $userId = (int)($this->session->get('user_id') ?? 0);

        $newData = $exercise;
        unset($newData['id'], $newData['created_at'], $newData['updated_at'], $newData['created_by_name']);
        $newData['title']      = $exercise['title'] . ' (Kopie)';
        $newData['created_by'] = $userId ?: null;

        $newId = $this->repo->createExercise($newData);
        $this->session->flash('success', 'Übung dupliziert.');
        $this->redirect('/tcp/bibliothek/' . $newId . '/bearbeiten');
    }

    /* ── API: return exercise library as JSON for homework picker ── */
    public function apiExerciseLibrary(array $params = []): void
    {
        $category = $this->get('category', '');
        $search   = $this->get('search', '');

        $exercises = $this->repo->getExerciseLibrary(
            $category ?: null,
            $search   ?: null
        );

        /* Strip heavy fields not needed in the picker */
        $light = array_map(static function (array $e): array {
            return [
                'id'           => $e['id'],
                'title'        => $e['title'],
                'category'     => $e['category'],
                'description'  => $e['description'],
                'instructions' => $e['instructions'] ?? '',
                'frequency'    => $e['frequency'] ?? '',
                'duration'     => $e['duration'] ?? '',
                'species_tags' => $e['species_tags'] ?? '',
                'therapy_tags' => $e['therapy_tags'] ?? '',
                'contraindications' => $e['contraindications'] ?? '',
            ];
        }, $exercises);

        $this->json(['ok' => true, 'exercises' => $light]);
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 6 — NATURAL THERAPY
    ══════════════════════════════════════════════════════════ */

    public function naturalIndex(array $params = []): void
    {
        $patientId = (int)$params['id'];
        $patient   = $this->repo->getPatientWithOwner($patientId);
        if (!$patient) { $this->abort(404); }

        $entries = $this->repo->getNaturalEntriesForPatient($patientId);
        $types   = $this->repo->getNaturalTherapyTypesByCategory();

        $this->render('@therapy-care-pro/natural_index.twig', [
            'page_title' => 'Naturheilkunde — ' . $patient['name'],
            'patient'    => $patient,
            'entries'    => $entries,
            'types'      => $types,
            'csrf_token' => $this->session->generateCsrfToken(),
            'success'    => $this->session->getFlash('success'),
            'error'      => $this->session->getFlash('error'),
        ]);
    }

    public function naturalStore(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)$params['id'];
        $patient   = $this->repo->getPatientWithOwner($patientId);
        if (!$patient) { $this->abort(404); }

        $userId = (int)($this->session->get('user_id') ?? 0);
        $typeId = $this->post('type_id', '') ?: null;

        $data = [
            'patient_id'     => $patientId,
            'type_id'        => $typeId,
            'therapy_type'   => $this->sanitize($this->post('therapy_type', '')),
            'agent'          => $this->sanitize($this->post('agent', '')),
            'dosage'         => $this->sanitize($this->post('dosage', '')),
            'frequency'      => $this->sanitize($this->post('frequency', '')),
            'duration'       => $this->sanitize($this->post('duration', '')),
            'notes'          => $this->post('notes', ''),
            'show_in_portal' => (int)(bool)$this->post('show_in_portal'),
            'recorded_by'    => $userId,
            'entry_date'     => $this->post('entry_date', date('Y-m-d')),
        ];

        if (empty($data['therapy_type'])) {
            $this->session->flash('error', 'Therapieform ist ein Pflichtfeld.');
            $this->redirect("/patienten/{$patientId}/naturheilkunde");
            return;
        }

        $entryId = $this->repo->createNaturalEntry($data);

        $this->addTimelineEntry($patientId, $userId, 'treatment',
            'Naturheilkundliche Maßnahme: ' . $data['therapy_type'],
            $data['notes'] ?? '',
            'natural_therapy', $entryId);

        $this->session->flash('success', 'Naturheilkundliche Maßnahme gespeichert.');
        $this->redirect("/patienten/{$patientId}/naturheilkunde");
    }

    public function naturalUpdate(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)$params['id'];

        $data = [
            'therapy_type'   => $this->sanitize($this->post('therapy_type', '')),
            'agent'          => $this->sanitize($this->post('agent', '')),
            'dosage'         => $this->sanitize($this->post('dosage', '')),
            'frequency'      => $this->sanitize($this->post('frequency', '')),
            'duration'       => $this->sanitize($this->post('duration', '')),
            'notes'          => $this->post('notes', ''),
            'show_in_portal' => (int)(bool)$this->post('show_in_portal'),
            'entry_date'     => $this->post('entry_date', date('Y-m-d')),
        ];

        $this->repo->updateNaturalEntry((int)$params['entry_id'], $data);
        $this->session->flash('success', 'Eintrag aktualisiert.');
        $this->redirect("/patienten/{$patientId}/naturheilkunde");
    }

    public function naturalDelete(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)$params['id'];
        $this->repo->deleteNaturalEntry((int)$params['entry_id']);
        $this->session->flash('success', 'Eintrag gelöscht.');
        $this->redirect("/patienten/{$patientId}/naturheilkunde");
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 7 — ENHANCED TIMELINE
    ══════════════════════════════════════════════════════════ */

    public function timelineIndex(array $params = []): void
    {
        $patientId  = (int)$params['id'];
        $patient    = $this->repo->getPatientWithOwner($patientId);
        if (!$patient) { $this->abort(404); }

        $typeFilter = $this->get('type', '');
        $dateFrom   = $this->get('date_from', '');
        $dateTo     = $this->get('date_to', '');

        $timeline = $this->repo->getEnrichedTimeline($patientId);

        if ($typeFilter) {
            $timeline = array_filter($timeline, function ($e) use ($typeFilter) {
                return ($e['tcp_event_type'] ?? $e['type']) === $typeFilter
                    || $e['type'] === $typeFilter;
            });
            $timeline = array_values($timeline);
        }
        if ($dateFrom) {
            $timeline = array_filter($timeline, fn($e) => $e['entry_date'] >= $dateFrom);
            $timeline = array_values($timeline);
        }
        if ($dateTo) {
            $timeline = array_filter($timeline, fn($e) => substr($e['entry_date'], 0, 10) <= $dateTo);
            $timeline = array_values($timeline);
        }

        $this->render('@therapy-care-pro/timeline_index.twig', [
            'page_title'  => 'Erweiterte Timeline — ' . $patient['name'],
            'patient'     => $patient,
            'timeline'    => $timeline,
            'type_filter' => $typeFilter,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'csrf_token'  => $this->session->generateCsrfToken(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════
       MODULE 8 — PORTAL VISIBILITY SETTINGS
    ══════════════════════════════════════════════════════════ */

    public function portalVisibilityUpdate(array $params = []): void
    {
        $this->validateCsrf();
        $patientId = (int)$params['id'];

        $this->repo->savePortalVisibility($patientId, [
            'show_progress' => (int)(bool)$this->post('show_progress'),
            'show_natural'  => (int)(bool)$this->post('show_natural'),
            'show_reports'  => (int)(bool)$this->post('show_reports'),
        ]);

        if ($this->isAjax()) {
            $this->json(['ok' => true]);
            return;
        }
        $this->session->flash('success', 'Portal-Freigaben aktualisiert.');
        $this->redirect("/patienten/{$params['id']}");
    }

    /* ══════════════════════════════════════════════════════════
       ADMIN SETTINGS PAGE
    ══════════════════════════════════════════════════════════ */

    public function adminSettings(array $params = []): void
    {
        $this->requireAdmin();

        $progressCategories    = $this->repo->getAllProgressCategories();
        $naturalTherapyTypes   = $this->repo->getAllNaturalTherapyTypes();
        $reminderTemplates     = $this->repo->getAllReminderTemplates();
        $stats                 = $this->repo->getProgressDashboardStats();
        $settings              = $this->settingsRepo->all();

        $this->render('@therapy-care-pro/admin_settings.twig', [
            'page_title'         => 'TherapyCare Pro — Einstellungen',
            'progress_categories'  => $progressCategories,
            'natural_therapy_types'=> $naturalTherapyTypes,
            'reminder_templates'   => $reminderTemplates,
            'stats'                => $stats,
            'settings'             => $settings,
            'csrf_token'           => $this->session->generateCsrfToken(),
            'success'              => $this->session->getFlash('success'),
            'error'                => $this->session->getFlash('error'),
        ]);
    }

    public function adminProgressCategoryStore(array $params = []): void
    {
        $this->validateCsrf();
        $this->requireAdmin();

        $data = [
            'name'           => $this->sanitize($this->post('name', '')),
            'description'    => $this->post('description', ''),
            'scale_min'      => (int)$this->post('scale_min', 1),
            'scale_max'      => (int)$this->post('scale_max', 10),
            'scale_label_min'=> $this->sanitize($this->post('scale_label_min', '')),
            'scale_label_max'=> $this->sanitize($this->post('scale_label_max', '')),
            'color'          => $this->sanitize($this->post('color', '#4f7cff')),
            'sort_order'     => (int)$this->post('sort_order', 0),
            'is_active'      => 1,
        ];

        if (empty($data['name'])) {
            $this->session->flash('error', 'Name ist ein Pflichtfeld.');
        } else {
            $this->repo->createProgressCategory($data);
            $this->session->flash('success', 'Kategorie erstellt.');
        }
        $this->redirect('/tcp/admin/einstellungen#fortschritt');
    }

    public function adminProgressCategoryUpdate(array $params = []): void
    {
        $this->validateCsrf();
        $this->requireAdmin();

        $data = [
            'name'           => $this->sanitize($this->post('name', '')),
            'description'    => $this->post('description', ''),
            'scale_min'      => (int)$this->post('scale_min', 1),
            'scale_max'      => (int)$this->post('scale_max', 10),
            'scale_label_min'=> $this->sanitize($this->post('scale_label_min', '')),
            'scale_label_max'=> $this->sanitize($this->post('scale_label_max', '')),
            'color'          => $this->sanitize($this->post('color', '#4f7cff')),
            'sort_order'     => (int)$this->post('sort_order', 0),
            'is_active'      => (int)(bool)$this->post('is_active', '1'),
        ];

        $this->repo->updateProgressCategory((int)$params['id'], $data);
        $this->session->flash('success', 'Kategorie aktualisiert.');
        $this->redirect('/tcp/admin/einstellungen#fortschritt');
    }

    public function adminProgressCategoryDelete(array $params = []): void
    {
        $this->validateCsrf();
        $this->requireAdmin();
        $this->repo->deleteProgressCategory((int)$params['id']);
        $this->session->flash('success', 'Kategorie gelöscht.');
        $this->redirect('/tcp/admin/einstellungen#fortschritt');
    }

    public function adminNaturalTypeStore(array $params = []): void
    {
        $this->validateCsrf();
        $this->requireAdmin();

        $this->repo->createNaturalTherapyType([
            'name'       => $this->sanitize($this->post('name', '')),
            'category'   => $this->sanitize($this->post('category', 'Sonstiges')),
            'description'=> $this->post('description', ''),
            'sort_order' => (int)$this->post('sort_order', 0),
            'is_active'  => 1,
        ]);
        $this->session->flash('success', 'Therapietyp erstellt.');
        $this->redirect('/tcp/admin/einstellungen#naturheilkunde');
    }

    public function adminNaturalTypeUpdate(array $params = []): void
    {
        $this->validateCsrf();
        $this->requireAdmin();

        $this->repo->updateNaturalTherapyType((int)$params['id'], [
            'name'       => $this->sanitize($this->post('name', '')),
            'category'   => $this->sanitize($this->post('category', 'Sonstiges')),
            'description'=> $this->post('description', ''),
            'sort_order' => (int)$this->post('sort_order', 0),
            'is_active'  => (int)(bool)$this->post('is_active', '1'),
        ]);
        $this->session->flash('success', 'Therapietyp aktualisiert.');
        $this->redirect('/tcp/admin/einstellungen#naturheilkunde');
    }

    public function adminNaturalTypeDelete(array $params = []): void
    {
        $this->validateCsrf();
        $this->requireAdmin();
        $this->repo->deleteNaturalTherapyType((int)$params['id']);
        $this->session->flash('success', 'Therapietyp gelöscht.');
        $this->redirect('/tcp/admin/einstellungen#naturheilkunde');
    }

    /* ══════════════════════════════════════════════════════════
       CRON ENDPOINT — process reminder queue
    ══════════════════════════════════════════════════════════ */

    public function cronReminders(array $params = []): void
    {
        $settings      = $this->settingsRepo->all();
        $expectedToken = $settings['tcp_cron_token'] ?? '';

        if ($expectedToken !== '') {
            $provided = $_GET['token'] ?? '';
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (str_starts_with($authHeader, 'Bearer ')) {
                $provided = substr($authHeader, 7);
            }
            if (!hash_equals($expectedToken, $provided)) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Ungültiger Token.']);
                exit;
            }
        }

        $queue = $this->repo->getPendingReminderQueue();

        $db      = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
        $mailer  = \App\Core\Application::getInstance()->getContainer()->get(\App\Services\MailService::class);

        $sent = 0; $failed = 0;
        foreach ($queue as $item) {
            try {
                $ok = $mailer->sendRaw(
                    $item['owner_email'],
                    $item['owner_first_name'] . ' ' . $item['owner_last_name'],
                    $item['subject'],
                    $this->wrapReminderBody($item['body'])
                );
                if ($ok) {
                    $this->repo->markReminderSent($item['id']);
                    $this->repo->logReminder([
                        'queue_id'  => $item['id'],
                        'type'      => $item['type'],
                        'recipient' => $item['owner_email'],
                        'subject'   => $item['subject'],
                        'status'    => 'sent',
                    ]);
                    $sent++;
                } else {
                    throw new \RuntimeException($mailer->getLastError());
                }
            } catch (\Throwable $e) {
                $this->repo->markReminderFailed($item['id'], $e->getMessage());
                $this->repo->logReminder([
                    'queue_id'  => $item['id'],
                    'type'      => $item['type'],
                    'recipient' => $item['owner_email'],
                    'subject'   => $item['subject'],
                    'status'    => 'failed',
                    'error'     => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'sent' => $sent, 'failed' => $failed, 'total' => count($queue)]);
        exit;
    }

    /* ══════════════════════════════════════════════════════════
       API ENDPOINTS
    ══════════════════════════════════════════════════════════ */

    public function apiProgressData(array $params = []): void
    {
        $patientId = (int)$params['id'];
        $entries   = $this->repo->getProgressEntriesForPatient($patientId);
        $latest    = $this->repo->getLatestProgressForPatient($patientId);
        $cats      = $this->repo->getActiveProgressCategories();
        $chart     = $this->buildChartData($cats, $entries);

        $this->json(['entries' => $entries, 'latest' => $latest, 'chart' => $chart]);
    }

    public function apiModalData(array $params = []): void
    {
        $patientId = (int)$params['id'];
        try {
            $cats    = $this->repo->getActiveProgressCategories();
            $entries = $this->repo->getProgressEntriesForPatient($patientId);
            $latest  = $this->repo->getLatestProgressForPatient($patientId);
            $chart   = $this->buildChartData($cats, $entries);
            $natural = $this->repo->getNaturalEntriesForPatient($patientId);
            $reports = $this->repo->getTherapyReportsForPatient($patientId);
            $vis     = $this->repo->getPortalVisibility($patientId);

            $this->json([
                'ok'         => true,
                'categories' => $cats,
                'latest'     => $latest,
                'chart'      => $chart,
                'natural'    => $natural,
                'reports'    => $reports,
                'visibility' => $vis,
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function apiPortalVisibility(array $params = []): void
    {
        $patientId  = (int)$params['id'];
        $visibility = $this->repo->getPortalVisibility($patientId);
        $this->json($visibility);
    }

    /* ══════════════════════════════════════════════════════════
       PRIVATE HELPERS
    ══════════════════════════════════════════════════════════ */

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

    private function applyPlaceholders(string $text, array $patient): string
    {
        $settings = $this->settingsRepo->all();
        $map = [
            '{{patient_name}}'     => $patient['name'] ?? '',
            '{{owner_name}}'       => ($patient['owner_first_name'] ?? '') . ' ' . ($patient['owner_last_name'] ?? ''),
            '{{company_name}}'     => $settings['company_name'] ?? '',
            '{{appointment_date}}' => date('d.m.Y'),
            '{{appointment_time}}' => date('H:i'),
        ];
        return str_replace(array_keys($map), array_values($map), $text);
    }

    private function wrapReminderBody(string $text): string
    {
        $company = htmlspecialchars($this->settingsRepo->get('company_name', 'Tierphysio Manager'));
        $content = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>"
             . "<body style='font-family:sans-serif;background:#0f0f1a;color:rgba(255,255,255,.82);padding:32px;'>"
             . "<div style='max-width:560px;margin:0 auto;background:rgba(255,255,255,.06);border-radius:12px;padding:28px;'>"
             . "<p style='margin:0 0 16px;'>{$content}</p>"
             . "<hr style='border-color:rgba(255,255,255,.1);margin:20px 0;'>"
             . "<p style='font-size:.78rem;color:rgba(255,255,255,.35);margin:0;'>{$company}</p>"
             . "</div></body></html>";
    }

    private function addTimelineEntry(
        int $patientId,
        int $userId,
        string $type,
        string $title,
        string $content,
        string $tcpEventType,
        ?int $refId = null
    ): void {
        try {
            $db = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
            $db->execute(
                'INSERT INTO patient_timeline (patient_id, user_id, type, title, content, entry_date)
                 VALUES (?, ?, ?, ?, ?, CURDATE())',
                [$patientId, $userId ?: null, $type, $title, $content]
            );
            $timelineId = (int)$db->lastInsertId();
            if ($timelineId) {
                $this->repo->setTimelineMeta($timelineId, $tcpEventType, $refId,
                    $refId ? 'tcp_' . $tcpEventType : null);
            }
        } catch (\Throwable) {}
    }
}
