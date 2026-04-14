-- ============================================================
-- Migration 040 – Schema Self-Healing & Hardening
-- Ensures all core and plugin tables exist across all tenants.
-- This fixes drift where base schema (tenant_schema.sql) was out of sync.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Core: vet_reports (v15)
CREATE TABLE IF NOT EXISTS `vet_reports` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`   INT UNSIGNED NOT NULL,
    `created_by`   INT UNSIGNED NULL,
    `filename`     VARCHAR(255) NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_vr_patient` (`patient_id`),
    CONSTRAINT `fk_vr_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vr_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Core: invoice_cancellations & GoBD Hardening (v28/v29)
CREATE TABLE IF NOT EXISTS `invoice_cancellations` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `original_invoice_id`  INT UNSIGNED NOT NULL,
    `new_invoice_id`       INT UNSIGNED NOT NULL,
    `reason`               TEXT NULL,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ic_orig` FOREIGN KEY (`original_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ic_new`  FOREIGN KEY (`new_invoice_id`)      REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `invoices` ADD COLUMN `invoice_type` ENUM('normal','cancellation') NOT NULL DEFAULT 'normal' AFTER `invoice_number`;
ALTER TABLE `invoices` ADD COLUMN `cancellation_reason` TEXT NULL AFTER `status`;
ALTER TABLE `invoices` ADD COLUMN `cancelled_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `invoices` ADD COLUMN `cancelled_by` INT UNSIGNED NULL DEFAULT NULL AFTER `cancelled_at`;
ALTER TABLE `invoices` ADD COLUMN `cancels_invoice_id` INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `invoices` ADD COLUMN `cancellation_invoice_id` INT UNSIGNED NULL DEFAULT NULL AFTER `cancels_invoice_id`;

-- 3. Core: expenses (v34)
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
    INDEX `idx_date` (`date`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Plugin: TherapyCare Pro (v37 / v39)
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
    CONSTRAINT `fk_tcp_pe_patient`  FOREIGN KEY (`patient_id`)  REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_pe_category` FOREIGN KEY (`category_id`) REFERENCES `tcp_progress_categories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_pe_user`     FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tcp_reminder_templates` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`         ENUM('appointment','homework','followup') NOT NULL DEFAULT 'appointment',
    `name`         VARCHAR(150) NOT NULL,
    `subject`      VARCHAR(255) NOT NULL,
    `body`         TEXT NOT NULL,
    `trigger_hours` INT NOT NULL DEFAULT 24,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    CONSTRAINT `fk_tcp_rq_patient_v40` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_rq_owner_v40`   FOREIGN KEY (`owner_id`)   REFERENCES `owners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tcp_reminder_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_id`   INT UNSIGNED NULL,
    `type`       VARCHAR(50) NOT NULL,
    `recipient`  VARCHAR(255) NOT NULL,
    `subject`    VARCHAR(255) NOT NULL,
    `status`     ENUM('sent','failed') NOT NULL,
    `error`      TEXT NULL,
    `sent_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- More core tables reported missing by dashboard sync
CREATE TABLE IF NOT EXISTS `gobd_audit_log` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `table_name`    VARCHAR(100) NOT NULL,
    `row_id`        INT UNSIGNED NULL,
    `action`        ENUM('create','update','delete','immutable_final','storno') NOT NULL,
    `old_values`    LONGTEXT NULL,
    `new_values`    LONGTEXT NULL,
    `user_id`       INT UNSIGNED NULL,
    `hash`          VARCHAR(64) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
