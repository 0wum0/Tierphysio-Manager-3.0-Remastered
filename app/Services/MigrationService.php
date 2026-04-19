<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

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
        private readonly Database $db
    ) {}

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    public function getCurrentVersion(): int
    {
        try {
            $result = $this->db->fetchColumn(
                "SELECT value FROM `{$this->t('settings')}` WHERE `key` = 'db_version'"
            );
            return $result !== false ? (int)$result : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function getLatestVersion(): int
    {
        $files = $this->getMigrationFiles();
        if (empty($files)) return 0;
        $last = end($files);
        preg_match('/^(\d+)/', basename($last), $m);
        return isset($m[1]) ? (int)$m[1] : 0;
    }

    public function forceSync(): array
    {
        // 1. Reset version to 0 to make all migrations "pending"
        $this->setVersion(0);
        
        // 2. Run all migrations (our engine handles IF NOT EXISTS/IGNORE)
        return $this->runPending();
    }

    public function getPendingMigrations(): array
    {
        $current = $this->getCurrentVersion();
        $pending = [];

        foreach ($this->getMigrationFiles() as $file) {
            preg_match('/^(\d+)/', basename($file), $m);
            $version = isset($m[1]) ? (int)$m[1] : 0;
            if ($version > $current) {
                $pending[] = [
                    'version' => $version,
                    'file'    => basename($file),
                    'path'    => $file,
                ];
            }
        }

        return $pending;
    }

    public function runPending(): array
    {
        $pending = $this->getPendingMigrations();
        $ran     = [];

        foreach ($pending as $migration) {
            $this->runMigration($migration['path']);
            $this->setVersion($migration['version']);
            $ran[] = $migration['file'];
        }

        return $ran;
    }

    private function runMigration(string $file): void
    {
        $sql = file_get_contents($file);
        $sql = $this->applyPrefixToSql($sql);

        $statements = $this->splitStatements($sql);

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $this->db->execute($statement);
                } catch (\Throwable $e) {
                    /* Silently ignore structural-already-exists / missing-object errors
                     * so migrations are idempotent across tenants and re-runs.
                     * 1050 = Table already exists
                     * 1060 = Duplicate column name
                     * 1061 = Duplicate key name
                     * 1072 = Key column doesn't exist (ADD INDEX on absent column)
                     * 1091 = Can't DROP key; check that it exists
                     * 1146 = Table doesn't exist (ALTER on absent table)
                     * 1215 = Cannot add foreign key constraint
                     */
                    $errno = 0;
                    if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
                        $errno = (int)$e->errorInfo[1];
                    }
                    if (!in_array($errno, [1050, 1060, 1061, 1062, 1072, 1091, 1146, 1215], true)) {
                        /* 1054 (Unknown column) in idempotenten INSERTs tolerieren:
                         * Altbestand-Tenants haben manchmal schlankere Schemas;
                         * Self-Healing-Migrationen (INSERT IGNORE ...) sollen
                         * nicht die ganze Migration zum Scheitern bringen. */
                        if ($errno === 1054 && preg_match('/^\s*INSERT\s+IGNORE\b/i', $statement)) {
                            error_log('[MigrationService] tolerating 1054 on INSERT IGNORE: ' . $e->getMessage());
                            continue;
                        }
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * Robuster SQL-Statement-Splitter.
     *
     * Ersetzt das bisherige `explode(';', $sql)`, das Semikola in Kommentaren
     * (`-- comment; more comment`) und in String-Literalen zerriss. Der Splitter
     * versteht Line-Comments (`-- …`, `# …`), Block-Comments (`/* … */`),
     * Single-/Double-Quoted-Strings und Backtick-Identifier.
     *
     * @return array<int, string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer     = '';
        $len        = strlen($sql);
        $state      = 'normal';
        $i          = 0;

        while ($i < $len) {
            $ch   = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            switch ($state) {
                case 'normal':
                    /* Line-Comment -- … oder # … */
                    if (($ch === '-' && $next === '-') || $ch === '#') {
                        $state = 'lc';
                        $i += ($ch === '#') ? 1 : 2;
                        break;
                    }
                    /* Block-Comment /* … *\/ */
                    if ($ch === '/' && $next === '*') {
                        $state = 'bc';
                        $i += 2;
                        break;
                    }
                    /* String-Literale: Semikola DARIN sind keine Statement-Grenzen */
                    if ($ch === "'" || $ch === '"') {
                        $state    = ($ch === "'") ? 'sq' : 'dq';
                        $buffer  .= $ch;
                        $i++;
                        break;
                    }
                    /* Backtick-Identifier — enthält eigentlich nie ';', aber sicher ist sicher */
                    if ($ch === '`') {
                        $state   = 'bt';
                        $buffer .= $ch;
                        $i++;
                        break;
                    }
                    /* Statement-Ende */
                    if ($ch === ';') {
                        $trimmed = trim($buffer);
                        if ($trimmed !== '') {
                            $statements[] = $trimmed;
                        }
                        $buffer = '';
                        $i++;
                        break;
                    }
                    $buffer .= $ch;
                    $i++;
                    break;

                case 'lc':
                    /* Line-Comment endet am nächsten \n */
                    if ($ch === "\n") {
                        $state   = 'normal';
                        $buffer .= ' '; // Whitespace erhalten, Tokens trennen
                    }
                    $i++;
                    break;

                case 'bc':
                    if ($ch === '*' && $next === '/') {
                        $state   = 'normal';
                        $buffer .= ' ';
                        $i += 2;
                        break;
                    }
                    $i++;
                    break;

                case 'sq':
                case 'dq':
                    $buffer .= $ch;
                    $quote   = ($state === 'sq') ? "'" : '"';
                    /* Backslash-Escape (MySQL-Default) */
                    if ($ch === '\\' && $next !== '') {
                        $buffer .= $next;
                        $i += 2;
                        break;
                    }
                    /* Verdoppeltes Anführungszeichen = Escape */
                    if ($ch === $quote && $next === $quote) {
                        $buffer .= $next;
                        $i += 2;
                        break;
                    }
                    if ($ch === $quote) {
                        $state = 'normal';
                    }
                    $i++;
                    break;

                case 'bt':
                    $buffer .= $ch;
                    if ($ch === '`') {
                        $state = 'normal';
                    }
                    $i++;
                    break;
            }
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    /**
     * Ersetzt in einem SQL-String Tabellennamen mit dem Tenant-Prefix.
     * Schützt globale Tabellen vor dem Prefixing.
     */
    private function applyPrefixToSql(string $sql): string
    {
        $prefix = $this->db->prefix('');
        if ($prefix === '') {
            return $sql;
        }

        /* 1. Constraint-Namen prefixen: CONSTRAINT `fk_xyz` → CONSTRAINT `{prefix}fk_xyz` */
        $sql = preg_replace_callback(
            '/\bCONSTRAINT\s+`([^`]+)`/i',
            fn($m) => 'CONSTRAINT `' . $prefix . $m[1] . '`',
            $sql
        );

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
        
        // JOIN / FROM patterns
        $sql = preg_replace_callback('/\b(FROM|JOIN)\s+`([^`]+)`/i', function($m) use ($addPrefix) {
            return $m[1] . ' `' . $addPrefix($m[2]) . '`';
        }, $sql);

        // REFERENCES
        $sql = preg_replace_callback('/\bREFERENCES\s+`([^`]+)`/i', fn($m) => str_replace('`'.$m[1].'`', '`'.$addPrefix($m[1]).'`', $m[0]), $sql);

        return $sql;
    }

    private function setVersion(int $version): void
    {
        $this->db->execute(
            "INSERT INTO `{$this->t('settings')}` (`key`, value) VALUES ('db_version', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [(string)$version]
        );
    }

    private function getMigrationFiles(): array
    {
        $files = glob(MIGRATIONS_PATH . '/*.sql');
        if (!$files) return [];
        sort($files);
        return $files;
    }
}
