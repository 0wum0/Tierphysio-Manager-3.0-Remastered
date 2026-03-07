ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(50) NOT NULL DEFAULT 'ueberweisung' AFTER `payment_terms`;
