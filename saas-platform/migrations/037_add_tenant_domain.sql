-- Domain-Spalte zur tenants-Tabelle hinzufügen
-- Für die korrekte URL-Generierung bei Cronjob-Tests

ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `domain` VARCHAR(200) COMMENT 'Domain der Praxis-App (z.B. praxis.example.com)' AFTER `db_name`;

-- Bestehende Tenants mit Domain aus E-Mail aktualisieren
UPDATE `tenants` SET `domain` = SUBSTRING_INDEX(`email`, '@', -1) WHERE `domain` IS NULL OR `domain` = '';
