-- Migration 006: Zahlungsmethode und Bezahldatum für Rechnungen
ALTER TABLE `invoices`
    ADD COLUMN `payment_method` ENUM('rechnung','bar') NOT NULL DEFAULT 'rechnung' AFTER `payment_terms`,
    ADD COLUMN `paid_at` DATETIME NULL AFTER `payment_method`;

-- Index für Auswertungen nach Zahlungsmethode
CREATE INDEX `idx_payment_method` ON `invoices` (`payment_method`);
