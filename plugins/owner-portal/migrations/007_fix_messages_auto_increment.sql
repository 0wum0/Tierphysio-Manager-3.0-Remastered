-- Migration 007: Fix portal_messages AUTO_INCREMENT
-- Root cause: if tables existed before migration 004 ran (e.g. created manually or from
-- an older schema), the id column may lack AUTO_INCREMENT. This causes
-- "Duplicate entry '0' for key 'PRIMARY'" on every INSERT.
-- Safe to re-run: ALTER MODIFY does nothing if AUTO_INCREMENT already correct.

-- 1. Remove orphan rows with id=0 (created when AUTO_INCREMENT was missing)
DELETE FROM `{PREFIX}portal_messages` WHERE `id` = 0;
DELETE FROM `{PREFIX}portal_message_threads` WHERE `id` = 0;

-- 2. Restore AUTO_INCREMENT on both tables (MySQL recalculates value from MAX(id)+1)
ALTER TABLE `{PREFIX}portal_messages`
    MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `{PREFIX}portal_message_threads`
    MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;
