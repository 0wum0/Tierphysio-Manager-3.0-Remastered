-- Migration 074: Repair passport_number + insurance columns
-- 037_patient_insurance_passport.sql was never executed because
-- 037_gobd_infrastructure.sql shared the same version prefix.
-- Tenant is already at v73, so this runs as v74.

ALTER TABLE `patients`
    ADD COLUMN `insurance_company`  VARCHAR(255) NULL DEFAULT NULL AFTER `weight`,
    ADD COLUMN `insurance_policy`   VARCHAR(100) NULL DEFAULT NULL AFTER `insurance_company`,
    ADD COLUMN `insurance_type`     VARCHAR(100) NULL DEFAULT NULL AFTER `insurance_policy`,
    ADD COLUMN `passport_number`    VARCHAR(100) NULL DEFAULT NULL AFTER `insurance_type`;
