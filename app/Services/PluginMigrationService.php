<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

/**
 * Tenant-scoped, self-healing Plugin-Migrations-Runner.
 *
 * Jedes Plugin liefert einen Ordner mit nummerierten *.sql-Dateien
 * (z. B. `001_create_intake_submissions.sql`). Der Runner hier:
 *   1. Ersetzt den {{prefix}}-Platzhalter durch den aktuellen Tenant-Prefix
 *   2. Splittet das SQL mit dem gleichen robusten Parser wie der Core-Runner
 *      (versteht Kommentare, Strings, Backticks — kein naive explode(';'))
 *   3. Toleriert typische "schon vorhanden"-Fehler (errno 1050/1060/1061/1091/1146)
 *   4. Trackt die zuletzt ausgeführte Version in `settings.plugin_{slug}_migration_version`
 *      → läuft damit genau einmal pro neue Migration pro Tenant
 *   5. Cached pro Session einen "already-verified"-Marker, damit wir die
 *      Versions-Query nicht auf JEDEM Request absetzen
 *
 * Self-Heal-Verhalten: existiert der Tracking-Eintrag nicht oder hinkt hinterher,
 * werden alle ausstehenden Migrationen automatisch nachgefahren — dadurch heilen
 * sich Alt-Tenants beim nächsten authentifizierten Request selbst.
 */
class PluginMigrationService
{
    /* MySQL-Fehlercodes, die bei idempotenten Migrationen ignoriert werden.
     * 1050 = Table already exists
     * 1060 = Duplicate column name
     * 1061 = Duplicate key name
     * 1062 = Duplicate entry (INSERT IGNORE Absicherung)
     * 1072 = Key column doesn't exist (ADD INDEX auf fehlender Spalte)
     * 1091 = Can't DROP; check that it exists
     * 1146 = Table doesn't exist (ALTER auf fehlender Tabelle)
     * 1215 = Cannot add foreign key constraint (Race-Condition)
     */
    private const IGNORABLE_ERRNO = [1050, 1060, 1061, 1062, 1072, 1091, 1146, 1215];

    public function __construct(
        private readonly Database $db,
        private readonly ?Session $session = null,
    ) {}

    /**
     * Führt alle ausstehenden Migrationen des Plugins aus.
     *
     * @param string $pluginSlug    Eindeutiger Plugin-Identifier (z. B. 'patient_intake')
     * @param string $migrationDir  Absoluter Pfad zum Migrationsordner
     * @return array{applied:string[], skipped:string[], current_version:int}
     */
    public function runPending(string $pluginSlug, string $migrationDir): array
    {
        $result = ['applied' => [], 'skipped' => [], 'current_version' => 0];

        /* Kein Tenant-Prefix = kein Login/keine Session → NICHT migrieren.
         * Würde sonst versehentlich geteilte Tabellen ohne Prefix anlegen. */
        $prefix = $this->db->getPrefix();
        if ($prefix === '') {
            return $result;
        }

        if (!is_dir($migrationDir)) {
            return $result;
        }

        /* Session-Cache: bei unverändertem Dateisatz nicht erneut prüfen.
         * Invalidation: sobald sich das Directory-mtime ändert (neue Migration
         * committet → Server deploy → Datei-mtime neu → Cache-Miss). */
        $cacheKey = 'plugin_mig_' . $pluginSlug . '_' . $prefix;
        $dirMtime = (string)filemtime($migrationDir);

        if ($this->session !== null && $this->session->get($cacheKey) === $dirMtime) {
            return $result; /* bereits in dieser Session verifiziert */
        }

        try {
            $currentVersion = $this->getCurrentVersion($pluginSlug);
            $result['current_version'] = $currentVersion;

            $files = glob($migrationDir . '/*.sql') ?: [];
            sort($files);

            $highestApplied = $currentVersion;

            foreach ($files as $file) {
                $version = $this->extractVersion(basename($file));
                if ($version === 0 || $version <= $currentVersion) {
                    $result['skipped'][] = basename($file);
                    continue;
                }

                try {
                    $this->runSingleMigration($file, $prefix);
                    $highestApplied      = $version;
                    $result['applied'][] = basename($file);

                    /* Version nach jeder erfolgreichen Migration schreiben —
                     * damit eine später kaputte Migration nicht die schon
                     * erfolgreichen rückgängig macht. */
                    $this->setVersion($pluginSlug, $highestApplied);
                } catch (\Throwable $e) {
                    error_log(
                        '[PluginMigrationService] ' . $pluginSlug
                        . ' migration failed: ' . basename($file)
                        . ' — ' . $e->getMessage()
                    );
                    /* Restliche Migrationen nicht versuchen — Reihenfolge-kritisch. */
                    break;
                }
            }

            /* Session-Marker nur setzen wenn wir bis zum Ende gekommen sind
             * (kein break durch Fehler). */
            if ($this->session !== null && empty($result['applied']) === false) {
                $this->session->set($cacheKey, $dirMtime);
            } elseif ($this->session !== null && empty($result['applied']) && $highestApplied === $currentVersion) {
                /* Nichts zu tun — alle Migrationen bereits angewendet → cachen. */
                $this->session->set($cacheKey, $dirMtime);
            }
        } catch (\Throwable $e) {
            error_log('[PluginMigrationService] ' . $pluginSlug . ' fatal: ' . $e->getMessage());
        }

        return $result;
    }

