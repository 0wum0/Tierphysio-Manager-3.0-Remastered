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
            'success'  => true,
            'ran_count' => 0
        ];

        if ($currentVersion >= $latestVersion) {
            return $stats;
        }

        // Use root migrations folder
        $migrationsDir = dirname($this->config->getRootPath()) . '/migrations';
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
                        $stats['ran_count']++;
                    } catch (\Throwable $e) {
                        $stats['errors'][] = [
                            'version' => $version,
                            'file'    => $file,
                            'error'   => $e->getMessage()
                        ];
                        $stats['success'] = false;
                        break; // Stop on error
                    }
                }
            }
        }

        // Fallback: Also update settings table if it exists
        if ($stats['success']) {
            try {
                $pdo->prepare("UPDATE `{$prefix}settings` SET `value` = ? WHERE `key` = 'db_version'")
                    ->execute([$latestVersion]);
            } catch (\Throwable) {}
        }

        return $stats;
    }

    /**
     * FORCE SYNC: Resets version to 0 and runs ALL migrations.
     * This is used for "Repair" functionality.
     */
    public function forceSyncTenant(string $prefix): array
    {
        $pdo = $this->db->getPdo();
        $migTbl = $prefix . 'migrations';
        $setTbl = $prefix . 'settings';

        try {
            // Reset version in both places
            $pdo->exec("DELETE FROM `{$migTbl}`");
            try {
                $pdo->exec("UPDATE `{$setTbl}` SET `value` = '0' WHERE `key` = 'db_version'");
            } catch (\Throwable) {}
            
            // Re-run everything
            return $this->migrateTenant($prefix);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Reset failed: ' . $e->getMessage()
            ];
        }
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
                // Ignore safe errors (already exists, duplicate column, etc.)
                $code = (int)($e->errorInfo[1] ?? 0);
                if (!in_array($code, [1050, 1060, 1061, 1062, 1068, 1091, 1054], true) && $e->getCode() !== '42S01') {
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
     */
    private function prefixTableNames(string $sql, string $prefix): string
    {
        $addPrefix = function($name) use ($prefix) {
            if (in_array(strtolower($name), self::GLOBAL_TABLES)) return $name;
            return str_starts_with($name, $prefix) ? $name : $prefix . $name;
        };

        // Prefixing logic
        $sql = preg_replace_callback('/\b(CREATE|DROP|ALTER)\s+TABLE\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?[`"]?([^`"\s]+)[`"]?/i', fn($m) => str_replace($m[2], $addPrefix($m[2]), $m[0]), $sql);
        $sql = preg_replace_callback('/\b(INSERT|REPLACE|UPDATE|DELETE\s+FROM|TRUNCATE\s+TABLE)\s+(?:IGNORE\s+)?(?:INTO\s+)?[`"]?([^`"\s]+)[`"]?/i', fn($m) => str_replace($m[2], $addPrefix($m[2]), $m[0]), $sql);
        $sql = preg_replace_callback('/\b(FROM|JOIN|REFERENCES)\s+[`"]?([^`"\s]+)[`"]?/i', fn($m) => str_replace($m[2], $addPrefix($m[2]), $m[0]), $sql);
        $sql = preg_replace_callback('/\bCONSTRAINT\s+[`"]?([^`"\s]+)[`"]?/i', fn($m) => 'CONSTRAINT `' . $prefix . $m[1] . '`', $sql);

        return $sql;
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
