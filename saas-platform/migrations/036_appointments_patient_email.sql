-- Patient-Erinnerungen für Termine
-- Fügt patient_email Spalte hinzu, um Patienten direkt erinnern zu können

ALTER TABLE `appointments` ADD COLUMN `patient_email` VARCHAR(255) NULL DEFAULT NULL AFTER `owner_id`;
ALTER TABLE `appointments` ADD COLUMN `send_patient_reminder` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_minutes`;
ALTER TABLE `appointments` ADD COLUMN `patient_reminder_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_sent`;
