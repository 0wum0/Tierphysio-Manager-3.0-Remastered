<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Repositories\UserRepository;
use App\Repositories\PatientRepository;
use App\Repositories\OwnerRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\ReminderDunningRepository;
use App\Repositories\TreatmentTypeRepository;
use App\Services\MailService;

/**
 * Mobile REST API — Bearer token authentication.
 * All responses: JSON, CORS headers for Flutter app.
 */
class MobileApiController
{
    private Database                 $db;
    private UserRepository           $users;
    private PatientRepository        $patients;
    private OwnerRepository          $owners;
    private InvoiceRepository        $invoices;
    private SettingsRepository       $settings;
    private ReminderDunningRepository $reminderDunning;
    private TreatmentTypeRepository  $treatmentTypeRepo;
    private MailService              $mail;

    private ?array $authUser  = null;
    private ?array $bodyCache = null;

    public function __construct(
        Database                  $db,
        UserRepository            $userRepository,
        PatientRepository         $patientRepository,
        OwnerRepository           $ownerRepository,
        InvoiceRepository         $invoiceRepository,
        SettingsRepository        $settingsRepository,
        ReminderDunningRepository $reminderDunningRepository,
        TreatmentTypeRepository   $treatmentTypeRepository,
        MailService               $mailService
    ) {
        // Intercept all exceptions for the mobile API and return them as JSON
        set_exception_handler([$this, 'exceptionHandler']);

        // Start output buffering immediately so any PHP notices/warnings don't
        // corrupt the JSON response body.
        if (!ob_get_level()) {
            ob_start();
        }

        $this->db                = $db;
        $this->users             = $userRepository;
        $this->patients          = $patientRepository;
        $this->owners            = $ownerRepository;
        $this->invoices          = $invoiceRepository;
        $this->settings          = $settingsRepository;
        $this->reminderDunning   = $reminderDunningRepository;
        $this->treatmentTypeRepo = $treatmentTypeRepository;
        $this->mail              = $mailService;
    }

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }


    /**
     * Normalize tenant prefixes to canonical format: t_<slug>_
     */
    private function normalizeTenantPrefix(string $raw): string
    {
        $p = trim($raw);
        if ($p === '') {
            return '';
        }

        if (str_ends_with($p, '_users')) {
            $p = substr($p, 0, -strlen('users'));
        }

        if (!str_starts_with($p, 't_')) {
            $p = 't_' . $p;
        }

        $p = preg_replace('/_+/', '_', $p) ?? $p;
        if (!str_ends_with($p, '_')) {
            $p .= '_';
        }

        return $p;
    }

    private function tableHasColumn(string $tableName, string $column): bool
    {
        try {
            $count = (int)$this->db->fetchColumn(
                "SELECT COUNT(*)
                   FROM information_schema.columns
                  WHERE table_schema = DATABASE()
                    AND table_name = ?
                    AND column_name = ?",
                [$tableName, $column]
            );
            return $count > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function findTokenRowInTable(string $tableName, string $token): array|false
    {
        $hasToken      = $this->tableHasColumn($tableName, 'token');
        $hasTokenHash  = $this->tableHasColumn($tableName, 'token_hash');
        $hasTenantPref = $this->tableHasColumn($tableName, 'tenant_prefix');
        $hasExpiresAt  = $this->tableHasColumn($tableName, 'expires_at');

        if (!$hasToken && !$hasTokenHash) {
            return false;
        }

        $tokenColumn    = $hasToken ? 'token' : 'token_hash';
        $tokenToCompare = $hasToken ? $token : hash('sha256', $token);
        $tenantSelect   = $hasTenantPref ? 't.tenant_prefix' : "'' AS tenant_prefix";
        $expiryFilter   = $hasExpiresAt ? ' AND (t.expires_at IS NULL OR t.expires_at > NOW())' : '';

        return $this->db->fetch(
            "SELECT t.*, u.*, u.id AS user_id, {$tenantSelect}
               FROM `{$tableName}` t
               JOIN `{$this->t('users')}` u ON u.id = t.user_id
              WHERE t.{$tokenColumn} = ?{$expiryFilter}
              LIMIT 1",
            [$tokenToCompare]
        );
    }

    /**
     * Resolve the tenant table-prefix for a given user email.
     *
     * The Mobile API is stateless (no PHP session), so we cannot rely on the
     * session-based prefix that the web controllers use.  Instead we:
     *   1. Look up the prefix stored in the token row (set at login time).
     *   2. If not stored yet, auto-detect via INFORMATION_SCHEMA: find the
     *      t_*_users table whose rows contain the given e-mail address.
     *   3. Fallback only if there is exactly one tenant users table.
     *
     * The detected prefix is written into the token row on the first call so
     * subsequent requests skip the expensive schema lookup.
     */
    private function resolveTenantPrefixForEmail(string $email): string
    {
        // 1. Already set on the DB object by requireAuth() from the token row?
        $current = $this->db->getPrefix();
        if ($current !== '') {
            return $current;
        }

        // 2. Auto-detect by looking for the tenant whose users table has this email.
        try {
            $rows = $this->db->fetchAll(
                "SELECT table_name FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name LIKE 't\_%\_users'
                  ORDER BY table_name ASC"
            );
            foreach ($rows as $row) {
                $tableName = $row['table_name'] ?? $row['TABLE_NAME'] ?? '';
                if (str_contains($tableName, 'portal') || str_contains($tableName, 'attempt')) {
                    continue;
                }
                $prefix = $this->normalizeTenantPrefix(substr($tableName, 0, -strlen('users')));
                // Verify: does this tenant actually have this user?
                $found = $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM `{$tableName}` WHERE email = ? LIMIT 1",
                    [$email]
                );
                if ((int)$found > 0) {
                    $this->db->setPrefix($prefix);
                    return $prefix;
                }
            }
            // Fallback only when there is exactly one usable tenant users table.
            $usable = [];
            foreach ($rows as $row) {
                $tableName = $row['table_name'] ?? $row['TABLE_NAME'] ?? '';
                if ($tableName === '' || str_contains($tableName, 'portal') || str_contains($tableName, 'attempt')) {
                    continue;
                }
                $usable[] = $tableName;
            }
            if (count($usable) === 1) {
                $prefix = $this->normalizeTenantPrefix(substr($usable[0], 0, -strlen('users')));
                $this->db->setPrefix($prefix);
                return $prefix;
            }
        } catch (\Throwable $e) {
            // Schema lookup failed – log silently
        }
        return '';
    }

    /**
     * Resolve the exact tenant by validating credentials against all tenant users tables.
     * This avoids picking the wrong tenant when the same email exists in multiple tenants.
     */
    private function resolveTenantPrefixForCredentials(string $email, string $password): string
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT table_name FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name LIKE 't\_%\_users'
                  ORDER BY table_name ASC"
            );

            foreach ($rows as $row) {
                $tableName = $row['table_name'] ?? $row['TABLE_NAME'] ?? '';
                if ($tableName === '' || str_contains($tableName, 'portal') || str_contains($tableName, 'attempt')) {
                    continue;
                }

                try {
                    $candidate = $this->db->fetch(
                        "SELECT * FROM `{$tableName}` WHERE email = ? LIMIT 1",
                        [$email]
                    );
                    if (!$candidate) {
                        continue;
                    }

                    $hash = (string)($candidate['password'] ?? $candidate['password_hash'] ?? '');
                    if ($hash === '' || !password_verify($password, $hash)) {
                        continue;
                    }

                    $isActive = (int)($candidate['active'] ?? $candidate['is_active'] ?? 1);
                    if ($isActive !== 1) {
                        continue;
                    }

                    $prefix = $this->normalizeTenantPrefix(substr($tableName, 0, -strlen('users')));
                    $this->db->setPrefix($prefix);
                    return $prefix;
                } catch (\Throwable) {
                    continue;
                }
            }
        } catch (\Throwable) {
        }

        return '';
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
        // Discard any accidentally output PHP warnings/notices
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function error(string $message, int $status = 400): never
    {
        $this->json(['error' => $message], $status);
    }

    private function exceptionHandler(\Throwable $e): never
    {
        $logMsg = sprintf(
            "[%s] MobileApi Exception: %s in %s:%d\nStack trace:\n%s\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        error_log($logMsg);

        $msg = $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
        $this->error('500 Internal Error: ' . $msg, 500);
    }

    private function body(): array
    {
        if ($this->bodyCache !== null) return $this->bodyCache;
        $raw     = file_get_contents('php://input');
        $decoded = $raw ? json_decode($raw, true) : null;
        $this->bodyCache = is_array($decoded) ? $decoded : $_POST;
        return $this->bodyCache;
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

        $tokenRow = false;
        $savedPrefix = $this->db->getPrefix();
        
        // 1. Search prefixed tenant token tables first (authoritative in multi-tenant mode).
        try {
            $tables = $this->db->fetchAll(
                "SELECT table_name FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name LIKE 't\_%\_mobile\_api\_tokens'"
            );
            foreach ($tables as $row) {
                $tableName = $row['table_name'] ?? $row['TABLE_NAME'] ?? '';
                $prefix = $this->normalizeTenantPrefix(substr($tableName, 0, -strlen('mobile_api_tokens')));

                $this->db->setPrefix($prefix);
                try {
                    $testRow = $this->findTokenRowInTable($this->t('mobile_api_tokens'), $token);
                    if ($testRow) {
                        $tokenRow = $testRow;
                        if (empty($tokenRow['tenant_prefix'])) {
                            $tokenRow['tenant_prefix'] = $prefix;
                        }
                        break;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        } catch (\Throwable) {}

        // 2. Fallback: check global (legacy) token table.
        if ($tokenRow === false) {
            $this->db->setPrefix('');
            try {
                $tokenRow = $this->findTokenRowInTable('mobile_api_tokens', $token);
            } catch (\Throwable) {
                $tokenRow = false;
            }
        }

        // 3. Still nothing? Fallback to whatever current active prefix is (if randomly resolved)
        if ($tokenRow === false && $savedPrefix !== '') {
            $this->db->setPrefix($savedPrefix);
            try {
                $tokenRow = $this->findTokenRowInTable($this->t('mobile_api_tokens'), $token);
            } catch (\Throwable) { }
        }

        if (!$tokenRow) {
            $this->error('Ungültiger oder abgelaufener Token.', 401);
        }
        
        $isActive = (int)($tokenRow['active'] ?? $tokenRow['is_active'] ?? 1);
        if ($isActive !== 1) {
            $this->error('Konto ist deaktiviert.', 401);
        }

        // Apply discovered prefix permanently for this request
        $storedPrefix = $this->normalizeTenantPrefix((string)($tokenRow['tenant_prefix'] ?? ''));
        if ($storedPrefix !== '') {
            $this->db->setPrefix($storedPrefix);
        } else {
            $this->resolveTenantPrefixForEmail($tokenRow['email'] ?? '');
        }

        // Persist tenant_prefix if it was inferred dynamically.
        if (($tokenRow['tenant_prefix'] ?? '') === '' && $this->db->getPrefix() !== '') {
            try {
                $tokenTable = $this->t('mobile_api_tokens');
                if ($this->tableHasColumn($tokenTable, 'tenant_prefix')) {
                    $tokenCol = $this->tableHasColumn($tokenTable, 'token') ? 'token' : 'token_hash';
                    $tokenVal = $tokenCol === 'token' ? $token : hash('sha256', $token);
                    $this->db->execute(
                        "UPDATE `{$tokenTable}` SET tenant_prefix = ? WHERE {$tokenCol} = ?",
                        [$this->db->getPrefix(), $tokenVal]
                    );
                }
            } catch (\Throwable) {
            }
        }

        // Update last_used
        try {
            $tokenTable = $this->t('mobile_api_tokens');
            if ($this->tableHasColumn($tokenTable, 'last_used')) {
                $tokenCol = $this->tableHasColumn($tokenTable, 'token') ? 'token' : 'token_hash';
                $tokenVal = $tokenCol === 'token' ? $token : hash('sha256', $token);
                $this->db->execute(
                    "UPDATE `{$tokenTable}` SET last_used = NOW() WHERE {$tokenCol} = ?",
                    [$tokenVal]
                );
            }
        } catch (\Throwable) {}

        $this->authUser = $tokenRow;
        return $tokenRow;
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
        // Suppress PHP warnings to ensure clean JSON output
        $prev = error_reporting(0);
        $this->cors();
        $email      = trim((string)$this->input('email', ''));
        $password   = (string)$this->input('password', '');
        $deviceName = trim((string)$this->input('device_name', 'Flutter App'));

        if (!$email || !$password) {
            error_reporting($prev);
            $this->error('E-Mail und Passwort erforderlich.');
        }

        // ── Tenant-Prefix-Auflösung ────────────────────────────────────────────
        // The Mobile API is stateless – no PHP session. We must find the correct
        // tenant prefix from the database schema before querying the users table.
        $prefix = $this->resolveTenantPrefixForCredentials($email, $password);
        if ($prefix === '') {
            $prefix = $this->resolveTenantPrefixForEmail($email);
        }

        if ($prefix === '') {
            // Log this for diagnostics
            error_log('[MobileApi] Could not resolve tenant prefix for: ' . $email);
            error_reporting($prev);
            $this->error('Tenant nicht gefunden. Bitte Support kontaktieren.', 503);
        }

        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, $user['password'] ?? $user['password_hash'] ?? '')) {
            error_reporting($prev);
            $this->error('Ungültige Anmeldedaten.', 401);
        }

        // Support both 'active' and 'is_active' column names
        $isActive = (int)($user['active'] ?? $user['is_active'] ?? 1);
        if ($isActive !== 1) {
            error_reporting($prev);
            $this->error('Konto ist deaktiviert.', 403);
        }

        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

        // Store the tenant prefix IN the token row so requireAuth() can restore it
        // on every subsequent stateless API request (no session needed).
        try {
            $tableName      = $this->t('mobile_api_tokens');
            $hasToken       = $this->tableHasColumn($tableName, 'token');
            $hasTokenHash   = $this->tableHasColumn($tableName, 'token_hash');
            $hasDeviceName  = $this->tableHasColumn($tableName, 'device_name');
            $hasDevice      = $this->tableHasColumn($tableName, 'device');
            $hasTenantPref  = $this->tableHasColumn($tableName, 'tenant_prefix');
            $hasExpiresAt   = $this->tableHasColumn($tableName, 'expires_at');

            $columns = ['user_id'];
            $values  = [(int)$user['id']];

            if ($hasToken) {
                $columns[] = 'token';
                $values[]  = $token;
            } elseif ($hasTokenHash) {
                $columns[] = 'token_hash';
                $values[]  = $tokenHash;
            } else {
                throw new \RuntimeException('mobile_api_tokens has neither token nor token_hash column.');
            }

            if ($hasDeviceName) {
                $columns[] = 'device_name';
                $values[]  = $deviceName;
            } elseif ($hasDevice) {
                $columns[] = 'device';
                $values[]  = $deviceName;
            }

            if ($hasTenantPref) {
                $columns[] = 'tenant_prefix';
                $values[]  = $prefix;
            }

            if ($hasExpiresAt) {
                $columns[] = 'expires_at';
                $values[]  = $expiresAt;
            }

            $columns[] = 'created_at';
            $placeholder = implode(', ', array_fill(0, count($columns) - 1, '?'));
            $this->db->execute(
                "INSERT INTO `{$tableName}` (" . implode(', ', $columns) . ") VALUES ({$placeholder}, NOW())",
                $values
            );
        } catch (\Throwable $e) {
            $errorStr = $e->getMessage();
            // If the table is missing entirely (Base table or view not found), auto-create it!
            if (str_contains($errorStr, "doesn't exist") || str_contains($errorStr, 'not found') || str_contains($errorStr, '1146')) {
                try {
                    $this->db->execute("
                        CREATE TABLE IF NOT EXISTS `{$this->t('mobile_api_tokens')}` (
                            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            user_id     INT UNSIGNED NOT NULL,
                            token       VARCHAR(64)  NOT NULL UNIQUE,
                            device_name VARCHAR(100) NOT NULL DEFAULT '',
                            tenant_prefix VARCHAR(64) NOT NULL DEFAULT '',
                            last_used   DATETIME     NULL,
                            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            expires_at  DATETIME     NULL,
                            INDEX idx_token (token),
                            INDEX idx_user  (user_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ");
                    // Retry the insert!
                    $this->db->execute(
                        "INSERT INTO `{$this->t('mobile_api_tokens')}` (user_id, token, device_name, tenant_prefix, expires_at, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())",
                        [$user['id'], $token, $deviceName, $prefix, $expiresAt]
                    );
                } catch (\Throwable $innerEx) {
                    error_log('[MobileApi] Could not create tokens table: ' . $innerEx->getMessage());
                    error_reporting($prev);
                    $this->error('Datenbank-Fehler (Tabelle fehlt). Bitte an Admin wenden.', 500);
                }
            } else {
                // Otherwise (e.g. column drift), self-heal and retry with tenant_prefix.
                try {
                    $this->db->execute(
                        "ALTER TABLE `{$this->t('mobile_api_tokens')}` ADD COLUMN IF NOT EXISTS `tenant_prefix` VARCHAR(64) NOT NULL DEFAULT ''"
                    );
                } catch (\Throwable) {}
                try {
                    $this->db->execute(
                        "INSERT INTO `{$this->t('mobile_api_tokens')}` (user_id, token, device_name, tenant_prefix, expires_at, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())",
                        [$user['id'], $token, $deviceName, $prefix, $expiresAt]
                    );
                } catch (\Throwable $fallbackEx) {
                    error_log('[MobileApi] Insert fallback failed: ' . $fallbackEx->getMessage());
                    error_reporting($prev);
                    $this->error('Interner Datenbankfehler beim Login.', 500);
                }
            }
        }

        $this->users->updateLastLogin($user['id']);

        error_reporting($prev);
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
        $rawToken = trim($m[1] ?? '');
        $tokenTable = $this->t('mobile_api_tokens');
        $tokenCol = $this->tableHasColumn($tokenTable, 'token') ? 'token' : 'token_hash';
        $tokenVal = $tokenCol === 'token' ? $rawToken : hash('sha256', $rawToken);
        $this->db->execute("DELETE FROM `{$tokenTable}` WHERE {$tokenCol} = ?", [$tokenVal]);
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

        $stats = [
            'revenue_month' => 0.0,
            'revenue_year'  => 0.0,
            'open_count'    => 0,
            'overdue_count' => 0,
            'open_amount'   => 0.0,
            'overdue_amount'=> 0.0,
        ];
        $settings = [];

        try { $stats = array_merge($stats, (array)$this->invoices->getStats()); } catch (\Throwable) {}
        try { $settings = (array)$this->settings->all(); } catch (\Throwable) {}

        $patientsTotal = 0;
        $patientsNew   = 0;
        $ownersTotal   = 0;
        try {
            $patientsTotal = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('patients')}`");
            $patientsNew   = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('patients')}` WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            $ownersTotal = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('owners')}`");
        } catch (\Throwable) {}

        $todayApts       = 0;
        $upcomingApts    = 0;
        $todayAptsList   = [];
        $nextAptsList    = [];
        $newIntakes      = 0;
        $birthdaysToday  = [];
        $upcomingBirthdays = [];
        try {
            $todayApts = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('appointments')}` WHERE DATE(start_at) = CURDATE() AND status != 'cancelled'"
            );
            $upcomingApts = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('appointments')}` WHERE start_at > NOW() AND status IN ('scheduled','confirmed')"
            );
            $apt = $this->t('appointments'); $pat = $this->t('patients'); $own = $this->t('owners'); $tt = $this->t('treatment_types');
            $todayAptsList = $this->db->fetchAll(
                "SELECT a.id, a.title, a.start_at, a.end_at, a.status, a.color,
                        a.patient_id,
                        p.name AS patient_name, p.species AS patient_species,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                        tt.name AS treatment_type_name, tt.color AS treatment_color
                 FROM `{$apt}` a
                 LEFT JOIN `{$pat}` p  ON p.id = a.patient_id
                 LEFT JOIN `{$own}` o    ON o.id = a.owner_id
                 LEFT JOIN `{$tt}` tt ON tt.id = a.treatment_type_id
                 WHERE DATE(a.start_at) = CURDATE() AND a.status NOT IN ('cancelled','noshow')
                 ORDER BY a.start_at ASC"
            );
            $nextAptsList = $this->db->fetchAll(
                "SELECT a.id, a.title, a.start_at, a.end_at, a.status, a.color,
                        a.patient_id,
                        p.name AS patient_name, p.species AS patient_species,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                        tt.name AS treatment_type_name, tt.color AS treatment_color
                 FROM `{$apt}` a
                 LEFT JOIN `{$pat}` p  ON p.id = a.patient_id
                 LEFT JOIN `{$own}` o    ON o.id = a.owner_id
                 LEFT JOIN `{$tt}` tt ON tt.id = a.treatment_type_id
                 WHERE DATE(a.start_at) > CURDATE() AND a.status NOT IN ('cancelled','noshow')
                 ORDER BY a.start_at ASC
                 LIMIT 3"
            );
        } catch (\Throwable) {}
        try {
            $newIntakes = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('patient_intake')}` WHERE status = 'pending'"
            );
        } catch (\Throwable) {
            try {
                $newIntakes = (int)$this->db->fetchColumn(
                    "SELECT COUNT(*) FROM `{$this->t('patients')}` WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
                );
            } catch (\Throwable) {}
        }
        try {
            $pat2 = $this->t('patients'); $own2 = $this->t('owners');
            $birthdaysToday = $this->db->fetchAll(
                "SELECT p.id, p.name, p.species,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                        p.date_of_birth,
                        TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) AS age
                 FROM `{$pat2}` p
                 LEFT JOIN `{$own2}` o ON o.id = p.owner_id
                 WHERE p.date_of_birth IS NOT NULL
                   AND DATE_FORMAT(p.date_of_birth, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
                 ORDER BY p.name ASC"
            );
            $upcomingBirthdays = $this->db->fetchAll(
                "SELECT p.id, p.name, p.species,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                        p.date_of_birth,
                        TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) + 1 AS age,
                        DAYOFYEAR(DATE(CONCAT(YEAR(CURDATE()),'-',DATE_FORMAT(p.date_of_birth,'%m-%d'))))
                          - DAYOFYEAR(CURDATE()) AS days_until
                 FROM `{$pat2}` p
                 LEFT JOIN `{$own2}` o ON o.id = p.owner_id
                 WHERE p.date_of_birth IS NOT NULL
                   AND DATE_FORMAT(p.date_of_birth, '%m-%d') != DATE_FORMAT(CURDATE(), '%m-%d')
                   AND (
                     DAYOFYEAR(DATE(CONCAT(YEAR(CURDATE()),'-',DATE_FORMAT(p.date_of_birth,'%m-%d'))))
                       - DAYOFYEAR(CURDATE())
                   ) BETWEEN 1 AND 14
                 ORDER BY days_until ASC
                 LIMIT 3"
            );
        } catch (\Throwable) {}

        // Monthly revenue for last 6 months
        $monthlyRevenue = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS ym,
                        DATE_FORMAT(issue_date, '%b')     AS month,
                        SUM(total_gross)                  AS revenue
                 FROM `{$this->t('invoices')}`
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
            $userName = trim($this->authUser['name'] ?? '');
            if ($userName === '') $userName = $this->authUser['email'] ?? '';
        }

        $this->json([
            'company_name'      => $settings['company_name'] ?? '',
            'user_name'         => $userName,
            'patients_total'    => $patientsTotal,
            'patients_new'      => $patientsNew,
            'owners_total'      => $ownersTotal,
            'today_apts'        => $todayApts,
            'upcoming_apts'     => $upcomingApts,
            'today_appointments'  => $todayAptsList,
            'next_appointments'   => $nextAptsList,
            'new_intakes'         => $newIntakes,
            'birthdays_today'     => $birthdaysToday,
            'upcoming_birthdays'  => $upcomingBirthdays,
            'revenue_month'     => round($stats['revenue_month'], 2),
            'revenue_year'      => round($stats['revenue_year'], 2),
            'open_invoices'     => $stats['open_count'],
            'overdue_invoices'  => $stats['overdue_count'],
            'open_amount'       => round($stats['open_amount'], 2),
            'overdue_amount'    => round($stats['overdue_amount'], 2),
            'monthly_revenue'   => $monthlyRevenue,
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
        $per    = min(500, max(10, (int)($_GET['per_page'] ?? 20)));
        $search = trim($_GET['search'] ?? '');
        $filter = trim($_GET['filter'] ?? '');

        $result = $this->patients->getPaginated($page, $per, $search, $filter);
        $result['items'] = array_map(static function (array $p): array {
            if (!empty($p['photo'])) {
                $p['photo_url'] = '/api/mobile/patients/' . $p['id'] . '/foto/' . rawurlencode(basename($p['photo']));
            }
            return $p;
        }, $result['items'] ?? []);
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

        // Expose attachment as file_url using the mobile API media endpoint (Bearer auth)
        $timeline = array_map(static function (array $e): array {
            if (!empty($e['attachment'])) {
                $filename = basename($e['attachment']);
                $e['file_url'] = '/api/mobile/patients/' . $e['patient_id'] . '/media/' . rawurlencode($filename);
            }
            return $e;
        }, $rawTimeline);

        if (!empty($patient['photo'])) {
            $patient['photo_url'] = '/api/mobile/patients/' . $id . '/foto/' . rawurlencode(basename($patient['photo']));
        }
        $patient['timeline']      = $timeline;
        $patient['invoice_stats'] = $invoiceStats;

        try {
            $patient['upcoming_appointments'] = $this->db->fetchAll(
                "SELECT a.id, a.title, a.start_at, a.end_at, a.status,
                        tt.name AS treatment_type_name, tt.color AS treatment_color
                 FROM `{$this->t('appointments')}` a
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 WHERE a.patient_id = ? AND a.start_at >= NOW() AND a.status NOT IN ('cancelled','noshow')
                 ORDER BY a.start_at ASC
                 LIMIT 1",
                [$id]
            );
        } catch (\Throwable) {
            $patient['upcoming_appointments'] = [];
        }

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

        $insertData = [
            'name'       => trim($data['name']),
            'species'    => trim($data['species']),
            'breed'      => trim($data['breed'] ?? ''),
            'gender'     => $data['gender'] ?? 'unbekannt',
            'birth_date' => ($data['birth_date'] ?? '') !== '' ? $data['birth_date'] : null,
            'chip_number'=> trim($data['chip_number'] ?? ''),
            'color'      => trim($data['color'] ?? ''),
            'notes'      => trim($data['notes'] ?? ''),
            'status'     => $data['status'] ?? 'active',
        ];
        if (!empty($data['owner_id'])) {
            $insertData['owner_id'] = (int)$data['owner_id'];
        }
        if (isset($data['weight']) && $data['weight'] !== '' && $data['weight'] !== null) {
            $insertData['weight'] = (float)$data['weight'];
        }

        $id = $this->patients->create($insertData);

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
        $uploadDir = tenant_storage_path('patients/' . $patientId . '/timeline/');
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

        $fileUrl  = '/api/mobile/patients/' . $patientId . '/media/' . rawurlencode($filename);

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
                "DELETE FROM `{$this->t('patient_timeline')}` WHERE id = ? AND patient_id = ?",
                [$entryId, $patientId]
            );
        } catch (\Throwable $e) {
            $this->error('Eintrag konnte nicht gelöscht werden.');
        }

        $this->json(['success' => true]);
    }

    /**
     * Serve a patient photo — Bearer authenticated.
     * Searches: storage/patients/{id}/{file}, storage/patients/{file}, storage/intake/{file}
     */
    public function mediaServePhoto(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id   = (int)($params['id'] ?? 0);
        $file = basename($params['file'] ?? '');
        if ($file === '') { http_response_code(404); exit; }

        $candidates  = [
            tenant_storage_path('patients/' . $id . '/' . $file),
            tenant_storage_path('patients/' . $file),
            tenant_storage_path('intake/' . $file),
        ];

        $path = null;
        foreach ($candidates as $c) {
            if (is_file($c)) { $path = $c; break; }
        }
        if ($path === null) { http_response_code(404); exit; }

        $this->streamFile($path);
    }

    /**
     * Serve a patient timeline media file — Bearer authenticated.
     * Searches: storage/patients/{id}/timeline/{file}, storage/patients/{id}/{file}
     */
    public function mediaServeFile(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id   = (int)($params['id'] ?? 0);
        $file = basename($params['file'] ?? '');
        if ($file === '') { http_response_code(404); exit; }

        $candidates  = [
            tenant_storage_path('patients/' . $id . '/timeline/' . $file),
            tenant_storage_path('patients/' . $id . '/' . $file),
        ];

        $path = null;
        foreach ($candidates as $c) {
            if (is_file($c)) { $path = $c; break; }
        }
        if ($path === null) { http_response_code(404); exit; }

        $this->streamFile($path);
    }

    private function streamFile(string $path): void
    {
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mime     = $finfo->file($path);
        $size     = filesize($path);
        $filename = basename($path);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
        header('Cache-Control: private, max-age=86400');
        header('Accept-Ranges: bytes');

        // Support range requests for video streaming
        $start = 0;
        $end   = $size - 1;
        if (isset($_SERVER['HTTP_RANGE'])) {
            preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m);
            $start = (int)($m[1] ?? 0);
            $end   = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : $size - 1;
            $end   = min($end, $size - 1);
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Content-Length: ' . ($end - $start + 1));
        }

        $fp = fopen($path, 'rb');
        fseek($fp, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($fp)) {
            $chunk = fread($fp, min(8192, $remaining));
            echo $chunk;
            $remaining -= strlen($chunk);
            flush();
        }
        fclose($fp);
        exit;
    }

    /* ══════════════════════════════════════════════════════
       OWNERS
    ══════════════════════════════════════════════════════ */

    public function ownersList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $per    = min(500, max(10, (int)($_GET['per_page'] ?? 20)));
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

        $rawPatients = $this->patients->findByOwner($id);
        $owner['patients'] = array_values(array_map(static function (array $p): array {
            if (!empty($p['photo'])) {
                $p['photo_url'] = '/patient-photos/' . $p['id'] . '/' . $p['photo'];
            }
            return $p;
        }, $rawPatients));
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
        $cancellationReason = ($status === 'cancelled') ? ($data['cancellation_reason'] ?? null) : null;
        
        $this->invoices->updateStatus($id, $status, $paidAt, $cancellationReason);
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
                        tt.name AS treatment_type_name, tt.color AS treatment_type_color,
                        CASE
                            WHEN a.google_event_id IS NOT NULL THEN 'google'
                            ELSE 'internal'
                        END AS source
                 FROM `{$this->t('appointments')}` a
                 LEFT JOIN `{$this->t('patients')}` p  ON p.id  = a.patient_id
                 LEFT JOIN `{$this->t('owners')}` o    ON o.id  = a.owner_id
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 WHERE a.start_at >= ? AND a.start_at <= ?
                   AND a.status != 'cancelled'
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
                "INSERT INTO `{$this->t('appointments')}` (title, start_at, end_at, patient_id, owner_id,
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
            $this->googleSyncAppointment((int)$id, 'create');
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
                "UPDATE `{$this->t('appointments')}` SET
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
            $this->googleSyncAppointment($id, 'update');
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
            $this->googleSyncAppointment($id, 'delete');
            $this->db->execute("DELETE FROM `{$this->t('appointments')}` WHERE id = ?", [$id]);
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
        $rows = $this->db->fetchAll("SELECT * FROM `{$this->t('treatment_types')}` ORDER BY name ASC");
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

    /* ══════════════════════════════════════════════════════
       MESSAGING (portal_message_threads / portal_messages)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/nachrichten — list all threads for the logged-in admin */
    public function messageThreads(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        try {
            $rows = $this->db->fetchAll(
                "SELECT t.id, t.subject, t.status, t.last_message_at, t.created_at,
                        CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                        o.id   AS owner_id,
                        o.email AS owner_email,
                        (SELECT COUNT(*) FROM `{$this->t('portal_messages')}` m
                         WHERE m.thread_id = t.id AND m.is_read = 0
                           AND m.sender_type = 'owner') AS unread_count,
                        (SELECT m2.body FROM `{$this->t('portal_messages')}` m2
                         WHERE m2.thread_id = t.id
                         ORDER BY m2.created_at DESC LIMIT 1) AS last_body
                 FROM `{$this->t('portal_message_threads')}` t
                 JOIN `{$this->t('owners')}` o ON o.id = t.owner_id
                 ORDER BY t.last_message_at DESC"
            );
        } catch (\Throwable) {
            $this->json([]);
            return;
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'             => (int)$r['id'],
                'subject'        => $r['subject'],
                'status'         => $r['status'],
                'last_message_at'=> $r['last_message_at'] ?? $r['created_at'],
                'owner_id'       => (int)$r['owner_id'],
                'owner_name'     => $r['owner_name'],
                'owner_email'    => $r['owner_email'] ?? '',
                'unread_count'   => (int)($r['unread_count'] ?? 0),
                'last_body'      => $r['last_body'] ?? '',
            ];
        }
        $this->json($out);
    }

    /** GET /api/mobile/nachrichten/ungelesen — total unread badge count */
    public function messageUnread(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        try {
            $count = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('portal_messages')}` WHERE sender_type = 'owner' AND is_read = 0"
            );
        } catch (\Throwable) {
            $count = 0;
        }
        $this->json(['unread' => $count]);
    }

    /** GET /api/mobile/nachrichten/{id} — thread + all messages, marks admin-read */
    public function messageThread(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);

        try {
            $thread = $this->db->fetch(
                "SELECT t.*, CONCAT(o.first_name,' ',o.last_name) AS owner_name, o.email AS owner_email
                 FROM `{$this->t('portal_message_threads')}` t
                 JOIN `{$this->t('owners')}` o ON o.id = t.owner_id
                 WHERE t.id = ? LIMIT 1",
                [$id]
            );
            if (!$thread) $this->error('Thread nicht gefunden.', 404);

            /* Mark owner messages as read */
            $this->db->execute(
                "UPDATE `{$this->t('portal_messages')}` SET is_read = 1
                 WHERE thread_id = ? AND sender_type = 'owner' AND is_read = 0",
                [$id]
            );

            $msgs = $this->db->fetchAll(
                "SELECT m.*,
                        CASE WHEN m.sender_type = 'admin'
                             THEN COALESCE(u.name,'Team')
                             ELSE CONCAT(o.first_name,' ',o.last_name)
                        END AS sender_name
                 FROM `{$this->t('portal_messages')}` m
                 LEFT JOIN `{$this->t('users')}` u ON u.id = m.sender_id AND m.sender_type = 'admin'
                 LEFT JOIN `{$this->t('portal_message_threads')}` t2 ON t2.id = m.thread_id
                 LEFT JOIN `{$this->t('owners')}` o ON o.id = t2.owner_id AND m.sender_type = 'owner'
                 WHERE m.thread_id = ?
                 ORDER BY m.created_at ASC",
                [$id]
            );
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }

        $messages = [];
        foreach ($msgs as $m) {
            $messages[] = [
                'id'          => (int)$m['id'],
                'sender_type' => $m['sender_type'],
                'sender_name' => $m['sender_name'] ?? '',
                'body'        => $m['body'],
                'is_read'     => (bool)$m['is_read'],
                'created_at'  => $m['created_at'],
            ];
        }

        $this->json([
            'id'             => (int)$thread['id'],
            'subject'        => $thread['subject'],
            'status'         => $thread['status'],
            'owner_id'       => (int)$thread['owner_id'],
            'owner_name'     => $thread['owner_name'],
            'last_message_at'=> $thread['last_message_at'],
            'messages'       => $messages,
        ]);
    }

    /** POST /api/mobile/nachrichten/{id}/antworten — admin replies to a thread */
    public function messageReply(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();

        $id   = (int)($params['id'] ?? 0);
        $body = trim((string)($this->body()['body'] ?? ''));
        if ($body === '') $this->error('Nachricht darf nicht leer sein.', 422);

        try {
            $thread = $this->db->fetch(
                "SELECT * FROM `{$this->t('portal_message_threads')}` WHERE id = ? LIMIT 1", [$id]
            );
            if (!$thread) $this->error('Thread nicht gefunden.', 404);

            $this->db->execute(
                "INSERT INTO `{$this->t('portal_messages')}` (thread_id, sender_type, sender_id, body, is_read, created_at)
                 VALUES (?, 'admin', ?, ?, 0, NOW())",
                [$id, (int)$user['user_id'], $body]
            );
            $msgId = (int)$this->db->lastInsertId();

            $this->db->execute(
                "UPDATE `{$this->t('portal_message_threads')}` SET last_message_at = NOW(), status = 'open' WHERE id = ?",
                [$id]
            );
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }

        $this->json([
            'ok'          => true,
            'id'          => $msgId,
            'sender_type' => 'admin',
            'sender_name' => $user['name'] ?? 'Team',
            'body'        => $body,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /** POST /api/mobile/nachrichten — start a new thread from admin */
    public function messageCreate(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();

        $data    = $this->body();
        $ownerId = (int)($data['owner_id'] ?? 0);
        $subject = trim((string)($data['subject'] ?? ''));
        $body    = trim((string)($data['body']    ?? ''));

        if (!$ownerId || $subject === '' || $body === '') {
            $this->error('owner_id, subject und body sind erforderlich.', 422);
        }

        $owner = $this->owners->findById($ownerId);
        if (!$owner) $this->error('Tierhalter nicht gefunden.', 404);

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('portal_message_threads')}` (owner_id, subject, status, created_by, last_message_at, created_at)
                 VALUES (?, ?, 'open', 'admin', NOW(), NOW())",
                [$ownerId, $subject]
            );
            $threadId = (int)$this->db->lastInsertId();

            $this->db->execute(
                "INSERT INTO `{$this->t('portal_messages')}` (thread_id, sender_type, sender_id, body, is_read, created_at)
                 VALUES (?, 'admin', ?, ?, 0, NOW())",
                [$threadId, (int)$user['user_id'], $body]
            );
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }

        $this->json(['ok' => true, 'thread_id' => $threadId], 201);
    }

    /** POST /api/mobile/nachrichten/{id}/status — open or close a thread */
    public function messageSetStatus(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id     = (int)($params['id'] ?? 0);
        $status = trim((string)($this->body()['status'] ?? 'closed'));
        if (!in_array($status, ['open', 'closed'], true)) $this->error('Ungültiger Status.');

        try {
            $this->db->execute(
                "UPDATE `{$this->t('portal_message_threads')}` SET status = ? WHERE id = ?", [$status, $id]
            );
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['ok' => true, 'status' => $status]);
    }

    /** POST /api/mobile/nachrichten/{id}/loeschen — delete a thread */
    public function messageDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        try {
            $this->db->execute("DELETE FROM `{$this->t('portal_messages')}` WHERE thread_id = ?", [$id]);
            $this->db->execute("DELETE FROM `{$this->t('portal_message_threads')}` WHERE id = ?", [$id]);
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['ok' => true]);
    }

    /* ══════════════════════════════════════════════════════
       INVOICES — extended
    ══════════════════════════════════════════════════════ */

    public function invoiceUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id      = (int)($params['id'] ?? 0);
        $invoice = $this->invoices->findById($id);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $data      = $this->body();
        $positions = $data['positions'] ?? null;

        $fields = [];
        foreach (['owner_id','patient_id','issue_date','due_date','notes','payment_method','status'] as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = $data[$f];
        }

        if ($positions !== null) {
            $totalNet = $totalTax = 0.0;
            foreach ($positions as $pos) {
                $qty   = (float)($pos['quantity']  ?? 1);
                $price = (float)($pos['unit_price'] ?? 0);
                $tax   = (float)($pos['tax_rate']   ?? 0);
                $line  = $qty * $price;
                $totalNet += $line;
                $totalTax += $line * ($tax / 100);
            }
            $fields['total_net']   = round($totalNet, 2);
            $fields['total_tax']   = round($totalTax, 2);
            $fields['total_gross'] = round($totalNet + $totalTax, 2);
        }

        if (!empty($fields)) {
            $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $id;
            $this->db->execute("UPDATE `{$this->t('invoices')}` SET {$sets}, updated_at = NOW() WHERE id = ?", $values);
        }

        if ($positions !== null) {
            $this->db->execute("DELETE FROM `{$this->t('invoice_positions')}` WHERE invoice_id = ?", [$id]);
            foreach ($positions as $i => $pos) {
                $qty   = (float)($pos['quantity']  ?? 1);
                $price = (float)($pos['unit_price'] ?? 0);
                $tax   = (float)($pos['tax_rate']   ?? 0);
                $this->invoices->addPosition($id, [
                    'description' => trim($pos['description'] ?? ''),
                    'quantity'    => $qty,
                    'unit_price'  => $price,
                    'tax_rate'    => $tax,
                    'total'       => round($qty * $price, 2),
                ], $i);
            }
        }

        $inv = $this->invoices->findById($id);
        $inv['positions'] = $this->invoices->getPositions($id);
        $this->json($inv);
    }

    public function invoiceDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id      = (int)($params['id'] ?? 0);
        $invoice = $this->invoices->findById($id);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $this->db->execute("DELETE FROM `{$this->t('invoice_positions')}` WHERE invoice_id = ?", [$id]);
        $this->db->execute("DELETE FROM `{$this->t('invoices')}` WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    public function invoicePdfUrl(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id      = (int)($params['id'] ?? 0);
        $invoice = $this->invoices->findById($id);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $this->json([
            'pdf_url'     => '/rechnungen/' . $id . '/pdf',
            'receipt_url' => '/rechnungen/' . $id . '/quittung',
        ]);
    }

    public function invoiceStats(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $stats = $this->invoices->getStats();
        $this->json($stats);
    }

    public function invoiceSendEmail(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id      = (int)($params['id'] ?? 0);
        $invoice = $this->invoices->findById($id);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $owner = $invoice['owner_id'] ? $this->owners->findById((int)$invoice['owner_id']) : null;
        if (!$owner || empty($owner['email'])) {
            $this->error('Kein E-Mail-Versand möglich – keine E-Mail-Adresse hinterlegt.', 422);
        }

        try {
            $positions = $this->invoices->getPositions($id);
            $patient   = $invoice['patient_id'] ? $this->patients->findById((int)$invoice['patient_id']) : null;
            $pdfService = \App\Core\Application::getInstance()->getContainer()->get(\App\Services\PdfService::class);
            $pdf = $pdfService->generateInvoicePdf($invoice, $positions, $owner, $patient);
            $sent = $this->mail->sendInvoice($invoice, $owner, $pdf);
            if ($sent) {
                $this->invoices->markEmailSent($id);
                $this->json(['success' => true, 'email' => $owner['email']]);
            } else {
                $this->error('E-Mail-Versand fehlgeschlagen: ' . ($this->mail->getLastError() ?? ''), 500);
            }
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
    }

    public function reminderSendEmail(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $reminderId = (int)($params['rid'] ?? 0);
        $reminder   = $this->reminderDunning->findReminderById($reminderId);
        if (!$reminder) $this->error('Erinnerung nicht gefunden.', 404);

        $invoice = $this->invoices->findById((int)$reminder['invoice_id']);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $owner   = $invoice['owner_id'] ? $this->owners->findById((int)$invoice['owner_id']) : null;
        $patient = $invoice['patient_id'] ? $this->patients->findById((int)$invoice['patient_id']) : null;

        if (!$owner || empty($owner['email'])) {
            $this->error('Kein E-Mail-Versand möglich – keine E-Mail-Adresse hinterlegt.', 422);
        }

        try {
            $pdfService = \App\Core\Application::getInstance()->getContainer()->get(\App\Services\PdfService::class);
            $pdf  = $pdfService->generateReminderPdf($invoice, $reminder, $owner, $patient);
            $sent = $this->mail->sendInvoiceReminder($invoice, $reminder, $owner, $pdf);
            if ($sent) {
                $this->reminderDunning->markReminderSent($reminderId, $owner['email']);
                $this->json(['success' => true, 'email' => $owner['email']]);
            } else {
                $this->error('E-Mail-Versand fehlgeschlagen: ' . ($this->mail->getLastError() ?? ''), 500);
            }
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
    }

    public function dunningSendEmail(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $dunningId = (int)($params['did'] ?? 0);
        $dunning   = $this->reminderDunning->findDunningById($dunningId);
        if (!$dunning) $this->error('Mahnung nicht gefunden.', 404);

        $invoice = $this->invoices->findById((int)$dunning['invoice_id']);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $owner   = $invoice['owner_id'] ? $this->owners->findById((int)$invoice['owner_id']) : null;
        $patient = $invoice['patient_id'] ? $this->patients->findById((int)$invoice['patient_id']) : null;

        if (!$owner || empty($owner['email'])) {
            $this->error('Kein E-Mail-Versand möglich – keine E-Mail-Adresse hinterlegt.', 422);
        }

        try {
            $pdfService = \App\Core\Application::getInstance()->getContainer()->get(\App\Services\PdfService::class);
            $pdf  = $pdfService->generateDunningPdf($invoice, $dunning, $owner, $patient);
            $sent = $this->mail->sendDunning($invoice, $dunning, $owner, $pdf);
            if ($sent) {
                $this->reminderDunning->markDunningSent($dunningId, $owner['email']);
                $this->json(['success' => true, 'email' => $owner['email']]);
            } else {
                $this->error('E-Mail-Versand fehlgeschlagen: ' . ($this->mail->getLastError() ?? ''), 500);
            }
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
    }

    /* ══════════════════════════════════════════════════════
       REMINDERS (Zahlungserinnerungen)
    ══════════════════════════════════════════════════════ */

    public function remindersList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $this->json($this->reminderDunning->getAllReminders($search, $status));
    }

    public function remindersForInvoice(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $invoiceId = (int)($params['id'] ?? 0);
        $this->json($this->reminderDunning->getRemindersForInvoice($invoiceId));
    }

    public function reminderCreate(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();

        $invoiceId = (int)($params['id'] ?? 0);
        $invoice   = $this->invoices->findById($invoiceId);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $data = $this->body();
        $id   = $this->reminderDunning->createReminder([
            'invoice_id' => $invoiceId,
            'due_date'   => $data['due_date'] ?? date('Y-m-d', strtotime('+14 days')),
            'fee'        => (float)($data['fee'] ?? 0),
            'notes'      => trim($data['notes'] ?? ''),
            'created_by' => $user['user_id'],
        ]);
        $this->json($this->reminderDunning->findReminderById($id), 201);
    }

    public function reminderDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['rid'] ?? 0);
        $this->reminderDunning->deleteReminder($id);
        $this->json(['success' => true]);
    }

    public function overdueAlerts(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $this->json($this->reminderDunning->getOverdueAlertInvoices());
    }

    /* ══════════════════════════════════════════════════════
       DUNNINGS (Mahnungen)
    ══════════════════════════════════════════════════════ */

    public function dunningsList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $this->json($this->reminderDunning->getAllDunnings($search, $status));
    }

    public function dunningsForInvoice(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $invoiceId = (int)($params['id'] ?? 0);
        $this->json($this->reminderDunning->getDunningsForInvoice($invoiceId));
    }

    public function dunningCreate(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();

        $invoiceId = (int)($params['id'] ?? 0);
        $invoice   = $this->invoices->findById($invoiceId);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $data  = $this->body();
        $level = $this->reminderDunning->getNextDunningLevel($invoiceId);
        $id    = $this->reminderDunning->createDunning([
            'invoice_id' => $invoiceId,
            'level'      => $level,
            'due_date'   => $data['due_date'] ?? date('Y-m-d', strtotime('+14 days')),
            'fee'        => (float)($data['fee'] ?? 5.00),
            'notes'      => trim($data['notes'] ?? ''),
            'created_by' => $user['user_id'],
        ]);
        $this->json($this->reminderDunning->findDunningById($id), 201);
    }

    public function dunningDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['did'] ?? 0);
        $this->reminderDunning->deleteDunning($id);
        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       PATIENTS — extended
    ══════════════════════════════════════════════════════ */

    public function patientDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        if (!$this->patients->findById($id)) $this->error('Patient nicht gefunden.', 404);

        $this->db->execute("UPDATE `{$this->t('patients')}` SET status = 'archived' WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    public function patientPhotoUpload(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        if (!$this->patients->findById($id)) $this->error('Patient nicht gefunden.', 404);

        if (empty($_FILES['photo'])) $this->error('Kein Bild empfangen.');
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) $this->error('Upload-Fehler.');

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mime     = $finfo->file($file['tmp_name']);
        $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) $this->error('Nur Bilder erlaubt (jpg, png, webp, gif).');

        $dir = tenant_storage_path('patients/' . $id . '/');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = 'photo_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) $this->error('Datei konnte nicht gespeichert werden.');

        $this->db->execute("UPDATE `{$this->t('patients')}` SET photo = ?, updated_at = NOW() WHERE id = ?", [$filename, $id]);
        $this->json(['success' => true, 'photo_url' => '/patient-photos/' . $id . '/' . $filename]);
    }

    public function patientTimelineUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $patientId = (int)($params['id']  ?? 0);
        $entryId   = (int)($params['eid'] ?? 0);
        if (!$this->patients->findById($patientId)) $this->error('Patient nicht gefunden.', 404);

        $data = $this->body();
        $this->patients->updateTimelineEntry($entryId, [
            'type'              => $data['type']         ?? 'note',
            'treatment_type_id' => isset($data['treatment_type_id']) ? (int)$data['treatment_type_id'] : null,
            'title'             => trim($data['title']   ?? ''),
            'content'           => trim($data['content'] ?? ''),
            'status_badge'      => $data['status_badge'] ?? null,
            'entry_date'        => $data['entry_date']   ?? date('Y-m-d'),
        ]);
        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       OWNERS — extended
    ══════════════════════════════════════════════════════ */

    public function ownerDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        if (!$this->owners->findById($id)) $this->error('Tierhalter nicht gefunden.', 404);

        $this->db->execute("DELETE FROM `{$this->t('owners')}` WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    public function ownerInvoices(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id   = (int)($params['id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = min(50, max(10, (int)($_GET['per_page'] ?? 20)));
        if (!$this->owners->findById($id)) $this->error('Tierhalter nicht gefunden.', 404);

        $rows = $this->db->fetchAll(
            "SELECT i.*, p.name AS patient_name
             FROM `{$this->t('invoices')}` i
             LEFT JOIN `{$this->t('patients')}` p ON p.id = i.patient_id
             WHERE i.owner_id = ?
             ORDER BY i.issue_date DESC
             LIMIT ? OFFSET ?",
            [$id, $per, ($page - 1) * $per]
        );
        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('invoices')}` WHERE owner_id = ?", [$id]);
        $this->json(['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $per]);
    }

    public function ownerPatients(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        if (!$this->owners->findById($id)) $this->error('Tierhalter nicht gefunden.', 404);

        $patients = $this->patients->findByOwner($id);
        $patients = array_map(static function (array $p): array {
            if (!empty($p['photo'])) {
                $p['photo_url'] = '/patient-photos/' . $p['id'] . '/' . $p['photo'];
            }
            return $p;
        }, $patients);
        $this->json($patients);
    }


    /* ══════════════════════════════════════════════════════
       APPOINTMENTS — extended
    ══════════════════════════════════════════════════════ */

    public function appointmentShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        try {
            $row = $this->db->fetch(
                "SELECT a.*,
                        p.name AS patient_name,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                        tt.name AS treatment_type_name, tt.color AS treatment_type_color
                 FROM `{$this->t('appointments')}` a
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = a.patient_id
                 LEFT JOIN `{$this->t('owners')}` o   ON o.id = a.owner_id
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 WHERE a.id = ? LIMIT 1",
                [$id]
            );
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        if (!$row) $this->error('Termin nicht gefunden.', 404);
        $this->json($row);
    }

    public function appointmentStatusUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id     = (int)($params['id'] ?? 0);
        $data   = $this->body();
        $status = $data['status'] ?? '';
        $allowed = ['scheduled','confirmed','cancelled','completed','no_show'];
        if (!in_array($status, $allowed, true)) $this->error('Ungültiger Status.');

        try {
            $this->db->execute("UPDATE `{$this->t('appointments')}` SET status = ? WHERE id = ?", [$status, $id]);
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['success' => true, 'status' => $status]);
    }

    public function appointmentToday(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        try {
            $rows = $this->db->fetchAll(
                "SELECT a.*,
                        p.name AS patient_name,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                        tt.name AS treatment_type_name, tt.color AS treatment_type_color
                 FROM `{$this->t('appointments')}` a
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = a.patient_id
                 LEFT JOIN `{$this->t('owners')}` o   ON o.id = a.owner_id
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 WHERE DATE(a.start_at) = CURDATE()
                 ORDER BY a.start_at ASC"
            );
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /* ── Waitlist ── */

    public function waitlistList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        try {
            $rows = $this->db->fetchAll(
                "SELECT w.*,
                        p.name AS patient_name,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                        tt.name AS treatment_type_name
                 FROM `{$this->t('appointment_waitlist')}` w
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = w.patient_id
                 LEFT JOIN `{$this->t('owners')}` o   ON o.id = w.owner_id
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = w.treatment_type_id
                 ORDER BY w.created_at ASC"
            );
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    public function waitlistAdd(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data = $this->body();
        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('appointment_waitlist')}` (patient_id, owner_id, treatment_type_id, preferred_date, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    isset($data['patient_id'])        ? (int)$data['patient_id']        : null,
                    isset($data['owner_id'])           ? (int)$data['owner_id']           : null,
                    isset($data['treatment_type_id']) ? (int)$data['treatment_type_id'] : null,
                    $data['preferred_date'] ?? null,
                    trim($data['notes'] ?? ''),
                ]
            );
            $id = (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['success' => true, 'id' => $id], 201);
    }

    public function waitlistDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        try {
            $this->db->execute("DELETE FROM `{$this->t('appointment_waitlist')}` WHERE id = ?", [$id]);
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['success' => true]);
    }

    public function waitlistSchedule(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $waitId = (int)($params['id'] ?? 0);
        $data   = $this->body();
        if (empty($data['start_at'])) $this->error('start_at ist erforderlich.');

        try {
            $entry = $this->db->fetch("SELECT * FROM `{$this->t('appointment_waitlist')}` WHERE id = ? LIMIT 1", [$waitId]);
            if (!$entry) $this->error('Wartelisten-Eintrag nicht gefunden.', 404);

            $apptId = $this->db->insert(
                "INSERT INTO `{$this->t('appointments')}` (title, start_at, end_at, patient_id, owner_id, treatment_type_id, status, description, notes, reminder_minutes, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?, ?, ?, NOW())",
                [
                    $data['title'] ?? ('Termin für ' . ($entry['patient_id'] ? 'Patient' : 'Besitzer')),
                    $data['start_at'],
                    $data['end_at'] ?? null,
                    $entry['patient_id'],
                    $entry['owner_id'],
                    $entry['treatment_type_id'],
                    trim($data['description'] ?? ''),
                    trim($data['notes'] ?? $entry['notes'] ?? ''),
                    (int)($data['reminder_minutes'] ?? 60),
                ]
            );
            $this->db->execute("DELETE FROM `{$this->t('appointment_waitlist')}` WHERE id = ?", [$waitId]);
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['success' => true, 'appointment_id' => $apptId], 201);
    }

    /* ══════════════════════════════════════════════════════
       TREATMENT TYPES — full CRUD
    ══════════════════════════════════════════════════════ */

    public function treatmentTypeShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id  = (int)($params['id'] ?? 0);
        $row = $this->db->fetch("SELECT * FROM `{$this->t('treatment_types')}` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) $this->error('Behandlungsart nicht gefunden.', 404);
        $this->json($row);
    }

    public function treatmentTypeCreate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data = $this->body();
        if (empty($data['name'])) $this->error('Name ist erforderlich.');

        $id = $this->db->insert(
            "INSERT INTO `{$this->t('treatment_types')}` (name, color, duration_minutes, price, description, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                trim($data['name']),
                $data['color']            ?? '#4f7cff',
                (int)($data['duration_minutes'] ?? 60),
                (float)($data['price']    ?? 0),
                trim($data['description'] ?? ''),
            ]
        );
        $this->json($this->db->fetch("SELECT * FROM `{$this->t('treatment_types')}` WHERE id = ?", [(int)$id]), 201);
    }

    public function treatmentTypeUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id  = (int)($params['id'] ?? 0);
        $row = $this->db->fetch("SELECT id FROM `{$this->t('treatment_types')}` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) $this->error('Behandlungsart nicht gefunden.', 404);

        $data   = $this->body();
        $fields = [];
        foreach (['name','color','duration_minutes','price','description'] as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = $data[$f];
        }
        if ($fields) {
            $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $id;
            $this->db->execute("UPDATE `{$this->t('treatment_types')}` SET {$sets} WHERE id = ?", $values);
        }
        $this->json($this->db->fetch("SELECT * FROM `{$this->t('treatment_types')}` WHERE id = ?", [$id]));
    }

    public function treatmentTypeDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $this->db->execute("DELETE FROM `{$this->t('treatment_types')}` WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       USERS (admin only)
    ══════════════════════════════════════════════════════ */

    public function usersList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->requireAdmin();

        $rows = $this->db->fetchAll(
            "SELECT id, name, email, role, active, last_login, created_at FROM `{$this->t('users')}` ORDER BY name ASC"
        );
        $this->json($rows);
    }

    public function userShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->requireAdmin();

        $id  = (int)($params['id'] ?? 0);
        $row = $this->db->fetch("SELECT id, name, email, role, active, last_login, created_at FROM `{$this->t('users')}` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) $this->error('Benutzer nicht gefunden.', 404);
        $this->json($row);
    }

    public function userCreate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->requireAdmin();

        $data = $this->body();
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            $this->error('name, email und password sind erforderlich.');
        }
        if ($this->db->fetch("SELECT id FROM `{$this->t('users')}` WHERE email = ? LIMIT 1", [trim($data['email'])])) {
            $this->error('E-Mail bereits vergeben.', 409);
        }

        $id = $this->db->insert(
            "INSERT INTO `{$this->t('users')}` (name, email, password, role, active, created_at) VALUES (?, ?, ?, ?, 1, NOW())",
            [
                trim($data['name']),
                strtolower(trim($data['email'])),
                password_hash($data['password'], PASSWORD_BCRYPT),
                $data['role'] ?? 'mitarbeiter',
            ]
        );
        $this->json($this->db->fetch("SELECT id, name, email, role, active FROM `{$this->t('users')}` WHERE id = ?", [(int)$id]), 201);
    }

    public function userUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->requireAdmin();

        $id  = (int)($params['id'] ?? 0);
        $row = $this->db->fetch("SELECT id FROM `{$this->t('users')}` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) $this->error('Benutzer nicht gefunden.', 404);

        $data   = $this->body();
        $fields = [];
        foreach (['name','email','role','active'] as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = $data[$f];
        }
        if (!empty($data['password'])) {
            $fields['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        if ($fields) {
            $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $id;
            $this->db->execute("UPDATE `{$this->t('users')}` SET {$sets} WHERE id = ?", $values);
        }
        $this->json($this->db->fetch("SELECT id, name, email, role, active FROM `{$this->t('users')}` WHERE id = ?", [$id]));
    }

    public function userDeactivate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->requireAdmin();

        $id = (int)($params['id'] ?? 0);
        $this->db->execute("UPDATE `{$this->t('users')}` SET active = 0 WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    public function userApiTokens(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->requireAdmin();

        $id   = (int)($params['id'] ?? 0);
        $rows = $this->db->fetchAll(
            "SELECT id, device_name, created_at, last_used, expires_at FROM `{$this->t('mobile_api_tokens')}` WHERE user_id = ? ORDER BY last_used DESC",
            [$id]
        );
        $this->json($rows);
    }

    public function userRevokeToken(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $tokenId = (int)($params['tid'] ?? 0);
        $this->db->execute("DELETE FROM `{$this->t('mobile_api_tokens')}` WHERE id = ?", [$tokenId]);
        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       SETTINGS — extended (admin write)
    ══════════════════════════════════════════════════════ */

    public function settingsUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->requireAdmin();

        $data    = $this->body();
        $allowed = [
            'company_name','company_address','company_phone','company_email',
            'currency','tax_rate','kleinunternehmer','invoice_prefix',
            'invoice_due_days','company_iban','company_bic','company_bank',
        ];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $this->settings->set($key, (string)$data[$key]);
            }
        }
        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       ANALYTICS
    ══════════════════════════════════════════════════════ */

    public function analyticsOverview(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $summary      = $this->invoices->getFinancialSummary();
        $byMonth      = $this->invoices->getRevenueByMonth(12);
        $byYear       = $this->invoices->getRevenueByYear();
        $ownerSpeed   = $this->invoices->getOwnerPaymentSpeed();
        $ownerRevenue = $this->invoices->getOwnerRevenue(10);
        $aging        = $this->invoices->getOverdueAging();
        $topPositions = $this->invoices->getTopPositions(10);
        $forecast     = $this->invoices->getRevenueForForecast(12);

        /* Linear regression — next 3 months */
        $values = array_column($forecast, 'revenue');
        $n      = count($values);
        $forecastMonths = [];
        if ($n >= 3) {
            $sumX = $sumY = $sumXY = $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) {
                $sumX  += $i; $sumY  += $values[$i];
                $sumXY += $i * $values[$i]; $sumX2 += $i * $i;
            }
            $denom     = ($n * $sumX2 - $sumX * $sumX);
            $slope     = $denom != 0 ? ($n * $sumXY - $sumX * $sumY) / $denom : 0;
            $intercept = ($sumY - $slope * $sumX) / $n;
            for ($f = 1; $f <= 3; $f++) {
                $forecastMonths[] = [
                    'month' => date('Y-m', strtotime('+' . $f . ' months')),
                    'value' => round(max(0, $intercept + $slope * ($n - 1 + $f)), 2),
                ];
            }
        }

        $this->json([
            'summary'        => $summary,
            'by_month'       => $byMonth,
            'by_year'        => $byYear,
            'owner_speed'    => $ownerSpeed,
            'owner_revenue'  => $ownerRevenue,
            'aging'          => $aging,
            'top_positions'  => $topPositions,
            'forecast_history' => $forecast,
            'forecast_next'  => $forecastMonths,
        ]);
    }

    /* ══════════════════════════════════════════════════════
       GLOBAL SEARCH
    ══════════════════════════════════════════════════════ */

    public function globalSearch(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) $this->error('Suchbegriff zu kurz (min. 2 Zeichen).');

        $like = "%{$q}%";

        $patients = $this->db->fetchAll(
            "SELECT id, name, species, breed, status, 'patient' AS type FROM `{$this->t('patients')}`
             WHERE name LIKE ? OR chip_number LIKE ? LIMIT 10",
            [$like, $like]
        );

        $owners = $this->db->fetchAll(
            "SELECT id, CONCAT(first_name,' ',last_name) AS name, email, phone, 'owner' AS type
             FROM `{$this->t('owners')}` WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? LIMIT 10",
            [$like, $like, $like, $like]
        );

        $invoices = $this->db->fetchAll(
            "SELECT i.id, i.invoice_number AS name, i.status, i.total_gross, i.issue_date, 'invoice' AS type
             FROM `{$this->t('invoices')}` i WHERE i.invoice_number LIKE ? LIMIT 10",
            [$like]
        );

        $appointments = [];
        try {
            $appointments = $this->db->fetchAll(
                "SELECT a.id, a.title AS name, a.start_at, a.status, 'appointment' AS type
                 FROM `{$this->t('appointments')}` a WHERE a.title LIKE ? OR a.description LIKE ? LIMIT 10",
                [$like, $like]
            );
        } catch (\Throwable) {}

        $this->json([
            'query'        => $q,
            'patients'     => $patients,
            'owners'       => $owners,
            'invoices'     => $invoices,
            'appointments' => $appointments,
            'total'        => count($patients) + count($owners) + count($invoices) + count($appointments),
        ]);
    }

    /* ══════════════════════════════════════════════════════
       HOMEWORK PLANS
    ══════════════════════════════════════════════════════ */

    public function homeworkList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $patientId = (int)($params['id'] ?? 0);
        if ($patientId && !$this->patients->findById($patientId)) $this->error('Patient nicht gefunden.', 404);

        try {
            $sql = "SELECT hp.*,
                           p.name  AS patient_name,
                           u.name  AS therapist_name_resolved
                    FROM `{$this->t('portal_homework_plans')}` hp
                    LEFT JOIN `{$this->t('patients')}` p ON p.id = hp.patient_id
                    LEFT JOIN `{$this->t('users')}`    u ON u.id = hp.created_by";
            $args = [];
            if ($patientId) { $sql .= " WHERE hp.patient_id = ?"; $args[] = $patientId; }
            $sql .= " ORDER BY hp.plan_date DESC LIMIT 50";
            $plans = $this->db->fetchAll($sql, $args);

            /* Attach tasks to each plan */
            foreach ($plans as &$plan) {
                try {
                    $plan['tasks'] = $this->db->fetchAll(
                        "SELECT * FROM `{$this->t('portal_homework_plan_tasks')}` WHERE plan_id = ? ORDER BY sort_order ASC, id ASC",
                        [(int)$plan['id']]
                    );
                } catch (\Throwable) { $plan['tasks'] = []; }
            }
            unset($plan);
        } catch (\Throwable) { $plans = []; }
        $this->json($plans);
    }

    public function homeworkShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        try {
            $plan = $this->db->fetch("SELECT * FROM `{$this->t('homework_plans')}` WHERE id = ? LIMIT 1", [$id]);
            if (!$plan) $this->error('Hausaufgabenplan nicht gefunden.', 404);
            $exercises = $this->db->fetchAll("SELECT * FROM `{$this->t('homework_exercises')}` WHERE plan_id = ? ORDER BY sort_order ASC", [$id]);
            $plan['exercises'] = $exercises;
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json($plan);
    }

    /* ══════════════════════════════════════════════════════
       NOTIFICATIONS / PING
    ══════════════════════════════════════════════════════ */

    public function ping(array $params = []): void
    {
        $this->cors();
        $this->json([
            'ok'      => true,
            'time'    => date('Y-m-d H:i:s'),
            'version' => '2.0',
        ]);
    }

    public function notificationSummary(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $unreadMessages = 0;
        $overdueCount   = 0;
        $todayApts      = 0;
        $waitlistCount  = 0;

        try {
            $unreadMessages = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('portal_messages')}` WHERE sender_type = 'owner' AND is_read = 0"
            );
        } catch (\Throwable) {}

        try {
            $overdueCount = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('invoices')}` WHERE status = 'overdue'"
            );
        } catch (\Throwable) {}

        try {
            $todayApts = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('appointments')}` WHERE DATE(start_at) = CURDATE() AND status != 'cancelled'"
            );
        } catch (\Throwable) {}

        try {
            $waitlistCount = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('appointment_waitlist')}` WHERE status = 'waiting'"
            );
        } catch (\Throwable) {}

        $this->json([
            'unread_messages' => $unreadMessages,
            'overdue_invoices'=> $overdueCount,
            'today_appointments' => $todayApts,
            'waitlist_entries'   => $waitlistCount,
            'total_badge'        => $unreadMessages + $overdueCount,
        ]);
    }

    /* ══════════════════════════════════════════════════════
       OWNER PORTAL — Admin (Besitzerportal-Verwaltung)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/portal-admin/benutzer — list all portal users */
    public function portalUsersList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        try {
            $rows = $this->db->fetchAll(
                "SELECT u.*, o.first_name, o.last_name
                 FROM `{$this->t('owner_portal_users')}` u
                 JOIN `{$this->t('owners')}` o ON o.id = u.owner_id
                 ORDER BY o.last_name ASC, o.first_name ASC"
            );
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /** GET /api/mobile/portal-admin/benutzer/{id} — show single portal user */
    public function portalUserShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        try {
            $row = $this->db->fetch(
                "SELECT u.*, o.first_name, o.last_name, o.email AS owner_email, o.phone
                 FROM `{$this->t('owner_portal_users')}` u
                 JOIN `{$this->t('owners')}` o ON o.id = u.owner_id
                 WHERE u.id = ? LIMIT 1",
                [$id]
            );
        } catch (\Throwable) { $row = null; }
        if (!$row) $this->error('Portal-Benutzer nicht gefunden.', 404);
        $this->json($row);
    }

    /** POST /api/mobile/portal-admin/einladen — invite an owner to the portal */
    public function portalInvite(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $data    = $this->body();
        $ownerId = (int)($data['owner_id'] ?? 0);
        $email   = strtolower(trim((string)($data['email'] ?? '')));

        if (!$ownerId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('owner_id und gültige E-Mail sind erforderlich.');
        }

        $existing = $this->db->fetch("SELECT id FROM `{$this->t('owner_portal_users')}` WHERE email = ? LIMIT 1", [$email]);
        if ($existing) $this->error('Diese E-Mail hat bereits einen Portal-Account.', 409);

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('owner_portal_users')}` (owner_id, email, password_hash, is_active, invite_token, invite_expires)
                 VALUES (?, ?, NULL, 0, ?, ?)",
                [$ownerId, $email, $token, $expires]
            );
            $userId = (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }

        $settings   = $this->settings->all();
        $portalUrl  = rtrim($settings['portal_base_url'] ?? '', '/');
        $inviteLink = $portalUrl . '/portal/registrieren?token=' . $token;

        $this->json([
            'success'     => true,
            'id'          => $userId,
            'invite_link' => $inviteLink,
            'expires_at'  => $expires,
        ], 201);
    }

    /** POST /api/mobile/portal-admin/benutzer/{id}/neu-einladen — resend invite */
    public function portalResendInvite(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id  = (int)($params['id'] ?? 0);
        $row = $this->db->fetch("SELECT * FROM `{$this->t('owner_portal_users')}` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) $this->error('Portal-Benutzer nicht gefunden.', 404);

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

        $this->db->execute(
            "UPDATE `{$this->t('owner_portal_users')}` SET invite_token = ?, invite_expires = ?, invite_used_at = NULL, is_active = 0 WHERE id = ?",
            [$token, $expires, $id]
        );

        $settings   = $this->settings->all();
        $portalUrl  = rtrim($settings['portal_base_url'] ?? '', '/');
        $inviteLink = $portalUrl . '/portal/registrieren?token=' . $token;

        $this->json(['success' => true, 'invite_link' => $inviteLink, 'expires_at' => $expires]);
    }

    /** POST /api/mobile/portal-admin/benutzer/{id}/aktivieren */
    public function portalActivate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $this->db->execute("UPDATE `{$this->t('owner_portal_users')}` SET is_active = 1 WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    /** POST /api/mobile/portal-admin/benutzer/{id}/deaktivieren */
    public function portalDeactivate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $this->db->execute("UPDATE `{$this->t('owner_portal_users')}` SET is_active = 0 WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    /** POST /api/mobile/portal-admin/benutzer/{id}/loeschen */
    public function portalUserDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $this->db->execute("DELETE FROM `{$this->t('owner_portal_users')}` WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    /** GET /api/mobile/portal-admin/stats — portal usage summary */
    public function portalStats(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        try {
            $total    = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('owner_portal_users')}`");
            $active   = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('owner_portal_users')}` WHERE is_active = 1");
            $pending  = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('owner_portal_users')}` WHERE is_active = 0 AND invite_token IS NOT NULL");
            $unread   = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('portal_messages')}` WHERE sender_type = 'owner' AND is_read = 0");
        } catch (\Throwable) {
            $total = $active = $pending = $unread = 0;
        }

        $this->json([
            'total_users'    => $total,
            'active_users'   => $active,
            'pending_invites'=> $pending,
            'unread_messages'=> $unread,
        ]);
    }

    /* ══════════════════════════════════════════════════════
       EXERCISES (Übungen) per Patient
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/portal-admin/patienten/{id}/uebungen */
    public function exercisesList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $patientId = (int)($params['id'] ?? 0);
        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM `{$this->t('pet_exercises')}` WHERE patient_id = ? ORDER BY sort_order ASC, id ASC",
                [$patientId]
            );
        } catch (\Throwable) { $rows = []; }

        $rows = array_map(function (array $e): array {
            if (!empty($e['image'])) {
                $e['image_url'] = '/storage/uploads/exercises/' . basename($e['image']);
            }
            return $e;
        }, $rows);

        $this->json($rows);
    }

    /** GET /api/mobile/portal-admin/uebungen/{id} */
    public function exerciseShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id  = (int)($params['id'] ?? 0);
        $row = $this->db->fetch("SELECT * FROM `{$this->t('pet_exercises')}` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) $this->error('Übung nicht gefunden.', 404);
        if (!empty($row['image'])) {
            $row['image_url'] = '/storage/uploads/exercises/' . basename($row['image']);
        }
        $this->json($row);
    }

    /** POST /api/mobile/portal-admin/patienten/{id}/uebungen — create exercise (JSON or multipart) */
    public function exerciseCreate(array $params = []): void
    {
        $this->cors();
        $user      = $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);

        $data = $this->body();
        if (empty($data['title'])) $this->error('Titel ist erforderlich.');

        $image = null;
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $finfo   = new \finfo(FILEINFO_MIME_TYPE);
            $mime    = $finfo->file($_FILES['image']['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp','image/gif' => 'gif'];
            if (isset($allowed[$mime])) {
                $dir = tenant_storage_path('uploads/exercises/');
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $filename)) {
                    $image = $filename;
                }
            }
        }

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('pet_exercises')}` (patient_id, title, description, video_url, image, sort_order, is_active, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())",
                [
                    $patientId,
                    trim($data['title']),
                    trim($data['description'] ?? ''),
                    trim($data['video_url']   ?? ''),
                    $image,
                    (int)($data['sort_order'] ?? 0),
                    $user['user_id'],
                ]
            );
            $id = (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }

        $row = $this->db->fetch("SELECT * FROM `{$this->t('pet_exercises')}` WHERE id = ?", [$id]);
        if (!empty($row['image'])) $row['image_url'] = '/storage/uploads/exercises/' . basename($row['image']);
        $this->json($row, 201);
    }

    /** POST /api/mobile/portal-admin/uebungen/{id}/update */
    public function exerciseUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id  = (int)($params['id'] ?? 0);
        $row = $this->db->fetch("SELECT id FROM `{$this->t('pet_exercises')}` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) $this->error('Übung nicht gefunden.', 404);

        $data   = $this->body();
        $fields = [];
        foreach (['title','description','video_url','sort_order','is_active'] as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = $data[$f];
        }

        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $finfo   = new \finfo(FILEINFO_MIME_TYPE);
            $mime    = $finfo->file($_FILES['image']['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp','image/gif' => 'gif'];
            if (isset($allowed[$mime])) {
                $dir = tenant_storage_path('uploads/exercises/');
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $filename)) {
                    $fields['image'] = $filename;
                }
            }
        }

        if ($fields) {
            $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $id;
            $this->db->execute("UPDATE `{$this->t('pet_exercises')}` SET {$sets} WHERE id = ?", $values);
        }

        $updated = $this->db->fetch("SELECT * FROM `{$this->t('pet_exercises')}` WHERE id = ?", [$id]);
        if (!empty($updated['image'])) $updated['image_url'] = '/storage/uploads/exercises/' . basename($updated['image']);
        $this->json($updated);
    }

    /** POST /api/mobile/portal-admin/uebungen/{id}/loeschen */
    public function exerciseDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $this->db->execute("DELETE FROM `{$this->t('pet_exercises')}` WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       HOMEWORK PLANS (Hausaufgabenpläne)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/portal-admin/hausaufgabenplaene — all plans (optional ?owner_id= or ?patient_id=) */
    public function homeworkPlanList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $ownerId   = (int)($_GET['owner_id']   ?? 0);
        $patientId = (int)($_GET['patient_id'] ?? 0);

        try {
            $sql    = "SELECT hp.*, p.name AS patient_name,
                              CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                              u.name AS created_by_name
                       FROM `{$this->t('portal_homework_plans')}` hp
                       JOIN `{$this->t('patients')}` p ON p.id = hp.patient_id
                       JOIN `{$this->t('owners')}` o   ON o.id = hp.owner_id
                       LEFT JOIN `{$this->t('users')}` u ON u.id = hp.created_by
                       WHERE 1=1";
            $binds  = [];
            if ($ownerId)   { $sql .= " AND hp.owner_id = ?";   $binds[] = $ownerId; }
            if ($patientId) { $sql .= " AND hp.patient_id = ?"; $binds[] = $patientId; }
            $sql .= " ORDER BY hp.plan_date DESC, hp.id DESC LIMIT 100";
            $rows = $this->db->fetchAll($sql, $binds);
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /** GET /api/mobile/portal-admin/hausaufgabenplaene/{id} — plan detail with tasks */
    public function homeworkPlanShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        try {
            $plan = $this->db->fetch(
                "SELECT hp.*, p.name AS patient_name,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                        u.name AS created_by_name
                 FROM `{$this->t('portal_homework_plans')}` hp
                 JOIN `{$this->t('patients')}` p ON p.id = hp.patient_id
                 JOIN `{$this->t('owners')}` o   ON o.id = hp.owner_id
                 LEFT JOIN `{$this->t('users')}` u ON u.id = hp.created_by
                 WHERE hp.id = ? LIMIT 1",
                [$id]
            );
        } catch (\Throwable) { $plan = null; }
        if (!$plan) $this->error('Hausaufgabenplan nicht gefunden.', 404);

        try {
            $tasks = $this->db->fetchAll(
                "SELECT * FROM `{$this->t('portal_homework_plan_tasks')}` WHERE plan_id = ? ORDER BY sort_order ASC, id ASC",
                [$id]
            );
        } catch (\Throwable) { $tasks = []; }

        $plan['tasks'] = $tasks;
        $this->json($plan);
    }

    /** POST /api/mobile/portal-admin/hausaufgabenplaene — create plan + tasks */
    public function homeworkPlanCreate(array $params = []): void
    {
        $this->cors();
        $user = $this->requireAuth();

        $data      = $this->body();
        $patientId = (int)($data['patient_id'] ?? 0);
        $ownerId   = (int)($data['owner_id']   ?? 0);
        if (!$patientId || !$ownerId) $this->error('patient_id und owner_id sind erforderlich.');

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('portal_homework_plans')}`
                 (patient_id, owner_id, plan_date, physio_principles, short_term_goals,
                  long_term_goals, therapy_means, general_notes, next_appointment,
                  therapist_name, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $patientId,
                    $ownerId,
                    $data['plan_date']         ?? date('Y-m-d'),
                    $data['physio_principles'] ?? null,
                    $data['short_term_goals']  ?? null,
                    $data['long_term_goals']   ?? null,
                    $data['therapy_means']     ?? null,
                    $data['general_notes']     ?? null,
                    $data['next_appointment']  ?? null,
                    $data['therapist_name']    ?? null,
                    $data['status']            ?? 'active',
                    $user['user_id'],
                ]
            );
            $planId = (int)$this->db->lastInsertId();

            $this->saveHomeworkTasks($planId, $data['tasks'] ?? []);

            // Send e-mail notification to owner with portal link
            $patient = $this->patients->findById($patientId);
            $owner   = $this->owners->findById($ownerId);
            if ($patient && $owner && !empty($owner['email'])) {
                $portalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/portal';
                $planTitle = $data['general_notes'] ?? $data['physio_principles'] ?? 'Neuer Hausaufgabenplan';
                $this->mail->sendHomeworkNotification($patient, $owner, $planTitle, $portalUrl);
            }
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }

        $this->json(['success' => true, 'id' => $planId], 201);
    }

    /** POST /api/mobile/portal-admin/hausaufgabenplaene/{id}/update */
    public function homeworkPlanUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id   = (int)($params['id'] ?? 0);
        $plan = $this->db->fetch("SELECT id FROM `{$this->t('portal_homework_plans')}` WHERE id = ? LIMIT 1", [$id]);
        if (!$plan) $this->error('Hausaufgabenplan nicht gefunden.', 404);

        $data   = $this->body();
        $fields = [];
        foreach (['plan_date','physio_principles','short_term_goals','long_term_goals',
                  'therapy_means','general_notes','next_appointment','therapist_name','status'] as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = $data[$f];
        }
        if ($fields) {
            $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $id;
            $this->db->execute("UPDATE `{$this->t('portal_homework_plans')}` SET {$sets} WHERE id = ?", $values);
        }
        if (array_key_exists('tasks', $data)) {
            $this->saveHomeworkTasks($id, $data['tasks']);
        }
        $this->json(['success' => true]);
    }

    /** POST /api/mobile/portal-admin/hausaufgabenplaene/{id}/loeschen */
    public function homeworkPlanDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $this->db->execute("DELETE FROM `{$this->t('portal_homework_plan_tasks')}` WHERE plan_id = ?", [$id]);
        $this->db->execute("DELETE FROM `{$this->t('portal_homework_plans')}` WHERE id = ?", [$id]);
        $this->json(['success' => true]);
    }

    /** GET /api/mobile/portal-admin/hausaufgabenplaene/{id}/pdf — returns redirect URL to PDF */
    public function homeworkPlanPdfUrl(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id   = (int)($params['id'] ?? 0);
        $plan = $this->db->fetch("SELECT id FROM `{$this->t('portal_homework_plans')}` WHERE id = ? LIMIT 1", [$id]);
        if (!$plan) $this->error('Hausaufgabenplan nicht gefunden.', 404);

        $this->json(['pdf_url' => '/portal-admin/hausaufgaben/' . $id . '/pdf']);
    }

    /** POST /api/mobile/portal-admin/hausaufgabenplaene/{id}/senden — send plan PDF via email */
    public function homeworkPlanSend(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $id = (int)($params['id'] ?? 0);
        $this->json([
            'success' => false,
            'message' => 'E-Mail-Versand bitte über den Browser unter /portal-admin/hausaufgaben/' . $id . '/senden ausführen (PDF-Generierung ist Server-seitig).',
            'web_url' => '/portal-admin/hausaufgaben/' . $id . '/senden',
        ], 501);
    }

    /** GET /api/mobile/portal-admin/vorlagen — homework templates */
    public function homeworkTemplates(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM `{$this->t('homework_templates')}` WHERE is_active = 1 ORDER BY category ASC, title ASC"
            );
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /** GET /api/mobile/portal-admin/besitzer/{id}/uebersicht — full overview for one owner: portal user, pets, exercises, plans */
    public function portalOwnerOverview(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $ownerId = (int)($params['id'] ?? 0);
        $owner   = $this->owners->findById($ownerId);
        if (!$owner) $this->error('Tierhalter nicht gefunden.', 404);

        try {
            $portalUser = $this->db->fetch(
                "SELECT id, email, is_active, invite_token, invite_expires, invite_used_at, last_login, created_at
                 FROM `{$this->t('owner_portal_users')}` WHERE owner_id = ? LIMIT 1",
                [$ownerId]
            );
        } catch (\Throwable) { $portalUser = null; }

        $patients = $this->patients->findByOwner($ownerId);
        $patients = array_map(function (array $p): array {
            if (!empty($p['photo'])) {
                $p['photo_url'] = '/patient-photos/' . $p['id'] . '/' . $p['photo'];
            }
            try {
                $p['exercises'] = $this->db->fetchAll(
                    "SELECT * FROM `{$this->t('pet_exercises')}` WHERE patient_id = ? AND is_active = 1 ORDER BY sort_order ASC",
                    [(int)$p['id']]
                );
                $p['exercises'] = array_map(function (array $e): array {
                    if (!empty($e['image'])) $e['image_url'] = '/storage/uploads/exercises/' . basename($e['image']);
                    return $e;
                }, $p['exercises']);
            } catch (\Throwable) { $p['exercises'] = []; }

            try {
                $p['homework_plans'] = $this->db->fetchAll(
                    "SELECT id, plan_date, status, therapist_name, pdf_sent_at FROM `{$this->t('portal_homework_plans')}`
                     WHERE patient_id = ? ORDER BY plan_date DESC",
                    [(int)$p['id']]
                );
            } catch (\Throwable) { $p['homework_plans'] = []; }

            return $p;
        }, $patients);

        $this->json([
            'owner'       => $owner,
            'portal_user' => $portalUser,
            'patients'    => $patients,
        ]);
    }

    /* ── private helper ── */

    private function saveHomeworkTasks(int $planId, array $tasks): void
    {
        $this->db->execute("DELETE FROM `{$this->t('portal_homework_plan_tasks')}` WHERE plan_id = ?", [$planId]);
        foreach ($tasks as $i => $task) {
            if (empty(trim((string)($task['title'] ?? '')))) continue;
            $this->db->execute(
                "INSERT INTO `{$this->t('portal_homework_plan_tasks')}`
                 (plan_id, template_id, title, description, frequency, duration, therapist_notes, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $planId,
                    isset($task['template_id']) ? (int)$task['template_id'] : null,
                    trim($task['title']),
                    $task['description']     ?? null,
                    $task['frequency']       ?? null,
                    $task['duration']        ?? null,
                    $task['therapist_notes'] ?? null,
                    $i,
                ]
            );
        }
    }

    /* ══════════════════════════════════════════════════════
       PROFILE — current user
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/profil */
    public function profileGet(array $params = []): void
    {
        $this->cors();
        $auth = $this->requireAuth();
        $user = $this->users->findById((int)$auth['user_id']);
        if (!$user) $this->error('Benutzer nicht gefunden.', 404);
        unset($user['password']);
        $this->json($user);
    }

    /** POST /api/mobile/profil — update name + email */
    public function profileUpdate(array $params = []): void
    {
        $this->cors();
        $auth = $this->requireAuth();
        $data = $this->body();

        $fields = [];
        if (!empty($data['name']))  $fields['name']  = trim($data['name']);
        if (!empty($data['email'])) $fields['email'] = strtolower(trim($data['email']));

        if ($fields) {
            $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = (int)$auth['user_id'];
            $this->db->execute("UPDATE `{$this->t('users')}` SET {$sets} WHERE id = ?", $values);
        }

        $user = $this->users->findById((int)$auth['user_id']);
        unset($user['password']);
        $this->json($user);
    }

    /** POST /api/mobile/profil/passwort — change own password */
    public function profileChangePassword(array $params = []): void
    {
        $this->cors();
        $auth = $this->requireAuth();
        $data = $this->body();

        $current = (string)($data['current_password'] ?? '');
        $new     = (string)($data['new_password']     ?? '');
        $confirm = (string)($data['confirm_password'] ?? '');

        if (strlen($new) < 8)      $this->error('Neues Passwort muss mindestens 8 Zeichen lang sein.');
        if ($new !== $confirm)     $this->error('Passwörter stimmen nicht überein.');

        $user = $this->users->findById((int)$auth['user_id']);
        if (!password_verify($current, $user['password'])) {
            $this->error('Aktuelles Passwort ist falsch.', 403);
        }

        $this->db->execute(
            "UPDATE `{$this->t('users')}` SET password = ? WHERE id = ?",
            [password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), (int)$auth['user_id']]
        );
        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       THERAPY CARE PRO — Progress Tracking
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/tcp/patienten/{id}/fortschritt */
    public function tcpProgressList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);
        $dateFrom  = $_GET['from'] ?? null;
        $dateTo    = $_GET['to']   ?? null;

        try {
            $sql    = "SELECT e.*, c.name AS category_name, c.color AS category_color,
                              c.scale_min, c.scale_max, u.name AS recorded_by_name
                       FROM `{$this->t('tcp_progress_entries')}` e
                       JOIN `{$this->t('tcp_progress_categories')}` c ON c.id = e.category_id
                       LEFT JOIN `{$this->t('users')}` u ON u.id = e.recorded_by
                       WHERE e.patient_id = ?";
            $params2 = [$patientId];
            if ($dateFrom) { $sql .= " AND e.entry_date >= ?"; $params2[] = $dateFrom; }
            if ($dateTo)   { $sql .= " AND e.entry_date <= ?"; $params2[] = $dateTo; }
            $sql .= " ORDER BY e.entry_date DESC";
            $entries = $this->db->fetchAll($sql, $params2);
        } catch (\Throwable) { $entries = []; }

        try {
            $categories = $this->db->fetchAll(
                "SELECT * FROM `{$this->t('tcp_progress_categories')}` WHERE is_active = 1 ORDER BY sort_order ASC"
            );
        } catch (\Throwable) { $categories = []; }

        $this->json(['entries' => $entries, 'categories' => $categories]);
    }

    /** POST /api/mobile/tcp/patienten/{id}/fortschritt — add entry */
    public function tcpProgressStore(array $params = []): void
    {
        $this->cors();
        $auth      = $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);
        $data      = $this->body();

        if (empty($data['category_id']) || !isset($data['score'])) {
            $this->error('category_id und score sind erforderlich.');
        }

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('tcp_progress_entries')}` (patient_id, category_id, appointment_id, score, notes, recorded_by, entry_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $patientId,
                    (int)$data['category_id'],
                    isset($data['appointment_id']) ? (int)$data['appointment_id'] : null,
                    (float)$data['score'],
                    trim($data['notes'] ?? ''),
                    (int)$auth['user_id'],
                    $data['entry_date'] ?? date('Y-m-d'),
                ]
            );
            $id = (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['success' => true, 'id' => $id], 201);
    }

    /** POST /api/mobile/tcp/fortschritt/{entry_id}/loeschen */
    public function tcpProgressDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->db->execute("DELETE FROM `{$this->t('tcp_progress_entries')}` WHERE id = ?", [(int)($params['entry_id'] ?? 0)]);
        $this->json(['success' => true]);
    }

    /** GET /api/mobile/tcp/fortschritt/kategorien — all progress categories */
    public function tcpProgressCategories(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        try {
            $rows = $this->db->fetchAll("SELECT * FROM `{$this->t('tcp_progress_categories')}` ORDER BY sort_order ASC, name ASC");
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /* ══════════════════════════════════════════════════════
       THERAPY CARE PRO — Exercise Feedback
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/tcp/patienten/{id}/feedback */
    public function tcpFeedbackList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);
        $days      = (int)($_GET['days'] ?? 30);

        try {
            $sql = "SELECT f.*, ph.title AS homework_title, o.first_name, o.last_name
                    FROM `{$this->t('tcp_exercise_feedback')}` f
                    LEFT JOIN `{$this->t('portal_homework_plans')}` ph ON ph.id = f.homework_id
                    LEFT JOIN `{$this->t('owners')}` o ON o.id = f.owner_id
                    WHERE f.patient_id = ?";
            $p = [$patientId];
            if ($days > 0) { $sql .= " AND f.feedback_date >= DATE_SUB(NOW(), INTERVAL ? DAY)"; $p[] = $days; }
            $sql .= " ORDER BY f.feedback_date DESC";
            $rows = $this->db->fetchAll($sql, $p);
        } catch (\Throwable) { $rows = []; }

        try {
            $summary = $this->db->fetch(
                "SELECT COUNT(*) AS total,
                        SUM(status='good') AS good,
                        SUM(status='ok') AS ok_count,
                        SUM(status='bad') AS bad
                 FROM `{$this->t('tcp_exercise_feedback')}` WHERE patient_id = ?",
                [$patientId]
            );
        } catch (\Throwable) { $summary = []; }

        $this->json(['feedback' => $rows, 'summary' => $summary]);
    }

    /** GET /api/mobile/tcp/feedback/problematisch — feedback with status=bad (last 7 days) */
    public function tcpFeedbackProblematic(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        try {
            $rows = $this->db->fetchAll(
                "SELECT f.*, ph.title AS homework_title, p.name AS patient_name,
                        o.first_name, o.last_name
                 FROM `{$this->t('tcp_exercise_feedback')}` f
                 LEFT JOIN `{$this->t('portal_homework_plans')}` ph ON ph.id = f.homework_id
                 JOIN `{$this->t('patients')}` p ON p.id = f.patient_id
                 LEFT JOIN `{$this->t('owners')}` o ON o.id = f.owner_id
                 WHERE f.status = 'bad' AND f.feedback_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY f.feedback_date DESC"
            );
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /* ══════════════════════════════════════════════════════
       THERAPY CARE PRO — Therapy Reports (Berichte)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/tcp/patienten/{id}/berichte */
    public function tcpReportList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);
        try {
            $rows = $this->db->fetchAll(
                "SELECT r.*, u.name AS created_by_name
                 FROM `{$this->t('tcp_therapy_reports')}` r
                 LEFT JOIN `{$this->t('users')}` u ON u.id = r.created_by
                 WHERE r.patient_id = ?
                 ORDER BY r.report_date DESC",
                [$patientId]
            );
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /** GET /api/mobile/tcp/berichte/{id} — single report */
    public function tcpReportShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        try {
            $row = $this->db->fetch("SELECT * FROM `{$this->t('tcp_therapy_reports')}` WHERE id = ? LIMIT 1", [$id]);
        } catch (\Throwable) { $row = null; }
        if (!$row) $this->error('Bericht nicht gefunden.', 404);
        $this->json($row);
    }

    /** POST /api/mobile/tcp/patienten/{id}/berichte — create report */
    public function tcpReportCreate(array $params = []): void
    {
        $this->cors();
        $auth      = $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);
        $data      = $this->body();

        if (empty($data['title'])) $this->error('Titel ist erforderlich.');

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('tcp_therapy_reports')}`
                 (patient_id, title, content, diagnosis, treatment_summary, recommendations,
                  next_appointment, report_date, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $patientId,
                    trim($data['title']),
                    $data['content']             ?? null,
                    $data['diagnosis']           ?? null,
                    $data['treatment_summary']   ?? null,
                    $data['recommendations']     ?? null,
                    $data['next_appointment']    ?? null,
                    $data['report_date']         ?? date('Y-m-d'),
                    (int)$auth['user_id'],
                ]
            );
            $id = (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['success' => true, 'id' => $id, 'pdf_url' => '/patienten/' . $patientId . '/berichte/' . $id . '/download'], 201);
    }

    /** POST /api/mobile/tcp/berichte/{id}/loeschen */
    public function tcpReportDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->db->execute("DELETE FROM `{$this->t('tcp_therapy_reports')}` WHERE id = ?", [(int)($params['id'] ?? 0)]);
        $this->json(['success' => true]);
    }

    /** GET /api/mobile/tcp/berichte/{id}/pdf — returns URL for download */
    public function tcpReportPdfUrl(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id  = (int)($params['id'] ?? 0);
        $row = $this->db->fetch("SELECT patient_id FROM `{$this->t('tcp_therapy_reports')}` WHERE id = ? LIMIT 1", [$id]);
        if (!$row) $this->error('Bericht nicht gefunden.', 404);
        $this->json(['pdf_url' => '/patienten/' . $row['patient_id'] . '/berichte/' . $id . '/download']);
    }

    /* ══════════════════════════════════════════════════════
       THERAPY CARE PRO — Exercise Library (Übungsbibliothek)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/tcp/bibliothek?category=&search= */
    public function tcpLibraryList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $category = trim($_GET['category'] ?? '');
        $search   = trim($_GET['search']   ?? '');

        try {
            $sql  = "SELECT * FROM `{$this->t('tcp_exercise_library')}` WHERE 1=1";
            $bind = [];
            if ($category) { $sql .= " AND category = ?"; $bind[] = $category; }
            if ($search)   { $sql .= " AND (title LIKE ? OR description LIKE ?)"; $bind[] = "%{$search}%"; $bind[] = "%{$search}%"; }
            $sql .= " ORDER BY category ASC, title ASC";
            $rows = $this->db->fetchAll($sql, $bind);
        } catch (\Throwable) { $rows = []; }

        $rows = array_map(function (array $e): array {
            if (!empty($e['image'])) $e['image_url'] = '/storage/uploads/tcp/' . basename($e['image']);
            if (!empty($e['video_file'])) $e['video_url'] = '/storage/uploads/tcp/' . basename($e['video_file']);
            return $e;
        }, $rows);

        $this->json($rows);
    }

    /** GET /api/mobile/tcp/bibliothek/{id} */
    public function tcpLibraryShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id  = (int)($params['id'] ?? 0);
        try {
            $row = $this->db->fetch("SELECT * FROM `{$this->t('tcp_exercise_library')}` WHERE id = ? LIMIT 1", [$id]);
        } catch (\Throwable) { $row = null; }
        if (!$row) $this->error('Übung nicht gefunden.', 404);
        if (!empty($row['image']))      $row['image_url'] = '/storage/uploads/tcp/' . basename($row['image']);
        if (!empty($row['video_file'])) $row['video_url'] = '/storage/uploads/tcp/' . basename($row['video_file']);
        $this->json($row);
    }

    /** POST /api/mobile/tcp/bibliothek — create library entry */
    public function tcpLibraryCreate(array $params = []): void
    {
        $this->cors();
        $auth = $this->requireAuth();
        $data = $this->body();
        if (empty($data['title'])) $this->error('Titel ist erforderlich.');

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('tcp_exercise_library')}`
                 (title, description, category, duration_minutes, repetitions, video_url, instructions,
                  contraindications, equipment, difficulty, is_active, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
                [
                    trim($data['title']),
                    $data['description']      ?? null,
                    $data['category']         ?? 'sonstiges',
                    isset($data['duration_minutes']) ? (int)$data['duration_minutes'] : null,
                    $data['repetitions']      ?? null,
                    $data['video_url']        ?? null,
                    $data['instructions']     ?? null,
                    $data['contraindications']?? null,
                    $data['equipment']        ?? null,
                    $data['difficulty']       ?? 'mittel',
                    (int)$auth['user_id'],
                ]
            );
            $id = (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['success' => true, 'id' => $id], 201);
    }

    /** POST /api/mobile/tcp/bibliothek/{id}/update */
    public function tcpLibraryUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id   = (int)($params['id'] ?? 0);
        $data = $this->body();

        $allowed = ['title','description','category','duration_minutes','repetitions',
                    'video_url','instructions','contraindications','equipment','difficulty','is_active'];
        $fields = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = $data[$f];
        }
        if ($fields) {
            $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $id;
            $this->db->execute("UPDATE `{$this->t('tcp_exercise_library')}` SET {$sets} WHERE id = ?", $values);
        }
        $this->json(['success' => true]);
    }

    /** POST /api/mobile/tcp/bibliothek/{id}/loeschen */
    public function tcpLibraryDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->db->execute("DELETE FROM `{$this->t('tcp_exercise_library')}` WHERE id = ?", [(int)($params['id'] ?? 0)]);
        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       THERAPY CARE PRO — Natural Therapy (Naturheilkunde)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/tcp/patienten/{id}/naturheilkunde */
    public function tcpNaturalList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);
        try {
            $rows = $this->db->fetchAll(
                "SELECT n.*, u.name AS recorded_by_name
                 FROM `{$this->t('tcp_natural_therapy')}` n
                 LEFT JOIN `{$this->t('users')}` u ON u.id = n.recorded_by
                 WHERE n.patient_id = ?
                 ORDER BY n.session_date DESC",
                [$patientId]
            );
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /** POST /api/mobile/tcp/patienten/{id}/naturheilkunde — add session */
    public function tcpNaturalCreate(array $params = []): void
    {
        $this->cors();
        $auth      = $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);
        $data      = $this->body();

        if (empty($data['therapy_type'])) $this->error('therapy_type ist erforderlich.');

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('tcp_natural_therapy')}`
                 (patient_id, therapy_type, products_used, dosage, application_method,
                  response, notes, session_date, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $patientId,
                    trim($data['therapy_type']),
                    $data['products_used']      ?? null,
                    $data['dosage']             ?? null,
                    $data['application_method'] ?? null,
                    $data['response']           ?? null,
                    $data['notes']              ?? null,
                    $data['session_date']       ?? date('Y-m-d'),
                    (int)$auth['user_id'],
                ]
            );
            $id = (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['success' => true, 'id' => $id], 201);
    }

    /** POST /api/mobile/tcp/naturheilkunde/{id}/update */
    public function tcpNaturalUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id   = (int)($params['id'] ?? 0);
        $data = $this->body();

        $allowed = ['therapy_type','products_used','dosage','application_method','response','notes','session_date'];
        $fields  = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) $fields[$f] = $data[$f];
        }
        if ($fields) {
            $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $id;
            $this->db->execute("UPDATE `{$this->t('tcp_natural_therapy')}` SET {$sets} WHERE id = ?", $values);
        }
        $this->json(['success' => true]);
    }

    /** POST /api/mobile/tcp/naturheilkunde/{id}/loeschen */
    public function tcpNaturalDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $this->db->execute("DELETE FROM `{$this->t('tcp_natural_therapy')}` WHERE id = ?", [(int)($params['id'] ?? 0)]);
        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       THERAPY CARE PRO — Reminder Queue (Erinnerungswarteschlange)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/tcp/erinnerungen/vorlagen */
    public function tcpReminderTemplates(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM `{$this->t('tcp_reminder_templates')}` WHERE is_active = 1 ORDER BY type ASC, name ASC"
            );
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /** GET /api/mobile/tcp/patienten/{id}/erinnerungen — queued reminders for patient */
    public function tcpReminderQueue(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);
        try {
            $rows = $this->db->fetchAll(
                "SELECT q.*, t.name AS template_name
                 FROM `{$this->t('tcp_reminder_queue')}` q
                 LEFT JOIN `{$this->t('tcp_reminder_templates')}` t ON t.id = q.template_id
                 WHERE q.patient_id = ?
                 ORDER BY q.send_at ASC",
                [$patientId]
            );
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /** POST /api/mobile/tcp/patienten/{id}/erinnerungen — queue a reminder */
    public function tcpReminderQueueStore(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);
        $data      = $this->body();

        if (empty($data['type']) || empty($data['subject']) || empty($data['send_at'])) {
            $this->error('type, subject und send_at sind erforderlich.');
        }

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('tcp_reminder_queue')}`
                 (template_id, type, patient_id, owner_id, appointment_id, subject, body, send_at, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                [
                    isset($data['template_id']) ? (int)$data['template_id'] : null,
                    $data['type'],
                    $patientId,
                    isset($data['owner_id'])      ? (int)$data['owner_id']      : null,
                    isset($data['appointment_id'])? (int)$data['appointment_id']: null,
                    trim($data['subject']),
                    $data['body']    ?? null,
                    $data['send_at'],
                ]
            );
            $id = (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }
        $this->json(['success' => true, 'id' => $id], 201);
    }

    /* ══════════════════════════════════════════════════════
       TAX EXPORT PRO — Steuerexport
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/steuerexport?from=YYYY-MM-DD&to=YYYY-MM-DD&status= */
    public function taxExportList(array $params = []): void
    {
        $this->cors();
        $this->requireAdmin();

        $from   = $_GET['from']   ?? date('Y-01-01');
        $to     = $_GET['to']     ?? date('Y-12-31');
        $status = $_GET['status'] ?? '';

        try {
            $sql  = "SELECT i.id, i.invoice_number, i.invoice_type,
                            i.status, i.issue_date, i.due_date,
                            i.total_net, i.total_tax, i.total_gross,
                            i.payment_method, i.cancels_invoice_id,
                            i.cancellation_invoice_id, i.cancellation_reason,
                            i.cancelled_at, i.finalized_at, i.gobd_hash,
                            CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                            o.email AS owner_email,
                            p.name AS patient_name
                     FROM `{$this->t('invoices')}` i
                     LEFT JOIN `{$this->t('owners')}` o ON o.id = i.owner_id
                     LEFT JOIN `{$this->t('patients')}` p ON p.id = i.patient_id
                     WHERE i.issue_date >= ? AND i.issue_date <= ?";
            $bind = [$from, $to];
            if ($status) { $sql .= " AND i.status = ?"; $bind[] = $status; }
            $sql .= " ORDER BY i.invoice_type ASC, i.issue_date ASC, i.invoice_number ASC";
            $rows = $this->db->fetchAll($sql, $bind);
        } catch (\Throwable) { $rows = []; }

        try {
            $stats = $this->db->fetch(
                "SELECT
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN status = 'paid' THEN total_gross ELSE 0 END) AS total_paid,
                    SUM(CASE WHEN status = 'open' THEN total_gross ELSE 0 END) AS total_open,
                    SUM(total_net) AS sum_net,
                    SUM(total_tax) AS sum_tax,
                    SUM(total_gross) AS sum_gross
                 FROM `{$this->t('invoices')}`
                 WHERE issue_date >= ? AND issue_date <= ?",
                [$from, $to]
            );
        } catch (\Throwable) { $stats = []; }

        $this->json(['invoices' => $rows, 'stats' => $stats, 'period' => ['from' => $from, 'to' => $to]]);
    }

    /** POST /api/mobile/steuerexport/{id}/finalisieren — GoBD finalize (admin) */
    public function taxExportFinalize(array $params = []): void
    {
        $this->cors();
        $this->requireAdmin();
        $id      = (int)($params['id'] ?? 0);
        $invoice = $this->invoices->findById($id);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        try {
            $existing = $this->db->fetchColumn(
                "SELECT finalized_at FROM `{$this->t('invoices')}` WHERE id = ?", [$id]
            );
            if ($existing) $this->error('Rechnung ist bereits finalisiert.', 409);

            $hash = hash('sha256', json_encode($invoice));
            $this->db->execute(
                "UPDATE `{$this->t('invoices')}` SET finalized_at = NOW(), gobd_hash = ? WHERE id = ?",
                [$hash, $id]
            );

            try {
                $this->db->execute(
                    "INSERT INTO `{$this->t('gobd_audit_log')}` (invoice_id, invoice_number, action, user_id, meta)
                     VALUES (?, ?, 'finalized', ?, ?)",
                    [$id, $invoice['invoice_number'] ?? '', null, json_encode(['hash' => $hash])]
                );
            } catch (\Throwable) {}
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'bereits finalisiert')) throw $e;
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }

        $this->json(['success' => true, 'gobd_hash' => $hash]);
    }

    /** POST /api/mobile/steuerexport/{id}/stornieren — GoBD-konformer Storno (admin) */
    public function taxExportCancel(array $params = []): void
    {
        $this->cors();
        $auth = $this->requireAdmin();
        $id   = (int)($params['id'] ?? 0);

        $invoice = $this->invoices->findById($id);
        if (!$invoice) $this->error('Rechnung nicht gefunden.', 404);

        $data   = $this->body();
        $reason = trim($data['reason'] ?? '');
        if ($reason === '') $this->error('Stornogrund darf nicht leer sein.', 422);

        try {
            $svc    = new \App\Services\InvoiceCancellationService($this->db, $this->invoices, $this->settings);
            $result = $svc->cancel($id, $reason, (int)($auth['user_id'] ?? 0));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage(), 422);
        }

        $this->json(['success' => true, 'cancellation_id' => $result['cancellation_id'], 'cancellation_number' => $result['cancellation_number']]);
    }

    /** GET /api/mobile/steuerexport/audit-log?limit=50 */
    public function taxExportAuditLog(array $params = []): void
    {
        $this->cors();
        $this->requireAdmin();
        $limit = min((int)($_GET['limit'] ?? 50), 200);
        try {
            $rows = $this->db->fetchAll(
                "SELECT a.*, i.invoice_number, CONCAT(o.first_name,' ',o.last_name) AS owner_name
                 FROM `{$this->t('gobd_audit_log')}` a
                 LEFT JOIN `{$this->t('invoices')}` i ON i.id = a.invoice_id
                 LEFT JOIN `{$this->t('owners')}` o ON o.id = i.owner_id
                 ORDER BY a.created_at DESC LIMIT ?",
                [$limit]
            );
        } catch (\Throwable) { $rows = []; }
        $this->json($rows);
    }

    /** GET /api/mobile/steuerexport/export-url — returns CSV/ZIP download URLs */
    public function taxExportUrls(array $params = []): void
    {
        $this->cors();
        $this->requireAdmin();
        $from   = $_GET['from']   ?? date('Y-01-01');
        $to     = $_GET['to']     ?? date('Y-12-31');
        $status = $_GET['status'] ?? 'paid';

        $base = '/steuerexport/export-';
        $qs   = '?' . http_build_query(['from' => $from, 'to' => $to, 'status' => $status]);

        $this->json([
            'csv_url'   => $base . 'csv'   . $qs,
            'zip_url'   => $base . 'zip'   . $qs,
            'pdf_url'   => $base . 'pdf'   . $qs,
            'datev_url' => $base . 'datev' . $qs,
        ]);
    }

    /* ══════════════════════════════════════════════════════
       MAILBOX (IMAP/SMTP)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/mailbox/status — is mailbox configured? + unread count */
    public function mailboxStatus(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $configured = false;
        $unread     = 0;
        try {
            $host = $this->settings->get('imap_host', '');
            $user = $this->settings->get('imap_user', '');
            $pass = $this->settings->get('imap_pass', '');
            $configured = !empty($host) && !empty($user) && !empty($pass);
        } catch (\Throwable) {}

        $this->json(['configured' => $configured, 'unread' => $unread]);
    }

    /** GET /api/mobile/mailbox/nachrichten?folder=INBOX&page=1 — list messages */
    public function mailboxList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $folder  = $_GET['folder'] ?? 'INBOX';
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;

        if (!function_exists('imap_open')) {
            $this->json(['items' => [], 'total' => 0, 'page' => $page,
                'error' => 'IMAP PHP-Extension nicht installiert.']);
            return;
        }

        try {
            $host = $this->settings->get('imap_host', '');
            $port = $this->settings->get('imap_port', '993');
            $user = $this->settings->get('imap_user', '');
            $pass = $this->settings->get('imap_pass', '');
            $enc  = $this->settings->get('imap_encryption', 'ssl');

            if (!$host || !$user || !$pass) {
                $this->json(['items' => [], 'total' => 0, 'page' => $page,
                    'error' => 'Mailbox nicht konfiguriert.']);
                return;
            }

            $flag = ($enc === 'ssl') ? '/ssl' : (($enc === 'tls') ? '/tls' : '');
            $conn  = @imap_open('{' . $host . ':' . $port . '/imap' . $flag . '}' . $folder,
                $user, $pass, 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);

            if (!$conn) {
                $this->json(['items' => [], 'total' => 0, 'page' => $page,
                    'error' => 'Verbindung fehlgeschlagen.']);
                return;
            }

            $total  = imap_num_msg($conn);
            $start  = max(1, $total - ($page - 1) * $perPage);
            $end    = max(1, $start - $perPage + 1);
            $items  = [];

            for ($i = $start; $i >= $end; $i--) {
                $hdr = imap_headerinfo($conn, $i);
                if (!$hdr) continue;
                $uid = imap_uid($conn, $i);
                $items[] = [
                    'uid'     => $uid,
                    'subject' => isset($hdr->subject) ? imap_utf8($hdr->subject) : '(kein Betreff)',
                    'from'    => isset($hdr->from[0]) ? ($hdr->from[0]->mailbox . '@' . $hdr->from[0]->host) : '',
                    'from_name' => isset($hdr->from[0]->personal) ? imap_utf8($hdr->from[0]->personal) : '',
                    'date'    => $hdr->date ?? '',
                    'unseen'  => (isset($hdr->Unseen) && $hdr->Unseen === 'U'),
                    'size'    => $hdr->Size ?? 0,
                ];
            }

            imap_close($conn);
            $this->json(['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
        } catch (\Throwable $e) {
            $this->json(['items' => [], 'total' => 0, 'page' => $page, 'error' => $e->getMessage()]);
        }
    }

    /** GET /api/mobile/mailbox/nachrichten/{uid}?folder=INBOX */
    public function mailboxShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $uid    = (int)($params['uid'] ?? 0);
        $folder = $_GET['folder'] ?? 'INBOX';

        if (!function_exists('imap_open')) {
            $this->error('IMAP nicht verfügbar.', 501);
        }

        try {
            $host = $this->settings->get('imap_host', '');
            $port = $this->settings->get('imap_port', '993');
            $user = $this->settings->get('imap_user', '');
            $pass = $this->settings->get('imap_pass', '');
            $enc  = $this->settings->get('imap_encryption', 'ssl');
            $flag = ($enc === 'ssl') ? '/ssl' : (($enc === 'tls') ? '/tls' : '');

            $conn = @imap_open('{' . $host . ':' . $port . '/imap' . $flag . '}' . $folder,
                $user, $pass, 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
            if (!$conn) $this->error('Verbindung fehlgeschlagen.', 503);

            $seq = imap_msgno($conn, $uid);
            if (!$seq) { imap_close($conn); $this->error('Nachricht nicht gefunden.', 404); }

            $hdr     = imap_headerinfo($conn, $seq);
            $body    = imap_fetchbody($conn, $seq, '1');
            $enc_b   = imap_fetchstructure($conn, $seq)->encoding ?? 0;
            if ($enc_b === 3) $body = base64_decode($body);
            elseif ($enc_b === 4) $body = quoted_printable_decode($body);

            imap_setflag_full($conn, (string)$uid, '\\Seen', ST_UID);
            imap_close($conn);

            $this->json([
                'uid'       => $uid,
                'subject'   => isset($hdr->subject) ? imap_utf8($hdr->subject) : '(kein Betreff)',
                'from'      => isset($hdr->from[0]) ? ($hdr->from[0]->mailbox . '@' . $hdr->from[0]->host) : '',
                'from_name' => isset($hdr->from[0]->personal) ? imap_utf8($hdr->from[0]->personal) : '',
                'to'        => isset($hdr->to[0]) ? ($hdr->to[0]->mailbox . '@' . $hdr->to[0]->host) : '',
                'date'      => $hdr->date ?? '',
                'body'      => $body,
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    /** POST /api/mobile/mailbox/senden */
    public function mailboxSend(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $data = $this->body();

        if (empty($data['to']) || empty($data['subject'])) {
            $this->error('to und subject sind erforderlich.');
        }

        try {
            $smtpHost = $this->settings->get('smtp_host', '');
            $smtpPort = (int)$this->settings->get('smtp_port', '587');
            $smtpUser = $this->settings->get('smtp_user', '');
            $smtpPass = $this->settings->get('smtp_pass', '');
            $fromMail = $this->settings->get('smtp_from_email', $smtpUser);
            $fromName = $this->settings->get('smtp_from_name', 'Tierphysio');
            $enc      = $this->settings->get('smtp_encryption', 'tls');

            if (!$smtpHost || !$smtpUser) $this->error('SMTP nicht konfiguriert.', 503);

            $boundary = bin2hex(random_bytes(8));
            $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromMail}>\r\n"
                       . "To: " . $data['to'] . "\r\n"
                       . "Subject: =?UTF-8?B?" . base64_encode($data['subject']) . "?=\r\n"
                       . "MIME-Version: 1.0\r\n"
                       . "Content-Type: text/html; charset=UTF-8\r\n"
                       . "Content-Transfer-Encoding: base64\r\n";

            $body = base64_chunk_split(base64_encode($data['body'] ?? ''));

            $prefix = ($enc === 'ssl') ? 'ssl://' : '';
            $sock   = @fsockopen($prefix . $smtpHost, $smtpPort, $errno, $errstr, 10);
            if (!$sock) $this->error("SMTP-Verbindung fehlgeschlagen: {$errstr}", 503);

            $read = fn() => fgets($sock, 512);
            $send = fn(string $cmd) => fputs($sock, $cmd . "\r\n");
            $read();
            $send("EHLO " . gethostname()); $read(); $read(); $read(); $read(); $read();
            if ($enc === 'tls') {
                $send("STARTTLS"); $read();
                stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $send("EHLO " . gethostname()); $read(); $read(); $read(); $read(); $read();
            }
            $send("AUTH LOGIN"); $read();
            $send(base64_encode($smtpUser)); $read();
            $send(base64_encode($smtpPass)); $read();
            $send("MAIL FROM:<{$fromMail}>"); $read();
            $send("RCPT TO:<{$data['to']}>"); $read();
            $send("DATA"); $read();
            fputs($sock, $headers . "\r\n" . $body . "\r\n.\r\n");
            $read();
            $send("QUIT");
            fclose($sock);
        } catch (\Throwable $e) {
            $this->error('E-Mail-Versand fehlgeschlagen: ' . $e->getMessage(), 500);
        }

        $this->json(['success' => true]);
    }

    /** POST /api/mobile/mailbox/nachrichten/{uid}/loeschen?folder=INBOX */
    public function mailboxDelete(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $uid    = (int)($params['uid'] ?? 0);
        $folder = $_GET['folder'] ?? 'INBOX';

        if (!function_exists('imap_open')) $this->error('IMAP nicht verfügbar.', 501);

        try {
            $host = $this->settings->get('imap_host', '');
            $port = $this->settings->get('imap_port', '993');
            $user = $this->settings->get('imap_user', '');
            $pass = $this->settings->get('imap_pass', '');
            $enc  = $this->settings->get('imap_encryption', 'ssl');
            $flag = ($enc === 'ssl') ? '/ssl' : (($enc === 'tls') ? '/tls' : '');

            $conn = @imap_open('{' . $host . ':' . $port . '/imap' . $flag . '}' . $folder,
                $user, $pass, 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
            if (!$conn) $this->error('Verbindung fehlgeschlagen.', 503);

            imap_delete($conn, (string)$uid, FT_UID);
            imap_expunge($conn);
            imap_close($conn);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500);
        }

        $this->json(['success' => true]);
    }


    /**
     * Fire-and-forget Google Calendar sync for a single appointment.
     * Called after create / update / delete from the mobile API.
     * Silently swallows all errors so it never breaks the main API response.
     */
    private function googleSyncAppointment(int $id, string $action): void
    {
        try {
            $repo = new \Plugins\GoogleCalendarSync\GoogleCalendarRepository($this->db);
            $conn = $repo->getConnection();
            if (!$conn || empty($conn['sync_enabled']) || empty($conn['access_token'])) {
                return; // Google not connected or sync disabled
            }
            $api  = new \Plugins\GoogleCalendarSync\GoogleApiService($repo);
            $sync = new \Plugins\GoogleCalendarSync\GoogleSyncService($repo, $api, $this->db);
            match ($action) {
                'create' => $sync->syncCreated($id),
                'update' => $sync->syncUpdated($id),
                'delete' => $sync->syncDeleted($id),
                default  => null,
            };
        } catch (\Throwable) {
            // Never let sync errors propagate to the caller
        }
    }

    /* ══════════════════════════════════════════════════════
       SYSTEM — Cron / Status
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/system/status — overall system health */
    public function systemStatus(array $params = []): void
    {
        $this->cors();
        $this->requireAdmin();

        $status = ['ok' => true, 'checks' => []];

        /* DB */
        try {
            $this->db->fetchColumn("SELECT 1");
            $status['checks']['database'] = 'ok';
        } catch (\Throwable) {
            $status['checks']['database'] = 'error';
            $status['ok'] = false;
        }

        /* Mail */
        try {
            $smtpHost = $this->settings->get('smtp_host', '');
            $status['checks']['smtp_configured'] = !empty($smtpHost);
        } catch (\Throwable) {
            $status['checks']['smtp_configured'] = false;
        }

        /* Google Calendar */
        try {
            $gc = $this->db->fetch("SELECT sync_enabled, last_sync_at FROM `{$this->t('google_calendar_connections')}` LIMIT 1");
            $status['checks']['google_calendar'] = $gc ? ['enabled' => (bool)$gc['sync_enabled'], 'last_sync' => $gc['last_sync_at']] : null;
        } catch (\Throwable) {
            $status['checks']['google_calendar'] = null;
        }

        /* TCP reminder queue */
        try {
            $pending = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('tcp_reminder_queue')}` WHERE status = 'pending'");
            $status['checks']['tcp_reminder_queue_pending'] = $pending;
        } catch (\Throwable) {
            $status['checks']['tcp_reminder_queue_pending'] = null;
        }

        /* Overdue invoices */
        try {
            $overdue = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('invoices')}` WHERE status = 'overdue'");
            $status['checks']['overdue_invoices'] = $overdue;
        } catch (\Throwable) {
            $status['checks']['overdue_invoices'] = null;
        }

        /* Portal */
        try {
            $portalUsers = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('owner_portal_users')}` WHERE is_active = 1");
            $status['checks']['portal_active_users'] = $portalUsers;
        } catch (\Throwable) {
            $status['checks']['portal_active_users'] = null;
        }

        $status['timestamp'] = date('Y-m-d H:i:s');
        $this->json($status);
    }

    /** GET /api/mobile/system/cronjobs — cron job overview */
    public function systemCronJobs(array $params = []): void
    {
        $this->cors();
        $this->requireAdmin();

        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM `{$this->t('cron_log')}` ORDER BY started_at DESC LIMIT 50"
            );
        } catch (\Throwable) { $rows = []; }

        $this->json($rows);
    }

    /* ══════════════════════════════════════════════════════
       OWNER PORTAL — AUTH (Besitzerportal Login/Logout)
       Separate token system for portal users (owners)
    ══════════════════════════════════════════════════════ */

    /** POST /api/mobile/portal/login — owner logs into portal */
    public function portalLogin(array $params = []): void
    {
        $this->cors();
        $data  = $this->body();
        $email = strtolower(trim($data['email'] ?? ''));
        $pass  = (string)($data['password'] ?? '');

        if (!$email || !$pass) $this->error('E-Mail und Passwort erforderlich.');

        try {
            $user = $this->db->fetch(
                "SELECT * FROM `{$this->t('owner_portal_users')}` WHERE email = ? LIMIT 1",
                [$email]
            );
        } catch (\Throwable $e) {
            $this->error('Datenbankfehler: ' . $e->getMessage(), 500);
        }

        if (!$user || !$user['is_active'] || !$user['password_hash'] ||
            !password_verify($pass, $user['password_hash'])) {
            $this->error('E-Mail oder Passwort ist falsch.', 401);
        }

        /* Generate portal token */
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        try {
            $this->db->execute(
                "UPDATE `{$this->t('owner_portal_users')}` SET last_login = NOW() WHERE id = ?",
                [(int)$user['id']]
            );
            $this->db->execute(
                "INSERT INTO `{$this->t('owner_portal_tokens')}` (user_id, token, expires_at, created_at)
                 VALUES (?, ?, ?, NOW())",
                [(int)$user['id'], hash('sha256', $token), $expires]
            );
        } catch (\Throwable) {
            /* Fallback: store token in mobile_api_tokens table */
            try {
                $this->db->execute(
                    "INSERT INTO `{$this->t('mobile_api_tokens')}` (user_id, token, name, expires_at, created_at)
                     VALUES (?, ?, 'portal_owner', ?, NOW())",
                    [(int)$user['id'], hash('sha256', $token), $expires]
                );
            } catch (\Throwable $e2) {
                $this->error('Token-Erstellung fehlgeschlagen: ' . $e2->getMessage(), 500);
            }
        }

        unset($user['password_hash'], $user['invite_token']);
        $this->json([
            'token'      => $token,
            'expires_at' => $expires,
            'user'       => $user,
            'owner_id'   => $user['owner_id'],
        ]);
    }

    /** POST /api/mobile/portal/logout — invalidate portal token */
    public function portalLogout(array $params = []): void
    {
        $this->cors();
        $raw = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        if (preg_match('/^Bearer\s+(.+)$/i', $raw, $m)) $token = $m[1];
        if ($token) {
            $hashed = hash('sha256', $token);
            try {
                $this->db->execute("DELETE FROM `{$this->t('owner_portal_tokens')}` WHERE token = ?", [$hashed]);
            } catch (\Throwable) {
                try {
                    $this->db->execute("DELETE FROM `{$this->t('mobile_api_tokens')}` WHERE token = ?", [$hashed]);
                } catch (\Throwable) {}
            }
        }
        $this->json(['success' => true]);
    }

    /** POST /api/mobile/portal/passwort-setzen/{token} — owner sets password via invite token */
    public function portalSetPassword(array $params = []): void
    {
        $this->cors();
        $inviteToken = $params['token'] ?? '';
        $data        = $this->body();

        if (!$inviteToken) $this->error('Token fehlt.');

        try {
            $user = $this->db->fetch(
                "SELECT * FROM `{$this->t('owner_portal_users')}` WHERE invite_token = ? LIMIT 1",
                [$inviteToken]
            );
        } catch (\Throwable $e) {
            $this->error('Datenbankfehler.', 500);
        }

        if (!$user) $this->error('Einladungslink ungültig oder bereits verwendet.', 404);
        if (!empty($user['invite_used_at'])) $this->error('Dieser Link wurde bereits verwendet.', 409);
        if (!empty($user['invite_expires']) && strtotime($user['invite_expires']) < time()) {
            $this->error('Dieser Einladungslink ist abgelaufen.', 410);
        }

        $password = (string)($data['password'] ?? '');
        $confirm  = (string)($data['confirm_password'] ?? '');

        if (strlen($password) < 8) $this->error('Passwort muss mindestens 8 Zeichen lang sein.');
        if ($password !== $confirm) $this->error('Passwörter stimmen nicht überein.');

        $this->db->execute(
            "UPDATE `{$this->t('owner_portal_users')}` SET
                password_hash  = ?,
                invite_used_at = NOW(),
                invite_token   = NULL,
                is_active      = 1
             WHERE id = ?",
            [password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), (int)$user['id']]
        );

        $this->json(['success' => true, 'message' => 'Passwort gesetzt. Du kannst dich jetzt einloggen.']);
    }

    /* ── helper: resolve portal user from Bearer token ── */
    private function requirePortalAuth(): array
    {
        $raw = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        if (preg_match('/^Bearer\s+(.+)$/i', $raw, $m)) $token = $m[1];
        if (!$token) $this->error('Portal-Token fehlt.', 401);

        $hashed = hash('sha256', $token);
        $row = null;

        /* Try dedicated portal token table first */
        try {
            $row = $this->db->fetch(
                "SELECT t.*, u.owner_id, u.email, u.is_active,
                        u.first_name, u.last_name, u.id AS portal_user_id
                 FROM `{$this->t('owner_portal_tokens')}` t
                 JOIN `{$this->t('owner_portal_users')}` u ON u.id = t.user_id
                 WHERE t.token = ? AND (t.expires_at IS NULL OR t.expires_at > NOW()) LIMIT 1",
                [$hashed]
            );
        } catch (\Throwable) {}

        if (!$row) {
            /* Fallback: shared mobile_api_tokens (portal users stored there) */
            try {
                $tok = $this->db->fetch(
                    "SELECT * FROM `{$this->t('mobile_api_tokens')}` WHERE token = ?
                     AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1",
                    [$hashed]
                );
                if ($tok) {
                    $u = $this->db->fetch(
                        "SELECT * FROM `{$this->t('owner_portal_users')}` WHERE id = ? LIMIT 1",
                        [(int)$tok['user_id']]
                    );
                    if ($u) $row = array_merge($tok, ['owner_id' => $u['owner_id'],
                        'portal_user_id' => $u['id'], 'is_active' => $u['is_active'],
                        'first_name' => $u['first_name'] ?? '', 'last_name' => $u['last_name'] ?? '',
                        'email' => $u['email']]);
                }
            } catch (\Throwable) {}
        }

        if (!$row || !$row['is_active']) $this->error('Portal-Sitzung ungültig oder abgelaufen.', 401);
        return $row;
    }

    /* ══════════════════════════════════════════════════════
       OWNER PORTAL — DASHBOARD
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/portal/dashboard */
    public function ownerPortalDashboard(array $params = []): void
    {
        $this->cors();
        $pUser   = $this->requirePortalAuth();
        $ownerId = (int)$pUser['owner_id'];

        try {
            $pets = $this->db->fetchAll(
                "SELECT p.*, (SELECT filename FROM `{$this->t('patient_timeline')}` WHERE patient_id = p.id AND type = 'photo' ORDER BY created_at DESC LIMIT 1) AS latest_photo
                 FROM `{$this->t('patients')}` p WHERE p.owner_id = ? AND p.status != 'archiviert' ORDER BY p.name ASC",
                [$ownerId]
            );
        } catch (\Throwable) { $pets = []; }

        foreach ($pets as &$pet) {
            $pet['photo_url'] = $this->resolvePatientPhotoUrl($pet);
        }
        unset($pet);

        try {
            $invoices = $this->db->fetchAll(
                "SELECT id, invoice_number, issue_date, due_date, total_gross, status
                 FROM `{$this->t('invoices')}` WHERE owner_id = ? ORDER BY issue_date DESC LIMIT 10",
                [$ownerId]
            );
        } catch (\Throwable) { $invoices = []; }

        try {
            $appointments = $this->db->fetchAll(
                "SELECT a.id, a.title, a.start_at, a.end_at, a.status,
                        p.name AS patient_name, tt.name AS treatment_name
                 FROM `{$this->t('appointments')}` a
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = a.patient_id
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 WHERE a.owner_id = ? AND a.start_at >= NOW()
                 ORDER BY a.start_at ASC LIMIT 5",
                [$ownerId]
            );
        } catch (\Throwable) { $appointments = []; }

        try {
            $unread = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('messaging_threads')}`
                 WHERE owner_id = ? AND owner_read_at IS NULL AND last_message_by != 'owner'",
                [$ownerId]
            );
        } catch (\Throwable) { $unread = 0; }

        $openInvoices = array_values(array_filter($invoices, fn($i) => in_array($i['status'], ['open','overdue'])));

        $this->json([
            'owner_id'             => $ownerId,
            'portal_user'          => [
                'id'         => $pUser['portal_user_id'],
                'email'      => $pUser['email'],
                'first_name' => $pUser['first_name'],
                'last_name'  => $pUser['last_name'],
            ],
            'pets'                 => $pets,
            'upcoming_appointments'=> $appointments,
            'open_invoices'        => $openInvoices,
            'unread_messages'      => $unread,
        ]);
    }

    /* ══════════════════════════════════════════════════════
       OWNER PORTAL — MEINE TIERE (Pets / Patienten)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/portal/tiere — all pets of this owner */
    public function ownerPortalPetList(array $params = []): void
    {
        $this->cors();
        $pUser   = $this->requirePortalAuth();
        $ownerId = (int)$pUser['owner_id'];

        try {
            $pets = $this->db->fetchAll(
                "SELECT * FROM `{$this->t('patients')}` WHERE owner_id = ? AND status != 'archiviert' ORDER BY name ASC",
                [$ownerId]
            );
        } catch (\Throwable) { $pets = []; }

        foreach ($pets as &$pet) {
            $pet['photo_url'] = $this->resolvePatientPhotoUrl($pet);
        }
        unset($pet);

        $this->json($pets);
    }

    /** GET /api/mobile/portal/tiere/{id} — full pet detail with timeline, exercises, TCP data */
    public function ownerPortalPetDetail(array $params = []): void
    {
        $this->cors();
        $pUser   = $this->requirePortalAuth();
        $ownerId = (int)$pUser['owner_id'];
        $petId   = (int)($params['id'] ?? 0);

        try {
            $pet = $this->db->fetch(
                "SELECT * FROM `{$this->t('patients')}` WHERE id = ? AND owner_id = ? LIMIT 1",
                [$petId, $ownerId]
            );
        } catch (\Throwable) { $pet = null; }
        if (!$pet) $this->error('Tier nicht gefunden oder kein Zugriff.', 404);

        $pet['photo_url'] = $this->resolvePatientPhotoUrl($pet);

        /* Timeline (public-visible entries) */
        try {
            $timeline = $this->db->fetchAll(
                "SELECT id, type, title, content, file_path, created_at
                 FROM `{$this->t('patient_timeline')}`
                 WHERE patient_id = ? AND (is_private IS NULL OR is_private = 0)
                 ORDER BY created_at DESC",
                [$petId]
            );
        } catch (\Throwable) { $timeline = []; }

        /* Build file URLs */
        foreach ($timeline as &$entry) {
            if (!empty($entry['file_path'])) {
                $entry['file_url'] = '/storage/patients/' . $petId . '/' . basename($entry['file_path']);
            }
        }
        unset($entry);

        /* Exercises */
        try {
            $exercises = $this->db->fetchAll(
                "SELECT * FROM `{$this->t('portal_exercises')}` WHERE patient_id = ? AND is_active = 1 ORDER BY sort_order ASC",
                [$petId]
            );
        } catch (\Throwable) { $exercises = []; }
        foreach ($exercises as &$ex) {
            if (!empty($ex['image'])) $ex['image_url'] = '/storage/uploads/exercises/' . basename($ex['image']);
        }
        unset($ex);

        /* Homework plans */
        try {
            $homeworkEnabled = ($this->settings->get('portal_show_homework', '1') === '1');
            $plans = $homeworkEnabled ? $this->db->fetchAll(
                "SELECT id, plan_date, therapist_name, status, pdf_sent_at
                 FROM `{$this->t('portal_homework_plans')}` WHERE patient_id = ? AND owner_id = ?
                 ORDER BY plan_date DESC",
                [$petId, $ownerId]
            ) : [];
        } catch (\Throwable) { $plans = []; }

        /* TCP data (only if plugin tables exist) */
        $tcpProgress = null;
        $tcpNatural  = null;
        $tcpReports  = null;
        $tcpFeedback = null;
        try {
            $vis = $this->db->fetch(
                "SELECT * FROM `{$this->t('tcp_portal_visibility')}` WHERE patient_id = ? LIMIT 1", [$petId]
            );
            if ($vis) {
                if (!empty($vis['show_progress'])) {
                    $tcpProgress = $this->db->fetchAll(
                        "SELECT e.*, c.name AS category_name, c.color AS category_color, c.scale_min, c.scale_max
                         FROM `{$this->t('tcp_progress_entries')}` e
                         JOIN `{$this->t('tcp_progress_categories')}` c ON c.id = e.category_id
                         WHERE e.patient_id = ? ORDER BY e.entry_date DESC LIMIT 20",
                        [$petId]
                    );
                }
                if (!empty($vis['show_natural'])) {
                    $tcpNatural = $this->db->fetchAll(
                        "SELECT * FROM `{$this->t('tcp_natural_therapy')}` WHERE patient_id = ? AND is_public = 1 ORDER BY session_date DESC",
                        [$petId]
                    );
                }
                if (!empty($vis['show_reports'])) {
                    $tcpReports = $this->db->fetchAll(
                        "SELECT id, title, report_date, filename FROM `{$this->t('tcp_therapy_reports')}`
                         WHERE patient_id = ? AND filename IS NOT NULL ORDER BY report_date DESC",
                        [$petId]
                    );
                }
                $tcpFeedback = $this->db->fetchAll(
                    "SELECT * FROM `{$this->t('tcp_exercise_feedback')}` WHERE patient_id = ?
                     AND feedback_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY feedback_date DESC",
                    [$petId]
                );
            }
        } catch (\Throwable) {}

        $this->json([
            'pet'           => $pet,
            'timeline'      => $timeline,
            'exercises'     => $exercises,
            'homework_plans'=> $plans,
            'tcp_progress'  => $tcpProgress,
            'tcp_natural'   => $tcpNatural,
            'tcp_reports'   => $tcpReports,
            'tcp_feedback'  => $tcpFeedback,
        ]);
    }

    /** POST /api/mobile/portal/tiere/{id}/bearbeiten — owner edits pet basic data */
    public function ownerPortalPetEdit(array $params = []): void
    {
        $this->cors();
        $pUser   = $this->requirePortalAuth();
        $ownerId = (int)$pUser['owner_id'];
        $petId   = (int)($params['id'] ?? 0);

        $pet = $this->db->fetch(
            "SELECT id FROM `{$this->t('patients')}` WHERE id = ? AND owner_id = ? LIMIT 1",
            [$petId, $ownerId]
        );
        if (!$pet) $this->error('Tier nicht gefunden oder kein Zugriff.', 404);

        /* Support both JSON body and multipart/form-data (photo upload) */
        $isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
        $data = $isMultipart ? $_POST : $this->body();

        $allowed = ['name','species','breed','birth_date','gender','color','chip_number'];
        $fields  = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data) && $data[$f] !== '') $fields[$f] = $data[$f];
        }
        if (empty($fields['name'])) $this->error('Name darf nicht leer sein.');

        /* Photo upload */
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $dir = tenant_storage_path('patients/' . $petId);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext   = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $fname = 'photo_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $fname)) {
                $fields['photo'] = $fname;
            }
        }

        if ($fields) {
            $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $petId;
            $this->db->execute("UPDATE `{$this->t('patients')}` SET {$sets}, updated_at = NOW() WHERE id = ?", $values);
        }

        $updated = $this->db->fetch("SELECT * FROM `{$this->t('patients')}` WHERE id = ? LIMIT 1", [$petId]);
        $updated['photo_url'] = $this->resolvePatientPhotoUrl($updated);
        $this->json($updated);
    }

    /* ══════════════════════════════════════════════════════
       OWNER PORTAL — RECHNUNGEN (invoices from owner view)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/portal/rechnungen */
    public function ownerPortalInvoices(array $params = []): void
    {
        $this->cors();
        $pUser   = $this->requirePortalAuth();
        $ownerId = (int)$pUser['owner_id'];

        try {
            $invoices = $this->db->fetchAll(
                "SELECT i.*, p.name AS patient_name
                 FROM `{$this->t('invoices')}` i
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = i.patient_id
                 WHERE i.owner_id = ?
                 ORDER BY i.issue_date DESC",
                [$ownerId]
            );
        } catch (\Throwable) { $invoices = []; }

        foreach ($invoices as &$inv) {
            $inv['pdf_url'] = '/portal/rechnungen/' . $inv['id'] . '/pdf';
        }
        unset($inv);

        $this->json($invoices);
    }

    /** GET /api/mobile/portal/rechnungen/{id}/pdf-url — secure URL for owner to download their invoice PDF */
    public function ownerPortalInvoicePdfUrl(array $params = []): void
    {
        $this->cors();
        $pUser     = $this->requirePortalAuth();
        $ownerId   = (int)$pUser['owner_id'];
        $invoiceId = (int)($params['id'] ?? 0);

        try {
            $inv = $this->db->fetch(
                "SELECT id FROM `{$this->t('invoices')}` WHERE id = ? AND owner_id = ? LIMIT 1",
                [$invoiceId, $ownerId]
            );
        } catch (\Throwable) { $inv = null; }
        if (!$inv) $this->error('Rechnung nicht gefunden oder kein Zugriff.', 404);

        $this->json(['pdf_url' => '/portal/rechnungen/' . $invoiceId . '/pdf']);
    }

    /* ══════════════════════════════════════════════════════
       OWNER PORTAL — TERMINE (appointments from owner view)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/portal/termine */
    public function ownerPortalAppointments(array $params = []): void
    {
        $this->cors();
        $pUser   = $this->requirePortalAuth();
        $ownerId = (int)$pUser['owner_id'];

        try {
            $appointments = $this->db->fetchAll(
                "SELECT a.id, a.title, a.start_at, a.end_at, a.status, a.description, a.notes,
                        p.name AS patient_name,
                        tt.name AS treatment_name
                 FROM `{$this->t('appointments')}` a
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = a.patient_id
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 WHERE a.owner_id = ?
                 ORDER BY a.start_at DESC",
                [$ownerId]
            );
        } catch (\Throwable) { $appointments = []; }

        $upcoming = array_values(array_filter($appointments, fn($a) => strtotime($a['start_at']) >= time()));
        $past     = array_values(array_filter($appointments, fn($a) => strtotime($a['start_at']) < time()));

        $this->json(['upcoming' => $upcoming, 'past' => $past]);
    }

    /* ══════════════════════════════════════════════════════
       OWNER PORTAL — NACHRICHTEN (Messaging from owner view)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/portal/nachrichten/ungelesen */
    public function ownerPortalUnread(array $params = []): void
    {
        $this->cors();
        $pUser   = $this->requirePortalAuth();
        $ownerId = (int)$pUser['owner_id'];

        try {
            $count = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('messaging_threads')}`
                 WHERE owner_id = ? AND owner_read_at IS NULL AND last_message_by != 'owner'",
                [$ownerId]
            );
        } catch (\Throwable) { $count = 0; }

        $this->json(['unread' => $count]);
    }

    /** GET /api/mobile/portal/nachrichten — thread list */
    public function ownerPortalThreadList(array $params = []): void
    {
        $this->cors();
        $pUser   = $this->requirePortalAuth();
        $ownerId = (int)$pUser['owner_id'];

        try {
            $threads = $this->db->fetchAll(
                "SELECT t.*,
                        (SELECT COUNT(*) FROM `{$this->t('messaging_messages')}` m
                         WHERE m.thread_id = t.id AND m.sender_type = 'admin' AND m.created_at > COALESCE(t.owner_read_at, '1970-01-01')) AS unread_count
                 FROM `{$this->t('messaging_threads')}` t
                 WHERE t.owner_id = ?
                 ORDER BY t.updated_at DESC",
                [$ownerId]
            );
        } catch (\Throwable) { $threads = []; }

        $this->json($threads);
    }

    /** GET /api/mobile/portal/nachrichten/{id} — show thread + mark read */
    public function ownerPortalThreadShow(array $params = []): void
    {
        $this->cors();
        $pUser    = $this->requirePortalAuth();
        $ownerId  = (int)$pUser['owner_id'];
        $threadId = (int)($params['id'] ?? 0);

        try {
            $thread = $this->db->fetch(
                "SELECT * FROM `{$this->t('messaging_threads')}` WHERE id = ? AND owner_id = ? LIMIT 1",
                [$threadId, $ownerId]
            );
        } catch (\Throwable) { $thread = null; }
        if (!$thread) $this->error('Thread nicht gefunden.', 404);

        /* Mark as read by owner */
        try {
            $this->db->execute(
                "UPDATE `{$this->t('messaging_threads')}` SET owner_read_at = NOW() WHERE id = ?",
                [$threadId]
            );
        } catch (\Throwable) {}

        try {
            $messages = $this->db->fetchAll(
                "SELECT * FROM `{$this->t('messaging_messages')}` WHERE thread_id = ? ORDER BY created_at ASC",
                [$threadId]
            );
        } catch (\Throwable) { $messages = []; }

        $this->json(['thread' => $thread, 'messages' => $messages]);
    }

    /** POST /api/mobile/portal/nachrichten/{id}/antworten — owner replies */
    public function ownerPortalReply(array $params = []): void
    {
        $this->cors();
        $pUser    = $this->requirePortalAuth();
        $ownerId  = (int)$pUser['owner_id'];
        $threadId = (int)($params['id'] ?? 0);
        $data     = $this->body();

        $body = trim((string)($data['body'] ?? ''));
        if (!$body) $this->error('Nachricht darf nicht leer sein.');

        try {
            $thread = $this->db->fetch(
                "SELECT * FROM `{$this->t('messaging_threads')}` WHERE id = ? AND owner_id = ? LIMIT 1",
                [$threadId, $ownerId]
            );
        } catch (\Throwable) { $thread = null; }
        if (!$thread) $this->error('Thread nicht gefunden.', 404);

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('messaging_messages')}` (thread_id, sender_type, sender_id, body, created_at)
                 VALUES (?, 'owner', ?, ?, NOW())",
                [$threadId, (int)$pUser['portal_user_id'], $body]
            );
            $msgId = (int)$this->db->lastInsertId();

            $this->db->execute(
                "UPDATE `{$this->t('messaging_threads')}` SET status = 'open', last_message_by = 'owner', updated_at = NOW() WHERE id = ?",
                [$threadId]
            );
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }

        $this->json([
            'ok'          => true,
            'id'          => $msgId,
            'body'        => $body,
            'sender_type' => 'owner',
            'created_at'  => date('Y-m-d H:i:s'),
        ], 201);
    }

    /** POST /api/mobile/portal/nachrichten/neu — owner starts new thread */
    public function ownerPortalNewThread(array $params = []): void
    {
        $this->cors();
        $pUser   = $this->requirePortalAuth();
        $ownerId = (int)$pUser['owner_id'];
        $data    = $this->body();

        $subject = trim((string)($data['subject'] ?? ''));
        $body    = trim((string)($data['body']    ?? ''));

        if (!$subject || !$body) $this->error('Betreff und Nachricht sind erforderlich.');

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('messaging_threads')}` (owner_id, subject, status, last_message_by, created_at, updated_at)
                 VALUES (?, ?, 'open', 'owner', NOW(), NOW())",
                [$ownerId, $subject]
            );
            $threadId = (int)$this->db->lastInsertId();

            $this->db->execute(
                "INSERT INTO `{$this->t('messaging_messages')}` (thread_id, sender_type, sender_id, body, created_at)
                 VALUES (?, 'owner', ?, ?, NOW())",
                [$threadId, (int)$pUser['portal_user_id'], $body]
            );
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }

        $this->json(['ok' => true, 'thread_id' => $threadId], 201);
    }

    /* ══════════════════════════════════════════════════════
       OWNER PORTAL — EIGENES PROFIL (portal user profile)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/portal/profil */
    public function ownerPortalProfile(array $params = []): void
    {
        $this->cors();
        $pUser   = $this->requirePortalAuth();
        $ownerId = (int)$pUser['owner_id'];

        try {
            $owner = $this->db->fetch("SELECT * FROM `{$this->t('owners')}` WHERE id = ? LIMIT 1", [$ownerId]);
        } catch (\Throwable) { $owner = null; }

        try {
            $portalUser = $this->db->fetch(
                "SELECT id, email, first_name, last_name, is_active, last_login FROM `{$this->t('owner_portal_users')}` WHERE id = ? LIMIT 1",
                [(int)$pUser['portal_user_id']]
            );
        } catch (\Throwable) { $portalUser = null; }

        $this->json(['portal_user' => $portalUser, 'owner' => $owner]);
    }

    /** POST /api/mobile/portal/profil/passwort — change own password (portal) */
    public function ownerPortalChangePassword(array $params = []): void
    {
        $this->cors();
        $pUser = $this->requirePortalAuth();
        $data  = $this->body();

        $current = (string)($data['current_password'] ?? '');
        $new     = (string)($data['new_password']     ?? '');
        $confirm = (string)($data['confirm_password'] ?? '');

        if (strlen($new) < 8)  $this->error('Neues Passwort muss mindestens 8 Zeichen lang sein.');
        if ($new !== $confirm)  $this->error('Passwörter stimmen nicht überein.');

        try {
            $user = $this->db->fetch(
                "SELECT password_hash FROM `{$this->t('owner_portal_users')}` WHERE id = ? LIMIT 1",
                [(int)$pUser['portal_user_id']]
            );
        } catch (\Throwable) { $user = null; }

        if (!$user || !password_verify($current, $user['password_hash'])) {
            $this->error('Aktuelles Passwort ist falsch.', 403);
        }

        $this->db->execute(
            "UPDATE `{$this->t('owner_portal_users')}` SET password_hash = ? WHERE id = ?",
            [password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), (int)$pUser['portal_user_id']]
        );

        $this->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════
       PATIENT INTAKE (Patientenanmeldung — public form)
    ══════════════════════════════════════════════════════ */

    /** POST /api/mobile/anmeldung — public: submit new patient registration */
    public function intakeSubmit(array $params = []): void
    {
        $this->cors();
        $data = $this->body();

        $required = ['owner_first_name','owner_last_name','owner_email','owner_phone','patient_name','patient_species','reason'];
        foreach ($required as $f) {
            if (empty(trim((string)($data[$f] ?? '')))) {
                $this->error("Feld '{$f}' ist erforderlich.");
            }
        }
        if (!filter_var($data['owner_email'], FILTER_VALIDATE_EMAIL)) {
            $this->error('Ungültige E-Mail-Adresse.');
        }

        /* Rate limiting by IP */
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateKey = 'intake_api_' . md5($ip);
        $now     = time();
        $history = $_SESSION[$rateKey] ?? [];
        $history = array_filter($history, fn(int $t) => $now - $t < 600);
        if (count($history) >= 5) {
            $this->error('Zu viele Anfragen. Bitte warte einige Minuten.', 429);
        }
        $history[] = $now;
        $_SESSION[$rateKey] = array_values($history);

        $row = [
            'owner_first_name'   => trim($data['owner_first_name']),
            'owner_last_name'    => trim($data['owner_last_name']),
            'owner_email'        => strtolower(trim($data['owner_email'])),
            'owner_phone'        => trim($data['owner_phone']        ?? ''),
            'owner_street'       => trim($data['owner_street']       ?? ''),
            'owner_zip'          => trim($data['owner_zip']          ?? ''),
            'owner_city'         => trim($data['owner_city']         ?? ''),
            'patient_name'       => trim($data['patient_name']),
            'patient_species'    => trim($data['patient_species']    ?? ''),
            'patient_breed'      => trim($data['patient_breed']      ?? ''),
            'patient_gender'     => trim($data['patient_gender']     ?? 'unbekannt'),
            'patient_birth_date' => !empty($data['patient_birth_date']) ? $data['patient_birth_date'] : null,
            'patient_color'      => trim($data['patient_color']      ?? ''),
            'patient_chip'       => trim($data['patient_chip']       ?? ''),
            'reason'             => trim($data['reason']),
            'appointment_wish'   => trim($data['appointment_wish']   ?? ''),
            'notes'              => trim($data['notes']              ?? ''),
            'status'             => 'neu',
            'ip_address'         => $ip,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        try {
            $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($row)));
            $phld = implode(', ', array_fill(0, count($row), '?'));
            $this->db->execute("INSERT INTO `{$this->t('patient_intake_submissions')}` ({$cols}) VALUES ({$phld})", array_values($row));
            $id = (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->error('Fehler beim Speichern: ' . $e->getMessage(), 500);
        }

        $this->json(['ok' => true, 'id' => $id, 'message' => 'Anmeldung erfolgreich eingegangen.'], 201);
    }

    /** GET /api/mobile/anmeldung — admin: list intake submissions */
    public function intakeInbox(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $status = $_GET['status'] ?? '';
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;

        try {
            $sql  = "SELECT * FROM `{$this->t('patient_intake_submissions')}`";
            $bind = [];
            if ($status) { $sql .= " WHERE status = ?"; $bind[] = $status; }
            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $bind[] = $limit;
            $bind[] = $offset;
            $rows = $this->db->fetchAll($sql, $bind);
        } catch (\Throwable) { $rows = []; }

        try {
            $counts = [
                'neu'            => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('patient_intake_submissions')}` WHERE status = 'neu'"),
                'in_bearbeitung' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('patient_intake_submissions')}` WHERE status = 'in_bearbeitung'"),
                'uebernommen'    => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('patient_intake_submissions')}` WHERE status = 'uebernommen'"),
                'abgelehnt'      => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('patient_intake_submissions')}` WHERE status = 'abgelehnt'"),
            ];
        } catch (\Throwable) { $counts = []; }

        $this->json(['items' => $rows, 'counts' => $counts, 'page' => $page]);
    }

    /** GET /api/mobile/anmeldung/{id} — admin: show single submission */
    public function intakeShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);

        try {
            $row = $this->db->fetch("SELECT * FROM `{$this->t('patient_intake_submissions')}` WHERE id = ? LIMIT 1", [$id]);
        } catch (\Throwable) { $row = null; }
        if (!$row) $this->error('Anmeldung nicht gefunden.', 404);

        /* Auto-mark in_bearbeitung when opened */
        if ($row['status'] === 'neu') {
            try {
                $this->db->execute("UPDATE `{$this->t('patient_intake_submissions')}` SET status = 'in_bearbeitung', updated_at = NOW() WHERE id = ?", [$id]);
                $row['status'] = 'in_bearbeitung';
            } catch (\Throwable) {}
        }

        $this->json($row);
    }

    /** POST /api/mobile/anmeldung/{id}/annehmen — admin: accept and create owner+patient */
    public function intakeAccept(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);

        try {
            $sub = $this->db->fetch("SELECT * FROM `{$this->t('patient_intake_submissions')}` WHERE id = ? LIMIT 1", [$id]);
        } catch (\Throwable) { $sub = null; }
        if (!$sub) $this->error('Anmeldung nicht gefunden.', 404);
        if ($sub['status'] === 'uebernommen') $this->error('Bereits übernommen.', 409);

        try {
            /* Find or create owner */
            $existing = $this->db->fetch("SELECT id FROM `{$this->t('owners')}` WHERE email = ? LIMIT 1", [$sub['owner_email']]);
            if ($existing) {
                $ownerId = (int)$existing['id'];
            } else {
                $this->db->execute(
                    "INSERT INTO `{$this->t('owners')}` (first_name, last_name, email, phone, street, zip, city, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,NOW(),NOW())",
                    [$sub['owner_first_name'], $sub['owner_last_name'], $sub['owner_email'],
                     $sub['owner_phone'], $sub['owner_street'], $sub['owner_zip'], $sub['owner_city']]
                );
                $ownerId = (int)$this->db->lastInsertId();
            }

            $allowedGenders = ['männlich','weiblich','kastriert','sterilisiert','unbekannt'];
            $gender = in_array($sub['patient_gender'] ?? '', $allowedGenders, true) ? $sub['patient_gender'] : 'unbekannt';

            $this->db->execute(
                "INSERT INTO `{$this->t('patients')}` (name, species, breed, gender, birth_date, color, chip_number, owner_id, status, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,'aktiv',NOW(),NOW())",
                [$sub['patient_name'], $sub['patient_species'], $sub['patient_breed'], $gender,
                 $sub['patient_birth_date'] ?: null, $sub['patient_color'], $sub['patient_chip'], $ownerId]
            );
            $patientId = (int)$this->db->lastInsertId();

            $this->db->execute(
                "UPDATE `{$this->t('patient_intake_submissions')}` SET status = 'uebernommen', accepted_patient_id = ?, accepted_owner_id = ?, updated_at = NOW() WHERE id = ?",
                [$patientId, $ownerId, $id]
            );
        } catch (\Throwable $e) {
            $this->error('Fehler beim Übernehmen: ' . $e->getMessage(), 500);
        }

        $this->json(['ok' => true, 'patient_id' => $patientId, 'owner_id' => $ownerId]);
    }

    /** POST /api/mobile/anmeldung/{id}/ablehnen — admin: reject */
    public function intakeReject(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        try {
            $this->db->execute(
                "UPDATE `{$this->t('patient_intake_submissions')}` SET status = 'abgelehnt', updated_at = NOW() WHERE id = ?", [$id]
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
        $this->json(['ok' => true]);
    }

    /** GET /api/mobile/anmeldung/benachrichtigungen — admin badge count */
    public function intakeNotifications(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        try {
            $count  = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('patient_intake_submissions')}` WHERE status = 'neu'");
            $latest = $this->db->fetchAll(
                "SELECT id, patient_name, owner_first_name, owner_last_name, created_at
                 FROM `{$this->t('patient_intake_submissions')}` WHERE status = 'neu' ORDER BY created_at DESC LIMIT 5"
            );
        } catch (\Throwable) { $count = 0; $latest = []; }
        $this->json(['count' => $count, 'items' => $latest]);
    }

    /* ══════════════════════════════════════════════════════
       BEFUNDBÖGEN
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/befunde — all befunde (paginated, optional ?status= ?search=) */
    public function befundeList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $page   = max(1, (int)($_GET['page']   ?? 1));
        $limit  = min(50, max(1, (int)($_GET['limit']  ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $conditions = [];
        $params2    = [];

        if ($status !== '') {
            $conditions[] = 'b.status = ?';
            $params2[]    = $status;
        }
        if ($search !== '') {
            $conditions[] = '(p.name LIKE ? OR CONCAT(o.first_name,\' \',o.last_name) LIKE ?)';
            $params2[]    = "%{$search}%";
            $params2[]    = "%{$search}%";
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        try {
            $items = $this->db->fetchAll(
                "SELECT b.id, b.patient_id, b.owner_id, b.status, b.datum, b.naechster_termin,
                        b.pdf_sent_at, b.pdf_sent_to, b.created_at, b.updated_at,
                        p.name AS patient_name, p.species AS patient_species,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name,
                        u.name AS ersteller_name
                 FROM `{$this->t('befundboegen')}` b
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = b.patient_id
                 LEFT JOIN `{$this->t('owners')}` o ON o.id = b.owner_id
                 LEFT JOIN `{$this->t('users')}` u ON u.id = b.created_by
                 {$where}
                 ORDER BY b.datum DESC, b.created_at DESC
                 LIMIT ? OFFSET ?",
                [...$params2, $limit, $offset]
            );
            $total = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('befundboegen')}` b
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = b.patient_id
                 LEFT JOIN `{$this->t('owners')}` o ON o.id = b.owner_id
                 {$where}",
                $params2
            );
        } catch (\Throwable $e) {
            $this->error('Datenbankfehler: ' . $e->getMessage(), 500);
        }

        $this->json(['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /** GET /api/mobile/befunde/patient/{id} — befunde for one patient */
    public function befundeByPatient(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $patientId = (int)($params['id'] ?? 0);

        try {
            $items = $this->db->fetchAll(
                "SELECT b.id, b.patient_id, b.owner_id, b.status, b.datum, b.naechster_termin,
                        b.pdf_sent_at, b.pdf_sent_to, b.created_at, b.updated_at,
                        u.name AS ersteller_name
                 FROM `{$this->t('befundboegen')}` b
                 LEFT JOIN `{$this->t('users')}` u ON u.id = b.created_by
                 WHERE b.patient_id = ?
                 ORDER BY b.datum DESC, b.created_at DESC",
                [$patientId]
            );
        } catch (\Throwable $e) {
            $this->error('Datenbankfehler: ' . $e->getMessage(), 500);
        }

        $this->json(['items' => $items, 'total' => count($items)]);
    }

    /** GET /api/mobile/befunde/{id} — single befundbogen with felder */
    public function befundeShow(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);

        try {
            $row = $this->db->fetch(
                "SELECT b.*, u.name AS ersteller_name,
                        p.name AS patient_name, p.species AS patient_species,
                        CONCAT(o.first_name,' ',o.last_name) AS owner_name
                 FROM `{$this->t('befundboegen')}` b
                 LEFT JOIN `{$this->t('users')}` u ON u.id = b.created_by
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = b.patient_id
                 LEFT JOIN `{$this->t('owners')}` o ON o.id = b.owner_id
                 WHERE b.id = ? LIMIT 1",
                [$id]
            );
        } catch (\Throwable $e) {
            $this->error('Datenbankfehler: ' . $e->getMessage(), 500);
        }

        if (!$row) $this->error('Befundbogen nicht gefunden.', 404);

        try {
            $feldRows = $this->db->fetchAll(
                "SELECT feldname, feldwert FROM `{$this->t('befundbogen_felder')}` WHERE befundbogen_id = ?",
                [$id]
            );
            $felder = [];
            foreach ($feldRows as $f) {
                $decoded = json_decode($f['feldwert'], true);
                $felder[$f['feldname']] = ($decoded !== null && json_last_error() === JSON_ERROR_NONE)
                    ? $decoded : $f['feldwert'];
            }
            $row['felder'] = $felder;
        } catch (\Throwable) {
            $row['felder'] = [];
        }

        $this->json($row);
    }

    /** GET /api/mobile/befunde/{id}/pdf-url — returns a URL to download the PDF */
    public function befundePdfUrl(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id  = (int)($params['id'] ?? 0);
        $url = $this->resolveAppUrl() . '/patienten/0/befunde/' . $id . '/pdf';
        $this->json(['pdf_url' => $url]);
    }

    /* ── Owner Portal Befunde ────────────────────────────── */

    /** GET /api/mobile/portal/befunde — owner portal: list own befunde (abgeschlossen/versendet) */
    public function ownerPortalBefunde(array $params = []): void
    {
        $this->cors();
        $portalUser = $this->requirePortalAuth();
        $ownerId    = (int)$portalUser['owner_id'];

        try {
            $items = $this->db->fetchAll(
                "SELECT b.id, b.patient_id, b.status, b.datum, b.naechster_termin,
                        b.pdf_sent_at, b.created_at,
                        p.name AS patient_name, p.species AS patient_species
                 FROM `{$this->t('befundboegen')}` b
                 LEFT JOIN `{$this->t('patients')}` p ON p.id = b.patient_id
                 WHERE b.owner_id = ? AND b.status != 'entwurf'
                 ORDER BY b.datum DESC, b.created_at DESC",
                [$ownerId]
            );
        } catch (\Throwable $e) {
            $this->error('Datenbankfehler: ' . $e->getMessage(), 500);
        }

        $this->json(['items' => $items, 'total' => count($items)]);
    }

    /** GET /api/mobile/portal/befunde/{id}/pdf-url — owner portal: PDF download URL */
    public function ownerPortalBefundPdfUrl(array $params = []): void
    {
        $this->cors();
        $portalUser  = $this->requirePortalAuth();
        $ownerId     = (int)$portalUser['owner_id'];
        $id          = (int)($params['id'] ?? 0);

        try {
            $row = $this->db->fetch(
                "SELECT id, owner_id, status FROM `{$this->t('befundboegen')}` WHERE id = ? LIMIT 1",
                [$id]
            );
        } catch (\Throwable $e) {
            $this->error('Datenbankfehler: ' . $e->getMessage(), 500);
        }

        if (!$row || (int)$row['owner_id'] !== $ownerId || $row['status'] === 'entwurf') {
            $this->error('Nicht gefunden oder kein Zugriff.', 403);
        }

        $url = $this->resolveAppUrl() . '/portal/befunde/' . $id . '/pdf';
        $this->json(['pdf_url' => $url]);
    }

    /* ══════════════════════════════════════════════════════
       PATIENT INVITE (Einladungslinks)
    ══════════════════════════════════════════════════════ */

    /** GET /api/mobile/einladungen — admin: list all invite tokens */
    public function inviteList(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM `{$this->t('patient_invite_tokens')}` ORDER BY created_at DESC LIMIT ? OFFSET ?",
                [$limit, $offset]
            );
            $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$this->t('patient_invite_tokens')}`");
        } catch (\Throwable) { $rows = []; $total = 0; }

        $baseUrl = $this->settings->get('app_url', '');
        foreach ($rows as &$row) {
            $row['invite_url'] = $baseUrl . '/einladung/' . $row['token'];
        }
        unset($row);

        $this->json(['items' => $rows, 'total' => $total, 'page' => $page]);
    }

    /** POST /api/mobile/einladungen — admin: create + send invite */
    public function inviteSend(array $params = []): void
    {
        $this->cors();
        $auth = $this->requireAuth();
        $data = $this->body();

        $email = strtolower(trim($data['email'] ?? ''));
        $phone = trim($data['phone'] ?? '');
        $note  = trim($data['note']  ?? '');

        if (!$email && !$phone) $this->error('E-Mail oder Telefonnummer erforderlich.');
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $this->error('Ungültige E-Mail-Adresse.');

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $baseUrl = $this->resolveAppUrl();
        $inviteUrl = $baseUrl . '/einladung/' . $token;
        $sentVia   = $phone && !$email ? 'whatsapp' : ($phone && $email ? 'both' : 'email');

        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('patient_invite_tokens')}` (token, email, phone, note, status, sent_via, expires_at, created_by, created_at)
                 VALUES (?, ?, ?, ?, 'offen', ?, ?, ?, NOW())",
                [$token, $email ?: '', $phone ?: '', $note ?: '', $sentVia, $expires, (int)$auth['user_id']]
            );
            $id = (int)$this->db->lastInsertId();
        } catch (\Throwable $e) {
            $this->error('Fehler: ' . $e->getMessage(), 500);
        }

        $whatsappUrl = null;
        if ($phone) {
            $whatsappUrl = $this->buildWhatsAppUrl($phone, $inviteUrl, $note);
        }

        $this->json([
            'ok'           => true,
            'id'           => $id,
            'invite_url'   => $inviteUrl,
            'whatsapp_url' => $whatsappUrl,
            'expires_at'   => $expires,
        ], 201);
    }

    /** POST /api/mobile/einladungen/{id}/widerrufen — admin: revoke invite */
    public function inviteRevoke(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        try {
            $this->db->execute(
                "UPDATE `{$this->t('patient_invite_tokens')}` SET status = 'abgelaufen', expires_at = NOW() WHERE id = ?",
                [$id]
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
        $this->json(['ok' => true]);
    }

    /** GET /api/mobile/einladungen/{id}/whatsapp — get WhatsApp share URL */
    public function inviteWhatsapp(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        try {
            $row = $this->db->fetch("SELECT * FROM `{$this->t('patient_invite_tokens')}` WHERE id = ? LIMIT 1", [$id]);
        } catch (\Throwable) { $row = null; }
        if (!$row) $this->error('Einladung nicht gefunden.', 404);

        $baseUrl   = $this->resolveAppUrl();
        $inviteUrl = $baseUrl . '/einladung/' . $row['token'];
        $phone     = $row['phone'] ?? '';
        $note      = $row['note']  ?? '';
        $waUrl     = $phone ? $this->buildWhatsAppUrl($phone, $inviteUrl, $note) : null;

        $this->json(['ok' => true, 'url' => $waUrl, 'whatsapp_url' => $waUrl, 'invite_url' => $inviteUrl]);
    }

    /** POST /api/mobile/einladungen/{id}/bearbeiten — edit pending invite (note/phone) */
    public function inviteUpdate(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        $id   = (int)($params['id'] ?? 0);
        $data = $this->body();
        try {
            $row = $this->db->fetch("SELECT * FROM `{$this->t('patient_invite_tokens')}` WHERE id = ? LIMIT 1", [$id]);
        } catch (\Throwable) { $row = null; }
        if (!$row) $this->error('Einladung nicht gefunden.', 404);
        if ($row['status'] !== 'offen') $this->error('Nur offene Einladungen können bearbeitet werden.', 422);

        $phone = isset($data['phone']) ? trim($data['phone']) : $row['phone'];
        $note  = isset($data['note'])  ? trim($data['note'])  : $row['note'];

        try {
            $this->db->execute(
                "UPDATE `{$this->t('patient_invite_tokens')}` SET phone = ?, note = ? WHERE id = ?",
                [$phone, $note, $id]
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500);
        }

        $baseUrl     = $this->resolveAppUrl();
        $inviteUrl   = $baseUrl . '/einladung/' . $row['token'];
        $whatsappUrl = $phone ? $this->buildWhatsAppUrl($phone, $inviteUrl, $note) : null;

        $this->json(['ok' => true, 'whatsapp_url' => $whatsappUrl, 'invite_url' => $inviteUrl]);
    }

    /** GET /api/mobile/einladungen/benachrichtigungen — pending invite count */
    public function inviteNotifications(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        try {
            $count = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('patient_invite_tokens')}` WHERE status = 'offen' AND expires_at > NOW()"
            );
        } catch (\Throwable) { $count = 0; }
        $this->json(['pending' => $count]);
    }

    /* ── GET /api/mobile/portal/feedback/neu — check-notification count for Flutter ── */
    public function portalFeedbackNew(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();

        $since = $_GET['since'] ?? date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $count = 0;

        try {
            $stmt  = $this->db->query(
                'SELECT COUNT(*) FROM `' . $this->t('portal_check_notifications') . '` WHERE created_at > ?',
                [$since]
            );
            $count = (int)$stmt->fetchColumn();
        } catch (\Throwable) {}

        $this->json(['count' => $count]);
    }

    /* ── private helper: resolve photo URL for a patient row ── */
    private function resolvePatientPhotoUrl(array $patient): ?string
    {
        $pid = (int)($patient['id'] ?? 0);

        if (empty($patient['photo'])) {
            /* Try latest_photo from timeline join */
            if (!empty($patient['latest_photo'])) {
                $base = basename($patient['latest_photo']);
                /* Check per-patient folder first, then flat patients dir */
                if (file_exists(STORAGE_PATH . '/patients/' . $pid . '/' . $base)) {
                    return '/patient-photos/' . $pid . '/' . $base;
                }
                /* Flat layout (intake copy): serve via /patients/{file} route */
                return '/patients/' . $base;
            }
            return null;
        }

        $file = basename($patient['photo']);

        /* Check per-patient folder first (mobile upload, web upload) */
        if (file_exists(STORAGE_PATH . '/patients/' . $pid . '/' . $file)) {
            return '/patient-photos/' . $pid . '/' . $file;
        }

        /* Flat layout: intake controller copies to storage/patients/{file} directly */
        if (file_exists(STORAGE_PATH . '/patients/' . $file)) {
            return '/patients/' . $file;
        }

        /* Fallback — assume per-patient folder */
        return '/patient-photos/' . $pid . '/' . $file;
    }

    /* ══════════════════════════════════════════════════════
       GOOGLE CALENDAR SYNC (2-Wege)
    ══════════════════════════════════════════════════════ */

    public function googleSyncStatus(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        try {
            // Table may not exist if plugin migration hasn't run yet
            try {
                $conn = $this->db->fetch(
                    'SELECT id, google_email, sync_enabled, auto_sync, calendar_id,
                            calendar_name, last_pull_at, created_at
                     FROM `' . $this->t('google_calendar_connections') . '` ORDER BY id ASC LIMIT 1'
                );
            } catch (\Throwable $e) {
                $this->json(['connected' => false, 'sync_enabled' => false, 'note' => 'plugin_not_installed']);
                return;
            }
            if (!$conn) {
                $this->json(['connected' => false, 'sync_enabled' => false]);
                return;
            }
            $lastSuccess  = null; $lastError = null; $lastPush = null;
            $pendingPush  = 0;    $pushToday = 0;   $lastPullLog = null;
            $pullToday    = 0;    $syncedToday = 0; $recentLogs = [];
            try {
                $lastSuccess = $this->db->fetchColumn(
                    "SELECT created_at FROM `{$this->t('google_calendar_sync_log')}`
                     WHERE success = 1 AND action IN ('create','update','delete','pull')
                     ORDER BY created_at DESC LIMIT 1"
                );
                $lastError = $this->db->fetch(
                    'SELECT message, created_at FROM `' . $this->t('google_calendar_sync_log') . '`
                     WHERE success = 0 ORDER BY created_at DESC LIMIT 1'
                );
                $lastPush = $this->db->fetchColumn(
                    "SELECT created_at FROM `{$this->t('google_calendar_sync_log')}`
                     WHERE success = 1 AND action IN ('create','update','delete')
                     ORDER BY created_at DESC LIMIT 1"
                );
                $pendingPush = (int)$this->db->fetchColumn(
                    "SELECT COUNT(*) FROM `{$this->t('google_calendar_sync_map')}` WHERE sync_status = 'pending'"
                );
                $pushToday = (int)$this->db->fetchColumn(
                    "SELECT COUNT(*) FROM `{$this->t('google_calendar_sync_log')}`
                     WHERE success = 1 AND action IN ('create','update','delete')
                     AND DATE(created_at) = CURDATE()"
                );
                $lastPullLog = $this->db->fetchColumn(
                    "SELECT created_at FROM `{$this->t('google_calendar_sync_log')}`
                     WHERE success = 1 AND action = 'pull'
                     ORDER BY created_at DESC LIMIT 1"
                );
                $pullToday = (int)$this->db->fetchColumn(
                    "SELECT COUNT(*) FROM `{$this->t('google_calendar_sync_log')}`
                     WHERE success = 1 AND action = 'pull' AND DATE(created_at) = CURDATE()"
                );
                $syncedToday = (int)$this->db->fetchColumn(
                    "SELECT COUNT(*) FROM `{$this->t('google_calendar_sync_log')}`
                     WHERE success = 1 AND DATE(created_at) = CURDATE()"
                );
                $recentLogs = $this->db->fetchAll(
                    'SELECT action, success, message, created_at
                     FROM `' . $this->t('google_calendar_sync_log') . '` ORDER BY created_at DESC LIMIT 10'
                );
            } catch (\Throwable $e) { /* log tables may not exist yet */ }
            $this->json([
                'connected'       => true,
                'sync_enabled'    => (bool)$conn['sync_enabled'],
                'auto_sync'       => (bool)($conn['auto_sync'] ?? false),
                'google_email'    => $conn['google_email'],
                'calendar_name'   => $conn['calendar_name'] ?? $conn['calendar_id'] ?? 'Primär',
                'last_pull_at'    => $conn['last_pull_at'],
                'last_success_at' => $lastSuccess ?: null,
                'last_error'      => $lastError ?: null,
                'synced_today'    => $syncedToday,
                'push_last_at'    => $lastPush ?: null,
                'push_pending'    => $pendingPush,
                'push_today'      => $pushToday,
                'pull_last_at'    => $lastPullLog ?: ($conn['last_pull_at'] ?? null),
                'pull_today'      => $pullToday,
                'recent_logs'     => $recentLogs,
            ]);
        } catch (\Throwable $e) {
            $this->json(['connected' => false, 'sync_enabled' => false, 'error' => $e->getMessage()]);
        }
    }

    public function googleSyncPull(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        try {
            $repo   = new \Plugins\GoogleCalendarSync\GoogleCalendarRepository($this->db);
            $api    = new \Plugins\GoogleCalendarSync\GoogleApiService($repo);
            $sync   = new \Plugins\GoogleCalendarSync\GoogleSyncService($repo, $api, $this->db);
            $result = $sync->pullFromGoogle();
            $this->json([
                'success'   => $result['success'] ?? false,
                'message'   => $result['message'] ?? 'Pull abgeschlossen',
                'direction' => 'pull',
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Pull fehlgeschlagen: ' . $e->getMessage(), 'direction' => 'pull']);
        }
    }

    public function googleSyncPush(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        try {
            $repo    = new \Plugins\GoogleCalendarSync\GoogleCalendarRepository($this->db);
            $api     = new \Plugins\GoogleCalendarSync\GoogleApiService($repo);
            $sync    = new \Plugins\GoogleCalendarSync\GoogleSyncService($repo, $api, $this->db);
            $result  = $sync->bulkSyncAll();
            $success = (int)($result['success'] ?? 0);
            $failed  = (int)($result['failed']  ?? 0);
            $this->json([
                'success'   => $failed === 0,
                'message'   => "Push abgeschlossen: {$success} synchronisiert" . ($failed > 0 ? ", {$failed} Fehler" : ''),
                'synced'    => $success,
                'failed'    => $failed,
                'direction' => 'push',
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Push fehlgeschlagen: ' . $e->getMessage(), 'direction' => 'push']);
        }
    }

    /* ── Alias für alte Route /google-kalender/status ── */
    public function googleCalendarStatus(array $params = []): void
    {
        $this->googleSyncStatus($params);
    }

    public function googleCalendarTriggerSync(array $params = []): void
    {
        $this->cors();
        $this->requireAuth();
        try {
            $repo   = new \Plugins\GoogleCalendarSync\GoogleCalendarRepository($this->db);
            $api    = new \Plugins\GoogleCalendarSync\GoogleApiService($repo);
            $sync   = new \Plugins\GoogleCalendarSync\GoogleSyncService($repo, $api, $this->db);
            $push   = $sync->bulkSyncAll();
            $pull   = $sync->pullFromGoogle();
            $this->json(['success' => true, 'push' => $push, 'pull' => $pull]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /* ── Private helpers ─────────────────────────────────────────── */

    /** Resolve the full app base URL from settings or $_SERVER fallback */
    private function resolveAppUrl(): string
    {
        $url = rtrim($this->settings->get('app_url', '') ?? '', '/');
        if ($url !== '') return $url;

        /* Fallback: build from current request */
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        return $scheme . '://' . $host;
    }

    /** Build a wa.me URL with a rich, personalised invitation message */
    private function buildWhatsAppUrl(string $phone, string $inviteUrl, string $note = ''): string
    {
        $phone   = preg_replace('/[^0-9+]/', '', $phone);
        $appName = $this->settings->get('company_name', $this->settings->get('practice_name', 'Tierphysio Manager'));
        $msg  = "Hallo! 👋\n\nSie wurden von {$appName} eingeladen, sich direkt in unserem System zu registrieren.";
        if ($note !== '') {
            $msg .= "\n\n💬 {$note}";
        }
        $msg .= "\n\nKlicken Sie auf den folgenden Link, um Ihr Tier und sich selbst zu registrieren:\n👉 {$inviteUrl}\n\nDer Link ist 7 Tage gültig.";
        return 'https://wa.me/' . ltrim($phone, '+') . '?text=' . rawurlencode($msg);
    }
}
