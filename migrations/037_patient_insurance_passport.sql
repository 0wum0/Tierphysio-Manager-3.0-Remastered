-- Migration 037: Versicherung + Impfpass + Gewicht (falls fehlt) für patients

ALTER TABLE `patients`
    ADD COLUMN `insurance_company`  VARCHAR(255) NULL DEFAULT NULL AFTER `weight`,
    ADD COLUMN `insurance_policy`   VARCHAR(100) NULL DEFAULT NULL AFTER `insurance_company`,
    ADD COLUMN `insurance_type`     VARCHAR(100) NULL DEFAULT NULL AFTER `insurance_policy`,
    ADD COLUMN `passport_number`    VARCHAR(100) NULL DEFAULT NULL AFTER `insurance_type`;
