-- Migration 030: Add custom content to vet_reports
ALTER TABLE `vet_reports` ADD COLUMN `type`    ENUM('auto', 'custom') NOT NULL DEFAULT 'auto' AFTER `created_by`;
ALTER TABLE `vet_reports` ADD COLUMN `title`   VARCHAR(255) NULL AFTER `type`;
ALTER TABLE `vet_reports` ADD COLUMN `content` TEXT NULL AFTER `title`;
