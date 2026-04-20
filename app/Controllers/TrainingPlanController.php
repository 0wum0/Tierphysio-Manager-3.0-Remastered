<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\TrainingPlanRepository;
use App\Repositories\PatientRepository;

/**
 * TrainingPlanController (TCP).
 *
 * Verwaltet Trainingspläne, Übungen-Katalog, Plan-Zuweisungen an Hunde,
 * Fortschritts-Erfassung und Hausaufgaben.
 *
 * Feature-Gates:
 *   - dogschool_training_plans : Pläne & Vorlagen
 *   - dogschool_exercises      : Übungs-Katalog-Editor
 *   - dogschool_progress       : Fortschritts-Erfassung (innerhalb Plans)
 *   - dogschool_homework       : Hausaufgaben (innerhalb Plans)
 */
class TrainingPlanController extends Controller
{
    private TrainingPlanRepository $plans;
    private PatientRepository $patients;

    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        TrainingPlanRepository $plans,
        PatientRepository $patients,
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->plans    = $plans;
        $this->patients = $patients;
    }

    /* ═════════════════════════ Plan-Vorlagen ═════════════════════════ */

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');

        $audience  = (string)$this->get('audience', '');
        $templates = $this->plans->listTemplates($audience);

        $this->render('dogschool/training/plans_index.twig', [
            'page_title'   => 'Trainingspläne',
            'active_nav'   => 'training_plans',
            'templates'    => $templates,
            'filter_audience' => $audience,
            'audiences'    => [
                ''         => 'Alle',
                'welpen'   => 'Welpen',
                'junghunde'=> 'Junghunde',
                'adult'    => 'Erwachsen',
                'senior'   => 'Senior',
                'problem'  => 'Problemhunde',
            ],
        ]);
    }

    public function show(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');

        $id   = (int)($params['id'] ?? 0);
        $plan = $this->plans->findPlanWithExercises($id);
        if (empty($plan)) {
            $this->flash('error', 'Plan nicht gefunden.');
            $this->redirect('/trainingsplaene');
            return;
        }

        $this->render('dogschool/training/plan_show.twig', [
            'page_title'       => $plan['name'] ?? 'Plan',
            'active_nav'       => 'training_plans',
            'plan'             => $plan,
            'categories'       => $this->plans->exerciseCategories(),
            'mastery_levels'   => $this->plans->masteryLevels(),
        ]);
    }

    public function create(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->render('dogschool/training/plan_form.twig', [
            'page_title' => 'Neuer Trainingsplan',
            'active_nav' => 'training_plans',
            'plan'       => [
                'id'                => 0,
                'name'              => '',
                'description'       => '',
                'target_audience'   => '',
                'duration_weeks'    => 8,
                'sessions_per_week' => 1,
                'difficulty'        => 'medium',
                'is_template'       => 1,
                'is_active'         => 1,
                'is_system'         => 0,
            ],
            'is_new' => true,
        ]);
    }

    public function edit(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $id   = (int)($params['id'] ?? 0);
        $plan = $this->plans->findById($id);
        if (!$plan) {
            $this->flash('error', 'Plan nicht gefunden.');
            $this->redirect('/trainingsplaene');
            return;
        }
        if ((int)($plan['is_system'] ?? 0) === 1) {
            $this->flash('warning', 'System-Pläne können nicht bearbeitet werden. Bitte kopieren.');
            $this->redirect('/trainingsplaene/' . $id);
            return;
        }
        $this->render('dogschool/training/plan_form.twig', [
            'page_title' => 'Plan bearbeiten',
            'active_nav' => 'training_plans',
            'plan'       => $plan,
            'is_new'     => false,
        ]);
    }

    public function store(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->validateCsrf();

        $id = $this->plans->createPlan($this->collectPlanData());
        $this->flash('success', 'Plan angelegt.');
        $this->redirect('/trainingsplaene/' . $id);
    }

    public function update(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->validateCsrf();

        $id = (int)($params['id'] ?? 0);
        $this->plans->updatePlan($id, $this->collectPlanData());
        $this->flash('success', 'Plan aktualisiert.');
        $this->redirect('/trainingsplaene/' . $id);
    }

    public function delete(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->validateCsrf();

        $id  = (int)($params['id'] ?? 0);
        $ok  = $this->plans->deletePlan($id);
        if ($ok === 0) {
            $this->flash('warning', 'Plan kann nicht gelöscht werden (System-Plan oder nicht vorhanden).');
        } else {
            $this->flash('success', 'Plan gelöscht.');
        }
        $this->redirect('/trainingsplaene');
    }

    public function addExercise(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->validateCsrf();

        $planId     = (int)($params['id'] ?? 0);
        $exerciseId = (int)$this->post('exercise_id', 0);
        $week       = max(1, (int)$this->post('week_number', 1));
        $session    = max(1, (int)$this->post('session_number', 1));
        $reps       = ((int)$this->post('target_repetitions', 0)) ?: null;
        $dur        = ((int)$this->post('target_duration_minutes', 0)) ?: null;
        $notes      = trim((string)$this->post('notes', '')) ?: null;

        if ($planId > 0 && $exerciseId > 0) {
            $this->plans->addPlanExercise($planId, $exerciseId, $week, $session, $reps, $dur, $notes);
            $this->flash('success', 'Übung hinzugefügt.');
        }
        $this->redirect('/trainingsplaene/' . $planId);
    }

    public function removeExercise(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->validateCsrf();

        $planId   = (int)($params['id'] ?? 0);
        $planExId = (int)($params['plan_exercise_id'] ?? 0);
        $this->plans->removePlanExercise($planExId);
        $this->flash('success', 'Übung entfernt.');
        $this->redirect('/trainingsplaene/' . $planId);
    }

    /* ═════════════════════════ Übungs-Katalog ═════════════════════════ */

    public function exercisesIndex(array $params = []): void
    {
        $this->requireFeature('dogschool_exercises');

        $category = (string)$this->get('category', '');
        $search   = trim((string)$this->get('q', ''));

        $this->render('dogschool/training/exercises_index.twig', [
            'page_title'  => 'Übungen-Katalog',
            'active_nav'  => 'exercises',
            'exercises'   => $this->plans->listExercises($category, $search),
            'categories'  => $this->plans->exerciseCategories(),
            'filter_category' => $category,
            'filter_q'    => $search,
        ]);
    }

    public function exerciseShow(array $params = []): void
    {
        $this->requireFeature('dogschool_exercises');
        $id = (int)($params['id'] ?? 0);
        $ex = $this->plans->findExercise($id);
        if (!$ex) {
            $this->flash('error', 'Übung nicht gefunden.');
            $this->redirect('/uebungen');
            return;
        }
        $this->render('dogschool/training/exercise_show.twig', [
            'page_title'  => $ex['name'] ?? 'Übung',
            'active_nav'  => 'exercises',
            'exercise'    => $ex,
            'categories'  => $this->plans->exerciseCategories(),
        ]);
    }

    public function exerciseCreate(array $params = []): void
    {
        $this->requireFeature('dogschool_exercises');
        $this->validateCsrf();

        $data = [
            'name'               => trim((string)$this->post('name', '')),
            'category'           => (string)$this->post('category', 'basics'),
            'description'        => trim((string)$this->post('description', '')),
            'instructions'       => trim((string)$this->post('instructions', '')),
            'difficulty'         => (string)$this->post('difficulty', 'medium'),
            'duration_minutes'   => max(1, (int)$this->post('duration_minutes', 10)),
            'min_age_months'     => ((int)$this->post('min_age_months', 0)) ?: null,
            'required_equipment' => trim((string)$this->post('required_equipment', '')),
            'video_url'          => trim((string)$this->post('video_url', '')),
        ];
        if ($data['name'] === '') {
            $this->flash('error', 'Name erforderlich.');
            $this->redirect('/uebungen');
            return;
        }
        $this->plans->createExercise($data);
        $this->flash('success', 'Übung angelegt.');
        $this->redirect('/uebungen');
    }

    /* ═════════════════════════ Zuweisungen ═════════════════════════ */

    public function assignToPatient(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->validateCsrf();

        $planId    = (int)($params['id'] ?? 0);
        $patientId = (int)$this->post('patient_id', 0);
        if ($patientId === 0 || $planId === 0) {
            $this->flash('error', 'Plan oder Hund fehlt.');
            $this->redirect('/trainingsplaene/' . $planId);
            return;
        }

        /* Owner aus Patient ableiten */
        $ownerId = null;
        $p = $this->patients->findById($patientId);
        if ($p && !empty($p['owner_id'])) {
            $ownerId = (int)$p['owner_id'];
        }

        $assignmentId = (int)$this->plans->assignPlanToPatient(
            $planId,
            $patientId,
            $ownerId,
            ((int)$this->post('course_id', 0)) ?: null,
            ((int)$this->post('trainer_user_id', 0)) ?: null,
            (string)$this->post('start_date', date('Y-m-d')),
            trim((string)$this->post('notes', '')) ?: null
        );
        $this->flash('success', 'Plan dem Hund zugewiesen.');
        $this->redirect('/trainingsplaene/zuweisung/' . $assignmentId);
    }

    public function assignmentShow(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');

        $id = (int)($params['id'] ?? 0);
        $a  = $this->plans->findAssignment($id);
        if (!$a) {
            $this->flash('error', 'Zuweisung nicht gefunden.');
            $this->redirect('/trainingsplaene');
            return;
        }
        $plan       = $this->plans->findPlanWithExercises((int)$a['plan_id']);
        $progress   = $this->plans->progressForAssignment($id);
        $mastery    = $this->plans->latestMasteryByExercise($id);

        $this->render('dogschool/training/assignment_show.twig', [
            'page_title'     => 'Trainingsplan: ' . ($a['patient_name'] ?? ''),
            'active_nav'     => 'training_plans',
            'assignment'     => $a,
            'plan'           => $plan,
            'progress'       => $progress,
            'mastery_map'    => $mastery,
            'mastery_levels' => $this->plans->masteryLevels(),
            'categories'     => $this->plans->exerciseCategories(),
        ]);
    }

    public function assignmentStatus(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->validateCsrf();

        $id     = (int)($params['id'] ?? 0);
        $status = (string)$this->post('status', 'active');
        $this->plans->updateAssignmentStatus($id, $status);
        $this->flash('success', 'Status aktualisiert.');
        $this->redirect('/trainingsplaene/zuweisung/' . $id);
    }

    /* ═════════════════════════ Fortschritt ═════════════════════════ */

    public function recordProgress(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->validateCsrf();

        $assignmentId = (int)($params['id'] ?? 0);
        $a = $this->plans->findAssignment($assignmentId);
        if (!$a) {
            $this->flash('error', 'Zuweisung nicht gefunden.');
            $this->redirect('/trainingsplaene');
            return;
        }

        $userId = (int)($this->session->getUser()['id'] ?? 0) ?: null;
        $this->plans->recordProgress([
            'assignment_id'       => $assignmentId,
            'patient_id'          => (int)$a['patient_id'],
            'exercise_id'         => (int)$this->post('exercise_id', 0),
            'mastery_level'       => (int)$this->post('mastery_level', 1),
            'repetitions'         => $this->post('repetitions', null),
            'duration_minutes'    => $this->post('duration_minutes', null),
            'success_rate_pct'    => $this->post('success_rate_pct', null),
            'notes'               => $this->post('notes', ''),
            'recorded_by_user_id' => $userId,
        ]);
        $this->flash('success', 'Fortschritt erfasst.');
        $this->redirect('/trainingsplaene/zuweisung/' . $assignmentId);
    }

    /* ═════════════════════════ Hausaufgaben ═════════════════════════ */

    public function createHomework(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->validateCsrf();

        $assignmentId = (int)($params['id'] ?? 0);
        $a = $this->plans->findAssignment($assignmentId);
        if (!$a) {
            $this->flash('error', 'Zuweisung nicht gefunden.');
            $this->redirect('/trainingsplaene');
            return;
        }

        $this->plans->createHomework([
            'assignment_id' => $assignmentId,
            'patient_id'    => (int)$a['patient_id'],
            'exercise_id'   => ((int)$this->post('exercise_id', 0)) ?: null,
            'title'         => trim((string)$this->post('title', '')),
            'description'   => trim((string)$this->post('description', '')),
            'due_date'      => (string)$this->post('due_date', '') ?: null,
        ]);
        $this->flash('success', 'Hausaufgabe angelegt.');
        $this->redirect('/trainingsplaene/zuweisung/' . $assignmentId);
    }

    public function updateHomework(array $params = []): void
    {
        $this->requireFeature('dogschool_training_plans');
        $this->validateCsrf();

        $assignmentId = (int)($params['id'] ?? 0);
        $homeworkId   = (int)($params['homework_id'] ?? 0);
        $status       = (string)$this->post('status', 'open');
        $update       = ['status' => $status];
        if ($status === 'done') {
            $update['completed_at'] = date('Y-m-d H:i:s');
        }
        $feedback = trim((string)$this->post('owner_feedback', ''));
        if ($feedback !== '') {
            $update['owner_feedback'] = $feedback;
        }
        $this->plans->updateHomework($homeworkId, $update);
        $this->flash('success', 'Hausaufgabe aktualisiert.');
        $this->redirect('/trainingsplaene/zuweisung/' . $assignmentId);
    }

    /* ═════════════════════════ Helpers ═════════════════════════ */

    private function collectPlanData(): array
    {
        return [
            'name'              => trim((string)$this->post('name', '')),
            'description'       => trim((string)$this->post('description', '')),
            'target_audience'   => (string)$this->post('target_audience', ''),
            'duration_weeks'    => max(1, (int)$this->post('duration_weeks', 8)),
            'sessions_per_week' => max(1, (int)$this->post('sessions_per_week', 1)),
            'difficulty'        => (string)$this->post('difficulty', 'medium'),
            'is_template'       => (int)(bool)$this->post('is_template', 0),
            'is_active'         => (int)(bool)$this->post('is_active', 0),
        ];
    }
}
