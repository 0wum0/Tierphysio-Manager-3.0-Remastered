<?php

declare(strict_types=1);

namespace Plugins\PatientIntake;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;
use App\Repositories\SettingsRepository;

class IntakeController extends Controller
{
    private IntakeRepository $repo;
    private IntakeMailService $mailer;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        Database $db,
        SettingsRepository $settingsRepository
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->repo   = new IntakeRepository($db);
        $this->mailer = new IntakeMailService($settingsRepository);
    }

    /* ─────────────────────────────────────────────────────────
       PUBLIC: Multi-Step Wizard (no auth)
    ───────────────────────────────────────────────────────── */

    public function form(array $params = []): void
    {
        $this->renderPublic('@patient-intake/form.twig', [
            'page_title' => 'Patientenanmeldung',
        ]);
    }

    public function submit(array $params = []): void
    {
        /* Basic honeypot spam protection */
        if (!empty($_POST['website'])) {
            $this->redirect('/anmeldung/danke');
            return;
        }

        /* CSRF validation */
        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!$this->session->validateCsrfToken($csrfToken)) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['ok' => false, 'errors' => ['Ungültige Anfrage. Bitte Seite neu laden.']]);
            exit;
        }

        /* IP-based rate limiting: max 5 submissions per 10 minutes */
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateKey = 'intake_rate_' . md5($ip);
        $window  = 600;
        $limit   = 5;
        $now     = time();
        $history = $_SESSION[$rateKey] ?? [];
        $history = array_filter($history, fn(int $t) => $now - $t < $window);
        if (count($history) >= $limit) {
            header('Content-Type: application/json');
            http_response_code(429);
            echo json_encode(['ok' => false, 'errors' => ['Zu viele Anfragen. Bitte warten Sie einige Minuten.']]);
            exit;
        }
        $history[] = $now;
        $_SESSION[$rateKey] = array_values($history);

        /* DSGVO-Pflicht-Haken: ohne aktives Häkchen keine Anmeldung */
        if ((string)($_POST['consent'] ?? '') !== '1') {
            header('Content-Type: application/json');
            echo json_encode([
                'ok'     => false,
                'errors' => ['Bitte bestätigen Sie die Datenschutzerklärung, um die Anmeldung abzuschließen.'],
            ]);
            exit;
        }

        $ownerData = $this->buildOwnerData();
        $dogs      = $this->collectDogs();
        $errors    = $this->validateOwnerAndDogs($ownerData, $dogs);

        if (!empty($errors)) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'errors' => $errors]);
            exit;
        }

        /* Pro Hund eine Submission-Zeile anlegen — Owner-Daten werden
         * auf jeder Zeile dupliziert, damit Admin-Inbox & Accept/Reject
         * pro Hund unverändert funktionieren. Reason & appointment_wish
         * gehören zur Anmeldung insgesamt und werden ebenfalls gespiegelt. */
        $reason          = $this->sanitize($this->post('reason', ''));
        $appointmentWish = $this->sanitize($this->post('appointment_wish', ''));
        $notes           = $this->sanitize($this->post('notes', ''));
        $ip              = $_SERVER['REMOTE_ADDR'] ?? '';
        $now             = date('Y-m-d H:i:s');

        $ids = [];
        foreach ($dogs as $idx => $dog) {
            $photoFile = $this->handleDogPhotoUpload($idx);

            $row = array_merge($ownerData, [
                'patient_name'       => $dog['name'],
                'patient_species'    => $dog['species'] ?: 'Hund',
                'patient_breed'      => $dog['breed'],
                'patient_gender'     => $dog['gender'],
                'patient_birth_date' => $dog['birth_date'] ?: null,
                'patient_color'      => $dog['color'],
                'patient_chip'       => $dog['chip'],
                'patient_photo'      => $photoFile,
                'reason'             => $reason,
                'appointment_wish'   => $appointmentWish,
                'notes'              => $notes,
                'status'             => 'neu',
                'ip_address'         => $ip,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            $ids[] = $this->repo->create($row);
        }

        /* Fire-and-forget Notifications pro Anmeldung — für Mails reicht
         * die erste Zeile, da Owner identisch ist. Admin bekommt weiter
         * eine Mail pro Hund, damit die Badge-Zählung stimmt. */
        foreach ($ids as $id) {
            $submission = $this->repo->findById($id);
            if ($submission) {
                try { $this->mailer->sendNewSubmissionNotification($submission); } catch (\Throwable) {}
            }
        }
        $firstSubmission = $this->repo->findById($ids[0] ?? 0);
        if ($firstSubmission) {
            try { $this->mailer->sendOwnerConfirmation($firstSubmission); } catch (\Throwable) {}
        }

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'ids' => $ids, 'count' => count($ids)]);
        exit;
    }

    public function thankYou(array $params = []): void
    {
        $this->renderPublic('@patient-intake/thankyou.twig', [
            'page_title' => 'Anmeldung erhalten',
        ]);
    }

    /* ─────────────────────────────────────────────────────────
       ADMIN: Eingangsmeldungen
    ───────────────────────────────────────────────────────── */

    public function inbox(array $params = []): void
    {
        $status = $this->get('status', '');
        $page   = max(1, (int)$this->get('page', 1));

        $result = $this->repo->getPaginated($page, 15, $status);

        $counts = [
            'neu'           => $this->repo->countByStatus('neu'),
            'in_bearbeitung'=> $this->repo->countByStatus('in_bearbeitung'),
            'uebernommen'   => $this->repo->countByStatus('uebernommen'),
            'abgelehnt'     => $this->repo->countByStatus('abgelehnt'),
        ];

        $this->render('@patient-intake/inbox.twig', [
            'page_title'   => 'Eingangsmeldungen',
            'submissions'  => $result['items'],
            'pagination'   => $result,
            'counts'       => $counts,
            'active_status'=> $status,
        ]);
    }

    public function show(array $params = []): void
    {
        $submission = $this->repo->findById((int)$params['id']);
        if (!$submission) {
            $this->abort(404);
        }

        /* Auto-mark as in_bearbeitung when opened */
        if ($submission['status'] === 'neu') {
            $this->repo->updateStatus((int)$params['id'], 'in_bearbeitung');
            $submission['status'] = 'in_bearbeitung';
        }

        $this->render('@patient-intake/show.twig', [
            'page_title'  => 'Anmeldung: ' . $submission['patient_name'],
            'submission'  => $submission,
        ]);
    }

    public function accept(array $params = []): void
    {
        /* CSRF validation */
        $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$this->session->validateCsrfToken($token)) {
            $this->jsonError('CSRF-Token ungültig', 403);
            return;
        }

        /* AJAX: create owner + patient from submission */
        $submission = $this->repo->findById((int)$params['id']);
        if (!$submission) {
            $this->jsonError('Nicht gefunden', 404);
            return;
        }

        try {
            $app = \App\Core\Application::getInstance();
            $db  = $app->getContainer()->get(Database::class);
            $pdo = $db->getPdo();

            /* 1. Find or create owner — use PDO directly to avoid any wrapper issues */
            $stmt = $pdo->prepare("SELECT id FROM `{$db->prefix('owners')}` WHERE email = ? LIMIT 1");
            $stmt->execute([$submission['owner_email']]);
            $existingOwner = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingOwner) {
                $ownerId = (int)$existingOwner['id'];
            } else {
                /* 11 Spalten ↔ 11 Werte: 7 Platzhalter für die Besitzer-Daten
                 * + 4 Literale (gdpr_consent=1, gdpr_consent_at=NOW(),
                 *   created_at=NOW(), updated_at=NOW()).  Siehe identischen
                 * Fix in plugins/patient-invite/InviteController.php. */
                $ins = $pdo->prepare(
                    "INSERT INTO `{$db->prefix('owners')}` (first_name, last_name, email, phone, street, zip, city, gdpr_consent, gdpr_consent_at, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,1,NOW(),NOW(),NOW())"
                );
                $ins->execute([
                    $submission['owner_first_name'],
                    $submission['owner_last_name'],
                    $submission['owner_email'],
                    $submission['owner_phone'],
                    $submission['owner_street'],
                    $submission['owner_zip'],
                    $submission['owner_city'],
                ]);
                $ownerId = (int)$pdo->lastInsertId();
            }

            /* 2. Copy photo from intake storage to patients storage */
            $photoFilename = '';
            if (!empty($submission['patient_photo'])) {
                $src    = tenant_storage_path('intake/' . $submission['patient_photo']);
                $dstDir = tenant_storage_path('patients');
                if (!is_dir($dstDir)) {
                    mkdir($dstDir, 0755, true);
                }
                $dst = $dstDir . '/' . $submission['patient_photo'];
                if (file_exists($src)) {
                    copy($src, $dst);
                    $photoFilename = $submission['patient_photo'];
                }
            }

            /* 3. Create patient — use PDO directly */
            $ins2 = $pdo->prepare(
                "INSERT INTO `{$db->prefix('patients')}` (name, species, breed, gender, birth_date, color, chip_number, owner_id, photo, status, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,'aktiv',NOW(),NOW())"
            );
            $allowedGenders = ['männlich', 'weiblich', 'kastriert', 'sterilisiert', 'unbekannt'];
            $gender = in_array($submission['patient_gender'] ?? '', $allowedGenders, true)
                ? $submission['patient_gender']
                : 'unbekannt';

            $ins2->execute([
                $submission['patient_name'],
                $submission['patient_species'],
                $submission['patient_breed'],
                $gender,
                $submission['patient_birth_date'] ?: null,
                $submission['patient_color'],
                $submission['patient_chip'],
                $ownerId,
                $photoFilename,
            ]);
            $patientId = (int)$pdo->lastInsertId();

            /* 3. Mark submission as accepted */
            $this->repo->updateStatus((int)$params['id'], 'uebernommen', [
                'accepted_patient_id' => $patientId,
                'accepted_owner_id'   => $ownerId,
            ]);

            header('Content-Type: application/json');
            echo json_encode([
                'ok'         => true,
                'patient_id' => $patientId,
                'owner_id'   => $ownerId,
            ]);
        } catch (\Throwable $e) {
            error_log('[PatientIntake] accept error: ' . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Fehler beim Übernehmen. Bitte erneut versuchen.']);
        }
        exit;
    }

    public function reject(array $params = []): void
    {
        /* CSRF validation */
        $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$this->session->validateCsrfToken($token)) {
            $this->jsonError('CSRF-Token ungültig', 403);
            return;
        }

        $submission = $this->repo->findById((int)$params['id']);
        if (!$submission) {
            $this->jsonError('Nicht gefunden', 404);
            return;
        }

        $this->repo->updateStatus((int)$params['id'], 'abgelehnt');

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    public function updateStatus(array $params = []): void
    {
        /* CSRF validation */
        $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$this->session->validateCsrfToken($token)) {
            $this->jsonError('CSRF-Token ungültig', 403);
            return;
        }

        $submission = $this->repo->findById((int)$params['id']);
        if (!$submission) {
            $this->jsonError('Nicht gefunden', 404);
            return;
        }

        $allowed = ['neu', 'in_bearbeitung', 'uebernommen', 'abgelehnt'];
        $status  = $_POST['status'] ?? '';

        if (!in_array($status, $allowed, true)) {
            header('Content-Type: application/json');
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Ungültiger Status']);
            exit;
        }

        $this->repo->updateStatus((int)$params['id'], $status);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    /* ─────────────────────────────────────────────────────────
       API: Notification count for header bell
    ───────────────────────────────────────────────────────── */

    public function apiNotifications(array $params = []): void
    {
        try {
            $count  = $this->repo->countUnread();
            $latest = $this->repo->getLatestUnread(5);
            header('Content-Type: application/json');
            echo json_encode(['count' => $count, 'items' => $latest]);
        } catch (\Throwable) {
            header('Content-Type: application/json');
            echo json_encode(['count' => 0, 'items' => []]);
        }
        exit;
    }

    /* ─────────────────────────────────────────────────────────
       Helpers
    ───────────────────────────────────────────────────────── */

    private function renderPublic(string $template, array $data = []): void
    {
        /* Render without auth — uses public layout */
        $this->view->render($template, array_merge([
            'csrf_token' => $this->session->generateCsrfToken(),
        ], $data));
    }

    /* ─────────────────────────────────────────────────────────
       Multi-Hund-Helfer (neue Anmeldung)
    ───────────────────────────────────────────────────────── */

    /** Nur die Besitzer-Felder — werden pro Hund dupliziert. */
    private function buildOwnerData(): array
    {
        return [
            'owner_first_name' => $this->sanitize($this->post('owner_first_name', '')),
            'owner_last_name'  => $this->sanitize($this->post('owner_last_name', '')),
            'owner_email'      => filter_var($this->post('owner_email', ''), FILTER_SANITIZE_EMAIL),
            'owner_phone'      => $this->sanitize($this->post('owner_phone', '')),
            'owner_street'     => $this->sanitize($this->post('owner_street', '')),
            'owner_zip'        => $this->sanitize($this->post('owner_zip', '')),
            'owner_city'       => $this->sanitize($this->post('owner_city', '')),
        ];
    }

    /**
     * Liest alle übergebenen Hunde aus `$_POST['dogs']` (Array-Form),
     * fällt auf Legacy-Felder `patient_name`/`patient_species` zurück
     * wenn keine Array-Notation gesendet wurde. Filtert leere Karten.
     */
    private function collectDogs(): array
    {
        $raw = $_POST['dogs'] ?? null;

        /* Legacy-Fallback: altes Formular ohne dogs[]-Array */
        if (!is_array($raw) || $raw === []) {
            $legacyName = trim((string)$this->post('patient_name', ''));
            if ($legacyName === '') return [];
            return [[
                'name'       => $legacyName,
                'species'    => $this->sanitize($this->post('patient_species', 'Hund')),
                'breed'      => $this->sanitize($this->post('patient_breed', '')),
                'gender'     => $this->sanitize($this->post('patient_gender', '')),
                'birth_date' => $this->post('patient_birth_date') ?: null,
                'color'      => $this->sanitize($this->post('patient_color', '')),
                'chip'       => $this->sanitize($this->post('patient_chip', '')),
            ]];
        }

        $dogs = [];
        foreach ($raw as $idx => $d) {
            if (!is_array($d)) continue;
            $name = trim((string)($d['name'] ?? ''));
            if ($name === '') continue; /* leere Karten überspringen */
            $dogs[(int)$idx] = [
                'name'       => $this->sanitize($name),
                'species'    => $this->sanitize((string)($d['species']    ?? 'Hund')),
                'breed'      => $this->sanitize((string)($d['breed']      ?? '')),
                'gender'     => $this->sanitize((string)($d['gender']     ?? '')),
                'birth_date' => ($d['birth_date'] ?? '') ?: null,
                'color'      => $this->sanitize((string)($d['color']      ?? '')),
                'chip'       => $this->sanitize((string)($d['chip']       ?? '')),
            ];
        }
        return $dogs;
    }

    /**
     * Validiert Owner + mindestens einen Hund + Anliegen.
     * @return string[] Fehler-Strings (leer = alles ok)
     */
    private function validateOwnerAndDogs(array $owner, array $dogs): array
    {
        $errors = [];

        if (empty($owner['owner_first_name'])) $errors[] = 'Vorname des Besitzers ist erforderlich.';
        if (empty($owner['owner_last_name']))  $errors[] = 'Nachname des Besitzers ist erforderlich.';
        if (empty($owner['owner_email']) || !filter_var($owner['owner_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Gültige E-Mail-Adresse ist erforderlich.';
        }
        if (empty($owner['owner_phone'])) $errors[] = 'Telefonnummer ist erforderlich.';

        if (count($dogs) === 0) {
            $errors[] = 'Bitte geben Sie mindestens einen Hund an.';
        }

        if (empty(trim((string)$this->post('reason', '')))) {
            $errors[] = 'Bitte beschreiben Sie Ihr Anliegen.';
        }

        return $errors;
    }

    /**
     * Verarbeitet ein Hund-Foto aus dem Multi-Upload-Array
     * `$_FILES['dog_photos']`, das pro Hund-Index einen Slot hat.
     * Liefert den generierten Dateinamen oder '' wenn kein Foto
     * hochgeladen wurde / Validation fehlschlug.
     */
    private function handleDogPhotoUpload(int $idx): string
    {
        if (empty($_FILES['dog_photos']['name'][$idx])) return '';
        if (($_FILES['dog_photos']['error'][$idx] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';

        $size = (int)($_FILES['dog_photos']['size'][$idx] ?? 0);
        if ($size <= 0 || $size > 8 * 1024 * 1024) return '';

        $tmp = (string)$_FILES['dog_photos']['tmp_name'][$idx];
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmp) ?: '';

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mimeType, $allowed, true)) return '';

        $ext = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'gif',
        };

        $dir = tenant_storage_path('intake');
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }

        $filename = 'intake_' . bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . '/' . $filename)) return '';
        return $filename;
    }

    /* ─────────────────────────────────────────────────────────
       Legacy-Helfer (einzel-Hund) — bleiben für Abwärtskompat.
    ───────────────────────────────────────────────────────── */

    private function buildSubmissionData(): array
    {
        return [
            'owner_first_name'  => $this->sanitize($this->post('owner_first_name', '')),
            'owner_last_name'   => $this->sanitize($this->post('owner_last_name', '')),
            'owner_email'       => filter_var($this->post('owner_email', ''), FILTER_SANITIZE_EMAIL),
            'owner_phone'       => $this->sanitize($this->post('owner_phone', '')),
            'owner_street'      => $this->sanitize($this->post('owner_street', '')),
            'owner_zip'         => $this->sanitize($this->post('owner_zip', '')),
            'owner_city'        => $this->sanitize($this->post('owner_city', '')),
            'patient_name'      => $this->sanitize($this->post('patient_name', '')),
            'patient_species'   => $this->sanitize($this->post('patient_species', '')),
            'patient_breed'     => $this->sanitize($this->post('patient_breed', '')),
            'patient_gender'    => $this->sanitize($this->post('patient_gender', '')),
            'patient_birth_date'=> $this->post('patient_birth_date') ?: null,
            'patient_color'     => $this->sanitize($this->post('patient_color', '')),
            'patient_chip'      => $this->sanitize($this->post('patient_chip', '')),
            'reason'            => $this->sanitize($this->post('reason', '')),
            'appointment_wish'  => $this->sanitize($this->post('appointment_wish', '')),
            'notes'             => $this->sanitize($this->post('notes', '')),
            'status'            => 'neu',
        ];
    }

    private function validateSubmission(array $data): array
    {
        $errors = [];

        if (empty($data['owner_first_name'])) $errors[] = 'Vorname des Besitzers ist erforderlich.';
        if (empty($data['owner_last_name']))  $errors[] = 'Nachname des Besitzers ist erforderlich.';
        if (empty($data['owner_email']) || !filter_var($data['owner_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Gültige E-Mail-Adresse ist erforderlich.';
        }
        if (empty($data['owner_phone']))      $errors[] = 'Telefonnummer ist erforderlich.';
        if (empty($data['patient_name']))     $errors[] = 'Name des Tieres ist erforderlich.';
        if (empty($data['patient_species']))  $errors[] = 'Tierart ist erforderlich.';
        if (empty($data['reason']))           $errors[] = 'Bitte beschreiben Sie Ihr Anliegen.';

        return $errors;
    }

    private function handlePhotoUpload(): string
    {
        $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize   = 8 * 1024 * 1024; /* 8 MB */
        $file      = $_FILES['patient_photo'];

        if ($file['size'] > $maxSize) {
            return '';
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowed, true)) {
            return '';
        }

        $ext  = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'gif',
        };

        $dir = tenant_storage_path('intake');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'intake_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $dest     = $dir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return $filename;
        }

        return '';
    }

    public function servePhoto(array $params = []): void
    {
        $file = basename($this->sanitize($params['file']));
        $path = tenant_storage_path('intake/' . $file);

        if (!file_exists($path) || !is_file($path)) {
            $this->abort(404);
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        if (!str_starts_with($mimeType, 'image/')) {
            $this->abort(403);
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }

    private function jsonError(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }
}
