-- ============================================================
-- Migration 048 — Ultimate Stabilization & Schema Sync
-- Ziel: Sicherstellen, dass ALLE Tabellen und Spalten vorhanden sind.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── core: vet_reports ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vet_reports` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`   INT UNSIGNED NOT NULL,
    `created_by`   INT UNSIGNED NULL,
    `filename`     VARCHAR(255) NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_vr_patient` (`patient_id`),
    CONSTRAINT `fk_vr_patient_v48` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── core: expenses ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `expenses` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `date`        DATE         NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `category`    VARCHAR(100) NOT NULL DEFAULT 'Sonstiges',
    `supplier`    VARCHAR(255) NULL,
    `amount_net`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_rate`    DECIMAL(5,2)  NOT NULL DEFAULT 19.00,
    `amount_gross` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes`       TEXT NULL,
    `receipt_file` VARCHAR(255) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TCP: progress categories ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `tcp_progress_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `scale_min`   TINYINT NOT NULL DEFAULT 1,
    `scale_max`   TINYINT NOT NULL DEFAULT 10,
    `scale_label_min` VARCHAR(50) NULL,
    `scale_label_max` VARCHAR(50) NULL,
    `color`       VARCHAR(7) NOT NULL DEFAULT '#4f7cff',
    `icon`        VARCHAR(50) NULL,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TCP: progress entries (Critical for saving progress!) ─────
CREATE TABLE IF NOT EXISTS `tcp_progress_entries` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`      INT UNSIGNED NOT NULL,
    `category_id`     INT UNSIGNED NOT NULL,
    `appointment_id`  INT UNSIGNED NULL,
    `score`           TINYINT NOT NULL,
    `notes`           TEXT NULL,
    `recorded_by`     INT UNSIGNED NULL,
    `entry_date`      DATE NOT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_tcp_pe_patient_v48`  FOREIGN KEY (`patient_id`)  REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_pe_category_v48` FOREIGN KEY (`category_id`) REFERENCES `tcp_progress_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TCP: reminder queue (Critical for emails!) ───────────────
CREATE TABLE IF NOT EXISTS `tcp_reminder_queue` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id`   INT UNSIGNED NULL,
    `type`          ENUM('appointment','homework','followup','custom') NOT NULL,
    `patient_id`    INT UNSIGNED NULL,
    `owner_id`      INT UNSIGNED NOT NULL,
    `appointment_id` INT UNSIGNED NULL,
    `subject`       VARCHAR(255) NOT NULL,
    `body`          TEXT NOT NULL,
    `send_at`       DATETIME NOT NULL,
    `sent_at`       DATETIME NULL,
    `status`        ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    `error_message` TEXT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_tcp_rq_patient_v48` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_rq_owner_v48`   FOREIGN KEY (`owner_id`)   REFERENCES `owners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TCP: Reports ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tcp_therapy_reports` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `patient_id`     INT UNSIGNED NOT NULL,
    `created_by`     INT UNSIGNED NULL,
    `title`          VARCHAR(255) NOT NULL,
    `filename`       VARCHAR(255) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tcp_rep_patient_v48` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SYSTEM: Cron Dispatcher Log ──────────────────────────────
CREATE TABLE IF NOT EXISTS `cron_dispatcher_log` (
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT,
  `job_key`    varchar(64)      NOT NULL,
  `status`     enum('success','error','skipped') NOT NULL DEFAULT 'success',
  `message`    text             DEFAULT NULL,
  `duration_ms` int(11) unsigned DEFAULT NULL,
  `created_at` datetime         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
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
