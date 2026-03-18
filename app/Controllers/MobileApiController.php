<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Repositories\UserRepository;
use App\Repositories\PatientRepository;
use App\Repositories\OwnerRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\SettingsRepository;

/**
 * Mobile REST API — Bearer token authentication.
 * All responses: JSON, CORS headers for Flutter app.
 */
class MobileApiController
{
    private Database          $db;
    private UserRepository    $users;
    private PatientRepository $patients;
    private OwnerRepository   $owners;
    private InvoiceRepository $invoices;
    private SettingsRepository $settings;

    private ?array $authUser = null;

    public function __construct(
        Database          $db,
        UserRepository    $userRepository,
        PatientRepository $patientRepository,
        OwnerRepository   $ownerRepository,
        InvoiceRepository $invoiceRepository,
        SettingsRepository $settingsRepository
    ) {
        $this->db       = $db;
        $this->users    = $userRepository;
        $this->patients = $patientRepository;
        $this->owners   = $ownerRepository;
        $this->invoices = $invoiceRepository;
        $this->settings = $settingsRepository;
    }

    /* ══════════════════════════════════════════════════════
       HELPERS
    ══════════════════════════════════════════════════════ */

    private function cors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    private function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function error(string $message, int $status = 400): never
    {
        $this->json(['error' => $message], $status);
    }

    private function body(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return $_POST;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function input(string $key, mixed $default = null): mixed
    {
        $body = $this->body();
        return $body[$key] ?? $_GET[$key] ?? $default;
    }

    private function requireAuth(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            $this->error('Kein Token angegeben.', 401);
        }
        $token = trim($m[1]);
        $row   = $this->db->fetch(
            "SELECT t.*, u.id AS user_id, u.name, u.email, u.role, u.active
             FROM mobile_api_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token = ?
               AND (t.expires_at IS NULL OR t.expires_at > NOW())",
            [$token]
        );
        if (!$row || (int)$row['active'] !== 1) {
            $this->error('Ungültiger oder abgelaufener Token.', 401);
        }
        $this->db->execute(
            "UPDATE mobile_api_tokens SET last_used = NOW() WHERE token = ?",
            [$token]
        );
        $this->authUser = $row;
        return $row;
    }

    private function requireAdmin(): void
    {
        if (($this->authUser['role'] ?? '') !== 'admin') {
            $this->error('Keine Berechtigung.', 403);
        }
    }

    /* ══════════════════════════════════════════════════════
       AUTH ENDPOINTS
    ══════════════════════════════════════════════════════ */

