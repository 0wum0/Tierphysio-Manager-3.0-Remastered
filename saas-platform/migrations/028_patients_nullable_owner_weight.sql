-- Migration 022: Make patients.owner_id nullable and add weight column

ALTER TABLE `patients`
    MODIFY COLUMN `owner_id` INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE `patients`
    ADD COLUMN IF NOT EXISTS `weight` DECIMAL(6,2) NULL DEFAULT NULL AFTER `chip_number`;

ALTER TABLE `patients`
    MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'active';
