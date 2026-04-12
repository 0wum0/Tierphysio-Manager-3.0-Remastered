-- Migration 033: Add veterinarian info fields to patients
ALTER TABLE `patients`
    ADD COLUMN `vet_name`    VARCHAR(255) NULL AFTER `chip_number`,
    ADD COLUMN `vet_phone`   VARCHAR(50)  NULL AFTER `vet_name`,
    ADD COLUMN `vet_address` TEXT         NULL AFTER `vet_phone`;
