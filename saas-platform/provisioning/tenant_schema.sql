-- ============================================================
-- Tierphysio Tenant Schema — vollständig (Stand Migration 023)
-- Applied when a new Praxis instance is provisioned
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── users (Migration 001) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `email`      VARCHAR(255) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('admin','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
    `active`     TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── settings (Migration 001 + 035) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── owners (Migration 001) ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `owners` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name`  VARCHAR(100) NOT NULL,
    `email`      VARCHAR(255) NULL,
    `phone`      VARCHAR(50) NULL,
    `birth_date` DATE NULL,
    `street`     VARCHAR(255) NULL,
    `zip`        VARCHAR(10) NULL,
    `city`       VARCHAR(100) NULL,
    `notes`      TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_last_name` (`last_name`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── patients (Migration 001 + 003 + 005 + 022) ───────────────
CREATE TABLE IF NOT EXISTS `patients` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_id`      INT UNSIGNED NULL DEFAULT NULL,
    `name`          VARCHAR(255) NOT NULL,
    `species`       VARCHAR(100) NULL,
    `breed`         VARCHAR(100) NULL,
    `birth_date`    DATE NULL,
    `gender`        ENUM('männlich','weiblich','kastriert','sterilisiert','unbekannt') NULL DEFAULT 'unbekannt',
    `color`         VARCHAR(100) NULL,
    `chip_number`   VARCHAR(50) NULL,
    `weight`        DECIMAL(6,2) NULL DEFAULT NULL,
    `photo`         VARCHAR(255) NULL,
    `status`        VARCHAR(50) NOT NULL DEFAULT 'active',
    `deceased_date` DATE NULL DEFAULT NULL,
    `notes`         TEXT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_owner_id` (`owner_id`),
    INDEX `idx_name` (`name`),
    CONSTRAINT `fk_patients_owner` FOREIGN KEY (`owner_id`) REFERENCES `owners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── patient_timeline (Migration 001 + 002 + 008) ─────────────
CREATE TABLE IF NOT EXISTS `patient_timeline` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`        INT UNSIGNED NOT NULL,
    `user_id`           INT UNSIGNED NULL,
    `type`              ENUM('note','treatment','photo','document','other','payment') NOT NULL DEFAULT 'note',
    `treatment_type_id` INT UNSIGNED NULL,
    `title`             VARCHAR(255) NOT NULL DEFAULT '',
    `content`           TEXT NULL,
    `status_badge`      VARCHAR(100) NULL,
    `attachment`        VARCHAR(255) NULL,
    `entry_date`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_entry_date` (`entry_date`),
    CONSTRAINT `fk_timeline_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_timeline_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── treatment_types (Migration 002) ───────────────────────────
CREATE TABLE IF NOT EXISTS `treatment_types` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `color`       VARCHAR(7) NOT NULL DEFAULT '#4f7cff',
    `price`       DECIMAL(10,2) NULL,
    `description` TEXT NULL,
    `active`      TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── appointments (Migration 001 + 006 + 009 + 011 + 036) ───────────
CREATE TABLE IF NOT EXISTS `appointments` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`              VARCHAR(255) NOT NULL,
    `description`        TEXT NULL,
    `start_at`           DATETIME NOT NULL,
    `end_at`             DATETIME NOT NULL,
    `all_day`            TINYINT(1) NOT NULL DEFAULT 0,
    `status`             ENUM('scheduled','confirmed','completed','cancelled','noshow') NOT NULL DEFAULT 'scheduled',
    `color`              VARCHAR(7) NULL,
    `patient_id`         INT UNSIGNED NULL,
    `owner_id`           INT UNSIGNED NULL,
    `patient_email`      VARCHAR(255) NULL DEFAULT NULL,
    `treatment_type_id`  INT UNSIGNED NULL,
    `user_id`            INT UNSIGNED NULL,
    `invoice_id`         INT UNSIGNED NULL,
    `recurrence_rule`    VARCHAR(500) NULL,
    `recurrence_parent`  INT UNSIGNED NULL,
    `notes`              TEXT NULL,
    `reminder_minutes`   INT UNSIGNED NOT NULL DEFAULT 60,
    `reminder_sent`      TINYINT(1) NOT NULL DEFAULT 0,
    `send_patient_reminder` TINYINT(1) NOT NULL DEFAULT 0,
    `patient_reminder_sent` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_start_at`   (`start_at`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_owner_id`   (`owner_id`),
    INDEX `idx_user_id`    (`user_id`),
    INDEX `idx_status`     (`status`),
    CONSTRAINT `fk_appt_patient`  FOREIGN KEY (`patient_id`)        REFERENCES `patients` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_appt_owner`    FOREIGN KEY (`owner_id`)          REFERENCES `owners` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_appt_user`     FOREIGN KEY (`user_id`)           REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_appt_tt`       FOREIGN KEY (`treatment_type_id`) REFERENCES `treatment_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── appointment_waitlist (Migration 006) ──────────────────────
CREATE TABLE IF NOT EXISTS `appointment_waitlist` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`        INT UNSIGNED NULL,
    `owner_id`          INT UNSIGNED NULL,
    `treatment_type_id` INT UNSIGNED NULL,
    `preferred_date`    DATE NULL,
    `notes`             TEXT NULL,
    `status`            ENUM('waiting','scheduled','cancelled') NOT NULL DEFAULT 'waiting',
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_aw_status` (`status`),
    CONSTRAINT `fk_aw_patient` FOREIGN KEY (`patient_id`)        REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_aw_owner`   FOREIGN KEY (`owner_id`)          REFERENCES `owners` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_aw_tt`      FOREIGN KEY (`treatment_type_id`) REFERENCES `treatment_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── invoices (Migration 001 + 007 + 016) ─────────────────────
CREATE TABLE IF NOT EXISTS `invoices` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `owner_id`       INT UNSIGNED NOT NULL,
    `patient_id`     INT UNSIGNED NULL,
    `status`         ENUM('draft','open','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    `cancellation_reason` TEXT NULL,
    `issue_date`     DATE NOT NULL,
    `due_date`       DATE NULL,
    `total_net`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_tax`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_gross`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes`          TEXT NULL,
    `diagnosis`      TEXT NULL,
    `payment_terms`  TEXT NULL,
    `payment_method` ENUM('rechnung','bar') NOT NULL DEFAULT 'rechnung',
    `paid_at`        DATETIME NULL,
    `email_sent_at`  DATETIME NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_owner_id`      (`owner_id`),
    INDEX `idx_patient_id`    (`patient_id`),
    INDEX `idx_status`        (`status`),
    INDEX `idx_issue_date`    (`issue_date`),
    INDEX `idx_payment_method`(`payment_method`),
    CONSTRAINT `fk_invoices_owner`   FOREIGN KEY (`owner_id`)   REFERENCES `owners` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_invoices_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── invoice_positions (Migration 001) ────────────────────────
CREATE TABLE IF NOT EXISTS `invoice_positions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`  INT UNSIGNED NOT NULL,
    `description` VARCHAR(500) NOT NULL,
    `quantity`    DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `unit_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_rate`    DECIMAL(5,2) NOT NULL DEFAULT 19.00,
    `total`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `sort_order`  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_invoice_id` (`invoice_id`),
    CONSTRAINT `fk_positions_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── invoice_reminders (Migration 017) ────────────────────────
CREATE TABLE IF NOT EXISTS `invoice_reminders` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`    INT UNSIGNED NOT NULL,
    `sent_at`       DATETIME NULL,
    `sent_to`       VARCHAR(255) NULL,
    `due_date`      DATE NULL,
    `fee`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes`         TEXT NULL,
    `pdf_generated` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ir_invoice` (`invoice_id`),
    CONSTRAINT `fk_ir_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ir_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── invoice_dunnings (Migration 017) ─────────────────────────
CREATE TABLE IF NOT EXISTS `invoice_dunnings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`    INT UNSIGNED NOT NULL,
    `level`         TINYINT NOT NULL DEFAULT 1,
    `sent_at`       DATETIME NULL,
    `sent_to`       VARCHAR(255) NULL,
    `due_date`      DATE NULL,
    `fee`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes`         TEXT NULL,
    `pdf_generated` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_id_invoice` (`invoice_id`),
    CONSTRAINT `fk_id_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_id_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── user_preferences (Migration 004) ─────────────────────────
CREATE TABLE IF NOT EXISTS `user_preferences` (
    `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `key`     VARCHAR(100) NOT NULL,
    `value`   LONGTEXT,
    UNIQUE KEY `uq_user_pref` (`user_id`, `key`),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── mobile_api_tokens (Migration 018) ────────────────────────
CREATE TABLE IF NOT EXISTS `mobile_api_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token`      VARCHAR(64)  NOT NULL UNIQUE,
    `device_name` VARCHAR(100) NOT NULL DEFAULT '',
    `tenant_prefix` VARCHAR(64) NOT NULL DEFAULT '',
    `last_used`  DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_user`  (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── cron_job_log (Migration 020) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `cron_job_log` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_key`      VARCHAR(64) NOT NULL,
    `status`       ENUM('success','error','skipped') NOT NULL DEFAULT 'success',
    `message`      TEXT NULL,
    `duration_ms`  INT UNSIGNED NULL,
    `triggered_by` ENUM('cron','manual','pixel') NOT NULL DEFAULT 'cron',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_job_key` (`job_key`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── befundboegen (Migration 023) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `befundboegen` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`       INT UNSIGNED NOT NULL,
    `owner_id`         INT UNSIGNED NULL,
    `created_by`       INT UNSIGNED NULL,
    `status`           ENUM('entwurf','abgeschlossen','versendet') NOT NULL DEFAULT 'entwurf',
    `datum`            DATE NOT NULL,
    `naechster_termin` DATE NULL,
    `notizen`          TEXT NULL,
    `pdf_path`         VARCHAR(500) NULL,
    `pdf_sent_at`      DATETIME NULL,
    `pdf_sent_to`      VARCHAR(255) NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_patient` (`patient_id`),
    KEY `idx_owner`   (`owner_id`),
    KEY `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `befundbogen_felder` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `befundbogen_id` INT UNSIGNED NOT NULL,
    `feldname`       VARCHAR(100) NOT NULL,
    `feldwert`       MEDIUMTEXT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_befundbogen` (`befundbogen_id`),
    FOREIGN KEY (`befundbogen_id`) REFERENCES `befundboegen`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── vet_reports (Migration 015) ───────────────────────────────
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

-- ── expenses (Migration 034) ──────────────────────────────────
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

-- ── TherapyCare Pro (Migration 039/040) ────────────────────────
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
    CONSTRAINT `fk_tcp_rq_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_rq_owner`   FOREIGN KEY (`owner_id`)   REFERENCES `owners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── migrations (tracking) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `migrations` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `version`    INT NOT NULL UNIQUE,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── cron_dispatcher_log (Dispatcher für alle Cronjobs) ──────────
CREATE TABLE IF NOT EXISTS `cron_dispatcher_log` (
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT,
  `job_key`    varchar(64)      NOT NULL COMMENT 'Job identifier: birthday, calendar_reminders, google_sync, tcp_reminders, holiday_greetings',
  `status`     enum('success','error','skipped') NOT NULL DEFAULT 'success',
  `message`    text             DEFAULT NULL,
  `duration_ms` int(11) unsigned DEFAULT NULL,
  `created_at` datetime         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_key` (`job_key`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── settings seed data ────────────────────────────────────────
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('company_name',                    ''),
('company_street',                  ''),
('company_zip',                     ''),
('company_city',                    ''),
('company_phone',                   ''),
('company_email',                   ''),
('company_website',                 ''),
('company_logo',                    ''),
('bank_name',                       ''),
('bank_iban',                       ''),
('bank_bic',                        ''),
('tax_number',                      ''),
('vat_number',                      ''),
('default_tax_rate',                '19'),
('payment_terms',                   'Bitte überweisen Sie den Betrag innerhalb von 14 Tagen.'),
('invoice_prefix',                  'RE'),
('invoice_start_number',            '1000'),
('smtp_host',                       'localhost'),
('smtp_port',                       '587'),
('smtp_username',                   ''),
('smtp_password',                   ''),
('smtp_encryption',                 'tls'),
('mail_from_address',               ''),
('mail_from_name',                  ''),
('default_language',                'de'),
('default_theme',                   'dark'),
('tenant_uuid',                     ''),
('license_token',                   ''),
('license_checked_at',              ''),
('email_payment_reminder_subject',  'Zahlungserinnerung: Rechnung {{invoice_number}}'),
('email_payment_reminder_body',     ''),
('email_dunning_subject',           '{{dunning_level}}. Mahnung: Rechnung {{invoice_number}}'),
('email_dunning_body',              ''),
('dunning_default_fee',             '5.00'),
('reminder_default_days',           '7'),
('db_version',                      '48');

-- ── treatment_types seed data ─────────────────────────────────
INSERT IGNORE INTO `treatment_types` (`name`, `color`, `price`, `sort_order`) VALUES
('Physiotherapie',    '#4f7cff', NULL, 1),
('Massage',           '#a855f7', NULL, 2),
('Akupunktur',        '#22c55e', NULL, 3),
('Hydrotherapie',     '#06b6d4', NULL, 4),
('Elektrotherapie',   '#f59e0b', NULL, 5),
('Manuelle Therapie', '#ef4444', NULL, 6),
('Kontrolle',         '#9090b0', NULL, 7);

-- ── migration version tracking ────────────────────────────────
INSERT IGNORE INTO `migrations` (`version`) VALUES
(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),
(11),(12),(13),(14),(15),(16),(17),(18),(19),(20),
(21),(22),(23),(24),(25),(26),(27),(28),(29),(30),
(31),(32),(33),(34),(35),(36),(37),(38),(39),(40),
(41),(42),(43),(44),(45),(46),(47),(48);

SET FOREIGN_KEY_CHECKS = 1;
