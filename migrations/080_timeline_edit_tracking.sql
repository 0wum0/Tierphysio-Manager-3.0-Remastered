-- Migration 080: Timeline edit tracking
-- Adds updated_at and updated_by to patient_timeline for edit audit trail
-- Migration service silently ignores duplicate column errors (1060)

ALTER TABLE `patient_timeline`
    ADD COLUMN `updated_at` DATETIME     NULL DEFAULT NULL COMMENT 'Zeitpunkt der letzten Bearbeitung';

ALTER TABLE `patient_timeline`
    ADD COLUMN `updated_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'User-ID des letzten Bearbeiters';
