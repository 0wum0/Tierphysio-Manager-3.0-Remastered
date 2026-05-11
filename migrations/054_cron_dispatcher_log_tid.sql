-- Migration 054: cron_dispatcher_log um tenant-ID erweitern
-- Damit der Cron-Monitor erkennt, welcher Tenant bei welchem Job verarbeitet wurde.
--
-- HINWEIS: Diese Migration ist für neue Tenants (v < 54).
-- Für bestehende Tenants (v >= 54) zieht Migration 055 die Änderungen nach.
-- Das MigrationService toleriert 1060 (Duplicate column) + 1061 (Duplicate key) → idempotent.

ALTER TABLE `cron_dispatcher_log`
    ADD COLUMN `tid` VARCHAR(120) NULL DEFAULT NULL
        COMMENT 'Tenant-ID aus ?tid= Parameter' AFTER `job_key`;

ALTER TABLE `cron_dispatcher_log`
    ADD COLUMN `http_code` SMALLINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'HTTP-Statuscode des Job-Endpunkts' AFTER `duration_ms`;

ALTER TABLE `cron_dispatcher_log`
    ADD COLUMN `response_excerpt` TEXT NULL DEFAULT NULL
        COMMENT 'Erste 1000 Zeichen der Job-Antwort (JSON)' AFTER `http_code`;

ALTER TABLE `cron_dispatcher_log`
    MODIFY COLUMN `status` ENUM('success','error','skipped','partial_error') NOT NULL DEFAULT 'success';

ALTER TABLE `cron_dispatcher_log`
    ADD INDEX `idx_tid` (`tid`);
