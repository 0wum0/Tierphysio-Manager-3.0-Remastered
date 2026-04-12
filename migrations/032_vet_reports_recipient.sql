-- Migration 032: Persist recipient field for custom vet reports
-- Skips gracefully if vet_reports table does not exist (plugin not enabled for tenant)
ALTER TABLE `vet_reports`
    ADD COLUMN `recipient` VARCHAR(500) NULL;
