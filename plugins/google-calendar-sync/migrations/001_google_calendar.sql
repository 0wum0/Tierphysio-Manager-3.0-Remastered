-- Migration 001: Google Calendar Sync tables

CREATE TABLE IF NOT EXISTS `google_calendar_connections` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NULL,
    `google_email`    VARCHAR(255) NULL,
    `access_token`    TEXT NULL,
    `refresh_token`   TEXT NULL,
    `token_expires_at` DATETIME NULL,
    `calendar_id`     VARCHAR(255) NULL DEFAULT 'primary',
    `calendar_name`   VARCHAR(255) NULL,
    `sync_enabled`    TINYINT(1) NOT NULL DEFAULT 1,
    `auto_sync`       TINYINT(1) NOT NULL DEFAULT 1,
    `skip_waitlist`   TINYINT(1) NOT NULL DEFAULT 1,
    `default_reminder_minutes` INT NULL DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `google_calendar_sync_map` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `appointment_id`  INT UNSIGNED NOT NULL,
    `connection_id`   INT UNSIGNED NOT NULL,
    `google_event_id` VARCHAR(255) NOT NULL,
    `google_calendar_id` VARCHAR(255) NOT NULL,
    `sync_status`     ENUM('synced','pending','failed','deleted') NOT NULL DEFAULT 'synced',
    `last_synced_at`  DATETIME NULL,
    `last_error`      TEXT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_appointment_connection` (`appointment_id`, `connection_id`),
    INDEX `idx_appointment_id` (`appointment_id`),
    INDEX `idx_sync_status` (`sync_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `google_calendar_sync_log` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `connection_id` INT UNSIGNED NULL,
    `action`       ENUM('create','update','delete','auth','error','test') NOT NULL,
    `appointment_id` INT UNSIGNED NULL,
    `google_event_id` VARCHAR(255) NULL,
    `message`      TEXT NULL,
    `success`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_connection_id` (`connection_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
