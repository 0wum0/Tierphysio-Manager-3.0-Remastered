-- Migration 011: Fix owner_portal_users AUTO_INCREMENT
-- Root cause: id column lacks AUTO_INCREMENT on some tenants (table existed before
-- migration 001 ran correctly), causing "Duplicate entry '0' for key 'PRIMARY'" on INSERT.

-- 1. Remove orphan rows with id=0
DELETE FROM `{PREFIX}owner_portal_users` WHERE `id` = 0;

-- 2. Restore AUTO_INCREMENT (MySQL recalculates value from MAX(id)+1)
ALTER TABLE `{PREFIX}owner_portal_users`
    MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;
