-- Migration 016: Add diagnosis field to invoices
ALTER TABLE `invoices` ADD COLUMN `diagnosis` TEXT NULL AFTER `notes`;
