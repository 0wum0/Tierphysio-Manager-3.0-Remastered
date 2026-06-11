-- Migration 072: Repair gdpr_consent columns on owners table
-- Migration 032 used PREPARE/EXECUTE which may have silently failed on some tenants.
-- This repair migration adds the columns directly — MigrationService tolerates
-- error 1060 (Duplicate column) so running this on tenants that already have the
-- columns is safe.

ALTER TABLE `{{prefix}}owners`
    ADD COLUMN `gdpr_consent`    TINYINT(1) NOT NULL DEFAULT 0 AFTER `notes`;

ALTER TABLE `{{prefix}}owners`
    ADD COLUMN `gdpr_consent_at` DATETIME NULL AFTER `gdpr_consent`;

ALTER TABLE `{{prefix}}owners`
    ADD INDEX `idx_gdpr_consent` (`gdpr_consent`);
