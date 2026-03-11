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
            error_log('API getHomeworkTemplates - Starting query');
            $templates = $this->homeworkRepository->findAllTemplates();
            error_log('API getHomeworkTemplates - Found ' . count($templates) . ' templates');
            
            // Kategorie-Emoji hinzufügen
            foreach ($templates as &$template) {
                $template['category_emoji'] = $this->getCategoryEmoji($template['category'] ?? 'sonstiges');
            }
            
            echo json_encode($templates);
        } catch (\Exception $e) {
            error_log('API getHomeworkTemplates - Exception: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Fehler beim Laden der Templates: ' . $e->getMessage()]);
        }
        exit;
    }

    public function getPatientHomework(array $params = []): void
    {
        $patientId = (int)($params['patient_id'] ?? 0);
        
        header('Content-Type: application/json');
        
        try {
            error_log('API getPatientHomework - Patient ID: ' . $patientId);
            
            if ($patientId === 0) {
                echo json_encode([]);
                exit;
            }

            // Prüfen ob Patient existiert und Zugriff erlaubt
            $patient = $this->patientRepository->findById($patientId);
            if (!$patient) {
                error_log('API getPatientHomework - Patient not found: ' . $patientId);
                http_response_code(404);
                echo json_encode(['error' => 'Patient nicht gefunden']);
                exit;
            }

            $homework = $this->homeworkRepository->findPatientHomework($patientId);
            error_log('API getPatientHomework - Found ' . count($homework) . ' homework items');
            
            // Kategorie-Emoji hinzufügen
            foreach ($homework as &$hw) {
                $hw['category_emoji'] = $this->getCategoryEmoji($hw['category'] ?? 'sonstiges');
            }
            
            echo json_encode($homework);
        } catch (\Exception $e) {
            error_log('API getPatientHomework - Exception: ' . $e->getMessage());
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
            error_log('API createPatientHomework - Starting for patient ID: ' . $patientId);
            
            // Prüfen ob Patient existiert
            $patient = $this->patientRepository->findById($patientId);
            if (!$patient) {
                error_log('API createPatientHomework - Patient not found: ' . $patientId);
                http_response_code(404);
                echo json_encode(['error' => 'Patient nicht gefunden']);
                exit;
            }

            $data = $_POST;
            error_log('API createPatientHomework - Raw POST data: ' . print_r($_POST, true));
            error_log('API createPatientHomework - Processed data: ' . print_r($data, true));
            
            // Template-Prüfung
            $templateId = (int)($data['homework_template_id'] ?? 0);
            if ($templateId > 0) {
                error_log('API createPatientHomework - Looking for template ID: ' . $templateId);
                $template = $this->homeworkRepository->findTemplateById($templateId);
                if (!$template) {
                    error_log('API createPatientHomework - Template not found for ID: ' . $templateId);
                    http_response_code(400);
                    echo json_encode(['error' => 'Template nicht gefunden']);
                    exit;
                }
                
                error_log('API createPatientHomework - Template found: ' . print_r($template, true));
                
                // Template-Daten übernehmen
                $data['title'] = $template['title'];
                $data['description'] = $template['description'];
                $data['category'] = $template['category'];
                $data['category_emoji'] = $template['category_emoji'];
            } else {
                // Wenn keine Template-ID, aber "custom" ausgewählt, dann prüfe ob Titel und Beschreibung manuell eingegeben wurden
                if (empty($data['title']) || empty($data['description'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Titel und Beschreibung sind erforderlich. Titel: "' . ($data['title'] ?? 'empty') . '", Beschreibung: "' . ($data['description'] ?? 'empty') . '". Bitte wählen Sie eine Vorlage ODER füllen Sie die Felder "Titel der Hausaufgabe" und "Beschreibung" aus.']);
                    exit;
                }
                // Custom Emoji setzen
                $data['category_emoji'] = $this->getCategoryEmoji($data['category'] ?? 'sonstiges');
            }

            // Pflichtfelder prüfen
            if (empty($data['title']) || empty($data['description'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Titel und Beschreibung sind erforderlich. Titel: "' . ($data['title'] ?? 'empty') . '", Beschreibung: "' . ($data['description'] ?? 'empty') . '"']);
                exit;
            }

            // Hausaufgabe erstellen
            $homeworkData = [
                'patient_id' => $patientId,
                'homework_template_id' => $templateId > 0 ? $templateId : null,
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'] ?? 'sonstiges',
                'category_emoji' => $data['category_emoji'] ?? '📌',
                'frequency' => $data['frequency'] ?? 'daily',
                'duration_value' => (int)($data['duration_value'] ?? 10),
                'duration_unit' => $data['duration_unit'] ?? 'minutes',
                'start_date' => $data['start_date'] ?? date('Y-m-d'),
                'end_date' => !empty($data['end_date']) ? $data['end_date'] : null,
                'therapist_notes' => $data['therapist_notes'] ?? '',
                'assigned_by' => Auth::user()['id'],
                'status' => 'pending'
            ];
            
            error_log('API createPatientHomework - Final homework data: ' . print_r($homeworkData, true));
            
            $homeworkId = $this->homeworkRepository->createPatientHomework($homeworkData);

            echo json_encode(['success' => true, 'homework_id' => $homeworkId]);
        } catch (\Exception $e) {
            error_log('API createPatientHomework - Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('API createPatientHomework - Exception trace: ' . $e->getTraceAsString());
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
