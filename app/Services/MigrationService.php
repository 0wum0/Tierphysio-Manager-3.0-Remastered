<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class MigrationService
{
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

        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $this->db->execute($statement);
                } catch (\Throwable $e) {
                    /* Silently ignore duplicate key / already-exists errors so
                     * migrations that add indexes or columns are idempotent.
                     * MySQL errno 1061 = Duplicate key name
                     * MySQL errno 1060 = Duplicate column name
                     * MySQL errno 1050 = Table already exists
                     */
                    $errno = 0;
                    if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
                        $errno = (int)$e->errorInfo[1];
                    }
                    if (!in_array($errno, [1050, 1060, 1061], true)) {
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * Ersetzt in einem SQL-String nur bekannte Tabellennamen (Whitelist) mit dem
     * Tenant-Prefix sowie CONSTRAINT-Namen, damit bei Multi-Tenant keine
     * doppelten Foreign-Key-Namen (errno 121) entstehen.
     *
     * WICHTIG: Niemals blind alle Backtick-Identifier prefixen — das würde auch
     * Spaltennamen wie `key`, `value`, `name` usw. kaputt machen.
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

        /* 2. Nur bekannte Tabellennamen prefixen */
        $tables = [
            'users','settings','owners','patients','appointments','appointment_waitlist',
            'invoices','invoice_items','invoice_positions','invoice_reminders','invoice_dunnings',
            'waitlist','user_preferences','migrations',
            'patient_timeline','treatment_types',
            'mobile_api_tokens','cron_job_log',
            'befundboegen','befundbogen_felder',
            'vet_reports',
            'gdpr_consents',
            'invoice_cancellations',
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