    public function login(array $params = []): void
    {
        $this->cors();
        $email      = trim((string)$this->input('email', ''));
        $password   = (string)$this->input('password', '');
        $deviceName = trim((string)$this->input('device_name', 'Flutter App'));

        if (!$email || !$password) {
            $this->error('E-Mail und Passwort erforderlich.');
        }

        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, $user['password'])) {
            $this->error('Ungültige Anmeldedaten.', 401);
        }
        if ((int)$user['active'] !== 1) {
            $this->error('Konto ist deaktiviert.', 403);
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

        $this->db->execute(
            "INSERT INTO mobile_api_tokens (user_id, token, device_name, expires_at, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$user['id'], $token, $deviceName, $expiresAt]
        );
        $this->users->updateLastLogin($user['id']);

        $this->json([
            'token'      => $token,
            'expires_at' => $expiresAt,
            'user'       => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);
    }

    public function logout(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        preg_match('/^Bearer\s+(.+)$/i', $header, $m);
        $this->db->execute("DELETE FROM mobile_api_tokens WHERE token = ?", [trim($m[1])]);
        $this->json(['success' => true]);
    }

    public function me(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();
        $this->json([
            'id'    => $user['user_id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);
    }

    /* ══════════════════════════════════════════════════════
       DASHBOARD
    ══════════════════════════════════════════════════════ */

    public function dashboard(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $stats    = $this->invoices->getStats();
        $settings = $this->settings->all();

        $patientsTotal = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM patients");
        $patientsNew   = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM patients WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $ownersTotal = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM owners");

        $todayApts = 0;
        $upcomingApts = 0;
        try {
            $todayApts = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM appointments WHERE DATE(start_at) = CURDATE() AND status != 'cancelled'"
            );
            $upcomingApts = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM appointments WHERE start_at > NOW() AND status IN ('scheduled','confirmed')"
            );
        } catch (\Throwable) {}

        // Monthly revenue for last 6 months
        $monthlyRevenue = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS ym,
                        DATE_FORMAT(issue_date, '%b')     AS month,
                        SUM(total_gross)                  AS revenue
                 FROM invoices
                 WHERE status = 'paid'
                   AND issue_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                 GROUP BY ym, month
                 ORDER BY ym ASC"
            );
            foreach ($rows as $r) {
                $monthlyRevenue[] = [
                    'month'   => $r['month'],
                    'revenue' => round((float)$r['revenue'], 2),
                ];
            }
        } catch (\Throwable) {}

        $userName = '';
        if (!empty($this->authUser)) {
            $userName = trim(($this->authUser['first_name'] ?? '') . ' ' . ($this->authUser['last_name'] ?? ''));
            if ($userName === '') $userName = $this->authUser['email'] ?? '';
        }

        $this->json([
            'company_name'    => $settings['company_name'] ?? '',
            'user_name'       => $userName,
            'patients_total'  => $patientsTotal,
            'patients_new'    => $patientsNew,
            'owners_total'    => $ownersTotal,
            'today_apts'      => $todayApts,
            'upcoming_apts'   => $upcomingApts,
            'revenue_month'   => round($stats['revenue_month'], 2),
            'revenue_year'    => round($stats['revenue_year'], 2),
            'open_invoices'   => $stats['open_count'],
            'overdue_invoices'=> $stats['overdue_count'],
            'open_amount'     => round($stats['open_amount'], 2),
            'overdue_amount'  => round($stats['overdue_amount'], 2),
            'monthly_revenue' => $monthlyRevenue,
        ]);
    }

    /* ══════════════════════════════════════════════════════
       PATIENTS
    ══════════════════════════════════════════════════════ */

    public function patientsList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $per    = min(50, max(10, (int)($_GET['per_page'] ?? 20)));
        $search = trim($_GET['search'] ?? '');
        $filter = trim($_GET['filter'] ?? '');

        $result = $this->patients->getPaginated($page, $per, $search, $filter);
        $this->json($result);
    }

    public function patientShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $patient = $this->patients->findWithOwner($id);
        if (!$patient) $this->error('Patient nicht gefunden.', 404);

        $rawTimeline  = $this->patients->getTimeline($id);
        $invoiceStats = $this->invoices->getInvoiceStatsByPatientId($id);

        // Expose attachment as file_url so Flutter can render media
        $timeline = array_map(static function (array $e): array {
            if (!empty($e['attachment'])) {
                $att = $e['attachment'];
                // attachment may be just a filename (legacy) or a full relative path
                if (!str_starts_with($att, '/')) {
                    $att = '/storage/patients/' . $e['patient_id'] . '/timeline/' . $att;
                }
                $e['file_url'] = $att;
            }
            return $e;
        }, $rawTimeline);

        $patient['timeline']      = $timeline;
        $patient['invoice_stats'] = $invoiceStats;
        $this->json($patient);
    }

    public function patientCreate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data = $this->body();
        $required = ['name', 'species'];
        foreach ($required as $f) {
            if (empty($data[$f])) $this->error("Feld '{$f}' ist erforderlich.");
        }

        $id = $this->patients->create([
            'name'       => trim($data['name']),
            'species'    => trim($data['species']),
            'breed'      => trim($data['breed'] ?? ''),
            'gender'     => $data['gender'] ?? 'unbekannt',
            'birth_date' => $data['birth_date'] ?? null,
            'owner_id'   => $data['owner_id'] ? (int)$data['owner_id'] : null,
            'chip_number'=> trim($data['chip_number'] ?? ''),
            'color'      => trim($data['color'] ?? ''),
            'weight'     => isset($data['weight']) ? (float)$data['weight'] : null,
            'notes'      => trim($data['notes'] ?? ''),
            'status'     => $data['status'] ?? 'active',
        ]);

        $patient = $this->patients->findById((int)$id);
        $this->json($patient, 201);
    }

    public function patientUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id      = (int)($params['id'] ?? 0);
        $patient = $this->patients->findById($id);
        if (!$patient) $this->error('Patient nicht gefunden.', 404);

        $data = $this->body();
        $this->patients->update($id, array_filter([
            'name'       => isset($data['name'])       ? trim($data['name']) : null,
            'species'    => isset($data['species'])     ? trim($data['species']) : null,
            'breed'      => isset($data['breed'])       ? trim($data['breed']) : null,
            'gender'     => $data['gender']             ?? null,
            'birth_date' => $data['birth_date']         ?? null,
            'owner_id'   => isset($data['owner_id'])   ? (int)$data['owner_id'] : null,
            'chip_number'=> isset($data['chip_number']) ? trim($data['chip_number']) : null,
            'color'      => isset($data['color'])       ? trim($data['color']) : null,
            'weight'     => isset($data['weight'])      ? (float)$data['weight'] : null,
            'notes'      => isset($data['notes'])       ? trim($data['notes']) : null,
            'status'     => $data['status']             ?? null,
        ], fn($v) => $v !== null));

        $this->json($this->patients->findById($id));
    }

    public function patientTimeline(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        if (!$this->patients->findById($id)) $this->error('Patient nicht gefunden.', 404);

        $this->json($this->patients->getTimeline($id));
    }

    public function patientTimelineCreate(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();

        $patientId = (int)($params['id'] ?? 0);
        if (!$this->patients->findById($patientId)) $this->error('Patient nicht gefunden.', 404);

        $data = $this->body();
        if (empty($data['title'])) $this->error('Titel ist erforderlich.');

        $entryId = $this->patients->addTimelineEntry([
            'patient_id'        => $patientId,
            'type'              => $data['type'] ?? 'note',
            'treatment_type_id' => isset($data['treatment_type_id']) ? (int)$data['treatment_type_id'] : null,
            'title'             => trim($data['title']),
            'content'           => trim($data['content'] ?? ''),
            'status_badge'      => $data['status_badge'] ?? null,
            'attachment'        => null,
            'entry_date'        => $data['entry_date'] ?? date('Y-m-d'),
            'user_id'           => $user['user_id'],
        ]);

        $this->json(['id' => $entryId, 'success' => true], 201);
    }

    public function patientTimelineUpload(array $params = []): void
    {
        $this->cors();
        $user      = $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);
        $patient   = $this->patients->findById($patientId);
        if (!$patient) $this->error('Patient nicht gefunden.', 404);

        if (empty($_FILES['file'])) $this->error('Keine Datei empfangen.');
        $file    = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) $this->error('Upload-Fehler: ' . $file['error']);

        $type    = trim($_POST['type'] ?? 'document');
        $title   = trim($_POST['title'] ?? $file['name']);
        $content = trim($_POST['content'] ?? '');
        $date    = $_POST['entry_date'] ?? date('Y-m-d');

        // Determine upload directory (same path as web PatientController)
        $storageBase = defined('STORAGE_PATH') ? rtrim(STORAGE_PATH, '/') : rtrim(dirname(__DIR__, 2) . '/storage', '/');
        $uploadDir   = $storageBase . '/patients/' . $patientId . '/timeline/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Validate by real MIME type
        $finfo       = new \finfo(FILEINFO_MIME_TYPE);
        $uploadMime  = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'image/jpeg','image/png','image/gif','image/webp',
            'video/mp4','video/webm','video/ogg','video/quicktime',
            'video/x-msvideo','video/x-matroska','video/x-m4v','video/mpeg',
            'application/pdf','application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
        ];
        if (!in_array($uploadMime, $allowedMimes, true)) $this->error('Dateityp nicht erlaubt: ' . $uploadMime);

        $extMap = [
            'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',
            'video/mp4'=>'mp4','video/webm'=>'webm','video/ogg'=>'ogv','video/quicktime'=>'mov',
            'video/x-msvideo'=>'avi','video/x-matroska'=>'mkv','video/x-m4v'=>'m4v','video/mpeg'=>'mpeg',
            'application/pdf'=>'pdf','application/msword'=>'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
            'text/plain'=>'txt',
        ];
        $ext      = $extMap[$uploadMime] ?? 'bin';
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest     = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) $this->error('Datei konnte nicht gespeichert werden.');

        $fileUrl  = '/storage/patients/' . $patientId . '/timeline/' . $filename;

        $entryId = $this->patients->addTimelineEntry([
            'patient_id'        => $patientId,
            'type'              => $type,
            'treatment_type_id' => null,
            'title'             => $title ?: $file['name'],
            'content'           => $content,
            'status_badge'      => null,
            'attachment'        => $fileUrl,
            'entry_date'        => $date,
            'user_id'           => $user['user_id'],
        ]);

        $this->json(['id' => $entryId, 'file_url' => $fileUrl, 'success' => true], 201);
    }

    public function patientTimelineDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $patientId = (int)($params['id']  ?? 0);
        $entryId   = (int)($params['eid'] ?? 0);

        try {
            $this->db->execute(
                "DELETE FROM patient_timeline WHERE id = ? AND patient_id = ?",
                [$entryId, $patientId]
            );
        } catch (\Throwable $e) {
            $this->error('Eintrag konnte nicht gelöscht werden.');
        }

        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       OWNERS
    ══════════════════════════════════════════════════════ */

    public function ownersList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $per    = min(50, max(10, (int)($_GET['per_page'] ?? 20)));
        $search = trim($_GET['search'] ?? '');

        $result = $this->owners->getPaginated($page, $per, $search);
        $this->json($result);
    }

    public function ownerShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id    = (int)($params['id'] ?? 0);
        $owner = $this->owners->findById($id);
        if (!$owner) $this->error('Tierhalter nicht gefunden.', 404);

        $owner['patients'] = $this->patients->findByOwner($id);
        $this->json($owner);
    }

    public function ownerCreate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data = $this->body();
        if (empty($data['last_name'])) $this->error('Nachname ist erforderlich.');

        $id = $this->owners->create([
            'first_name' => trim($data['first_name'] ?? ''),
            'last_name'  => trim($data['last_name']),
            'email'      => trim($data['email'] ?? ''),
            'phone'      => trim($data['phone'] ?? ''),
            'address'    => trim($data['address'] ?? ''),
            'city'       => trim($data['city'] ?? ''),
            'zip'        => trim($data['zip'] ?? ''),
            'notes'      => trim($data['notes'] ?? ''),
        ]);

        $this->json($this->owners->findById((int)$id), 201);
    }

    public function ownerUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id    = (int)($params['id'] ?? 0);
        $owner = $this->owners->findById($id);
        if (!$owner) $this->error('Tierhalter nicht gefunden.', 404);

        $data = $this->body();
        $this->owners->update($id, array_filter([
            'first_name' => isset($data['first_name']) ? trim($data['first_name']) : null,
            'last_name'  => isset($data['last_name'])  ? trim($data['last_name'])  : null,
            'email'      => isset($data['email'])       ? trim($data['email'])       : null,
            'phone'      => isset($data['phone'])       ? trim($data['phone'])       : null,
            'address'    => isset($data['address'])     ? trim($data['address'])     : null,
            'city'       => isset($data['city'])        ? trim($data['city'])        : null,
            'zip'        => isset($data['zip'])         ? trim($data['zip'])         : null,
            'notes'      => isset($data['notes'])       ? trim($data['notes'])       : null,
        ], fn($v) => $v !== null));

        $this->json($this->owners->findById($id));
    }

    /* ══════════════════════════════════════════════════════
       INVOICES
    ══════════════════════════════════════════════════════ */

    public function invoicesList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $per    = min(50, max(10, (int)($_GET['per_page'] ?? 20)));
        $status = trim($_GET['status'] ?? '');
        $search = trim($_GET['search'] ?? '');

        $this->json($this->invoices->getPaginated($page, $per, $status, $search));
    }

    public function invoiceShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id      = (int)($params['id'] ?? 0);
        $invoice = $this->invoices->findById($id);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $invoice['positions'] = $this->invoices->getPositions($id);
        $this->json($invoice);
    }

    public function invoiceCreate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data = $this->body();
        if (empty($data['owner_id'])) $this->error('Tierhalter ist erforderlich.');

        $settings = $this->settings->all();
        $prefix   = $settings['invoice_prefix'] ?? 'RE';
        $number   = $this->invoices->getNextInvoiceNumber($prefix);

        $positions = $data['positions'] ?? [];
        $totalNet  = 0.0;
        $totalTax  = 0.0;
        foreach ($positions as $pos) {
            $qty   = (float)($pos['quantity'] ?? 1);
            $price = (float)($pos['unit_price'] ?? 0);
            $tax   = (float)($pos['tax_rate'] ?? 0);
            $line  = $qty * $price;
            $totalNet += $line;
            $totalTax += $line * ($tax / 100);
        }

        $id = $this->invoices->create([
            'invoice_number' => $number,
            'owner_id'       => (int)$data['owner_id'],
            'patient_id'     => isset($data['patient_id']) ? (int)$data['patient_id'] : null,
            'issue_date'     => $data['issue_date'] ?? date('Y-m-d'),
            'due_date'       => $data['due_date'] ?? date('Y-m-d', strtotime('+14 days')),
            'status'         => $data['status'] ?? 'open',
            'notes'          => trim($data['notes'] ?? ''),
            'total_net'      => round($totalNet, 2),
            'total_tax'      => round($totalTax, 2),
            'total_gross'    => round($totalNet + $totalTax, 2),
            'payment_method' => $data['payment_method'] ?? 'rechnung',
        ]);

        foreach ($positions as $i => $pos) {
            $qty   = (float)($pos['quantity'] ?? 1);
            $price = (float)($pos['unit_price'] ?? 0);
            $tax   = (float)($pos['tax_rate'] ?? 0);
            $this->invoices->addPosition((int)$id, [
                'description' => trim($pos['description'] ?? ''),
                'quantity'    => $qty,
                'unit_price'  => $price,
                'tax_rate'    => $tax,
                'total'       => round($qty * $price, 2),
            ], $i);
        }

        $invoice = $this->invoices->findById((int)$id);
        $invoice['positions'] = $this->invoices->getPositions((int)$id);
        $this->json($invoice, 201);
    }

    public function invoiceUpdateStatus(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id      = (int)($params['id'] ?? 0);
        $invoice = $this->invoices->findById($id);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $data   = $this->body();
        $status = $data['status'] ?? '';
        $allowed = ['draft', 'open', 'paid', 'overdue', 'cancelled'];
        if (!in_array($status, $allowed, true)) $this->error('Ungültiger Status.');

        $paidAt = ($status === 'paid') ? ($data['paid_at'] ?? date('Y-m-d H:i:s')) : null;
        $this->invoices->updateStatus($id, $status, $paidAt);
        $this->json(['success' => true, 'status' => $status]);
    }

    /* ══════════════════════════════════════════════════════
       CALENDAR
    ══════════════════════════════════════════════════════ */

    public function appointmentsList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $start = $_GET['start'] ?? date('Y-m-d');
        $end   = $_GET['end']   ?? date('Y-m-d', strtotime('+30 days'));

        try {
            $rows = $this->db->fetchAll(
                "SELECT a.*,
                        p.name AS patient_name,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                        tt.name AS treatment_type_name, tt.color AS treatment_type_color
                 FROM appointments a
                 LEFT JOIN patients p  ON p.id  = a.patient_id
                 LEFT JOIN owners o    ON o.id  = a.owner_id
                 LEFT JOIN treatment_types tt ON tt.id = a.treatment_type_id
                 WHERE a.start_at >= ? AND a.start_at <= ?
                 ORDER BY a.start_at ASC",
                [$start . ' 00:00:00', $end . ' 23:59:59']
            );
            $this->json($rows);
        } catch (\Throwable $e) {
            $this->json([]);
        }
    }

    public function appointmentCreate(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();

        $data = $this->body();
        if (empty($data['title']) || empty($data['start_at'])) {
            $this->error('Titel und Startzeit sind erforderlich.');
        }

        try {
            $id = $this->db->insert(
                "INSERT INTO appointments (title, start_at, end_at, patient_id, owner_id,
                    treatment_type_id, status, color, description, notes, reminder_minutes, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    trim($data['title']),
                    $data['start_at'],
                    $data['end_at'] ?? null,
                    isset($data['patient_id']) ? (int)$data['patient_id'] : null,
                    isset($data['owner_id'])   ? (int)$data['owner_id']   : null,
                    isset($data['treatment_type_id']) ? (int)$data['treatment_type_id'] : null,
                    $data['status'] ?? 'scheduled',
                    $data['color']  ?? '#4f7cff',
                    trim($data['description'] ?? ''),
                    trim($data['notes']       ?? ''),
                    (int)($data['reminder_minutes'] ?? 60),
                ]
            );
            $this->json(['id' => $id, 'success' => true], 201);
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
    }

    public function appointmentUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id   = (int)($params['id'] ?? 0);
        $data = $this->body();

        try {
            $this->db->execute(
                "UPDATE appointments SET
                    title = COALESCE(?, title),
                    start_at = COALESCE(?, start_at),
                    end_at = COALESCE(?, end_at),
                    patient_id = ?,
                    owner_id = ?,
                    treatment_type_id = ?,
                    status = COALESCE(?, status),
                    color  = COALESCE(?, color),
                    description = COALESCE(?, description),
                    notes = COALESCE(?, notes)
                 WHERE id = ?",
                [
                    $data['title']    ?? null,
                    $data['start_at'] ?? null,
                    $data['end_at']   ?? null,
                    isset($data['patient_id'])        ? (int)$data['patient_id']        : null,
                    isset($data['owner_id'])           ? (int)$data['owner_id']           : null,
                    isset($data['treatment_type_id']) ? (int)$data['treatment_type_id'] : null,
                    $data['status']      ?? null,
                    $data['color']       ?? null,
                    $data['description'] ?? null,
                    $data['notes']       ?? null,
                    $id,
                ]
            );
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
    }

    public function appointmentDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        try {
            $this->db->execute("DELETE FROM appointments WHERE id = ?", [$id]);
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
    }

    /* ══════════════════════════════════════════════════════
       TREATMENT TYPES
    ══════════════════════════════════════════════════════ */

    public function treatmentTypes(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $rows = $this->db->fetchAll("SELECT * FROM treatment_types ORDER BY name ASC");
        $this->json($rows);
    }

    /* ══════════════════════════════════════════════════════
       SETTINGS (read-only for non-admin)
    ══════════════════════════════════════════════════════ */

    public function settingsGet(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $settings = $this->settings->all();
        $safe = [
            'company_name'    => $settings['company_name']    ?? '',
            'company_address' => $settings['company_address'] ?? '',
            'company_phone'   => $settings['company_phone']   ?? '',
            'company_email'   => $settings['company_email']   ?? '',
            'currency'        => $settings['currency']        ?? 'EUR',
            'tax_rate'        => $settings['tax_rate']        ?? '19',
            'kleinunternehmer'=> $settings['kleinunternehmer'] ?? '0',
        ];
        $this->json($safe);
    }
}
