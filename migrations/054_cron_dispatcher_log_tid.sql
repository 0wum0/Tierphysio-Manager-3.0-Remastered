-- Migration 054: cron_dispatcher_log um tenant-ID erweitern
-- Damit der Cron-Monitor erkennt, welcher Tenant bei welchem Job verarbeitet wurde.

ALTER TABLE `cron_dispatcher_log`
    ADD COLUMN IF NOT EXISTS `tid` VARCHAR(120) NULL DEFAULT NULL
        COMMENT 'Tenant-ID aus ?tid= Parameter' AFTER `job_key`,
    ADD COLUMN IF NOT EXISTS `http_code` SMALLINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'HTTP-Statuscode des Job-Endpunkts' AFTER `duration_ms`,
    ADD COLUMN IF NOT EXISTS `response_excerpt` TEXT NULL DEFAULT NULL
        COMMENT 'Erste 1000 Zeichen der Job-Antwort (JSON)' AFTER `http_code`;

ALTER TABLE `cron_dispatcher_log`
    MODIFY COLUMN `status` ENUM('success','error','skipped','partial_error') NOT NULL DEFAULT 'success';

ALTER TABLE `cron_dispatcher_log`
    ADD INDEX IF NOT EXISTS `idx_tid` (`tid`);
