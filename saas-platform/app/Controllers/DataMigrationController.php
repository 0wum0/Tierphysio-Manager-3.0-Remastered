<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Database;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\PlanRepository;
use Saas\Services\TenantProvisioningService;

class DataMigrationController extends Controller
{
    public function __construct(
        View                           $view,
        Session                        $session,
        private Database               $db,
        private TenantRepository       $tenantRepo,
        private PlanRepository         $planRepo,
        private TenantProvisioningService $provisioningService
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

        $tenantId      = (int)($this->post('tenant_id') ?? 0);
        $mode          = $this->post('mode') ?? 'smart';
        $adminEmail    = trim((string)($this->post('admin_email') ?? ''));
        $adminPassword = trim((string)($this->post('admin_password') ?? ''));
        $adminName     = trim((string)($this->post('admin_name') ?? ''));

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
        if ($mode === 'smart') {
            $result = $this->runSmart($sql, $prefix);
        } else {
            $result = $this->runRaw($sql, $prefix);
        }

        // ── Neuer Tenant: Admin-User wurde bereits beim Provisioning gesetzt ──
        // Beim Import aus einer alten DB überschreiben wir den vom Provisioning
        // angelegten Platzhalter-Admin mit den echten importierten Daten.
        if ($adminEmail !== '' && $adminPassword !== '') {
            $result['admin'] = $this->setAdminUser($prefix, $adminEmail, $adminPassword, $adminName);
        }

        // ── Provisioning-Info anhängen ──────────────────────────────────────
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
            if (preg_match('/^CREATE\s+TABLE.*?`?(\w+)`?\s*\(/i', $prefixed, $m)) {
                $tbl = $m[1] ?? '';
                if ($tbl && !in_array($tbl, $stats['tables'])) {
                    $stats['tables'][] = $tbl;
                }
            }

            try {
                $pdo->exec($prefixed);
                $stats['ok']++;
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                // Duplikat-Fehler sind OK (Daten bereits vorhanden)
                if (str_contains($msg, 'Duplicate entry') || str_contains($msg, '1062')) {
                    $stats['ok']++;
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
        // CREATE TABLE `name` oder CREATE TABLE IF NOT EXISTS `name`
        $sql = preg_replace_callback(
            '/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i',
            fn($m) => str_ireplace($m[1], $prefix . $m[1], $m[0]),
            $sql
        );

        // DROP TABLE [IF EXISTS] `name`
        $sql = preg_replace_callback(
            '/\bDROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?(\w+)`?/i',
            fn($m) => str_ireplace($m[1], $prefix . $m[1], $m[0]),
            $sql
        );

        // INSERT INTO `name`
        $sql = preg_replace_callback(
            '/\bINSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?/i',
            fn($m) => str_ireplace($m[1], $prefix . $m[1], $m[0]),
            $sql
        );

        // UPDATE `name`
        $sql = preg_replace_callback(
            '/\bUPDATE\s+`?(\w+)`?/i',
            fn($m) => str_ireplace($m[1], $prefix . $m[1], $m[0]),
            $sql
        );

        // ALTER TABLE `name`
        $sql = preg_replace_callback(
            '/\bALTER\s+TABLE\s+`?(\w+)`?/i',
            fn($m) => str_ireplace($m[1], $prefix . $m[1], $m[0]),
            $sql
        );

        // REFERENCES `name` — in FK-Definitionen (werden ohnehin übersprungen im Smart-Modus)
        $sql = preg_replace_callback(
            '/\bREFERENCES\s+`?(\w+)`?/i',
            fn($m) => str_ireplace($m[1], $prefix . $m[1], $m[0]),
            $sql
        );

        return $sql;
    }

    // ── SQL in einzelne Statements aufteilen (respektiert Strings) ──────────
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $inString   = false;
        $stringChar = '';
        $len        = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];

            if ($inString) {
                $current .= $c;
                if ($c === '\\') {
                    // Escaped char
                    if ($i + 1 < $len) {
                        $current .= $sql[++$i];
                    }
                } elseif ($c === $stringChar) {
                    $inString = false;
                }
            } else {
                if ($c === '"' || $c === "'") {
                    $inString   = true;
                    $stringChar = $c;
                    $current   .= $c;
                } elseif ($c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                    // Zeilen-Kommentar überspringen bis \n
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
        }

        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
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

    private function jsonError(string $message): never
    {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
