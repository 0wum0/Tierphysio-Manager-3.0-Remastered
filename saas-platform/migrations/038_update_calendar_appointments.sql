-- Fehlende Spalten zur appointments-Tabelle für alle Tenants hinzufügen
-- Standard-Erinnerung auf 24 Stunden (1440 Minuten) aktualisieren

-- Für jeden Tenant die appointments-Tabelle aktualisieren
-- Dieser SQL muss für jeden Tenant-Datenbank separat ausgeführt werden

-- Beispiel für Tenant-Datenbank mit Prefix t_praxis_wenzel_:
-- ALTER TABLE `t_praxis_wenzel_appointments` 
-- ADD COLUMN IF NOT EXISTS `patient_email` VARCHAR(200) NULL DEFAULT NULL COMMENT 'E-Mail des Patienten für Erinnerungen',
-- ADD COLUMN IF NOT EXISTS `send_patient_reminder` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Erinnerung an Patient senden';

-- UPDATE `t_praxis_wenzel_appointments` SET `reminder_minutes` = 1440 
-- WHERE `reminder_minutes` = 60 OR `reminder_minutes` IS NULL;

-- Bitte führen Sie diese ALTER TABLE und UPDATE Befehle für jeden Tenant-Datenbank separat aus
-- Ersetzen Sie t_praxis_wenzel_ mit dem tatsächlichen Prefix jedes Tenants
