-- ============================================================
-- TheraPano Push Notification System
-- Migration 078 — Tenant-scoped notification tables
-- ============================================================
--
-- Note: The global non-prefixed tables (push_device_tokens, push_notification_preferences,
-- push_notification_deliveries, push_appointment_reminders_sent) are created by the
-- push-thera Node.js server at startup via its own migration system.
--
-- This migration adds per-tenant prefixed tables.

SET NAMES utf8mb4;

-- ─── Per-tenant Notifications table ──────────────────────────────────────────
-- Note: This SQL is used as a template. The MigrationService will prefix all
-- table names with the tenant prefix (t_{id}_).

CREATE TABLE IF NOT EXISTS `push_notifications` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`         INT UNSIGNED    NOT NULL,
    `recipient_user_id` INT UNSIGNED    NOT NULL,
    `recipient_role`    ENUM('therapeut','trainer','owner','saas_admin') NOT NULL,
    `source_type`       VARCHAR(100),
    `source_id`         INT UNSIGNED,
    `notification_type` VARCHAR(100)    NOT NULL,
    `title`             VARCHAR(255)    NOT NULL,
    `body`              TEXT            NOT NULL,
    `data_json`         JSON,
    `priority`          ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    `read_at`           DATETIME,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notif_recipient` (`tenant_id`, `recipient_user_id`, `read_at`),
    INDEX `idx_notif_type`      (`notification_type`),
    INDEX `idx_notif_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Portal check notifications — extend existing table ──────────────────────
-- The portal_check_notifications table already exists in most tenants.
-- These ALTER statements add the push-relevant columns if missing.

ALTER TABLE `portal_check_notifications`
    ADD COLUMN IF NOT EXISTS `push_dispatched` TINYINT(1) NOT NULL DEFAULT 0 AFTER `read_at`,
    ADD COLUMN IF NOT EXISTS `push_dispatched_at` DATETIME AFTER `push_dispatched`;
