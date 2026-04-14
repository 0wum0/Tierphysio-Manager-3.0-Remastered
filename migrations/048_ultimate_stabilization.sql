-- ============================================================
-- Migration 048 — Ultimate Stabilization & Schema Sync
-- Ziel: Sicherstellen, dass ALLE Tabellen und Spalten vorhanden sind.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── TCP: reminder templates (Critical for missing 'type' error!) ─────
CREATE TABLE IF NOT EXISTS `tcp_reminder_templates` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `type`         ENUM('appointment','homework','followup') NOT NULL DEFAULT 'appointment',
    `name`         VARCHAR(150) NOT NULL,
    `subject`      VARCHAR(255) NOT NULL,
    `body`         TEXT NOT NULL,
    `trigger_hours` INT NOT NULL DEFAULT 24
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tcp_reminder_templates` ADD COLUMN `type` ENUM('appointment','homework','followup') NOT NULL DEFAULT 'appointment' AFTER `id`;
ALTER TABLE `tcp_reminder_templates` ADD COLUMN `name` VARCHAR(150) NOT NULL AFTER `type`;

-- ── TCP: reminder queue (Critical for emails!) ───────────────
CREATE TABLE IF NOT EXISTS `tcp_reminder_queue` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `template_id`   INT UNSIGNED NULL,
    `type`          ENUM('appointment','homework','followup','custom') NOT NULL,
    `patient_id`    INT UNSIGNED NULL,
    `owner_id`      INT UNSIGNED NOT NULL,
    `status`        ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tcp_reminder_queue` ADD COLUMN `type` ENUM('appointment','homework','followup','custom') NOT NULL AFTER `template_id`;
ALTER TABLE `tcp_reminder_queue` ADD COLUMN `status` ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending' AFTER `id`;

-- ── TCP: progress categories ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `tcp_progress_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `color`       VARCHAR(7) NOT NULL DEFAULT '#4f7cff',
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TCP: progress entries (Critical for saving progress!) ─────
CREATE TABLE IF NOT EXISTS `tcp_progress_entries` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `patient_id`      INT UNSIGNED NOT NULL,
    `category_id`     INT UNSIGNED NOT NULL,
    `score`           TINYINT NOT NULL,
    `entry_date`      DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TCP: Reports ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tcp_therapy_reports` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `patient_id`     INT UNSIGNED NOT NULL,
    `title`          VARCHAR(255) NOT NULL,
    `filename`       VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TCP: Natural Therapy Types ───────────────────────────────
CREATE TABLE IF NOT EXISTS `tcp_natural_therapy_types` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `category`    VARCHAR(100) NOT NULL DEFAULT 'sonstiges'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── core: vet_reports ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vet_reports` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `patient_id`   INT UNSIGNED NOT NULL,
    `filename`     VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `vet_reports` ADD COLUMN `type` ENUM('auto', 'custom') NOT NULL DEFAULT 'auto' AFTER `id`;
ALTER TABLE `vet_reports` ADD COLUMN `title` VARCHAR(255) NULL AFTER `type`;

-- ── SYSTEM: Cron Dispatcher Log ──────────────────────────────
CREATE TABLE IF NOT EXISTS `cron_dispatcher_log` (
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `job_key`    varchar(64)      NOT NULL,
  `status`     enum('success','error','skipped') NOT NULL DEFAULT 'success'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Column Hardening (Adding missing columns if skipped previously) ──
ALTER TABLE `appointments` ADD COLUMN `patient_email` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `appointments` ADD COLUMN `send_patient_reminder` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `appointments` ADD COLUMN `patient_reminder_sent` TINYINT(1) NOT NULL DEFAULT 0;

-- ── ENSURE DEFAULT CATEGORIES (Only if table was empty) ───────
INSERT IGNORE INTO `tcp_progress_categories` (id, name, color, is_active, sort_order) VALUES 
(1, 'Schmerzgrad', '#ef4444', 1, 1),
(2, 'Beweglichkeit', '#4f7cff', 1, 2),
(3, 'Allgemeinbefinden', '#22c55e', 1, 3);

-- ── FINAL SYNC ───────────────────────────────────────────────
DELETE FROM `settings` WHERE `key` = 'db_version';
INSERT INTO `settings` (`key`, `value`) VALUES ('db_version', '48');

SET FOREIGN_KEY_CHECKS = 1;
