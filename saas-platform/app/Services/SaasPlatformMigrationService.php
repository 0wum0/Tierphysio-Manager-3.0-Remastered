<?php

declare(strict_types=1);

namespace Saas\Services;

use PDO;
use Saas\Core\Database;

/**
 * SaasPlatformMigrationService
 * ─────────────────────────────
 * Runs and tracks migrations for the GLOBAL SaaS database
 * (tables: plans, subscriptions, subscription_events, tenants, …).
 *
 * Files live in /saas-platform/saas-migrations/*.sql
 * Applied versions are tracked in the `saas_migrations` table.
 *
 * Unlike MigrationService (which prefixes table names per-tenant),
 * this service runs SQL directly with NO prefix substitution.
 */
class SaasPlatformMigrationService
{
    private string $migrationsDir;

    public function __construct(
        private readonly Database $db
    ) {
        $this->migrationsDir = dirname(__DIR__, 2) . '/saas-migrations';
    }

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Ensure the tracking table exists, then run all pending migrations.
     *
     * @return array{success: bool, ran: int, skipped: int, errors: list<string>, log: list<string>}
     */
    public function runPending(): array
    {
        $this->ensureTrackingTable();

        $applied = $this->getAppliedVersions();
        $files   = $this->scanMigrationFiles();

        $result = ['success' => true, 'ran' => 0, 'skipped' => 0, 'errors' => [], 'log' => []];

        foreach ($files as ['version' => $version, 'file' => $file]) {
            if (in_array($version, $applied, true)) {
                $result['skipped']++;
                continue;
            }

            $runResult = $this->executeFile($file, $version);
            $result['log'][] = basename($file) . ': ' . ($runResult['message'] ?? '?');

            if ($runResult['success']) {
                $result['ran']++;
            } else {
                $result['success'] = false;
                $result['errors']  = array_merge($result['errors'], $runResult['errors']);
                break;
            }
        }

        return $result;
    }

    /**
     * Returns how many migrations are pending (not yet applied).
     */
    public function pendingCount(): int
    {
        try {
            $this->ensureTrackingTable();
            $applied = $this->getAppliedVersions();
            $files   = $this->scanMigrationFiles();
            $pending = 0;
            foreach ($files as ['version' => $version]) {
                if (!in_array($version, $applied, true)) {
                    $pending++;
                }
            }
            return $pending;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Returns a status summary suitable for the admin UI.
     *
     * @return array{latest: int, applied: int, pending: int, files: list<array>}
     */
    public function status(): array
    {
        try {
            $this->ensureTrackingTable();
            $applied = $this->getAppliedVersions();
            $files   = $this->scanMigrationFiles();

            $rows = [];
            foreach ($files as ['version' => $v, 'file' => $f]) {
                $rows[] = [
                    'version'  => $v,
                    'filename' => basename($f),
                    'applied'  => in_array($v, $applied, true),
                ];
            }

            return [
                'latest'  => empty($files) ? 0 : max(array_column($files, 'version')),
                'applied' => count($applied),
                'pending' => count(array_filter($rows, fn($r) => !$r['applied'])),
                'files'   => $rows,
            ];
        } catch (\Throwable $e) {
            return ['latest' => 0, 'applied' => 0, 'pending' => 0, 'files' => [], 'error' => $e->getMessage()];
        }
    }

    // ── Internal ────────────────────────────────────────────────────────────

    private function ensureTrackingTable(): void
    {
        $pdo = $this->db->getPdo();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `saas_migrations` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `version`    INT          NOT NULL UNIQUE,
                `filename`   VARCHAR(200) NOT NULL DEFAULT '',
                `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /** @return list<int> */
    private function getAppliedVersions(): array
    {
        $pdo  = $this->db->getPdo();
        $rows = $pdo->query("SELECT version FROM `saas_migrations` ORDER BY version ASC")->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $rows ?: []);
    }

    /** @return list<array{version: int, file: string}> */
    private function scanMigrationFiles(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files = [];
        foreach (scandir($this->migrationsDir) ?: [] as $name) {
            if (!preg_match('/^(\d{3})_.*\.sql$/i', $name, $m)) {
                continue;
            }
            $files[] = ['version' => (int)$m[1], 'file' => $this->migrationsDir . '/' . $name];
        }
        usort($files, fn($a, $b) => $a['version'] <=> $b['version']);
        return $files;
    }

    /**
     * @return array{success: bool, message: string, errors: list<string>}
     */
    private function executeFile(string $file, int $version): array
    {
        $sql = @file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            $this->markApplied($version, basename($file));
            return ['success' => true, 'message' => 'empty / skipped', 'errors' => []];
        }

        $pdo        = $this->db->getPdo();
        $statements = $this->splitStatements($sql);
        $errors     = [];
        $ok         = 0;
        $skipped    = 0;

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || $stmt === ';') {
                continue;
            }
            if (preg_match('/^(--|#|\/\*|SET\s+NAMES)/i', $stmt)) {
                continue;
            }

            try {
                $pdo->exec($stmt);
                $ok++;
            } catch (\PDOException $e) {
                $code = (int)($e->errorInfo[1] ?? 0);

                // Idempotent errors → safe to skip
                if (in_array($code, [1050, 1060, 1061, 1062, 1068, 1091], true)) {
                    $skipped++;
                    continue;
                }

                $errors[] = 'SQL error ' . $code . ': ' . $e->getMessage()
                    . ' | Statement: ' . mb_substr($stmt, 0, 120);
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        if (empty($errors)) {
            $this->markApplied($version, basename($file));
            return [
                'success' => true,
                'message' => "{$ok} OK, {$skipped} skipped",
                'errors'  => [],
            ];
        }

        return [
            'success' => false,
            'message' => count($errors) . ' error(s)',
            'errors'  => $errors,
        ];
    }

    private function markApplied(int $version, string $filename): void
    {
        $pdo = $this->db->getPdo();
        $pdo->prepare(
            "INSERT IGNORE INTO `saas_migrations` (version, filename, applied_at) VALUES (?, ?, NOW())"
        )->execute([$version, $filename]);
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

            if ($inBacktick) {
                $current .= $c;
                if ($c === '`') $inBacktick = false;
                continue;
            }
            if ($inString) {
                $current .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $current .= $sql[++$i];
                } elseif ($c === $stringChar) {
                    $inString = false;
                }
                continue;
            }

            if ($c === '`') {
                $inBacktick = true;
                $current   .= $c;
            } elseif ($c === "'" || $c === '"') {
                $inString   = true;
                $stringChar = $c;
                $current   .= $c;
            } elseif ($c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
            } elseif ($c === '#') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
            } elseif ($c === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) $i++;
                $i += 2;
            } elseif ($c === ';') {
                $stmt = trim($current);
                if ($stmt !== '') $statements[] = $stmt;
                $current = '';
            } else {
                $current .= $c;
            }
        }

        $stmt = trim($current);
        if ($stmt !== '') $statements[] = $stmt;
        return $statements;
    }
}
