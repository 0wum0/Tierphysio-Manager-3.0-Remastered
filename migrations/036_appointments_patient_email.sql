-- Patient-Erinnerungen für Termine
-- Fügt patient_email Spalte hinzu, um Patienten direkt erinnern zu können

-- Zuerst fehlende Spalten hinzufügen (für ältere Datenbanken)
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `reminder_minutes` SMALLINT UNSIGNED NULL DEFAULT 60 AFTER `notes`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_minutes`;

-- Dann neue Spalten hinzufügen
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `patient_email` VARCHAR(255) NULL DEFAULT NULL AFTER `owner_id`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `send_patient_reminder` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_minutes`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `patient_reminder_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_sent`;
