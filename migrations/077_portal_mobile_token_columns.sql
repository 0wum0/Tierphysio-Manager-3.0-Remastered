-- Migration 077: Add mobile_token columns to owner_portal_users for Flutter portal app
-- Safe to re-run: MigrationService ignores error 1060 (duplicate column)

ALTER TABLE `{PREFIX}owner_portal_users`
    ADD COLUMN `mobile_token` VARCHAR(64) NULL DEFAULT NULL AFTER `last_login`;

ALTER TABLE `{PREFIX}owner_portal_users`
    ADD COLUMN `mobile_token_expires` DATETIME NULL DEFAULT NULL AFTER `mobile_token`;
