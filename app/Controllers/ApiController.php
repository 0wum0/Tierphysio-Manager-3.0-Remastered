<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Repositories\HomeworkRepository;
use App\Repositories\PatientRepository;

class ApiController
{
    private HomeworkRepository $homeworkRepository;
    private PatientRepository $patientRepository;

    public function __construct(HomeworkRepository $homeworkRepository, PatientRepository $patientRepository)
    {
        $this->homeworkRepository = $homeworkRepository;
        $this->patientRepository = $patientRepository;
    }

    public function getHomeworkTemplates(): void
    {
        header('Content-Type: application/json');
        
        try {
            $templates = $this->homeworkRepository->findAllTemplates();
            
            // Kategorie-Emoji hinzufügen
            foreach ($templates as &$template) {
                $template['category_emoji'] = $this->getCategoryEmoji($template['category']);
            }
            
            echo json_encode($templates);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Laden der Templates: ' . $e->getMessage()]);
        }
        exit;
    }

    public function getPatientHomework(array $params = []): void
    {
        $patientId = (int)($params['patient_id'] ?? 0);
        
        header('Content-Type: application/json');
        
        if ($patientId === 0) {
            echo json_encode([]);
            exit;
        }

        try {
            // Prüfen ob Patient existiert
            $patient = $this->patientRepository->findById($patientId);
            if (!$patient) {
                http_response_code(404);
                echo json_encode(['error' => 'Patient nicht gefunden']);
                exit;
            }

            $homework = $this->homeworkRepository->findPatientHomework($patientId);
            
            // Kategorie-Emoji hinzufügen
            foreach ($homework as &$hw) {
                $hw['category_emoji'] = $this->getCategoryEmoji($hw['category'] ?? 'sonstiges');
            }
            
            echo json_encode($homework);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Laden der Hausaufgaben: ' . $e->getMessage()]);
        }
        exit;
    }

    public function createPatientHomework(array $params = []): void
    {
        $patientId = (int)($params['patient_id'] ?? 0);
        
        header('Content-Type: application/json');
        
        if ($patientId === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Patient-ID fehlt']);
            exit;
        }

        try {
            // Prüfen ob Patient existiert
            $patient = $this->patientRepository->findById($patientId);
            if (!$patient) {
                http_response_code(404);
                echo json_encode(['error' => 'Patient nicht gefunden']);
                exit;
            }

            $data = $_POST;
            
            // Template-Prüfung
            $templateId = (int)($data['homework_template_id'] ?? 0);
            if ($templateId > 0 && $templateId !== 'custom') {
                $template = $this->homeworkRepository->findTemplateById($templateId);
                if (!$template) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Template nicht gefunden']);
                    exit;
                }
                
                // Template-Daten übernehmen
                $data['title'] = $template['title'];
                $data['description'] = $template['description'];
                $data['category'] = $template['category'];
            }

            // Pflichtfelder prüfen
            if (empty($data['title']) || empty($data['description'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Titel und Beschreibung sind erforderlich']);
                exit;
            }

            // Hausaufgabe erstellen
            $homeworkId = $this->homeworkRepository->createPatientHomework([
                'patient_id' => $patientId,
                'homework_template_id' => $templateId > 0 && $templateId !== 'custom' ? $templateId : null,
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'] ?? 'sonstiges',
                'frequency' => $data['frequency'] ?? 'daily',
                'duration_value' => (int)($data['duration_value'] ?? 10),
                'duration_unit' => $data['duration_unit'] ?? 'minutes',
                'start_date' => $data['start_date'] ?? date('Y-m-d'),
                'end_date' => !empty($data['end_date']) ? $data['end_date'] : null,
                'therapist_notes' => $data['therapist_notes'] ?? '',
                'assigned_by' => Auth::user()['id'],
                'status' => 'active'
            ]);

            echo json_encode(['success' => true, 'homework_id' => $homeworkId]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Erstellen der Hausaufgabe: ' . $e->getMessage()]);
        }
        exit;
    }

    public function deletePatientHomework(array $params = []): void
    {
        $patientId = (int)($params['patient_id'] ?? 0);
        $homeworkId = (int)($params['homework_id'] ?? 0);
        
        header('Content-Type: application/json');
        
        if ($patientId === 0 || $homeworkId === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Patient-ID oder Hausaufgaben-ID fehlt']);
            exit;
        }

        try {
            // Prüfen ob Patient existiert
            $patient = $this->patientRepository->findById($patientId);
            if (!$patient) {
                http_response_code(404);
                echo json_encode(['error' => 'Patient nicht gefunden']);
                exit;
            }

            // Hausaufgabe löschen
            $success = $this->homeworkRepository->deletePatientHomework($homeworkId, $patientId);
            
            if (!$success) {
                http_response_code(404);
                echo json_encode(['error' => 'Hausaufgabe nicht gefunden']);
                exit;
            }

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Löschen der Hausaufgabe: ' . $e->getMessage()]);
        }
        exit;
    }

    private function getCategoryEmoji(string $category): string
    {
        return match($category) {
            'bewegung' => '🏃',
            'dehnung' => '🤸',
            'massage' => '💆',
            'kalt_warm' => '🌡️',
            'medikamente' => '💊',
            'fuetterung' => '🍽️',
            'beobachtung' => '👁️',
            'sonstiges' => '📌',
            default => '📌'
        };
    }
}
