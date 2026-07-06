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
                        p.date_of_birth, p.weight, p.color, p.chip_number,
                        p.foto_url AS photo_url
                   FROM `{$prefix}patients` p
                  WHERE p.owner_id = ? AND p.deceased_at IS NULL
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
            $stmt = $pdo->prepare(
                "SELECT p.id, p.name, p.species, p.breed, p.gender,
                        p.date_of_birth, p.weight, p.color, p.chip_number,
                        p.foto_url AS photo_url, p.notes,
                        p.created_at
                   FROM `{$prefix}patients` p
                  WHERE p.id = ? AND p.owner_id = ? LIMIT 1"
            );
            $stmt->execute([$id, $ownerId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) { $this->json(['error' => 'Nicht gefunden'], 404); return; }
            $this->json($row);
        } catch (\Throwable) {
            $this->json(['error' => 'Fehler'], 500);
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
                "SELECT i.id, i.invoice_number, i.invoice_date AS date,
                        i.total_gross AS total, i.status
                   FROM `{$prefix}invoices` i
                  WHERE i.owner_id = ?
                  ORDER BY i.invoice_date DESC
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
                "SELECT b.id, b.title, b.created_at,
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
                "SELECT hp.id, hp.title, hp.description, hp.created_at,
                        p.name AS patient_name
                   FROM `{$prefix}homework_plans` hp
                   LEFT JOIN `{$prefix}patients` p ON p.id = hp.patient_id
                  WHERE p.owner_id = ?
                  ORDER BY hp.created_at DESC
                  LIMIT 30"
            );
            $stmt->execute([$ownerId]);
            $plans = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($plans as &$plan) {
                try {
                    $s = $pdo->prepare(
                        "SELECT id, name, repetitions, sets, duration
                           FROM `{$prefix}homework_exercises`
                          WHERE homework_plan_id = ?
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
