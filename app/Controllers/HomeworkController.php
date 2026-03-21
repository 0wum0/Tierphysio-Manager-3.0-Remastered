<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Repositories\HomeworkRepository;
use App\Repositories\PatientRepository;
use App\Repositories\OwnerRepository;
use App\Services\MailService;

class HomeworkController
{
    private HomeworkRepository $homeworkRepository;
    private PatientRepository $patientRepository;
    private Database $db;
    private OwnerRepository $ownerRepository;
    private MailService $mailService;

    public function __construct(
        HomeworkRepository $homeworkRepository,
        PatientRepository $patientRepository,
        Database $db,
        OwnerRepository $ownerRepository,
        MailService $mailService
    ) {
        $this->homeworkRepository = $homeworkRepository;
        $this->patientRepository  = $patientRepository;
        $this->db                 = $db;
        $this->ownerRepository    = $ownerRepository;
        $this->mailService        = $mailService;
    }

    public function getTemplates(): void
    {
        header('Content-Type: application/json');
        $freqMap = [
            'daily'             => 'Täglich',
            'twice_daily'       => '2x täglich',
            'three_times_daily' => '3x täglich',
            'weekly'            => 'Wöchentlich',
            'as_needed'         => 'Bei Bedarf',
        ];
        $unitMap = [
            'minutes' => 'Minuten',
            'hours'   => 'Stunden',
            'days'    => 'Tage',
            'weeks'   => 'Wochen',
        ];
        $templates = $this->homeworkRepository->findAllTemplates();
        foreach ($templates as &$t) {
            $t['frequency']     = $freqMap[$t['frequency']]     ?? $t['frequency'];
            $t['duration_unit'] = $unitMap[$t['duration_unit']] ?? $t['duration_unit'];
        }
        echo json_encode($templates);
        exit;
    }

