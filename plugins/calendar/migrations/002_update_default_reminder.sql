-- Spalte reminder_minutes zur appointments-Tabelle hinzufügen
ALTER TABLE `appointments` 
ADD COLUMN IF NOT EXISTS `reminder_minutes` SMALLINT UNSIGNED NULL DEFAULT 1440 COMMENT 'Erinnerung in Minuten vor Termin (15, 30, 60, 120, 1440)';

-- Standard-Erinnerung auf 24 Stunden (1440 Minuten) aktualisieren
UPDATE `appointments` SET `reminder_minutes` = 1440 
WHERE `reminder_minutes` = 60 OR `reminder_minutes` IS NULL;
