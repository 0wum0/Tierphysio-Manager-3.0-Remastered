-- Migration 007: Zahlungsmethode und Bezahldatum für Rechnungen
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `payment_method` ENUM('rechnung','bar') NOT NULL DEFAULT 'rechnung' AFTER `payment_terms`,
    ADD COLUMN IF NOT EXISTS `paid_at` DATETIME NULL AFTER `payment_method`;

-- Index nur anlegen wenn er noch nicht existiert
CREATE INDEX IF NOT EXISTS `idx_payment_method` ON `invoices` (`payment_method`);
