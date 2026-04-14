-- Fehlende Spalten zur appointments-Tabelle hinzufügen
ALTER TABLE `appointments` 
ADD COLUMN IF NOT EXISTS `reminder_minutes` SMALLINT UNSIGNED NULL DEFAULT 1440 COMMENT 'Erinnerung in Minuten vor Termin',
ADD COLUMN IF NOT EXISTS `patient_email` VARCHAR(200) NULL DEFAULT NULL COMMENT 'E-Mail des Patienten für Erinnerungen',
ADD COLUMN IF NOT EXISTS `send_patient_reminder` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Erinnerung an Patient senden';

-- Standard-Erinnerung auf 24 Stunden (1440 Minuten) aktualisieren
-- Für alle bestehenden Termine ohne Erinnerung oder mit 60 Minuten
UPDATE `appointments` SET `reminder_minutes` = 1440 
WHERE `reminder_minutes` = 60 OR `reminder_minutes` IS NULL;
