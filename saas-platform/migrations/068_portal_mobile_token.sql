-- Migration 068: Add mobile_token column to owner_portal_users for Flutter app auth
-- Tolerant: MigrationService handles duplicate column errors (1060)

ALTER TABLE `{PREFIX}owner_portal_users`
    ADD COLUMN `mobile_token` VARCHAR(64) NULL DEFAULT NULL AFTER `last_login`;

ALTER TABLE `{PREFIX}owner_portal_users`
    ADD COLUMN `mobile_token_expires` DATETIME NULL DEFAULT NULL AFTER `mobile_token`;
