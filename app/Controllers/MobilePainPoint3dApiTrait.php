<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PainPoint3dRepository;

/**
 * Mobile REST API — 3D-Schmerzanalyse (Phase 4).
 *
 * Endpunkte unter /api/mobile/patients/{id}/schmerzpunkte/… und nutzen die
 * privaten Helfer des MobileApiController (cors, requireAuth, json, error,
 * body, input, t). Die Methoden werden per `use` in den Controller gemischt.
 *
 * Antwortformat ist identisch zum Web-Controller (PainPoint3dController),
 * damit der eingebettete 3D-Viewer (anatomy-3d-viewer.js) unverändert
 * dieselben JSON-Strukturen verarbeiten kann:
 *   GET   → { success: true, points: [...] }
 *   POST  → { success: true, id: <int> }
 *   DELETE→ { success: <bool> }
 *
 * Tabelle (tenant-prefixed): patient_3d_pain_points
 */
trait MobilePainPoint3dApiTrait
{
    private ?PainPoint3dRepository $painPoint3dRepo = null;

    private function painPoint3d(): PainPoint3dRepository
    {
        if ($this->painPoint3dRepo === null) {
            $this->painPoint3dRepo = new PainPoint3dRepository($this->db);
        }
        return $this->painPoint3dRepo;
    }

    /** GET /api/mobile/patients/{id}/schmerzpunkte */
    public function patientSchmerzpunkteList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $patientId  = (int)($params['id'] ?? 0);
        if ($patientId <= 0) {
            $this->error('Ungültige Patienten-ID.', 422);
        }
        $animalType = trim((string)($_GET['animal_type'] ?? ''));

        $repo = $this->painPoint3d();
        $repo->ensureSchema();

        $points = $animalType !== ''
            ? $repo->findByPatientAndType($patientId, $animalType)
            : $repo->findByPatient($patientId);

        $this->json(['success' => true, 'points' => $points]);
    }

    /** POST /api/mobile/patients/{id}/schmerzpunkte */
    public function patientSchmerzpunkteSave(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $patientId = (int)($params['id'] ?? 0);
        if ($patientId <= 0) {
            $this->error('Ungültige Patienten-ID.', 422);
        }

        $animalType    = trim((string)$this->input('animal_type', 'dog'));
        $muscleGroupId = trim((string)$this->input('muscle_group_id', ''));
        $side          = trim((string)$this->input('side', 'midline'));
        $painLevel     = max(0, min(10, (int)$this->input('pain_level', 0)));
        $painType      = trim((string)$this->input('pain_type', ''));
        $notes         = trim((string)$this->input('notes', ''));

        if ($muscleGroupId === '') {
            $this->json(['success' => false, 'error' => 'muscle_group_id fehlt'], 422);
        }

        $repo = $this->painPoint3d();
        $repo->ensureSchema();

        $id = $repo->upsert($patientId, $animalType, $muscleGroupId, $side, [
            'muscle_group_label' => trim((string)$this->input('muscle_group_label', '')),
            'region'             => trim((string)$this->input('region', '')),
            'pain_level'         => $painLevel,
            'pain_type'          => $painType,
            'notes'              => $notes !== '' ? $notes : null,
            'created_by'         => (int)($this->authUser['id'] ?? 0) ?: null,
        ]);

        $this->json(['success' => true, 'id' => $id]);
    }

    /** POST /api/mobile/patients/{id}/schmerzpunkte/{pid}/loeschen */
    public function patientSchmerzpunkteDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $patientId = (int)($params['id'] ?? 0);
        $pointId   = (int)($params['pid'] ?? 0);
        if ($patientId <= 0 || $pointId <= 0) {
            $this->error('Ungültige ID.', 422);
        }

        $repo = $this->painPoint3d();
        $repo->ensureSchema();
        $ok = $repo->deleteById($pointId, $patientId);

        $this->json(['success' => $ok]);
    }
}
