-- Migration 033: Add veterinarian info fields to patients
-- Separate statements so each is individually idempotent (errno 1060 ignored by runner).
-- No AFTER clause to avoid errno 1054 if the referenced column doesn't exist on a tenant.
ALTER TABLE `patients` ADD COLUMN `vet_name`    VARCHAR(255) NULL;
ALTER TABLE `patients` ADD COLUMN `vet_phone`   VARCHAR(50)  NULL;
ALTER TABLE `patients` ADD COLUMN `vet_address` TEXT         NULL;
