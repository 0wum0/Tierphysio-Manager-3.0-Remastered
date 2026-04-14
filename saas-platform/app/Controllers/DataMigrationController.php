<?php

declare(strict_types=1);

namespace Saas\Controllers;

use PDO;
use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Database;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\PlanRepository;
use Saas\Services\TenantProvisioningService;
use Saas\Services\MigrationService;

class DataMigrationController extends Controller
{
    public function __construct(
        View                           $view,
        Session                        $session,
        private Database               $db,
        private TenantRepository       $tenantRepo,
        private PlanRepository         $planRepo,
        private TenantProvisioningService $provisioningService,
        private MigrationService       $migrationService
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $tenants = $this->tenantRepo->all(200, 0);
        $plans   = $this->planRepo->allActive();

        $this->render('admin/migration/import.twig', [
            'tenants'    => $tenants,
            'plans'      => $plans,
            'page_title' => 'Daten-Import / Migration',
        ]);
    }

    public function run(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId        = (int)($this->post('tenant_id') ?? 0);
        $mode            = $this->post('mode') ?? 'smart';
        $adminEmail      = trim((string)($this->post('admin_email') ?? ''));
        $adminPassword   = trim((string)($this->post('admin_password') ?? ''));
        $adminName       = trim((string)($this->post('admin_name') ?? ''));
        $companyName     = trim((string)($this->post('company_name') ?? ''));
        $companyEmail    = trim((string)($this->post('company_email') ?? ''));
        $companyPhone    = trim((string)($this->post('company_phone') ?? ''));
        $companyAddress  = trim((string)($this->post('company_address') ?? ''));
        $companyCity     = trim((string)($this->post('company_city') ?? ''));
        $companyZip      = trim((string)($this->post('company_zip') ?? ''));

        // ── Neuen Tenant anlegen falls tenant_id = 0 ────────────────────────
        $provisionResult = null;
        if ($tenantId === 0) {
            $practiceName = trim((string)($this->post('practice_name') ?? ''));
            $planId       = (int)($this->post('plan_id') ?? 0);

            if ($practiceName === '') {
                $this->jsonError('Praxisname ist erforderlich um einen neuen Tenant anzulegen.');
            }
            if ($adminEmail === '') {
                $this->jsonError('E-Mail ist erforderlich um einen neuen Tenant anzulegen.');
            }
            if ($adminPassword === '') {
                $this->jsonError('Passwort ist erforderlich um einen neuen Tenant anzulegen.');
            }

            // Prüfen ob Email bereits vergeben
            $existing = $this->tenantRepo->findByEmail($adminEmail);
            if ($existing) {
                $this->jsonError("E-Mail \"{$adminEmail}\" ist bereits einem Tenant zugeordnet (ID {$existing['id']}: {$existing['practice_name']}). Bitte den vorhandenen Tenant auswählen.");
            }

            // Plan ermitteln
            $plan = $planId ? $this->planRepo->find($planId) : null;
            if (!$plan) {
                // Erstes aktives Plan-Fallback
                $plans = $this->planRepo->allActive();
                $plan  = $plans[0] ?? null;
            }
            if (!$plan) {
                $this->jsonError('Kein Abo-Plan gefunden. Bitte zuerst einen Plan anlegen.');
            }

            try {
                $provisioned = $this->provisioningService->provisionTenantOnly([
                    'practice_name'  => $practiceName,
                    'owner_name'     => $adminName !== '' ? $adminName : $practiceName,
                    'email'          => $adminEmail,
                    'admin_password' => $adminPassword,
                    'plan_slug'      => $plan['slug'],
                    'billing_cycle'  => 'monthly',
                    'phone'          => null,
                    'address'        => null,
                    'city'           => null,
                    'zip'            => null,
                    'country'        => 'DE',
                ]);
            } catch (\Throwable $e) {
                $this->jsonError('Tenant-Provisioning fehlgeschlagen: ' . $e->getMessage());
            }

            $tenantId        = $provisioned['tenant_id'];
            $provisionResult = $provisioned;
        }

        $tenant = $this->tenantRepo->find($tenantId);
        if (!$tenant) {
            $this->jsonError('Tenant nicht gefunden.');
        }

        $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_') . '_';

        // ── Datei-Upload prüfen ─────────────────────────────────────────────
        if (empty($_FILES['sql_file']['tmp_name'])) {
            $this->jsonError('Keine Datei hochgeladen.');
        }

        $file = $_FILES['sql_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->jsonError('Upload-Fehler: ' . $file['error']);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            $this->jsonError('Nur .sql Dateien erlaubt.');
        }

        if ($file['size'] > 50 * 1024 * 1024) {
            $this->jsonError('Datei zu groß (max. 50 MB).');
        }

        $sql = file_get_contents($file['tmp_name']);
        if ($sql === false || trim($sql) === '') {
            $this->jsonError('Datei ist leer oder nicht lesbar.');
        }

        // ── SQL verarbeiten ─────────────────────────────────────────────────
        try {
            if ($mode === 'smart') {
                $result = $this->runSmart($sql, $prefix);
            } else {
                $result = $this->runRaw($sql, $prefix);
            }

            // ── Post-Import Setup: migrations-Tabelle + settings + Admin-User ────
            $practiceData = [
                'company_name'    => $companyName !== '' ? $companyName : ($provisionResult['practice_name'] ?? ''),
                'company_email'   => $companyEmail !== '' ? $companyEmail : $adminEmail,
                'company_phone'   => $companyPhone,
                'company_address' => $companyAddress,
                'company_city'    => $companyCity,
                'company_zip'     => $companyZip,
            ];
            $result['setup'] = $this->postImportSetup($prefix, $practiceData);

            if ($adminEmail !== '' && $adminPassword !== '') {
                $result['admin'] = $this->setAdminUser($prefix, $adminEmail, $adminPassword, $adminName);
            }

            // ── Provisioning-Info anhängen ──────────────────────────────────
            if ($provisionResult !== null) {
                $result['provisioning'] = [
                    'success'      => true,
                    'tenant_id'    => $provisionResult['tenant_id'],
                    'db_prefix'    => $provisionResult['db_name'],
                    'license_token'=> $provisionResult['license_token'] ?? null,
                    'trial_ends'   => $provisionResult['trial_ends_at'] ?? null,
                    'message'      => "Neuer Tenant angelegt: ID {$provisionResult['tenant_id']}, Prefix: {$provisionResult['db_name']}",
                ];
            }
        } catch (\Throwable $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'ok'      => 0,
                'skipped' => 0,
                'errors'  => [['sql' => '', 'error' => $e->getMessage()]],
                'message' => 'PHP-Fehler: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // ── Smart-Modus: parst Statements, prefixiert Tabellennamen ─────────────
    private function runSmart(string $sql, string $prefix): array
    {
        $pdo = $this->db->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('SET sql_mode = ""');

        $statements = $this->splitStatements($sql);

        $stats = [
            'total'    => 0,
            'ok'       => 0,
            'skipped'  => 0,
            'errors'   => [],
            'tables'   => [],
        ];

        foreach ($statements as $raw) {
            $stmt = trim($raw);
            if ($stmt === '' || $stmt === ';') continue;

            // Kommentare und Direktiven überspringen
            if (
                str_starts_with($stmt, '--') ||
                str_starts_with($stmt, '#') ||
                str_starts_with($stmt, '/*!') ||
                preg_match('/^SET\s+(SQL_MODE|time_zone|character_set|names|collation)/i', $stmt) ||
                preg_match('/^(START TRANSACTION|COMMIT|ROLLBACK)/i', $stmt) ||
                preg_match('/^\/\*!/i', $stmt)
            ) {
                $stats['skipped']++;
                continue;
            }

            // Datenbank-spezifische Statements überspringen
            if (preg_match('/^(CREATE|DROP|USE)\s+DATABASE/i', $stmt)) {
                $stats['skipped']++;
                continue;
            }

            // ALTER TABLE ... MODIFY id AUTO_INCREMENT überspringen (nur Metadaten)
            if (preg_match('/^ALTER\s+TABLE\s+\S+\s+MODIFY\s+/i', $stmt)) {
                $stats['skipped']++;
                continue;
            }

            // FK-Constraints überspringen (wegen Prefix-Konflikten)
            if (
                preg_match('/^ALTER\s+TABLE\s+\S+\s+ADD\s+CONSTRAINT/i', $stmt) ||
                preg_match('/FOREIGN\s+KEY/i', $stmt)
            ) {
                $stats['skipped']++;
                continue;
            }

            // Tabellennamen prefixieren
            $prefixed = $this->prefixTableNames($stmt, $prefix);

            $stats['total']++;

            // Tabelle merken
            if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`/i', $prefixed, $m)) {
                $tbl = $m[1] ?? '';
                if ($tbl && !in_array($tbl, $stats['tables'])) {
                    $stats['tables'][] = $tbl;
                }
            }

            try {
                $pdo->exec($prefixed);
                $stats['ok']++;
            } catch (\Throwable $e) {
                $msg  = $e->getMessage();
                $code = $e->getCode();
                // Duplikat-Einträge → OK
                if (str_contains($msg, 'Duplicate entry') || str_contains($msg, '1062')) {
                    $stats['ok']++;
                // Tabelle existiert bereits (42S01 / 1050) → überspringen
                } elseif ($code === '42S01' || str_contains($msg, '1050')) {
                    $stats['skipped']++;
                // Mehrfacher Primary Key (1068) → überspringen
                } elseif (str_contains($msg, '1068') || str_contains($msg, 'Multiple primary key')) {
                    $stats['skipped']++;
                } else {
                    $short = mb_substr($prefixed, 0, 120);
                    $stats['errors'][] = ['sql' => $short . '…', 'error' => $msg];
                }
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $stats['success'] = count($stats['errors']) === 0;
        $stats['message'] = $stats['success']
            ? "Import abgeschlossen: {$stats['ok']} Statements ausgeführt, {$stats['skipped']} übersprungen."
            : "Import mit " . count($stats['errors']) . " Fehlern abgeschlossen ({$stats['ok']} OK, {$stats['skipped']} übersprungen).";

        return $stats;
    }

    // ── Raw-Modus: fügt nur Prefix vorne an, minimale Filterung ─────────────
    private function runRaw(string $sql, string $prefix): array
    {
        $pdo = $this->db->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $prefixed   = $this->prefixTableNames($sql, $prefix);
        $statements = $this->splitStatements($prefixed);

        $ok = $skipped = 0;
        $errors = [];

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || $stmt === ';') { $skipped++; continue; }
            if (preg_match('/^(\/\*|--|#|SET\s+SQL_MODE|START TRANSACTION|COMMIT)/i', $stmt)) {
                $skipped++;
                continue;
            }
            try {
                $pdo->exec($stmt);
                $ok++;
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'Duplicate entry') || str_contains($msg, '1062')) {
                    $ok++;
                } else {
                    $errors[] = ['sql' => mb_substr($stmt, 0, 120) . '…', 'error' => $msg];
                }
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        return [
            'success'  => count($errors) === 0,
            'ok'       => $ok,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'message'  => count($errors) === 0
                ? "Raw-Import: {$ok} OK, {$skipped} übersprungen."
                : "Raw-Import mit " . count($errors) . " Fehlern ({$ok} OK).",
        ];
    }

    // ── Alle Tabellennamen in einem SQL-Statement mit Prefix versehen ────────
    private function prefixTableNames(string $sql, string $prefix): string
    {
        // Schritt 1: SQL-Funktionen temporär durch Platzhalter ersetzen
        // damit sie beim Prefixing nicht angefasst werden
        $placeholders = [];
        $idx = 0;
        // Funktionen ohne Klammern (z.B. DEFAULT current_timestamp)
        $sql = preg_replace_callback(
            '/\b(current_timestamp|current_date|current_time|current_user|'
            . 'utc_timestamp|utc_date|utc_time|localtime|localtimestamp|sysdate)'
            . '(\s*\(\s*\))?/i',
            function ($m) use (&$placeholders, &$idx) {
                $key = '__SQLFN_' . ($idx++) . '__';
                $placeholders[$key] = $m[0];
                return $key;
            },
            $sql
        );
        // now() und uuid() nur mit Klammern (Wort allein zu generisch)
        $sql = preg_replace_callback(
            '/\b(now|uuid)\s*\(\s*\)/i',
            function ($m) use (&$placeholders, &$idx) {
                $key = '__SQLFN_' . ($idx++) . '__';
                $placeholders[$key] = $m[0];
                return $key;
            },
            $sql
        );

        // Schritt 2: Tabellennamen prefixen (nur Backtick-Namen)
        $addPrefix = function (string $name) use ($prefix): string {
            return str_starts_with($name, $prefix) ? $name : $prefix . $name;
        };

        // CREATE TABLE [`name`]
        $sql = preg_replace_callback(
            '/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`/i',
            function ($m) use ($addPrefix) {
                return str_replace('`' . $m[1] . '`', '`' . $addPrefix($m[1]) . '`', $m[0]);
            },
            $sql
        );

        // DROP TABLE [IF EXISTS] [`name`]
        $sql = preg_replace_callback(
            '/\bDROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`([^`]+)`/i',
            function ($m) use ($addPrefix) {
                return str_replace('`' . $m[1] . '`', '`' . $addPrefix($m[1]) . '`', $m[0]);
            },
            $sql
        );

        // INSERT [IGNORE] INTO [`name`]
        $sql = preg_replace_callback(
            '/\bINSERT\s+(?:IGNORE\s+)?INTO\s+`([^`]+)`/i',
            function ($m) use ($addPrefix) {
                return str_replace('`' . $m[1] . '`', '`' . $addPrefix($m[1]) . '`', $m[0]);
            },
            $sql
        );

        // UPDATE [`name`] — NUR standalone UPDATE, nicht ON UPDATE
        $sql = preg_replace_callback(
            '/(?<!\w)UPDATE\s+`([^`]+)`/i',
            function ($m) use ($addPrefix) {
                return str_replace('`' . $m[1] . '`', '`' . $addPrefix($m[1]) . '`', $m[0]);
            },
            $sql
        );

        // ALTER TABLE [`name`]
        $sql = preg_replace_callback(
            '/\bALTER\s+TABLE\s+`([^`]+)`/i',
            function ($m) use ($addPrefix) {
                return str_replace('`' . $m[1] . '`', '`' . $addPrefix($m[1]) . '`', $m[0]);
            },
            $sql
        );

        // REFERENCES [`name`]
        $sql = preg_replace_callback(
            '/\bREFERENCES\s+`([^`]+)`/i',
            function ($m) use ($addPrefix) {
                return str_replace('`' . $m[1] . '`', '`' . $addPrefix($m[1]) . '`', $m[0]);
            },
            $sql
        );

        // Schritt 3: Platzhalter wiederherstellen
        return strtr($sql, $placeholders);
    }

    // ── SQL in einzelne Statements aufteilen (respektiert Strings + Backticks) ─
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $inString   = false;
        $inBacktick = false;
        $stringChar = '';
        $len        = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];

            if ($inBacktick) {
                $current .= $c;
                if ($c === '`') {
                    $inBacktick = false;
                }
                continue;
            }

            if ($inString) {
                $current .= $c;
                if ($c === '\\') {
                    if ($i + 1 < $len) {
                        $current .= $sql[++$i];
                    }
                } elseif ($c === $stringChar) {
                    $inString = false;
                }
                continue;
            }

            // Außerhalb von Strings und Backticks
            if ($c === '`') {
                $inBacktick = true;
                $current   .= $c;
            } elseif ($c === '"' || $c === "'") {
                $inString   = true;
                $stringChar = $c;
                $current   .= $c;
            } elseif ($c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                // Zeilen-Kommentar bis \n überspringen
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
            } elseif ($c === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
            } elseif ($c === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                // Block-Kommentar überspringen
                $i += 2;
                while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2;
            } elseif ($c === ';') {
                $stmt = trim($current);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
            } else {
                $current .= $c;
            }
        }

        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }

    // ── Setzt die Migrations-Version eines Tenants zurück ─────────────────────────────
    public function resetTenantVersion(array $params = []): void
    {
        $this->requireAuth();

        $tenantId = (int)($params['tenant_id'] ?? $_GET['tenant_id'] ?? 0);
        $targetVersion = (int)($params['target_version'] ?? $_GET['target_version'] ?? 0);

        if (!$tenantId || !$targetVersion) {
            $this->jsonError('Tenant ID und Ziel-Version erforderlich');
        }

        $tenant = $this->tenantRepo->find($tenantId);
        if (!$tenant) {
            $this->jsonError('Tenant nicht gefunden');
        }

        $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_') . '_';
        $migTbl = $prefix . 'migrations';

        try {
            $pdo = $this->db->getPdo();
            $pdo->exec("DELETE FROM `{$migTbl}` WHERE version >= {$targetVersion}");
            $deleted = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();

            $currentVersion = (int)($pdo->query("SELECT MAX(version) FROM `{$migTbl}`")->fetchColumn() ?? 0);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => "Version zurückgesetzt auf v{$currentVersion}",
                'current_version' => $currentVersion
            ]);
            exit;
        } catch (\Throwable $e) {
            $this->jsonError('Fehler: ' . $e->getMessage());
        }
    }

    // ── Migriert alle Tenants auf die neueste Version ─────────────────────────────
    public function migrateAllTenants(array $params = []): void
    {
        $this->requireAuth();

        try {
            $tenants = $this->tenantRepo->all(1000, 0);
            if (empty($tenants)) {
                $this->jsonError('Keine Tenants gefunden.');
            }

            $successCount = 0;
            $errorCount = 0;
            $details = [];

            foreach ($tenants as $tenant) {
                $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_') . '_';
                if ($prefix === '_' || empty($tenant['db_name'])) continue;

                $result = $this->migrationService->migrateTenant($prefix);
                if ($result['success']) {
                    $successCount++;
                    if ($result['ran_count'] > 0) {
                        $details[] = "{$tenant['practice_name']}: {$result['ran_count']} Migrations angewandt.";
                    }
                } else {
                    $errorCount++;
                    $details[] = "{$tenant['practice_name']}: Fehler!";
                }
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $errorCount === 0,
                'message' => "Update abgeschlossen: {$successCount} erfolgreich, {$errorCount} Fehler.",
                'details' => $details
            ]);
            exit;
        } catch (\Throwable $e) {
            $this->jsonError('Fehler: ' . $e->getMessage());
        }
    }

    /**
     * GET /admin/migration/migrate-single
     * Migriert einen spezifischen Tenant.
     */
    public function migrateSingle(array $params = []): void
    {
        $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        $tenantId = (int)($params['tenant_id'] ?? $_GET['tenant_id'] ?? 0);
        if (!$tenantId) {
            echo json_encode(['success' => false, 'error' => 'Tenant ID erforderlich']);
            exit;
        }

        $tenant = $this->tenantRepo->find($tenantId);
        if (!$tenant) {
            echo json_encode(['success' => false, 'error' => 'Tenant nicht gefunden']);
            exit;
        }

        try {
            $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_') . '_';
            $result = $this->migrationService->migrateTenant($prefix);

            echo json_encode([
                'success' => $result['success'],
                'ran_count' => $result['ran_count'],
                'message' => $result['success'] 
                    ? ($result['ran_count'] > 0 ? "{$result['ran_count']} Migrations erfolgreich angewandt." : "Datenbank ist bereits aktuell.")
                    : "Fehler: " . implode(', ', $result['errors'] ?? []),
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * GET /admin/migration/check-all-versions
     * Prüft den Status ALLER Tenants in einem Batch-Call.
     */
    public function checkAllVersions(array $params = []): void
    {
        $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $tenants = $this->tenantRepo->all(1000, 0);
            $latestVersion = $this->migrationService->getLatestVersion();
            $results = [];

            foreach ($tenants as $tenant) {
                $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_') . '_';
                if ($prefix === '_' || empty($tenant['db_name'])) continue;

                $currentVersion = $this->migrationService->getTenantVersion($prefix);
                
                $results[$tenant['id']] = [
                    'current_version' => $currentVersion,
                    'latest_version'  => $latestVersion,
                    'is_up_to_date'   => $currentVersion >= $latestVersion,
                ];
            }

            echo json_encode([
                'success' => true,
                'tenants' => $results,
                'latest_version' => $latestVersion
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── API: Datenbank-Version eines Tenants ermitteln ─────────────────────────────
    public function getTenantVersion(array $params = []): void
    {
        $this->requireAuth();
        header('Content-Type: application/json; charset=utf-8');

        $tenantId = (int)($params['tenant_id'] ?? $_GET['tenant_id'] ?? 0);
        if (!$tenantId) {
            echo json_encode(['success' => false, 'error' => 'Tenant ID erforderlich']);
            exit;
        }

        $tenant = $this->tenantRepo->find($tenantId);
        if (!$tenant) {
            echo json_encode(['success' => false, 'error' => 'Tenant nicht gefunden']);
            exit;
        }

        try {
            $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_') . '_';
            $currentVersion = $this->migrationService->getTenantVersion($prefix);
            $latest = $this->migrationService->getLatestVersion();
            $isUpToDate = $currentVersion >= $latest;

            echo json_encode([
                'success' => true,
                'current_version' => $currentVersion,
                'latest_version' => $latest,
                'is_up_to_date' => $isUpToDate,
                'status' => $isUpToDate ? 'up_to_date' : 'update_available',
                'message' => $isUpToDate ? 'Datenbank ist aktuell' : 'Update verfügbar'
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Ermittelt die neueste verfügbare Migration-Version aus SaaS-Platform ─────
    private function getLatestMigrationVersion(): int
    {
        $migrationsDir = dirname(__DIR__, 2) . '/migrations';
        if (!is_dir($migrationsDir)) {
            return 0;
        }

        $maxVersion = 0;
        $files = scandir($migrationsDir);
        foreach ($files as $file) {
            if (preg_match('/^(\d{3})_/', $file, $matches)) {
                $version = (int)$matches[1];
                if ($version > $maxVersion) {
                    $maxVersion = $version;
                }
            }
        }
        return $maxVersion;
    }

    // ── Post-Import: migrations-Tabelle anlegen + settings befüllen ─────────
    private function postImportSetup(string $prefix, array $practiceData): array
    {
        $pdo      = $this->db->getPdo();
        $migTbl   = $prefix . 'migrations';
        $setTbl   = $prefix . 'settings';
        $messages = [];

        // 1. migrations-Tabelle anlegen (falls nicht vorhanden)
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `{$migTbl}` (
                    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `version`    INT NOT NULL UNIQUE,
                    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            // Alle bekannten Versionen als angewendet markieren (DB-Version aus Import übernehmen)
            $latestVersion = $this->getLatestMigrationVersion();
            if ($latestVersion === 0) {
                $latestVersion = 23; // Fallback
            }
            $values = implode(',', array_map(fn($v) => "({$v})", range(1, $latestVersion)));
            $pdo->exec("INSERT IGNORE INTO `{$migTbl}` (`version`) VALUES {$values}");
            $messages[] = "migrations-Tabelle angelegt und auf Version {$latestVersion} gesetzt.";
        } catch (\Throwable $e) {
            $messages[] = 'migrations-Tabelle: ' . $e->getMessage();
        }

        // 2. settings-Tabelle anlegen (falls nicht vorhanden — Fallback)
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `{$setTbl}` (
                    `key`        VARCHAR(100) NOT NULL,
                    `value`      TEXT NULL,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable) {}

        // 3. Praxisdaten in settings schreiben
        $settingsToSet = array_filter([
            'company_name'    => $practiceData['company_name']    ?? '',
            'company_email'   => $practiceData['company_email']   ?? '',
            'company_phone'   => $practiceData['company_phone']   ?? '',
            'company_address' => $practiceData['company_address'] ?? '',
            'company_city'    => $practiceData['company_city']    ?? '',
            'company_zip'     => $practiceData['company_zip']     ?? '',
        ], fn($v) => $v !== '');

        $written = 0;
        foreach ($settingsToSet as $key => $value) {
            try {
                $st = $pdo->prepare(
                    "INSERT INTO `{$setTbl}` (`key`, `value`) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
                );
                $st->execute([$key, $value]);
                $written++;
            } catch (\Throwable $e) {
                $messages[] = "settings[{$key}]: " . $e->getMessage();
            }
        }

        if ($written > 0) {
            $messages[] = "{$written} Praxisdaten in settings geschrieben.";
        }

        return ['success' => true, 'messages' => $messages];
    }

    // ── Admin-User in Tenant-Tabellen setzen / aktualisieren ────────────────
    private function setAdminUser(string $prefix, string $email, string $password, string $name): array
    {
        $pdo  = $this->db->getPdo();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $tbl  = $prefix . 'users';

        try {
            // Prüfen ob die Tabelle existiert
            $check = $pdo->query("SHOW TABLES LIKE '{$tbl}'")->fetchColumn();
            if (!$check) {
                return ['success' => false, 'message' => "Tabelle `{$tbl}` nicht gefunden — Import möglicherweise unvollständig."];
            }

            // Vorhandene Admin-Zeile suchen (role = admin)
            $existing = $pdo->prepare("SELECT id, email FROM `{$tbl}` WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $existing->execute();
            $adminRow = $existing->fetch(\PDO::FETCH_ASSOC);

            if ($adminRow) {
                // Admin-Zeile updaten
                $stmt = $pdo->prepare(
                    "UPDATE `{$tbl}` SET email = ?, password = ?, active = 1"
                    . ($name !== '' ? ", name = ?" : "")
                    . " WHERE id = ?"
                );
                if ($name !== '') {
                    $stmt->execute([$email, $hash, $name, $adminRow['id']]);
                } else {
                    $stmt->execute([$email, $hash, $adminRow['id']]);
                }
                return [
                    'success' => true,
                    'action'  => 'updated',
                    'message' => "Admin-User (ID {$adminRow['id']}, war: {$adminRow['email']}) → E-Mail auf \"{$email}\" und Passwort aktualisiert.",
                ];
            } else {
                // Kein Admin vorhanden — neu anlegen
                $insertName = $name !== '' ? $name : 'Admin';
                $stmt = $pdo->prepare(
                    "INSERT INTO `{$tbl}` (name, email, password, role, active, created_at)
                     VALUES (?, ?, ?, 'admin', 1, NOW())"
                );
                $stmt->execute([$insertName, $email, $hash]);
                $newId = (int)$pdo->lastInsertId();
                return [
                    'success' => true,
                    'action'  => 'created',
                    'message' => "Neuer Admin-User angelegt (ID {$newId}) mit E-Mail \"{$email}\".",
                ];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Fehler beim Admin-User setzen: ' . $e->getMessage()];
        }
    }


    /**
     * POST /admin/migration/google-plugin
     * Führt die 3 Google Calendar Plugin Migrations für ALLE bestehenden
     * Tenants aus. Sicher wiederholbar (IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).
     */
    public function migrateGooglePlugin(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $migrationFiles = [
            dirname(__DIR__, 3) . '/plugins/google-calendar-sync/migrations/001_google_calendar.sql',
            dirname(__DIR__, 3) . '/plugins/google-calendar-sync/migrations/002_google_twoway_sync.sql',
            dirname(__DIR__, 3) . '/plugins/google-calendar-sync/migrations/003_appointments_google_event_id.sql',
        ];

        // Prüfe ob alle Migrations-Dateien existieren
        foreach ($migrationFiles as $file) {
            if (!file_exists($file)) {
                $this->jsonError('Migrations-Datei nicht gefunden: ' . basename($file));
            }
        }

        $googleTables = [
            'google_calendar_connections',
            'google_calendar_sync_map',
            'google_calendar_sync_log',
            'google_calendar_imported_events',
        ];

        $tenants = $this->tenantRepo->all(1000, 0);
        $results = [];

        foreach ($tenants as $tenant) {
            $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_') . '_';
            if ($prefix === '_') continue;

            $pdo = $this->db->getPdo();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            $ok      = 0;
            $skipped = 0;
            $errors  = [];

            foreach ($migrationFiles as $file) {
                $sql = (string)file_get_contents($file);

                // Tabellennamen mit Tenant-Prefix versehen
                foreach ($googleTables as $table) {
                    $sql = preg_replace(
                        '/`' . preg_quote($table, '/') . '`/',
                        '`' . $prefix . $table . '`',
                        $sql
                    );
                }

                // Constraint-Namen prefixen
                $sql = preg_replace_callback(
                    '/\bCONSTRAINT\s+`([^`]+)`/i',
                    fn($m) => 'CONSTRAINT `' . $prefix . $m[1] . '`',
                    $sql
                );

                $statements = $this->splitStatements($sql);

                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '') continue;
                    if (preg_match('/^(--|#|\/\*)/i', $stmt)) continue;

                    try {
                        $pdo->exec($stmt);
                        $ok++;
                    } catch (\Throwable $e) {
                        $msg   = $e->getMessage();
                        $errno = 0;
                        if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
                            $errno = (int)$e->errorInfo[1];
                        }
                        // Ignoriere: Tabelle existiert, Spalte existiert, Duplikat-Key, Duplikat-Eintrag
                        if (in_array($errno, [1050, 1060, 1061, 1062], true)
                            || str_contains($msg, 'already exists')
                            || str_contains($msg, 'Duplicate')) {
                            $skipped++;
                        } else {
                            $errors[] = basename($file) . ': ' . $msg;
                        }
                    }
                }
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            $results[] = [
                'tenant'  => $tenant['practice_name'],
                'prefix'  => $prefix,
                'status'  => empty($errors) ? 'success' : 'error',
                'message' => empty($errors)
                    ? "OK: {$ok} ausgeführt, {$skipped} übersprungen."
                    : count($errors) . " Fehler ({$ok} OK)",
                'errors'  => $errors,
            ];
        }

        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $errorCount   = count($results) - $successCount;

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $errorCount === 0,
            'message' => "Google Plugin Migrations abgeschlossen: {$successCount} Tenants OK, {$errorCount} Fehler.",
            'results' => $results,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * POST /admin/migration/plugins-all-tenants
     *
     * Führt ALLE Plugin-Migrations fuer alle Tenants aus.
     * Sicher wiederholbar. Tabellen-Prefixe werden automatisch gesetzt.
     */
    public function migratePluginsAllTenants(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        // Plugin-Migrations-Verzeichnisse finden
        $pluginsDir = dirname(__DIR__, 3) . '/plugins';
        if (!is_dir($pluginsDir)) {
            $this->jsonError('Plugins-Verzeichnis nicht gefunden: ' . $pluginsDir);
        }

        // Alle SQL-Dateien aus allen plugins/*/migrations/ sammeln
        $migrationFiles = [];
        $pluginMigDirs  = glob($pluginsDir . '/*/migrations', GLOB_ONLYDIR) ?: [];
        foreach ($pluginMigDirs as $dir) {
            $files = glob($dir . '/*.sql') ?: [];
            sort($files);
            foreach ($files as $file) {
                $migrationFiles[] = $file;
            }
        }

        if (empty($migrationFiles)) {
            $this->jsonError('Keine Plugin-Migrations-Dateien gefunden.');
        }

        // Alle Plugin-Tabellennamen die geprefixed werden müssen
        // Plugin-Migrations können auch Core-Tabellen wie patients, invoices ändern
        $pluginTables = [
            'users','settings','owners','patients','appointments','appointment_waitlist',
            'invoices','invoice_items','invoice_positions','invoice_reminders','invoice_dunnings',
            'waitlist','user_preferences','migrations',
            'patient_timeline','treatment_types',
            'mobile_api_tokens','cron_job_log',
            'befundboegen','befundbogen_felder',
            'google_calendar_connections',
            'google_calendar_sync_map',
            'google_calendar_sync_log',
            'google_calendar_imported_events',
        ];

        $tenants = $this->tenantRepo->all(1000, 0);
        $results = [];

        foreach ($tenants as $tenant) {
            $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_') . '_';
            if ($prefix === '_' || empty($tenant['db_name'])) continue;

            $pdo = $this->db->getPdo();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            $ok = 0;
            $skipped = 0;
            $errors = [];

            foreach ($migrationFiles as $file) {
                $sql = (string)file_get_contents($file);

                // Plugin-Tabellen prefixen
                foreach ($pluginTables as $table) {
                    $sql = preg_replace(
                        '/`' . preg_quote($table, '/') . '`/',
                        '`' . $prefix . $table . '`',
                        $sql
                    );
                }

                // Constraint-Namen prefixen (verhindert Duplikat-Constraint-Fehler)
                $sql = preg_replace_callback(
                    '/\bCONSTRAINT\s+`([^`]+)`/i',
                    fn($m) => 'CONSTRAINT `' . $prefix . $m[1] . '`',
                    $sql
                );

                $statements = $this->splitStatements($sql);

                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '') continue;
                    if (preg_match('/^(--|#|\/\*)/i', $stmt)) continue;
                    if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS/i', $stmt)) continue;

                    try {
                        $pdo->exec($stmt);
                        $ok++;
                    } catch (\Throwable $e) {
                        $msg   = $e->getMessage();
                        $errno = 0;
                        if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
                            $errno = (int)$e->errorInfo[1];
                        }
                        if (in_array($errno, [1050, 1060, 1061, 1062, 1068], true)
                            || str_contains($msg, 'already exists')
                            || str_contains($msg, 'Duplicate')
                            || str_contains($msg, 'Multiple primary key')) {
                            $skipped++;
                        } else {
                            $errors[] = basename($file) . ': ' . mb_substr($stmt, 0, 60) . '… → ' . $msg;
                        }
                    }
                }
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            $results[] = [
                'tenant'  => $tenant['practice_name'],
                'prefix'  => $prefix,
                'status'  => empty($errors) ? 'success' : 'error',
                'message' => empty($errors)
                    ? "{$ok} ausgeführt, {$skipped} übersprungen."
                    : count($errors) . " Fehler ({$ok} OK)",
                'errors'  => $errors,
            ];
        }

        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $errorCount   = count($results) - $successCount;

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $errorCount === 0,
            'message' => "Plugin-Migrations abgeschlossen: {$successCount} Tenants OK, {$errorCount} Fehler.",
            'results' => $results,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * POST /admin/migration/schema-all-tenants
     *
     * Führt tenant_schema.sql für ALLE Tenants aus.
     * Legt fehlende Tabellen an, überspringt vorhandene (IF NOT EXISTS).
     * Aktualisiert auch die migrations-Versionstabelle.
     */
    public function migrateSchemaAllTenants(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $schemaFile = dirname(__DIR__, 2) . '/provisioning/tenant_schema.sql';
        if (!file_exists($schemaFile)) {
            $this->jsonError('Schema-Datei nicht gefunden: provisioning/tenant_schema.sql');
        }

        $rawSql = (string)file_get_contents($schemaFile);

        $tenants = $this->tenantRepo->all(1000, 0);
        $results = [];

        foreach ($tenants as $tenant) {
            $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_') . '_';
            if ($prefix === '_' || empty($tenant['db_name'])) continue;

            // Prefix auf alle Tabellen im Schema anwenden
            $tenantSql = $this->applyPrefixToSqlLocally($rawSql, $prefix);

            $pdo = $this->db->getPdo();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            $statements = $this->splitStatements($tenantSql);
            $ok = 0;
            $skipped = 0;
            $errors = [];

            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                if (preg_match('/^(--|#|\/\*|SET\s+NAMES|SET\s+FOREIGN)/i', $stmt)) continue;

                try {
                    $pdo->exec($stmt);
                    $ok++;
                } catch (\Throwable $e) {
                    $msg   = $e->getMessage();
                    $errno = 0;
                    if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
                        $errno = (int)$e->errorInfo[1];
                    }
                    if (in_array($errno, [1050, 1060, 1061, 1062, 1068], true)
                        || str_contains($msg, 'already exists')
                        || str_contains($msg, 'Duplicate')
                        || str_contains($msg, 'Multiple primary key')) {
                        $skipped++;
                    } else {
                        $errors[] = mb_substr($stmt, 0, 80) . '… → ' . $msg;
                    }
                }
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            $results[] = [
                'tenant'  => $tenant['practice_name'],
                'prefix'  => $prefix,
                'status'  => empty($errors) ? 'success' : 'error',
                'message' => empty($errors)
                    ? "{$ok} ausgeführt, {$skipped} übersprungen."
                    : count($errors) . " Fehler ({$ok} OK)",
                'errors'  => $errors,
            ];
        }

        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $errorCount   = count($results) - $successCount;

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $errorCount === 0,
            'message' => "Schema-Migration abgeschlossen: {$successCount} Tenants OK, {$errorCount} Fehler.",
            'results' => $results,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private function applyPrefixToSqlLocally(string $sql, string $prefix): string
    {
        $sql = preg_replace_callback(
            '/\bCONSTRAINT\s+`([^`]+)`/i',
            fn($m) => 'CONSTRAINT `' . $prefix . $m[1] . '`',
            $sql
        );

        $tables = [
            'users','settings','owners','patients','appointments','appointment_waitlist',
            'invoices','invoice_items','invoice_positions','invoice_reminders','invoice_dunnings',
            'waitlist','user_preferences','migrations',
            'patient_timeline','treatment_types',
            'mobile_api_tokens','cron_job_log',
            'befundboegen','befundbogen_felder',
            'vet_reports', 'homework_plans', 'homework_tasks', 'homework_templates',
            'google_calendar_connections',
            'google_calendar_sync_map',
            'google_calendar_sync_log',
            'google_calendar_imported_events',
        ];

        foreach ($tables as $table) {
            $sql = preg_replace(
                '/`' . preg_quote($table, '/') . '`/',
                '`' . $prefix . $table . '`',
                $sql
            );
        }

        return $sql;
    }

    private function jsonError(string $message): never
    {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
