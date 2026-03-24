-- Migration 016: Add diagnosis field to invoices
ALTER TABLE `invoices` ADD COLUMN IF NOT EXISTS `diagnosis` TEXT NULL AFTER `notes`;
