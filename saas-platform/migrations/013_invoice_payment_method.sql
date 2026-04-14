-- Migration 007: Zahlungsmethode und Bezahldatum für Rechnungen
-- ADD COLUMN IF NOT EXISTS ist idempotent: bricht nicht ab wenn Spalte schon existiert
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `payment_method` ENUM('rechnung','bar') NOT NULL DEFAULT 'rechnung' AFTER `payment_terms`;
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `paid_at` DATETIME NULL AFTER `payment_method`;

-- Index: erst entfernen falls vorhanden, dann neu anlegen
ALTER TABLE `invoices` DROP INDEX IF EXISTS `idx_payment_method`;
ALTER TABLE `invoices` ADD INDEX `idx_payment_method` (`payment_method`);
