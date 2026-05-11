-- Migration 066: Add max_subscribers to plans table
-- Defines how many active tenants may use a given plan simultaneously.
-- 99999999 = practically unlimited.
-- Safe to run multiple times (MigrationService tolerates 1060 Duplicate column).

ALTER TABLE `plans`
    ADD COLUMN `max_subscribers` INT UNSIGNED NOT NULL DEFAULT 99999999
        COMMENT 'Max number of active tenants on this plan. 99999999 = unlimited.'
    AFTER `max_users`;
