-- Standard-Erinnerung auf 24 Stunden (1440 Minuten) aktualisieren
-- Für alle bestehenden Termine ohne Erinnerung oder mit 60 Minuten

UPDATE `appointments` SET `reminder_minutes` = 1440 
WHERE `reminder_minutes` = 60 OR `reminder_minutes` IS NULL;