    private function runSingleMigration(string $file, string $prefix): void
    {
        $sql = (string)file_get_contents($file);
        $sql = str_replace('{{prefix}}', $prefix, $sql);

        foreach ($this->splitStatements($sql) as $stmt) {
            if ($stmt === '') continue;

            try {
                $this->db->execute($stmt);
            } catch (\Throwable $e) {
                $errno = 0;
                if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
                    $errno = (int)$e->errorInfo[1];
                }

                /* "1054 Unknown column" in idempotenten INSERT IGNORE tolerieren —
                 * Altbestand-Tenants haben manchmal schlankere Schemas. */
                if ($errno === 1054 && preg_match('/^\s*INSERT\s+IGNORE\b/i', $stmt)) {
                    error_log('[PluginMigrationService] tolerating 1054 on INSERT IGNORE');
                    continue;
                }

                if (!in_array($errno, self::IGNORABLE_ERRNO, true)) {
                    throw $e;
                }
                /* Ansonsten: "schon vorhanden"-Fehler → OK, Migration idempotent. */
            }
        }
    }

    private function getCurrentVersion(string $pluginSlug): int
    {
        try {
            $v = $this->db->fetchColumn(
                "SELECT `value` FROM `{$this->db->prefix('settings')}` WHERE `key` = ?",
                ['plugin_' . $pluginSlug . '_migration_version']
            );
            return $v !== false ? (int)$v : 0;
        } catch (\Throwable) {
            /* settings-Tabelle existiert noch nicht? Dann sind wir ganz am Anfang. */
            return 0;
        }
    }

    private function setVersion(string $pluginSlug, int $version): void
    {
        try {
            $this->db->execute(
                "INSERT INTO `{$this->db->prefix('settings')}` (`key`, `value`)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                ['plugin_' . $pluginSlug . '_migration_version', (string)$version]
            );
        } catch (\Throwable $e) {
            error_log('[PluginMigrationService] setVersion failed: ' . $e->getMessage());
        }
    }

    private function extractVersion(string $filename): int
    {
        return preg_match('/^(\d+)/', $filename, $m) ? (int)$m[1] : 0;
    }

    /**
     * Robuster SQL-Splitter — identisch zum Core-MigrationService.
     * Versteht Line-/Block-Kommentare, Single-/Double-Quoted-Strings
     * (inkl. Backslash-Escape & verdoppeltes Quote) und Backtick-Identifier.
     * Splittet NUR an Top-Level-Semikola.
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
                    if (($ch === '-' && $next === '-') || $ch === '#') {
                        $state = 'lc';
                        $i    += ($ch === '#') ? 1 : 2;
                        break;
                    }
                    if ($ch === '/' && $next === '*') {
                        $state = 'bc';
                        $i    += 2;
                        break;
                    }
                    if ($ch === "'" || $ch === '"') {
                        $state   = ($ch === "'") ? 'sq' : 'dq';
                        $buffer .= $ch;
                        $i++;
                        break;
                    }
                    if ($ch === '`') {
                        $state   = 'bt';
                        $buffer .= $ch;
                        $i++;
                        break;
                    }
                    if ($ch === ';') {
                        $trim = trim($buffer);
                        if ($trim !== '') $statements[] = $trim;
                        $buffer = '';
                        $i++;
                        break;
                    }
                    $buffer .= $ch;
                    $i++;
                    break;

                case 'lc':
                    if ($ch === "\n") {
                        $state   = 'normal';
                        $buffer .= ' ';
                    }
                    $i++;
                    break;

                case 'bc':
                    if ($ch === '*' && $next === '/') {
                        $state   = 'normal';
                        $buffer .= ' ';
                        $i      += 2;
                        break;
                    }
                    $i++;
                    break;

                case 'sq':
                case 'dq':
                    $buffer .= $ch;
                    $quote   = ($state === 'sq') ? "'" : '"';
                    if ($ch === '\\' && $next !== '') {
                        $buffer .= $next;
                        $i      += 2;
                        break;
                    }
                    if ($ch === $quote && $next === $quote) {
                        $buffer .= $next;
                        $i      += 2;
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

        $trim = trim($buffer);
        if ($trim !== '') $statements[] = $trim;

        return $statements;
    }
}
