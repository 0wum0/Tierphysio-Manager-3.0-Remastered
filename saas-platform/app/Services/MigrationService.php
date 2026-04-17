<?php

declare(strict_types=1);

namespace Saas\Services;

use PDO;
use Saas\Core\Config;
use Saas\Core\Database;

class MigrationService
{
    private const GLOBAL_TABLES = [
        'tenants',
        'plans',
        'saas_admins',
        'saas_logs',
        'failed_jobs',
        'migrations_global',
        // System / MySQL schemas
        'information_schema',
        'performance_schema',
        'mysql',
        'sys'
    ];

    public function __construct(
        private Config   $config,
        private Database $db
    ) {}

    /**
     * Get the latest available migration version from the migrations folder.
     */
    public function getLatestVersion(): int
    {
        $migrationsDir = $this->config->getRootPath() . '/migrations';
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

    /**
     * Get the current database version of a specific tenant.
     */
    public function getTenantVersion(string $prefix): int
    {
        $pdo = $this->db->getPdo();
        $migTbl = $prefix . 'migrations';

        try {
            $stCheck = $pdo->query("SHOW TABLES LIKE '{$migTbl}'");
            $check   = $stCheck->fetchColumn();
            $stCheck->closeCursor();

            if (!$check) {
                return 0;
            }

            $stVer   = $pdo->query("SELECT MAX(version) FROM `{$migTbl}`");
            $version = (int)($stVer->fetchColumn() ?? 0);
            $stVer->closeCursor();

            return $version;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Führt alle ausstehenden Migrations für einen Tenant aus.
     */
    public function migrateTenant(string $prefix): array
    {
        $this->db->getPdo()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $this->ensureTenantBaseSchema($prefix);
        $this->ensureMigrationsTable($prefix);
        $currentVersion = $this->getTenantVersion($prefix);
        $latestVersion  = $this->getLatestVersion();

        $results = [
            'success'   => true,
            'from'      => $currentVersion,
            'to'        => $currentVersion,
            'ran_count' => 0,
            'report'    => []
        ];

        if ($currentVersion >= $latestVersion) {
            return $results;
        }

        $migrationsDir = $this->config->getRootPath() . '/migrations';
        $files = scandir($migrationsDir);
        sort($files);

        foreach ($files as $file) {
            if (preg_match('/^(\d{3})_/', $file, $matches)) {
                $version = (int)$matches[1];
                if ($version > $currentVersion) {
                    $res = $this->executeMigration($migrationsDir . '/' . $file, $prefix);
                    $results['report'][] = $res['report'];
                    
                    if (!$res['success']) {
                        $results['success'] = false;
                        break;
                    }
                    
                    $results['ran_count']++;
                    $results['to'] = $version;
                }
            }
        }

        return $results;
    }

    /**
     * Erzwingt eine Neusynchronisation für einen Tenant.
     */
    public function forceSyncTenant(string $prefix): array
    {
        $pdo = $this->db->getPdo();
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $migTbl = $prefix . 'migrations';
        $setTbl = $prefix . 'settings';

        try {
            $pdo->exec("DELETE FROM `{$migTbl}`");
            try {
                $pdo->exec("UPDATE `{$setTbl}` SET `value` = '0' WHERE `key` = 'db_version'");
            } catch (\Throwable $e) {}
            
            return $this->migrateTenant($prefix);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'from'    => 0,
                'to'      => 0,
                'report'  => [],
                'message' => 'Reset fehlgeschlagen: ' . $e->getMessage()
            ];
        }
    }


    /**
     * Ensure the canonical tenant base schema exists before running incremental migrations.
     *
     * This self-heals tenants where core tables were accidentally removed and prevents
     * ALTER TABLE migrations from failing with 1146 / 42S02 (table not found).
     */
    private function ensureTenantBaseSchema(string $prefix): void
    {
        $schemaPath = $this->config->getRootPath() . '/provisioning/tenant_schema.sql';
        if (!is_file($schemaPath)) {
            return;
        }

        $sql = (string)file_get_contents($schemaPath);
        if ($sql === '') {
            return;
        }

        $pdo        = $this->db->getPdo();
        $prefixed   = $this->prefixTableNames($sql, $prefix);
        $statements = $this->splitStatements($prefixed);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || $stmt === ';') {
                continue;
            }

            // Never run destructive statements in schema bootstrap on existing tenants.
            if (!$this->isSafeBootstrapStatement($stmt)) {
                continue;
            }

            try {
                // Same rationale as executeMigration(): use query()+closeCursor()
                // so any accidental result set is consumed and the cursor released.
                $sth = $pdo->query($stmt);
                if ($sth instanceof \PDOStatement) {
                    $sth->closeCursor();
                    $sth = null;
                }
            } catch (\PDOException $e) {
                $code = (int)($e->errorInfo[1] ?? 0);

                // Ignore idempotent / already-existing failures while bootstrapping.
                if (in_array($code, [1050, 1060, 1061, 1062, 1068, 1091, 1054, 1146], true) || in_array($e->getCode(), ['42S01', '42S02'], true)) {
                    continue;
                }

                // Keep bootstrap non-fatal: migrations below can still heal incrementally.
                continue;
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }


    /**
     * Safety gate for base-schema bootstrap on existing tenants.
     *
     * We intentionally allow only additive statements to avoid any data loss
     * when repair is executed for productive practices.
     */
    private function isSafeBootstrapStatement(string $stmt): bool
    {
        $s = strtoupper(ltrim($stmt));

        if (str_starts_with($s, 'DROP ') || str_starts_with($s, 'TRUNCATE ') || str_starts_with($s, 'DELETE ')) {
            return false;
        }

        return str_starts_with($s, 'SET ')
            || str_starts_with($s, 'CREATE TABLE')
            || str_starts_with($s, 'ALTER TABLE')
            || str_starts_with($s, 'CREATE INDEX')
            || str_starts_with($s, 'CREATE UNIQUE INDEX')
            || str_starts_with($s, 'INSERT IGNORE INTO');
    }

    /**
     * Führt eine einzelne Migrationsdatei für einen Tenant aus.
     */
    public function executeMigration(string $file, string $prefix): array
    {
        if (!file_exists($file)) {
            return ['success' => false, 'error' => "Datei nicht gefunden: {$file}"];
        }

        $version = (int)basename($file);
        $sql     = (string)file_get_contents($file);
        $pdo     = $this->db->getPdo();
        $migTbl  = $prefix . 'migrations';

        $prefixedSql = $this->prefixTableNames($sql, $prefix);
        $statements  = $this->splitStatements($prefixedSql);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        
        $report = [
            'version' => $version,
            'applied' => [],
            'skipped' => [],
            'errors'  => []
        ];

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || $stmt === ';') continue;
            
            try {
                // Use query()+closeCursor() instead of exec() to safely handle
                // statements that may return a result set (e.g. EXECUTE stmt with
                // a SELECT fallback inside idempotent migrations like 032_gdpr_consent).
                // exec() leaves the server-side cursor open on SELECT, which causes
                // the next query on the same connection to fail with SQLSTATE 2014.
                $sth = $pdo->query($stmt);
                if ($sth instanceof \PDOStatement) {
                    $sth->closeCursor();
                    $sth = null;
                }
                $report['applied'][] = mb_substr($stmt, 0, 100) . '...';
            } catch (\PDOException $e) {
                $code = (int)($e->errorInfo[1] ?? 0);
                $msg  = $e->getMessage();
                
                if (in_array($code, [1050, 1060, 1061, 1062, 1068, 1091, 1054, 1146], true) || in_array($e->getCode(), ['42S01', '42S02'], true)) {
                    $report['skipped'][] = [
                        'code' => $code,
                        'msg'  => $msg,
                        'stmt' => mb_substr($stmt, 0, 100) . '...'
                    ];
                } else {
                    $report['errors'][] = [
                        'code' => $code,
                        'msg'  => $msg,
                        'stmt' => $stmt
                    ];
                    break;
                }
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        if (empty($report['errors'])) {
            $stIns = $pdo->prepare("INSERT IGNORE INTO `{$migTbl}` (version, applied_at) VALUES (?, NOW())");
            $stIns->execute([$version]);
            $stIns->closeCursor();
            return ['success' => true, 'report' => $report];
        }

        return ['success' => false, 'report' => $report];
    }

    /**
     * Robuste Tabellen-Präfix-Logik.
     * Maskiert Strings und Kommentare, um Syntax-Fehler zu vermeiden.
     */
    private function prefixTableNames(string $sql, string $prefix): string
    {
        $sql = str_replace(['{{prefix}}', '{{ prefix }}'], $prefix, $sql);

        $placeholders = [];
        $idx = 0;

        $maskPattern = "/('(?:''|\\\\.|[^'])*'|\"(?:\"\"|\\\\.|[^\"])*\"|\/\*.*?\*\/|--.*|#.*)/s";
        
        $sql = preg_replace_callback($maskPattern, function($m) use (&$placeholders, &$idx) {
            $key = "___SQL_MASK_" . ($idx++) . "___";
            $placeholders[$key] = $m[0];
            return $key;
        }, $sql);

        $reserved = [
            'current_timestamp', 'now', 'null', 'true', 'false', 'primary', 'key',
            'index', 'unique', 'constraint', 'references', 'cascade', 'restrict',
            'default', 'datetime', 'timestamp', 'varchar', 'text', 'enum', 'decimal',
            'unsigned', 'charset', 'collate', 'engine', 'innodb', 'comment', 'on',
            'update', 'delete', 'set', 'names', 'foreign', 'checks', 'exists', 'if', 'not',
            'stmt', 'database', 'information_schema', 'performance_schema', 'mysql', 'sys', 'tables', 'columns'
        ];

        $addPrefix = function($name) use ($prefix, $reserved) {
            $cleanName = trim($name, '`"\' ');
            $lowerName = strtolower($cleanName);
            
            if (str_starts_with($cleanName, '@')) return $name;
            if (in_array($lowerName, $reserved)) return $name;
            if (in_array($lowerName, self::GLOBAL_TABLES)) return $name;
            if (str_contains($cleanName, '.')) return $name;
            if (str_starts_with($lowerName, strtolower($prefix))) return $name;
            
            return $prefix . $cleanName;
        };

        // DDL: CREATE, DROP, ALTER TABLE
        $sql = preg_replace_callback('/\b(CREATE|DROP|ALTER)\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([`"]?)([^`"\s\(\),;\.]+)\2/i', function($m) use ($addPrefix) {
            return str_replace($m[3], $addPrefix($m[3]), $m[0]);
        }, $sql);

        // DML: INSERT, UPDATE, DELETE, TRUNCATE
        $sql = preg_replace_callback('/\b(INSERT|REPLACE|UPDATE|DELETE\s+FROM|TRUNCATE\s+TABLE)\s+(?:IGNORE\s+)?(?:INTO\s+)?([`"]?)([^`"\s\(\),;\.]+)\2/i', function($m) use ($addPrefix) {
            return str_replace($m[3], $addPrefix($m[3]), $m[0]);
        }, $sql);

        // FROM, JOIN, REFERENCES
        $sql = preg_replace_callback('/\b(FROM|JOIN|REFERENCES)\s+([`"]?)([^`"\s\(\),;\.]+)\2/i', function($m) use ($addPrefix) {
            return str_replace($m[3], $addPrefix($m[3]), $m[0]);
        }, $sql);

        // Constraints und Keys
        $sql = preg_replace_callback('/\b(CONSTRAINT|INDEX|KEY)\s+([`"]?)([^`"\s\(\),;\.]+)\2/i', function($m) use ($prefix, $reserved) {
            $name = $m[3];
            if (in_array(strtolower($name), $reserved)) return $m[0];
            if (str_starts_with(strtolower($name), strtolower($prefix))) return $m[0];
            return str_replace($name, $prefix . $name, $m[0]);
        }, $sql);

        if (!empty($placeholders)) {
            $sql = strtr($sql, $placeholders);
        }

        return $sql;
    }

    private function ensureMigrationsTable(string $prefix): void
    {
        $pdo = $this->db->getPdo();
        $migTbl = $prefix . 'migrations';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$migTbl}` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `version`    INT NOT NULL UNIQUE,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

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
            if ($inBacktick) { $current .= $c; if ($c === '`') $inBacktick = false; continue; }
            if ($inString) { 
                $current .= $c; 
                if ($c === '\\' && $i + 1 < $len) { $current .= $sql[++$i]; } 
                elseif ($c === $stringChar) $inString = false; 
                continue; 
            }
            if ($c === '`') { $inBacktick = true; $current .= $c; }
            elseif ($c === "'" || $c === '"') { $inString = true; $stringChar = $c; $current .= $c; }
            elseif ($c === '-' && $i + 1 < $len && $sql[$i+1] === '-') { 
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }
            elseif ($c === '#') { 
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }
            elseif ($c === ';') { 
                $stmt = trim($current); 
                if ($stmt !== '') $statements[] = $stmt; 
                $current = ''; 
            }
            else { $current .= $c; }
        }
        $stmt = trim($current); 
        if ($stmt !== '') $statements[] = $stmt;
        return $statements;
    }
}
