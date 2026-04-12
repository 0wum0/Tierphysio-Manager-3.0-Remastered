-- Migration 032: Persist recipient field for custom vet reports
ALTER TABLE `vet_reports`
    ADD COLUMN IF NOT EXISTS `recipient` VARCHAR(500) NULL AFTER `content`;
