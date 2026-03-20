-- Migration 002: Google Calendar 2-Way Sync support

-- Add sync_token column to connections for incremental polling
ALTER TABLE `google_calendar_connections`
    ADD COLUMN IF NOT EXISTS `sync_token`        TEXT NULL AFTER `default_reminder_minutes`,
    ADD COLUMN IF NOT EXISTS `last_pull_at`      DATETIME NULL AFTER `sync_token`,
    ADD COLUMN IF NOT EXISTS `pull_enabled`      TINYINT(1) NOT NULL DEFAULT 1 AFTER `last_pull_at`;

-- Extend sync log action enum to include 'pull'
ALTER TABLE `google_calendar_sync_log`
    MODIFY COLUMN `action` ENUM('create','update','delete','auth','error','test','pull') NOT NULL;

-- Table for Google-originated events imported into Tierphysio
CREATE TABLE IF NOT EXISTS `google_calendar_imported_events` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `connection_id`      INT UNSIGNED NOT NULL,
    `google_event_id`    VARCHAR(255) NOT NULL,
    `google_calendar_id` VARCHAR(255) NOT NULL,
    `appointment_id`     INT UNSIGNED NULL COMMENT 'NULL = external event shown read-only',
    `event_title`        VARCHAR(500) NULL,
    `event_start`        DATETIME NULL,
    `event_end`          DATETIME NULL,
    `event_description`  TEXT NULL,
    `is_all_day`         TINYINT(1) NOT NULL DEFAULT 0,
    `google_status`      VARCHAR(50) NULL COMMENT 'confirmed|tentative|cancelled',
    `raw_json`           MEDIUMTEXT NULL,
    `last_pulled_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_google_event_conn` (`google_event_id`, `connection_id`),
    INDEX `idx_appointment_id` (`appointment_id`),
    INDEX `idx_event_start` (`event_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
