-- DSGVO-Zustimmungsfeld für Besitzer (owners Tabelle)
-- SaaS-kompatible Migration: Prüft ob Spalten existieren, bevor sie hinzugefügt werden
SET @dbname = DATABASE();
SET @tablename = CONCAT('{{prefix}}owners');

-- Prüfen ob Tabelle existiert
SET @check_table = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @dbname AND table_name = @tablename);

-- Prüfen ob Spalte gdpr_consent existiert
SET @check_column = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'gdpr_consent');

-- Nur hinzufügen wenn Tabelle existiert und Spalte noch nicht existiert
SET @sql = IF(@check_table > 0 AND @check_column = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `gdpr_consent` TINYINT(1) DEFAULT 0 COMMENT ''DSGVO-Zustimmung (0=nein, 1=ja)'' AFTER `notes`'),
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Prüfen ob Spalte gdpr_consent_at existiert
SET @check_column2 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'gdpr_consent_at');

SET @sql = IF(@check_table > 0 AND @check_column2 = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `gdpr_consent_at` DATETIME NULL COMMENT ''Zeitpunkt der DSGVO-Zustimmung'' AFTER `gdpr_consent`'),
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index hinzufügen (falls noch nicht vorhanden)
SET @check_index = (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = @dbname AND table_name = @tablename AND index_name = 'idx_gdpr_consent');

SET @sql = IF(@check_table > 0 AND @check_index = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD INDEX `idx_gdpr_consent` (`gdpr_consent`)'),
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- DSGVO-Zustimmungsfeld für Patienten (patients Tabelle) - optional, falls auch hier benötigt
-- ALTER TABLE `{{prefix}}patients` ADD COLUMN `gdpr_consent` TINYINT(1) DEFAULT 0 COMMENT 'DSGVO-Zustimmung (0=nein, 1=ja)' AFTER `notes`;
