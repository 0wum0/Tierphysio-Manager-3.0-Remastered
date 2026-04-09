-- DSGVO-Zustimmungsfeld für Besitzer (owners Tabelle)
ALTER TABLE `{{prefix}}owners` ADD COLUMN `gdpr_consent` TINYINT(1) DEFAULT 0 COMMENT 'DSGVO-Zustimmung (0=nein, 1=ja)' AFTER `notes`;
ALTER TABLE `{{prefix}}owners` ADD COLUMN `gdpr_consent_at` DATETIME NULL COMMENT 'Zeitpunkt der DSGVO-Zustimmung' AFTER `gdpr_consent`;
ALTER TABLE `{{prefix}}owners` ADD INDEX `idx_gdpr_consent` (`gdpr_consent`);

-- DSGVO-Zustimmungsfeld für Patienten (patients Tabelle) - optional, falls auch hier benötigt
-- ALTER TABLE `{{prefix}}patients` ADD COLUMN `gdpr_consent` TINYINT(1) DEFAULT 0 COMMENT 'DSGVO-Zustimmung (0=nein, 1=ja)' AFTER `notes`;
