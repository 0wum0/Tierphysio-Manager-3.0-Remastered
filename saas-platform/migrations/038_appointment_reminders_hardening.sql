-- Appointment Reminders Hardening
-- Adds missing columns for automated reminders and ensures TherapyCare Pro queue tables exist.

-- 1. Appointment Columns
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `reminder_minutes` SMALLINT UNSIGNED NULL DEFAULT 60 AFTER `notes`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_minutes`;
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `patient_reminder_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_sent`;

-- 2. TherapyCare Pro Reminder Queue (if not already handled by plugin)
CREATE TABLE IF NOT EXISTS `tcp_reminder_queue` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` INT UNSIGNED NULL,
    `type` ENUM('appointment','homework','followup','custom') NOT NULL,
    `patient_id` INT UNSIGNED NULL,
    `owner_id` INT UNSIGNED NOT NULL,
    `appointment_id` INT UNSIGNED NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `send_at` DATETIME NOT NULL,
    `sent_at` DATETIME NULL,
    `status` ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    `error_message` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tcp_reminder_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_id` INT UNSIGNED NULL,
    `type` VARCHAR(50) NOT NULL,
    `recipient` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `status` ENUM('sent','failed') NOT NULL,
    `error` TEXT NULL,
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
