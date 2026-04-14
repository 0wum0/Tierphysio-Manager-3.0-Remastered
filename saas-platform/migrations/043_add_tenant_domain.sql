-- Domain-Spalte zur tenants-Tabelle hinzufügen
-- Für die korrekte URL-Generierung bei Cronjob-Tests

ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `domain` VARCHAR(200) COMMENT 'Domain der Praxis-App (z.B. praxis.example.com)' AFTER `db_name`;

-- Alle Tenants mit der gemeinsamen Domain app.therapano.de aktualisieren
UPDATE `tenants` SET `domain` = 'app.therapano.de' WHERE `domain` IS NULL OR `domain` = '';
