<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Repositories\PainPoint3dRepository;

class PainPoint3dController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly PainPoint3dRepository $repo,
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    /** GET /api/patienten/{patient_id}/schmerzpunkte */
    public function index(array $params = []): void
    {
        $patientId  = (int)$params['patient_id'];
        $animalType = $this->get('animal_type', '');

        $this->repo->ensureSchema();

        $points = $animalType
            ? $this->repo->findByPatientAndType($patientId, $animalType)
            : $this->repo->findByPatient($patientId);

        $this->json(['success' => true, 'points' => $points]);
    }

    /** POST /api/patienten/{patient_id}/schmerzpunkte */
    public function store(array $params = []): void
    {
        $this->validateCsrf();

        $patientId = (int)$params['patient_id'];
        $this->repo->ensureSchema();

        $animalType    = $this->sanitize((string)$this->post('animal_type', 'dog'));
        $muscleGroupId = $this->sanitize((string)$this->post('muscle_group_id', ''));
        $side          = $this->sanitize((string)$this->post('side', 'midline'));
        $painLevel     = max(0, min(10, (int)$this->post('pain_level', 0)));
        $painType      = $this->sanitize((string)$this->post('pain_type', ''));
        $notes         = $this->sanitize((string)$this->post('notes', ''));

        if ($muscleGroupId === '') {
            $this->json(['success' => false, 'error' => 'muscle_group_id fehlt'], 422);
            return;
        }

        $authUser = $this->session->getUser();

        $id = $this->repo->upsert($patientId, $animalType, $muscleGroupId, $side, [
            'muscle_group_label' => $this->sanitize((string)$this->post('muscle_group_label', '')),
            'region'             => $this->sanitize((string)$this->post('region', '')),
            'pain_level'         => $painLevel,
            'pain_type'          => $painType,
            'notes'              => $notes ?: null,
            'created_by'         => $authUser['id'] ?? null,
        ]);

        $this->json(['success' => true, 'id' => $id]);
    }

    /** POST /api/patienten/{patient_id}/schmerzpunkte/{id}/loeschen */
    public function delete(array $params = []): void
    {
        $this->validateCsrf();

        $patientId = (int)$params['patient_id'];
        $id        = (int)$params['id'];

        $this->repo->ensureSchema();
        $ok = $this->repo->deleteById($id, $patientId);

        $this->json(['success' => $ok]);
    }

}
