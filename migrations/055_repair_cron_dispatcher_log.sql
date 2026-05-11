-- Migration 055: Repair cron_dispatcher_log — fehlende Spalten nachziehen
--
-- HINTERGRUND:
-- Migration 054 wurde fälschlicherweise in die bestehende v54-Datei geschrieben.
-- Tenants, die bereits auf Version >= 54 sind, haben diese Änderungen nie erhalten.
-- Diese Migration (v055) zieht die fehlenden Strukturen idempotent nach.
--
-- Das MigrationService ignoriert MySQL-Fehler 1060 (Duplicate column name),
-- daher sind ADD COLUMN ohne IF NOT EXISTS sicher mehrfach ausführbar.
--
-- Folgende Spalten werden hinzugefügt (falls noch nicht vorhanden):
--   - tid             VARCHAR(120) NULL
--   - http_code       SMALLINT UNSIGNED NULL
--   - response_excerpt TEXT NULL
--
-- Außerdem: ENUM-Erweiterung um 'partial_error' und neuer Index idx_tid.

-- Schritt 1: Neue Spalten hinzufügen
-- Fehler 1060 (Duplicate column) wird vom MigrationService toleriert → idempotent.

ALTER TABLE `cron_dispatcher_log`
    ADD COLUMN `tid` VARCHAR(120) NULL DEFAULT NULL
        COMMENT 'Tenant-ID aus ?tid= Parameter' AFTER `job_key`;

ALTER TABLE `cron_dispatcher_log`
    ADD COLUMN `http_code` SMALLINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'HTTP-Statuscode des Job-Endpunkts' AFTER `duration_ms`;

ALTER TABLE `cron_dispatcher_log`
    ADD COLUMN `response_excerpt` TEXT NULL DEFAULT NULL
        COMMENT 'Erste 1000 Zeichen der Job-Antwort (JSON)' AFTER `http_code`;

-- Schritt 2: ENUM erweitern um 'partial_error'
-- MODIFY COLUMN ist idempotent bei gleicher Definition.
-- Fehler 1060 oder bei falschem ENUM-Wert wird toleriert (MigrationService ignoriert 1060).

ALTER TABLE `cron_dispatcher_log`
    MODIFY COLUMN `status` ENUM('success','error','skipped','partial_error') NOT NULL DEFAULT 'success';

-- Schritt 3: Index für tid (schnellere Abfragen nach Tenant)
-- Fehler 1061 (Duplicate key name) wird vom MigrationService toleriert → idempotent.

ALTER TABLE `cron_dispatcher_log`
    ADD INDEX `idx_tid` (`tid`);
