<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Repositories\HomeworkRepository;
use App\Repositories\PatientRepository;

class HomeworkController
{
    private HomeworkRepository $homeworkRepository;
    private PatientRepository $patientRepository;

    public function __construct(HomeworkRepository $homeworkRepository, PatientRepository $patientRepository)
    {
        $this->homeworkRepository = $homeworkRepository;
        $this->patientRepository = $patientRepository;
    }

    public function getTemplates(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->homeworkRepository->findAllTemplates());
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
