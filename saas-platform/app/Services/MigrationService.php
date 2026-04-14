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
        'migrations_global'
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
     * Run all missing migrations for a specific tenant.
     */
    public function migrateTenant(string $prefix): array
    {
        $currentVersion = $this->getTenantVersion($prefix);
        $latestVersion  = $this->getLatestVersion();
        
        $stats = [
            'tenant'   => $prefix,
            'from'     => $currentVersion,
            'to'       => $latestVersion,
            'applied'  => 0,
            'errors'   => [],
            'success'  => true
        ];

        if ($currentVersion >= $latestVersion) {
            return $stats;
        }

        $migrationsDir = $this->config->getRootPath() . '/migrations';
        $pdo = $this->db->getPdo();
        
        // Ensure migrations table exists
        $this->ensureMigrationsTable($prefix);

        $files = scandir($migrationsDir);
        sort($files);

        foreach ($files as $file) {
            if (preg_match('/^(\d{3})_/', $file, $matches)) {
                $version = (int)$matches[1];
                if ($version > $currentVersion) {
                    try {
                        $sql = (string)file_get_contents($migrationsDir . '/' . $file);
                        $this->executeMigration($prefix, $version, $sql);
                        $stats['applied']++;
                    } catch (\Throwable $e) {
                        $stats['errors'][] = [
                            'version' => $version,
                            'file'    => $file,
                            'error'   => $e->getMessage()
                        ];
                        $stats['success'] = false;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Execute a single migration SQL with proper prefixing.
     */
    private function executeMigration(string $prefix, int $version, string $sql): void
    {
        $pdo = $this->db->getPdo();
        $migTbl = $prefix . 'migrations';

        // Apply robust prefixing
        $prefixedSql = $this->prefixTableNames($sql, $prefix);
        $statements  = $this->splitStatements($prefixedSql);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || $stmt === ';') continue;
            
            try {
                $pdo->exec($stmt);
            } catch (\PDOException $e) {
                // Ignore safe errors (already exists etc.)
                $code = (int)($e->errorInfo[1] ?? 0);
                if (!in_array($code, [1050, 1060, 1061, 1062, 1068], true) && $e->getCode() !== '42S01') {
                     throw $e;
                }
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Mark as applied
        $pdo->prepare("INSERT IGNORE INTO `{$migTbl}` (version, applied_at) VALUES (?, NOW())")
            ->execute([$version]);
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

    /**
     * Robust table name prefixing logic.
     * Ported from DataMigrationController for maximum compatibility.
     */
    private function prefixTableNames(string $sql, string $prefix): string
    {
        $placeholders = [];
        $idx = 0;
        
        // 1. Placeholder SQL functions (avoid prefixing keywords that look like functions)
        $sql = preg_replace_callback(
            '/\b(current_timestamp|current_date|current_time|current_user|utc_timestamp|utc_date|utc_time|localtime|localtimestamp|sysdate)(\s*\(\s*\))?/i',
            function ($m) use (&$placeholders, &$idx) {
                $key = '__SQLFN_' . ($idx++) . '__';
                $placeholders[$key] = $m[0];
                return $key;
            },
            $sql
        );
        $sql = preg_replace_callback('/\b(now|uuid)\s*\(\s*\)/i', function ($m) use (&$placeholders, &$idx) {
            $key = '__SQLFN_' . ($idx++) . '__';
            $placeholders[$key] = $m[0];
            return $key;
        }, $sql);

        $addPrefix = function($name) use ($prefix) {
            // Check if it's a global table - if so, don't prefix
            if (in_array(strtolower($name), self::GLOBAL_TABLES)) {
                return $name;
            }
            return str_starts_with($name, $prefix) ? $name : $prefix . $name;
        };

        // 2. Prefix DDL/DML statements
        // CREATE TABLE / DROP TABLE
        $sql = preg_replace_callback('/\b(CREATE|DROP)\s+TABLE\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?`([^`]+)`/i', fn($m) => str_replace('`'.$m[2].'`', '`'.$addPrefix($m[2]).'`', $m[0]), $sql);
        
        // INSERT INTO / REPLACE INTO
        $sql = preg_replace_callback('/\b(INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s+`([^`]+)`/i', fn($m) => str_replace('`'.$m[2].'`', '`'.$addPrefix($m[2]).'`', $m[0]), $sql);
        
        // UPDATE
        $sql = preg_replace_callback('/(?<!\w)UPDATE\s+`([^`]+)`/i', fn($m) => str_replace('`'.$m[1].'`', '`'.$addPrefix($m[1]).'`', $m[0]), $sql);
        
        // DELETE FROM / TRUNCATE TABLE
        $sql = preg_replace_callback('/\bDELETE\s+FROM\s+`([^`]+)`/i', fn($m) => str_replace('`'.$m[1].'`', '`'.$addPrefix($m[1]).'`', $m[0]), $sql);
        $sql = preg_replace_callback('/\bTRUNCATE\s+TABLE\s+`([^`]+)`/i', fn($m) => str_replace('`'.$m[1].'`', '`'.$addPrefix($m[1]).'`', $m[0]), $sql);
        
        // ALTER TABLE
        $sql = preg_replace_callback('/\bALTER\s+TABLE\s+`([^`]+)`/i', fn($m) => str_replace('`'.$m[1].'`', '`'.$addPrefix($m[1]).'`', $m[0]), $sql);
        
        // JOIN / FROM patterns (for complex migrations)
        $sql = preg_replace_callback('/\b(FROM|JOIN)\s+`([^`]+)`/i', function($m) use ($addPrefix) {
            // Avoid prefixing if it looks like a subquery or keyword
            return $m[1] . ' `' . $addPrefix($m[2]) . '`';
        }, $sql);

        // REFERENCES
        $sql = preg_replace_callback('/\bREFERENCES\s+`([^`]+)`/i', fn($m) => str_replace('`'.$m[1].'`', '`'.$addPrefix($m[1]).'`', $m[0]), $sql);
        
        // Constraints
        $sql = preg_replace_callback('/\bCONSTRAINT\s+`([^`]+)`/i', fn($m) => 'CONSTRAINT `' . $prefix . $m[1] . '`', $sql);

        return strtr($sql, $placeholders);
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
            if ($inString) { $current .= $c; if ($c === '\\') { if ($i + 1 < $len) $current .= $sql[++$i]; } elseif ($c === $stringChar) $inString = false; continue; }
            if ($c === '`') { $inBacktick = true; $current .= $c; }
            elseif ($c === '"' || $c === "'") { $inString = true; $stringChar = $c; $current .= $c; }
            elseif ($c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') { while ($i < $len && $sql[$i] !== "\n") $i++; }
            elseif ($c === '#') { while ($i < $len && $sql[$i] !== "\n") $i++; }
            elseif ($c === '/' && $i + 1 < $len && $sql[$i + 1] === '*') { $i += 2; while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) $i++; $i += 2; }
            elseif ($c === ';') { $stmt = trim($current); if ($stmt !== '') $statements[] = $stmt; $current = ''; }
            else { $current .= $c; }
        }
        $stmt = trim($current); if ($stmt !== '') $statements[] = $stmt;
        return $statements;
    }
}
