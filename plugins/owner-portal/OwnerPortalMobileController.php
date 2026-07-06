<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;
use App\Repositories\SettingsRepository;

/**
 * Mobile API data endpoints for the Flutter owner-portal app.
 * All routes are authenticated via Bearer token (mobile_token in owner_portal_users).
 * No CSRF required — token-based auth only.
 */
class OwnerPortalMobileController extends Controller
{
    private OwnerPortalRepository $repo;
    private OwnerAuthController   $auth;
    private Database              $db;

    public function __construct(
        View       $view,
        Session    $session,
        Config     $config,
        Translator $translator,
        Database   $db
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->db   = $db;
        $this->repo = new OwnerPortalRepository($db);
        $this->auth = new OwnerAuthController($view, $session, $config, $translator, $db);
    }

    /* ──────────────────────────────────────────
     *  Auth helper
     * ────────────────────────────────────────── */

    /** Returns [user, tenantPrefix] or exits with 401 JSON */
    private function guard(): array
    {
        $result = $this->auth->requireMobileAuth();
        if ($result === null) { exit; }
        return $result;
    }

    private function settings(): SettingsRepository
    {
        return new SettingsRepository($this->db);
    }

    private function appUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = rtrim((string)($_SERVER['HTTP_HOST'] ?? 'app.therapano.de'), '/');
        return "{$scheme}://{$host}";
    }

    /** Serve a file from tenant storage, verifying ownership. */
    private function serveTenantFile(int $petId, int $ownerId, string $prefix, array $candidates, bool $imagesOnly = false): void
    {
        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare("SELECT id FROM `{$prefix}patients` WHERE id = ? AND owner_id = ? LIMIT 1");
            $stmt->execute([$petId, $ownerId]);
            if (!$stmt->fetch()) { $this->json(['error' => 'Forbidden'], 403); exit; }
        } catch (\Throwable) { $this->json(['error' => 'Fehler'], 500); exit; }

        $path = null;
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && file_exists($candidate) && is_file($candidate)) {
                $path = $candidate; break;
            }
        }
        if ($path === null) { $this->json(['error' => 'Nicht gefunden'], 404); exit; }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);
        if ($imagesOnly && !str_starts_with($mimeType, 'image/')) { $this->json(['error' => 'Forbidden'], 403); exit; }

        $isInline = str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'video/') || $mimeType === 'application/pdf';
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/dashboard
     * ────────────────────────────────────────── */
    public function dashboard(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];

        $petCount     = 0;
        $openInvoices = 0;
        $upcomingApts = 0;
        $unreadMsgs   = 0;

        try {
            $pdo = $this->db->getPdo();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}patients` WHERE owner_id = ?");
            $stmt->execute([$ownerId]);
            $petCount = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}invoices` WHERE owner_id = ? AND status NOT IN ('paid','bezahlt','cancelled','storniert')");
            $stmt->execute([$ownerId]);
            $openInvoices = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}appointments` WHERE owner_id = ? AND start_at > NOW() AND status NOT IN ('cancelled','noshow','no_show')");
            $stmt->execute([$ownerId]);
            $upcomingApts = (int)$stmt->fetchColumn();
        } catch (\Throwable) {}

        try {
            $msgRepo  = new MessagingRepository($this->db);
            $unreadMsgs = $msgRepo->countUnreadForOwner($ownerId);
        } catch (\Throwable) {}

        $practiceType  = $this->settings()->get('practice_type', 'therapeut');
        $practiceName  = $this->settings()->get('company_name', 'TheraPano');

        $this->json([
            'owner_name'           => trim($user['first_name'] . ' ' . $user['last_name']),
            'practice_name'        => $practiceName,
            'practice_type'        => $practiceType,
            'is_trainer'           => in_array($practiceType, ['trainer', 'dogschool'], true),
            'pet_count'            => $petCount,
            'open_invoices'        => $openInvoices,
            'upcoming_appointments'=> $upcomingApts,
            'unread_messages'      => $unreadMsgs,
        ]);
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/tiere
     * ────────────────────────────────────────── */
    public function pets(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare(
                "SELECT p.id, p.name, p.species, p.breed, p.gender,
                        p.birth_date, p.weight, p.color, p.chip_number,
                        p.photo AS photo_url
                   FROM `{$prefix}patients` p
                  WHERE p.owner_id = ? AND p.deceased_date IS NULL
                  ORDER BY p.name ASC"
            );
            $stmt->execute([$ownerId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->json($rows);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Fehler beim Laden'], 500);
        }
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/tiere/{id}
     * ────────────────────────────────────────── */
    public function petDetail(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];
        $id      = (int)($params['id'] ?? 0);

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM `{$prefix}patients` WHERE id = ? AND owner_id = ? LIMIT 1");
            $stmt->execute([$id, $ownerId]);
            $pet = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$pet) { $this->json(['error' => 'Nicht gefunden'], 404); return; }
        } catch (\Throwable) { $this->json(['error' => 'Fehler'], 500); return; }

        $appUrl = $this->appUrl();

        // Build photo URL (photo column stores filename)
        if (!empty($pet['photo'])) {
            $pet['photo_url'] = "{$appUrl}/api/portal/mobile/tiere/{$id}/foto/" . rawurlencode(basename((string)$pet['photo']));
        } else {
            $pet['photo_url'] = null;
        }

        // Timeline with media
        $timeline = [];
        try {
            $mediaService = new \App\Services\TimelineMediaService();
            $rawTimeline  = $this->repo->getPetTimeline($id);
            foreach ($rawTimeline as $entry) {
                $media = $mediaService->normalizeAttachmentToMedia($entry['attachment'] ?? null, $id);
                $media = array_values(array_map(function($m) use ($id, $appUrl) {
                    $m['url']           = "{$appUrl}/api/portal/mobile/tiere/{$id}/media/" . rawurlencode($m['filename']);
                    $m['thumbnail_url'] = $m['kind'] === 'image' ? $m['url'] : null;
                    return $m;
                }, $media));
                unset($entry['attachment']);
                $entry['media'] = $media;
                $timeline[]     = $entry;
            }
        } catch (\Throwable) {}

        // Exercises (pet_exercises table)
        $exercises = $this->repo->getExercisesByPatient($id);

        // Homework plans with tasks
        $homeworkPlans = [];
        try {
            $plans = $this->repo->getHomeworkPlansByPatient($id);
            foreach ($plans as $plan) {
                $planId          = (int)$plan['id'];
                $plan['tasks']   = $this->repo->getTasksByPlan($planId);
                $plan['checks']  = $this->repo->getChecksForPlan($planId, $ownerId);
                $homeworkPlans[] = $plan;
            }
        } catch (\Throwable) {}

        // Befundbögen for this pet
        $befunde = [];
        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare(
                "SELECT id, datum, status FROM `{$prefix}befundboegen`
                  WHERE patient_id = ? AND status != 'entwurf'
                  ORDER BY datum DESC"
            );
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $b) {
                $b['title']   = 'Befundbogen ' . $b['datum'];
                $b['pdf_url'] = "{$appUrl}/api/portal/mobile/befunde/{$b['id']}/pdf";
                $befunde[]    = $b;
            }
        } catch (\Throwable) {}

        $this->json(array_merge($pet, [
            'timeline'       => $timeline,
            'exercises'      => $exercises,
            'homework_plans' => $homeworkPlans,
            'befunde'        => $befunde,
        ]));
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/tiere/{id}/foto/{file}
     *  Serves the pet's profile photo via Bearer token auth
     * ────────────────────────────────────────── */
    public function petPhotoMobile(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];
        $petId   = (int)($params['id'] ?? 0);
        $file    = basename($params['file'] ?? '');
        if (!$file) { $this->json(['error' => 'Nicht gefunden'], 404); return; }

        $this->serveTenantFile($petId, $ownerId, $prefix, [
            tenant_storage_path('patients/' . $petId . '/' . $file),
            tenant_storage_path('patients/' . $file),
        ], imagesOnly: true);
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/tiere/{id}/media/{file}
     *  Serves timeline attachments (photos, documents) via Bearer token auth
     * ────────────────────────────────────────── */
    public function petMedia(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];
        $petId   = (int)($params['id'] ?? 0);
        $file    = basename($params['file'] ?? '');
        if (!$file) { $this->json(['error' => 'Nicht gefunden'], 404); return; }

        $this->serveTenantFile($petId, $ownerId, $prefix, [
            tenant_storage_path('patients/' . $petId . '/timeline/' . $file),
            tenant_storage_path('patients/' . $petId . '/docs/' . $file),
            tenant_storage_path('patients/' . $petId . '/' . $file),
        ]);
    }

    /* ──────────────────────────────────────────
     *  POST /api/portal/mobile/hausaufgaben/{plan_id}/aufgabe/{task_id}/abhaken
     * ────────────────────────────────────────── */
    public function homeworkTaskToggle(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];
        $planId  = (int)($params['plan_id'] ?? 0);
        $taskId  = (int)($params['task_id'] ?? 0);

        $body    = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $checked = (bool)($body['checked'] ?? true);

        try {
            // Verify this plan belongs to this owner
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare("SELECT id FROM `{$prefix}portal_homework_plans` WHERE id = ? AND owner_id = ? LIMIT 1");
            $stmt->execute([$planId, $ownerId]);
            if (!$stmt->fetch()) { $this->json(['error' => 'Nicht gefunden'], 404); return; }

            $this->repo->setTaskCheck($taskId, $planId, $ownerId, $checked);

            // Log check notification
            try {
                $plan = $this->repo->getHomeworkPlanById($planId);
                $taskStmt = $pdo->prepare("SELECT title FROM `{$prefix}portal_homework_plan_tasks` WHERE id = ? LIMIT 1");
                $taskStmt->execute([$taskId]);
                $taskTitle = (string)($taskStmt->fetchColumn() ?: 'Aufgabe');
                $petName   = $plan ? $this->repo->getPatientNameById((int)($plan['patient_id'] ?? 0)) : '';
                $ownerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

                $this->repo->createCheckNotification([
                    'owner_id'   => $ownerId,
                    'patient_id' => (int)($plan['patient_id'] ?? 0),
                    'task_id'    => $taskId,
                    'plan_id'    => $planId,
                    'task_title' => $taskTitle,
                    'owner_name' => $ownerName,
                    'pet_name'   => $petName,
                    'type'       => 'homework',
                    'checked'    => $checked,
                ]);
            } catch (\Throwable) {}

            $this->json(['ok' => true, 'checked' => $checked]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/termine
     * ────────────────────────────────────────── */
    public function appointments(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare(
                "SELECT a.id, a.title, a.start_at, a.end_at,
                        a.status, a.notes, p.name AS patient_name
                   FROM `{$prefix}appointments` a
                   LEFT JOIN `{$prefix}patients` p ON p.id = a.patient_id
                  WHERE a.owner_id = ?
                  ORDER BY a.start_at DESC
                  LIMIT 50"
            );
            $stmt->execute([$ownerId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->json($rows);
        } catch (\Throwable) {
            $this->json(['error' => 'Fehler'], 500);
        }
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/rechnungen
     * ────────────────────────────────────────── */
    public function invoices(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare(
                "SELECT i.id, i.invoice_number, i.issue_date AS date,
                        i.total_gross AS total_eur, i.status
                   FROM `{$prefix}invoices` i
                  WHERE i.owner_id = ?
                  ORDER BY i.issue_date DESC
                  LIMIT 50"
            );
            $stmt->execute([$ownerId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->json($rows);
        } catch (\Throwable) {
            $this->json(['error' => 'Fehler'], 500);
        }
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/rechnungen/{id}/pdf
     * ────────────────────────────────────────── */
    public function invoicePdf(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];
        $id      = (int)($params['id'] ?? 0);

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare("SELECT id FROM `{$prefix}invoices` WHERE id = ? AND owner_id = ? LIMIT 1");
            $stmt->execute([$id, $ownerId]);
            if (!$stmt->fetch()) { $this->json(['error' => 'Nicht gefunden'], 404); return; }
        } catch (\Throwable) { $this->json(['error' => 'Fehler'], 500); return; }

        $baseUrl = rtrim((string)$_SERVER['HTTP_HOST'], '/');
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $this->json(['url' => "{$scheme}://{$baseUrl}/rechnungen/{$id}/pdf"]);
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/befunde
     * ────────────────────────────────────────── */
    public function befunde(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare(
                "SELECT b.id,
                        CONCAT('Befundbogen ', b.datum) AS title,
                        b.datum AS date,
                        b.created_at,
                        p.name AS patient_name
                   FROM `{$prefix}befundboegen` b
                   LEFT JOIN `{$prefix}patients` p ON p.id = b.patient_id
                  WHERE p.owner_id = ?
                  ORDER BY b.created_at DESC
                  LIMIT 50"
            );
            $stmt->execute([$ownerId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->json($rows);
        } catch (\Throwable) {
            $this->json(['error' => 'Fehler'], 500);
        }
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/befunde/{id}/pdf
     * ────────────────────────────────────────── */
    public function befundPdf(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];
        $id      = (int)($params['id'] ?? 0);

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare(
                "SELECT b.id FROM `{$prefix}befundboegen` b
                   JOIN `{$prefix}patients` p ON p.id = b.patient_id
                  WHERE b.id = ? AND p.owner_id = ? LIMIT 1"
            );
            $stmt->execute([$id, $ownerId]);
            if (!$stmt->fetch()) { $this->json(['error' => 'Nicht gefunden'], 404); return; }
        } catch (\Throwable) { $this->json(['error' => 'Fehler'], 500); return; }

        $baseUrl = rtrim((string)$_SERVER['HTTP_HOST'], '/');
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $this->json(['url' => "{$scheme}://{$baseUrl}/befundboegen/{$id}/pdf"]);
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/hausaufgaben
     * ────────────────────────────────────────── */
    public function homework(array $params = []): void
    {
        [$user, $prefix] = $this->guard();
        $ownerId = (int)$user['owner_id'];

        try {
            $pdo  = $this->db->getPdo();
            $stmt = $pdo->prepare(
                "SELECT hp.id,
                        CONCAT('Übungsplan ', hp.plan_date) AS title,
                        hp.general_notes AS description,
                        hp.plan_date, hp.created_at,
                        p.name AS patient_name
                   FROM `{$prefix}portal_homework_plans` hp
                   LEFT JOIN `{$prefix}patients` p ON p.id = hp.patient_id
                  WHERE hp.owner_id = ? AND hp.status = 'active'
                  ORDER BY hp.created_at DESC
                  LIMIT 30"
            );
            $stmt->execute([$ownerId]);
            $plans = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($plans as &$plan) {
                try {
                    $s = $pdo->prepare(
                        "SELECT id, title AS name, description, frequency, duration
                           FROM `{$prefix}portal_homework_plan_tasks`
                          WHERE plan_id = ?
                          ORDER BY sort_order ASC"
                    );
                    $s->execute([$plan['id']]);
                    $plan['exercises'] = $s->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Throwable) {
                    $plan['exercises'] = [];
                }
            }

            $this->json($plans);
        } catch (\Throwable) {
            $this->json(['error' => 'Fehler'], 500);
        }
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/nachrichten/ungelesen
     * ────────────────────────────────────────── */
    public function unread(array $params = []): void
    {
        [$user] = $this->guard();
        $ownerId = (int)$user['owner_id'];

        try {
            $msgRepo = new MessagingRepository($this->db);
            $this->json(['count' => $msgRepo->countUnreadForOwner($ownerId)]);
        } catch (\Throwable) {
            $this->json(['count' => 0]);
        }
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/nachrichten
     * ────────────────────────────────────────── */
    public function threads(array $params = []): void
    {
        [$user] = $this->guard();
        $ownerId = (int)$user['owner_id'];

        try {
            $msgRepo = new MessagingRepository($this->db);
            $threads = $msgRepo->getThreadsByOwner($ownerId);
            $this->json($threads);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/nachrichten/{id}
     * ────────────────────────────────────────── */
    public function threadShow(array $params = []): void
    {
        [$user] = $this->guard();
        $ownerId = (int)$user['owner_id'];
        $id      = (int)($params['id'] ?? 0);

        try {
            $msgRepo = new MessagingRepository($this->db);
            $thread  = $msgRepo->getThreadById($id);
            if (!$thread || (int)$thread['owner_id'] !== $ownerId) {
                $this->json(['error' => 'Nicht gefunden'], 404); return;
            }
            $messages = $msgRepo->getMessages($id);
            $msgRepo->markThreadReadByOwner($id);
            $this->json(array_merge($thread, ['messages' => $messages]));
        } catch (\Throwable $e) {
            $this->json(['error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /* ──────────────────────────────────────────
     *  POST /api/portal/mobile/nachrichten/{id}/antworten
     * ────────────────────────────────────────── */
    public function threadReply(array $params = []): void
    {
        [$user] = $this->guard();
        $ownerId = (int)$user['owner_id'];
        $id      = (int)($params['id'] ?? 0);

        $body    = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $message = trim((string)($body['message'] ?? $body['body'] ?? ''));

        if ($message === '') { $this->json(['error' => 'Nachricht darf nicht leer sein'], 400); return; }

        try {
            $msgRepo = new MessagingRepository($this->db);
            $thread  = $msgRepo->getThreadById($id);
            if (!$thread || (int)$thread['owner_id'] !== $ownerId) {
                $this->json(['error' => 'Thread nicht gefunden'], 404); return;
            }
            $msgRepo->addMessage($id, 'owner', $ownerId, $message);
            $msgRepo->touchThread($id);
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /* ──────────────────────────────────────────
     *  POST /api/portal/mobile/nachrichten/neu
     * ────────────────────────────────────────── */
    public function threadNew(array $params = []): void
    {
        [$user] = $this->guard();
        $ownerId = (int)$user['owner_id'];

        $body    = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $subject = trim((string)($body['subject'] ?? $body['betreff'] ?? ''));
        $message = trim((string)($body['message'] ?? $body['body'] ?? ''));

        if ($subject === '' || $message === '') {
            $this->json(['error' => 'Betreff und Nachricht erforderlich'], 400); return;
        }

        try {
            $msgRepo  = new MessagingRepository($this->db);
            $threadId = $msgRepo->createThread($ownerId, $subject, 'owner');
            $msgRepo->addMessage($threadId, 'owner', $ownerId, $message);
            $this->json(['ok' => true, 'thread_id' => $threadId]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Fehler: ' . $e->getMessage()], 500);
        }
    }

    /* ──────────────────────────────────────────
     *  GET /api/portal/mobile/profil
     * ────────────────────────────────────────── */
    public function profile(array $params = []): void
    {
        [$user, $prefix] = $this->guard();

        $settings     = $this->settings();
        $practiceType = $settings->get('practice_type', 'therapeut');

        $this->json([
            'id'           => (int)$user['id'],
            'owner_id'     => (int)$user['owner_id'],
            'email'        => $user['email'],
            'name'         => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'first_name'   => $user['first_name'] ?? '',
            'last_name'    => $user['last_name']  ?? '',
            'phone'        => $user['phone']       ?? '',
            'practice_type'=> $practiceType,
            'is_trainer'   => in_array($practiceType, ['trainer', 'dogschool'], true),
        ]);
    }

    /* ──────────────────────────────────────────
     *  POST /api/portal/mobile/profil/passwort
     * ────────────────────────────────────────── */
    public function changePassword(array $params = []): void
    {
        [$user] = $this->guard();

        $body    = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $current = (string)($body['current_password'] ?? '');
        $new     = (string)($body['password'] ?? $body['new_password'] ?? '');
        $confirm = (string)($body['confirm_password'] ?? $body['password_confirmation'] ?? '');

        if (strlen($new) < 8) { $this->json(['error' => 'Passwort muss mindestens 8 Zeichen lang sein.'], 400); return; }
        if ($new !== $confirm) { $this->json(['error' => 'Passwörter stimmen nicht überein.'], 400); return; }

        try {
            $pdo   = $this->db->getPdo();
            $table = $this->db->prefix('owner_portal_users');
            $stmt  = $pdo->prepare("SELECT password_hash FROM `{$table}` WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$user['id']]);
            $row   = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable) { $row = null; }

        if (!$row || !password_verify($current, (string)($row['password_hash'] ?? ''))) {
            $this->json(['error' => 'Aktuelles Passwort ist falsch.'], 403); return;
        }

        try {
            $pdo   = $this->db->getPdo();
            $table = $this->db->prefix('owner_portal_users');
            $stmt  = $pdo->prepare("UPDATE `{$table}` SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), (int)$user['id']]);
        } catch (\Throwable) {
            $this->json(['error' => 'Datenbankfehler.'], 500); return;
        }

        $this->json(['success' => true]);
    }
}
