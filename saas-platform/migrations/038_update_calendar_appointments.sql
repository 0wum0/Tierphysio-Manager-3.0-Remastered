-- 038_update_calendar_appointments.sql

-- 1. Appointment Columns
ALTER TABLE `appointments` 
ADD COLUMN IF NOT EXISTS `patient_email` VARCHAR(200) NULL DEFAULT NULL COMMENT 'E-Mail des Patienten für Erinnerungen',
ADD COLUMN IF NOT EXISTS `send_patient_reminder` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Erinnerung an Patient senden';

-- 2. Standard-Erinnerung auf 24 Stunden (1440 Minuten) aktualisieren
UPDATE `appointments` SET `reminder_minutes` = 1440 
WHERE `reminder_minutes` = 60 OR `reminder_minutes` IS NULL;
