-- Migration 039: Fehlende Spalten in appointments-Tabelle nachträglich hinzufügen
-- Behebt: SQLSTATE[42S22] Unknown column 'recurrence_rule' für ältere Tenants
-- Sicher: ADD COLUMN IF NOT EXISTS kann mehrfach ausgeführt werden

ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `recurrence_rule`   VARCHAR(512) NULL DEFAULT NULL AFTER `user_id`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `recurrence_parent` INT UNSIGNED NULL DEFAULT NULL AFTER `recurrence_rule`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `patient_email`     VARCHAR(255) NULL DEFAULT NULL AFTER `owner_id`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `send_patient_reminder`  TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `patient_reminder_sent`  TINYINT(1) NOT NULL DEFAULT 0;
