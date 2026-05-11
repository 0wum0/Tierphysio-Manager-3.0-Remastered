-- Migration 062: Repair cron_dispatcher_log — fehlende Spalten nachziehen
--
-- KONTEXT:
-- Migrationen 054 + 055 haben diese Spalten definiert, aber alle Tenants
-- waren bereits auf v61 → 54 > 61 = false, 55 > 61 = false → nie ausgeführt.
-- Diese Migration (v062) hat version > 61 und wird daher ausgeführt.
--
-- Der MigrationService toleriert MySQL-Fehler 1060 (Duplicate column name)
-- und 1061 (Duplicate key name) → Migration ist mehrfach ausführbar ohne Crash.
-- KEIN ADD COLUMN IF NOT EXISTS (MySQL 8.0.3+ only, nicht universell).

-- Spalte: tid (Tenant-ID aus ?tid= Parameter)
ALTER TABLE `cron_dispatcher_log`
    ADD COLUMN `tid` VARCHAR(120) NULL DEFAULT NULL
        COMMENT 'Tenant-ID aus ?tid= Parameter' AFTER `job_key`;

-- Spalte: http_code (HTTP-Statuscode des Job-Endpunkts)
ALTER TABLE `cron_dispatcher_log`
    ADD COLUMN `http_code` SMALLINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'HTTP-Statuscode des Job-Endpunkts' AFTER `duration_ms`;

-- Spalte: response_excerpt (Erste 1000 Zeichen der Job-Antwort)
ALTER TABLE `cron_dispatcher_log`
    ADD COLUMN `response_excerpt` TEXT NULL DEFAULT NULL
        COMMENT 'Erste 1000 Zeichen der Job-Antwort (JSON)' AFTER `http_code`;

-- ENUM erweitern um 'partial_error'
ALTER TABLE `cron_dispatcher_log`
    MODIFY COLUMN `status` ENUM('success','error','skipped','partial_error') NOT NULL DEFAULT 'success';

-- Index auf tid (Fehler 1061 = Duplicate key wird toleriert)
ALTER TABLE `cron_dispatcher_log`
    ADD INDEX `idx_tid` (`tid`);
