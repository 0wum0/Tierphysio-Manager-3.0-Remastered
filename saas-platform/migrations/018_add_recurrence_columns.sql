-- Add recurrence columns to appointments table
ALTER TABLE `appointments` ADD COLUMN `recurrence_rule` VARCHAR(512) NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `appointments` ADD COLUMN `recurrence_parent` INT UNSIGNED NULL DEFAULT NULL AFTER `recurrence_rule`;