    public function getPatientHomework(array $params = []): void
    {
        $patientId = (int)($params['patient_id'] ?? 0);
        
        if ($patientId === 0) {
            header('Content-Type: application/json');
            echo json_encode([]);
            exit;
        }

        // Prüfen ob Patient existiert und Zugriff erlaubt
        $patient = $this->patientRepository->findById($patientId);
        if (!$patient) {
            http_response_code(404);
            echo json_encode(['error' => 'Patient nicht gefunden']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode($this->homeworkRepository->findPatientHomework($patientId));
        exit;
    }

    public function createPatientHomework(array $params = []): void
    {
        $patientId = (int)($params['patient_id'] ?? 0);
        
        if ($patientId === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Patient-ID fehlt']);
            exit;
        }

        // Prüfen ob Patient existiert
        $patient = $this->patientRepository->findById($patientId);
        if (!$patient) {
            http_response_code(404);
            echo json_encode(['error' => 'Patient nicht gefunden']);
            exit;
        }

        // CSRF-Token prüfen
        if (!isset($_POST['_csrf_token']) || !Auth::validateCsrfToken($_POST['_csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Ungültiger CSRF-Token']);
            exit;
        }

        // Template oder eigene Hausaufgabe
        $templateId = $_POST['homework_template_id'] ?? null;
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $category = $_POST['category'] ?? 'sonstiges';
        $categoryEmoji = $this->getCategoryEmoji($category);
        $frequency = $_POST['frequency'] ?? 'daily';
        $durationValue = (int)($_POST['duration_value'] ?? 10);
        $durationUnit = $_POST['duration_unit'] ?? 'minutes';
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $therapistNotes = $_POST['therapist_notes'] ?? null;

        // Wenn Template ausgewählt, Daten aus Template übernehmen
        if ($templateId && $templateId !== 'custom') {
            $template = $this->homeworkRepository->findTemplateById((int)$templateId);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template nicht gefunden']);
                exit;
            }
            
            $title = $template['title'];
            $description = $template['description'];
            $category = $template['category'];
            $categoryEmoji = $template['category_emoji'];
            $frequency = $template['frequency'];
            $durationValue = $template['duration_value'];
            $durationUnit = $template['duration_unit'];
            $therapistNotes = $template['therapist_notes'];
        }

        // Eigene Hausaufgabe validieren
        if ($templateId === 'custom') {
            if (empty($title) || empty($description)) {
                http_response_code(400);
                echo json_encode(['error' => 'Titel und Beschreibung sind erforderlich']);
                exit;
            }
        }

        try {
            $homeworkId = $this->homeworkRepository->createPatientHomework([
                'patient_id' => $patientId,
                'homework_template_id' => $templateId === 'custom' ? null : (int)$templateId,
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'category_emoji' => $categoryEmoji,
                'frequency' => $frequency,
                'duration_value' => $durationValue,
                'duration_unit' => $durationUnit,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'therapist_notes' => $therapistNotes,
                'assigned_by' => Auth::getCurrentUserId()
            ]);

            // Send e-mail notification to owner with portal link
            if (!empty($patient['owner_id'])) {
                $owner = $this->ownerRepository->findById((int)$patient['owner_id']);
                if ($owner && !empty($owner['email'])) {
                    $portalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/portal';
                    $this->mailService->sendHomeworkNotification($patient, $owner, $title, $portalUrl);
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'homework_id' => $homeworkId]);
            exit;

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Erstellen der Hausaufgabe']);
            exit;
        }
    }

    public function deletePatientHomework(array $params = []): void
    {
        $patientId = (int)($params['patient_id'] ?? 0);
        $homeworkId = (int)($params['homework_id'] ?? 0);

        if ($patientId === 0 || $homeworkId === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Patient-ID oder Hausaufgaben-ID fehlt']);
            exit;
        }

        // Prüfen ob Hausaufgabe existiert und zum Patienten gehört
        $homework = $this->homeworkRepository->findHomeworkById($homeworkId);
        if (!$homework || $homework['patient_id'] !== $patientId) {
            http_response_code(404);
            echo json_encode(['error' => 'Hausaufgabe nicht gefunden']);
            exit;
        }

        // CSRF-Token prüfen (aus Header für DELETE-Requests)
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::validateCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'Ungültiger CSRF-Token']);
            exit;
        }

        try {
            $success = $this->homeworkRepository->deletePatientHomework($homeworkId);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => $success]);
            exit;

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Löschen der Hausaufgabe']);
            exit;
        }
    }

    public function getPlanMeta(array $params = []): void
    {
        $patientId = (int)($params['patient_id'] ?? 0);
        if ($patientId === 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Patient-ID fehlt']);
            exit;
        }

        $meta = $this->homeworkRepository->getPatientPlanMeta($patientId);

        header('Content-Type: application/json');
        echo json_encode($meta ?? (object)[]);
        exit;
    }

    public function savePlanMeta(array $params = []): void
    {
        $patientId = (int)($params['patient_id'] ?? 0);
        if ($patientId === 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Patient-ID fehlt']);
            exit;
        }

        if (!isset($_POST['_csrf_token']) || !Auth::validateCsrfToken($_POST['_csrf_token'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Ungültiger CSRF-Token']);
            exit;
        }

        $this->homeworkRepository->savePatientPlanMeta($patientId, [
            'physiotherapeutische_grundsaetze' => $_POST['physiotherapeutische_grundsaetze'] ?? null,
            'kurzfristige_ziele'               => $_POST['kurzfristige_ziele'] ?? null,
            'langfristige_ziele'               => $_POST['langfristige_ziele'] ?? null,
            'therapiemittel'                   => $_POST['therapiemittel'] ?? null,
            'beachte_hinweise'                 => $_POST['beachte_hinweise'] ?? null,
            'wiedervorstellung_date'           => $_POST['wiedervorstellung_date'] ?? null,
            'therapist_name'                   => $_POST['therapist_name'] ?? null,
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    /* ── Homework Plans (portal_homework_plans) ── */

    private function ensurePlansTablesExist(): void
    {
        try {
        $this->db->getPdo()->exec("CREATE TABLE IF NOT EXISTS `portal_homework_plans` (
            `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `patient_id`          INT UNSIGNED NOT NULL,
            `owner_id`            INT UNSIGNED NOT NULL DEFAULT 0,
            `plan_date`           DATE NOT NULL,
            `physio_principles`   TEXT NULL,
            `short_term_goals`    TEXT NULL,
            `long_term_goals`     TEXT NULL,
            `therapy_means`       TEXT NULL,
            `general_notes`       TEXT NULL,
            `next_appointment`    VARCHAR(255) NULL,
            `therapist_name`      VARCHAR(255) NULL,
            `status`              ENUM('active','archived') NOT NULL DEFAULT 'active',
            `pdf_sent_at`         DATETIME NULL,
            `pdf_sent_to`         VARCHAR(255) NULL,
            `created_by`          INT UNSIGNED NULL,
            `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_php_patient_id` (`patient_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->getPdo()->exec("CREATE TABLE IF NOT EXISTS `portal_homework_plan_tasks` (
            `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `plan_id`           INT UNSIGNED NOT NULL,
            `template_id`       INT UNSIGNED NULL,
            `title`             VARCHAR(255) NOT NULL,
            `description`       TEXT NULL,
            `frequency`         VARCHAR(255) NULL,
            `duration`          VARCHAR(255) NULL,
            `therapist_notes`   TEXT NULL,
            `sort_order`        INT NOT NULL DEFAULT 0,
            `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_phpt_plan_id` (`plan_id`),
            CONSTRAINT `fk_phpt_plan` FOREIGN KEY (`plan_id`) REFERENCES `portal_homework_plans` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            /* Tables already exist or cannot be created — continue anyway */
        }
    }

    public function getPatientPlans(array $params = []): void
    {
        header('Content-Type: application/json');
        $patientId = (int)($params['patient_id'] ?? 0);
        if ($patientId === 0) { echo json_encode([]); exit; }

        try {
            $this->ensurePlansTablesExist();

            $plans = $this->db->query(
                'SELECT hp.*, u.name AS created_by_name
                 FROM portal_homework_plans hp
                 LEFT JOIN users u ON u.id = hp.created_by
                 WHERE hp.patient_id = ?
                 ORDER BY hp.plan_date DESC, hp.id DESC',
                [$patientId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($plans as &$plan) {
                $plan['tasks'] = $this->db->query(
                    'SELECT * FROM portal_homework_plan_tasks WHERE plan_id = ? ORDER BY sort_order ASC, id ASC',
                    [(int)$plan['id']]
                )->fetchAll(\PDO::FETCH_ASSOC);
            }
            echo json_encode($plans);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    public function createPatientPlan(array $params = []): void
    {
        header('Content-Type: application/json');
        $patientId = (int)($params['patient_id'] ?? 0);
        if ($patientId === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Patient-ID fehlt']);
            exit;
        }

        if (!Auth::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'Ungültiger CSRF-Token']);
            exit;
        }

        $patient = $this->patientRepository->findById($patientId);
        if (!$patient) {
            http_response_code(404);
            echo json_encode(['error' => 'Patient nicht gefunden']);
            exit;
        }

        try {
        $this->ensurePlansTablesExist();
        $ownerId = (int)($patient['owner_id'] ?? 0);
        $userId  = Auth::getCurrentUserId();

        $this->db->query(
            'INSERT INTO portal_homework_plans
             (patient_id, owner_id, plan_date, physio_principles, short_term_goals,
              long_term_goals, therapy_means, general_notes, next_appointment, therapist_name,
              status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $patientId,
                $ownerId,
                $_POST['plan_date'] ?? date('Y-m-d'),
                $_POST['physio_principles'] ?? null,
                $_POST['short_term_goals']  ?? null,
                $_POST['long_term_goals']   ?? null,
                $_POST['therapy_means']     ?? null,
                $_POST['general_notes']     ?? null,
                $_POST['next_appointment']  ?? null,
                $_POST['therapist_name']    ?? null,
                'active',
                $userId,
            ]
        );
        $planId = (int)$this->db->lastInsertId();

        $titles       = $_POST['task_title']       ?? [];
        $descriptions = $_POST['task_description'] ?? [];
        $frequencies  = $_POST['task_frequency']   ?? [];
        $durations    = $_POST['task_duration']    ?? [];
        $notes        = $_POST['task_notes']       ?? [];

        foreach ($titles as $i => $title) {
            if (empty(trim($title))) continue;
            $this->db->query(
                'INSERT INTO portal_homework_plan_tasks
                 (plan_id, title, description, frequency, duration, therapist_notes, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $planId,
                    trim($title),
                    $descriptions[$i] ?? null,
                    $frequencies[$i]  ?? null,
                    $durations[$i]    ?? null,
                    $notes[$i]        ?? null,
                    $i,
                ]
            );
        }

        /* ── Auto-create owner portal account if needed ── */
        $portalInviteSent = false;
        $portalInviteError = null;
        if ($ownerId > 0) {
            try {
                $ownerRow = $this->db->query(
                    'SELECT id, email, first_name, last_name FROM owners WHERE id = ? LIMIT 1',
                    [$ownerId]
                )->fetch(\PDO::FETCH_ASSOC);

                if ($ownerRow && !empty($ownerRow['email'])) {
                    $existing = $this->db->query(
                        'SELECT id FROM owner_portal_users WHERE owner_id = ? LIMIT 1',
                        [$ownerId]
                    )->fetch(\PDO::FETCH_ASSOC);

                    if (!$existing) {
                        $token   = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
                        $this->db->execute(
                            'INSERT INTO owner_portal_users (owner_id, email, password_hash, is_active, invite_token, invite_expires)
                             VALUES (?, ?, NULL, 0, ?, ?)',
                            [$ownerId, $ownerRow['email'], $token, $expires]
                        );

                        /* Send invite email via OwnerPortalMailService */
                        if (class_exists(\Plugins\OwnerPortal\OwnerPortalMailService::class)) {
                            $settingsRepo = new \App\Repositories\SettingsRepository($this->db);
                            $mailService  = new \App\Services\MailService($settingsRepo);
                            $mailer       = new \Plugins\OwnerPortal\OwnerPortalMailService($settingsRepo, $mailService);
                            $mailer->sendInvite($ownerRow['email'], $token);
                            $portalInviteSent = true;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $portalInviteError = $e->getMessage();
            }
        }

        echo json_encode([
            'success'            => true,
            'plan_id'            => $planId,
            'portal_invite_sent' => $portalInviteSent,
            'portal_invite_error'=> $portalInviteError,
        ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    public function deletePatientPlan(array $params = []): void
    {
        header('Content-Type: application/json');
        $patientId = (int)($params['patient_id'] ?? 0);
        $planId    = (int)($params['plan_id']    ?? 0);

        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? '');
        if (!Auth::validateCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'Ungültiger CSRF-Token']);
            exit;
        }

        try {
            $this->ensurePlansTablesExist();
            $row = $this->db->query(
                'SELECT id FROM portal_homework_plans WHERE id = ? AND patient_id = ? LIMIT 1',
                [$planId, $patientId]
            )->fetch();
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => 'Plan nicht gefunden']);
                exit;
            }
            $this->db->query('DELETE FROM portal_homework_plans WHERE id = ?', [$planId]);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    private function getCategoryEmoji(string $category): string
    {
        $emojis = [
            'bewegung' => '🏃',
            'dehnung' => '🤸',
            'massage' => '💆',
            'kalt_warm' => '🌡️',
            'medikamente' => '💊',
            'fuetterung' => '🍽️',
            'beobachtung' => '👁️',
            'sonstiges' => '📌'
        ];

        return $emojis[$category] ?? '📌';
    }
}
