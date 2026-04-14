ALTER TABLE `invoices` MODIFY COLUMN `status` ENUM('draft','open','paid','overdue','cancelled') NOT NULL DEFAULT 'draft';
ALTER TABLE `invoices` ADD COLUMN IF NOT EXISTS `cancellation_reason` TEXT NULL AFTER `status`;
