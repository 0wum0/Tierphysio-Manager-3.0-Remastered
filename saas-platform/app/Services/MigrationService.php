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
            // Check if migrations table exists
            $check = $pdo->query("SHOW TABLES LIKE '{$migTbl}'")->fetchColumn();
            if (!$check) {
                return 0;
            }

            return (int)($pdo->query("SELECT MAX(version) FROM `{$migTbl}`")->fetchColumn() ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Führt alle ausstehenden Migrations für einen Tenant aus.
     */
    public function migrateTenant(string $prefix): array
    {
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
        $migTbl = $prefix . 'migrations';
        $setTbl = $prefix . 'settings';

        try {
            // Version zurücksetzen
            $pdo->exec("DELETE FROM `{$migTbl}`");
            try {
                $pdo->exec("UPDATE `{$setTbl}` SET `value` = '0' WHERE `key` = 'db_version'");
            } catch (\Throwable $e) {}
            
            // Alles neu ausführen
            return $this->migrateTenant($prefix);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Reset fehlgeschlagen: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Führt eine einzelne Migrationsdatei für einen Tenant aus.
     * Gibt einen detaillierten Bericht zurück.
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

        // Robustes Präfixing anwenden
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
                $pdo->exec($stmt);
                $report['applied'][] = mb_substr($stmt, 0, 100) . '...';
            } catch (\PDOException $e) {
                $code = (int)($e->errorInfo[1] ?? 0);
                $msg  = $e->getMessage();
                
                // Bekannte "Safe"-Fehler (Existiert bereits etc.)
                if (in_array($code, [1050, 1060, 1061, 1062, 1068, 1091, 1054], true) || $e->getCode() === '42S01') {
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
                    // Bei kritischem Fehler abbrechen
                    break;
                }
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        if (empty($report['errors'])) {
            // Als angewandt markieren
            $pdo->prepare("INSERT IGNORE INTO `{$migTbl}` (version, applied_at) VALUES (?, NOW())")
                ->execute([$version]);
            return ['success' => true, 'report' => $report];
        }

        return ['success' => false, 'report' => $report];
    }

    /**
     * Robuste Tabellen-Präfix-Logik.
     * Schützt SQL-Keywords und System-Schemas.
     */
    private function prefixTableNames(string $sql, string $prefix): string
    {
        $reserved = [
            'current_timestamp', 'now', 'null', 'true', 'false', 'primary', 'key',
            'index', 'unique', 'constraint', 'references', 'cascade', 'restrict',
            'default', 'datetime', 'timestamp', 'varchar', 'text', 'enum', 'decimal',
            'unsigned', 'charset', 'collate', 'engine', 'innodb', 'comment', 'on',
            'update', 'delete', 'set', 'names', 'foreign', 'checks', 'exists', 'if', 'not',
            'information_schema', 'performance_schema', 'mysql', 'sys', 'tables', 'columns'
        ];

        $addPrefix = function($name) use ($prefix, $reserved) {
            $cleanName = trim($name, '`"');
            $lowerName = strtolower($cleanName);
            
            // NIEMALS Schlüsselwörter oder System-Schemas präfixen
            if (in_array($lowerName, $reserved)) return $name;
            if (in_array($lowerName, self::GLOBAL_TABLES)) return $name;
            if (str_contains($cleanName, '.')) return $name;
            
            // Bereits geprägt?
            if (str_starts_with($cleanName, $prefix)) return $name;
            
            return str_contains($name, '`') ? '`' . $prefix . $cleanName . '`' : $prefix . $cleanName;
        };

        // 1. DDL Statements (CREATE, DROP, ALTER)
        $sql = preg_replace_callback('/\b(CREATE|DROP|ALTER)\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?([^`"\s\(\),;\.]+)[`"]?/i', 
            fn($m) => str_replace($m[2], $addPrefix($m[2]), $m[0]), $sql);

        // 2. DML Statements (INSERT, UPDATE, DELETE, TRUNCATE)
        $sql = preg_replace_callback('/\b(INSERT|REPLACE|UPDATE|DELETE\s+FROM|TRUNCATE\s+TABLE)\s+(?:IGNORE\s+)?(?:INTO\s+)?[`"]?([^`"\s\(\),;\.]+)[`"]?/i', 
            fn($m) => str_replace($m[2], $addPrefix($m[2]), $m[0]), $sql);

        // 3. Relationen (FROM, JOIN, REFERENCES)
        $sql = preg_replace_callback('/\b(FROM|JOIN|REFERENCES)\s+[`"]?([^`"\s\(\),;\.]+)[`"]?/i', 
            fn($m) => str_replace($m[2], $addPrefix($m[2]), $m[0]), $sql);

        // 4. Constraints
        $sql = preg_replace_callback('/\bCONSTRAINT\s+[`"]?([^`"\s]+)[`"]?/i', 
            fn($m) => 'CONSTRAINT `' . $prefix . $m[1] . '`', $sql);

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
                // Skip comment line
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
