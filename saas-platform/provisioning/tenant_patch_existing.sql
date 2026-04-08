-- ============================================================
-- PATCH für bereits existierende Tenant-Tabellen
-- Führe dieses Script in phpMyAdmin aus (Datenbank: u772175418_therapano)
-- Ersetze demo_praxis_tierphys_6771f0_ mit dem echten Prefix des Tenants
-- (z.B. t_demo-praxis-a1b2c3_ — steht in der tenants.db_name Spalte)
-- ============================================================
-- Den Prefix findest du mit:
-- SELECT db_name FROM tenants LIMIT 5;
-- ============================================================

SET @prefix = 'demo_praxis_tierphys_6771f0';  -- <-- ANPASSEN!

SET FOREIGN_KEY_CHECKS = 0;

-- ── users: name statt first_name/last_name, active statt is_active ──────────
-- Spalte 'name' hinzufügen (kombiniert first_name + last_name)
ALTER TABLE `t_demo_praxis_tierphys_6771f0_users`
    ADD COLUMN IF NOT EXISTS `name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `id`;

-- name aus first_name + last_name befüllen
UPDATE `t_demo_praxis_tierphys_6771f0_users`
    SET `name` = TRIM(CONCAT(COALESCE(`first_name`, ''), ' ', COALESCE(`last_name`, '')))
    WHERE `name` = '' OR `name` IS NULL;

-- active Spalte hinzufügen (war is_active)
ALTER TABLE `t_demo_praxis_tierphys_6771f0_users`
    ADD COLUMN IF NOT EXISTS `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`;

-- active aus is_active befüllen
UPDATE `t_demo_praxis_tierphys_6771f0_users` SET `active` = `is_active` WHERE 1;

-- ── invoices: fehlende Spalten ───────────────────────────────────────────────
ALTER TABLE `t_demo_praxis_tierphys_6771f0_invoices`
    ADD COLUMN IF NOT EXISTS `invoice_number` VARCHAR(50) NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `total_net`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `due_date`,
    ADD COLUMN IF NOT EXISTS `total_tax`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `total_net`,
    ADD COLUMN IF NOT EXISTS `total_gross`    DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `total_tax`,
    ADD COLUMN IF NOT EXISTS `diagnosis`      TEXT NULL AFTER `notes`,
    ADD COLUMN IF NOT EXISTS `payment_terms`  TEXT NULL AFTER `diagnosis`,
    ADD COLUMN IF NOT EXISTS `payment_method` ENUM('rechnung','bar') NOT NULL DEFAULT 'rechnung' AFTER `payment_terms`,
    ADD COLUMN IF NOT EXISTS `paid_at`        DATETIME NULL AFTER `payment_method`,
    ADD COLUMN IF NOT EXISTS `email_sent_at`  DATETIME NULL AFTER `paid_at`;

-- invoice_number aus invoice_nr befüllen falls vorhanden
UPDATE `t_demo_praxis_tierphys_6771f0_invoices`
    SET `invoice_number` = `invoice_nr`
    WHERE `invoice_number` IS NULL AND `invoice_nr` IS NOT NULL;

-- total_gross aus total befüllen falls vorhanden
UPDATE `t_demo_praxis_tierphys_6771f0_invoices`
    SET `total_gross` = `total`, `total_net` = `subtotal`, `total_tax` = `tax_amount`
    WHERE `total_gross` = 0 AND `total` > 0;

-- ── fehlende Tabellen erstellen ──────────────────────────────────────────────

-- treatment_types
CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_treatment_types` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `color`       VARCHAR(7) NOT NULL DEFAULT '#4f7cff',
    `price`       DECIMAL(10,2) NULL,
    `description` TEXT NULL,
    `active`      TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `t_demo_praxis_tierphys_6771f0_treatment_types` (`name`, `color`, `price`, `sort_order`) VALUES
('Physiotherapie',    '#4f7cff', NULL, 1),
('Massage',           '#a855f7', NULL, 2),
('Akupunktur',        '#22c55e', NULL, 3),
('Hydrotherapie',     '#06b6d4', NULL, 4),
('Elektrotherapie',   '#f59e0b', NULL, 5),
('Manuelle Therapie', '#ef4444', NULL, 6),
('Kontrolle',         '#9090b0', NULL, 7);

-- patient_timeline (falls nicht vorhanden)
CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_patient_timeline` (
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
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- invoice_positions (falls nicht vorhanden — war invoice_items)
CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_invoice_positions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`  INT UNSIGNED NOT NULL,
    `description` VARCHAR(500) NOT NULL,
    `quantity`    DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `unit_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_rate`    DECIMAL(5,2) NOT NULL DEFAULT 19.00,
    `total`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `sort_order`  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- invoice_reminders
CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_invoice_reminders` (
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
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- invoice_dunnings
CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_invoice_dunnings` (
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
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mobile_api_tokens
CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_mobile_api_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL UNIQUE,
    `device`     VARCHAR(255) NULL,
    `last_used`  DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cron_job_log
CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_cron_job_log` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_key`      VARCHAR(64) NOT NULL,
    `status`       ENUM('success','error','skipped') NOT NULL DEFAULT 'success',
    `message`      TEXT NULL,
    `duration_ms`  INT UNSIGNED NULL,
    `triggered_by` ENUM('cron','manual','pixel') NOT NULL DEFAULT 'cron',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- befundboegen
CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_befundboegen` (
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
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_befundbogen_felder` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `befundbogen_id` INT UNSIGNED NOT NULL,
    `feldname`       VARCHAR(100) NOT NULL,
    `feldwert`       MEDIUMTEXT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user_preferences (falls nicht vorhanden)
CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_user_preferences` (
    `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `key`     VARCHAR(100) NOT NULL,
    `value`   LONGTEXT,
    UNIQUE KEY `uq_user_pref` (`user_id`, `key`),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migrations tracking
CREATE TABLE IF NOT EXISTS `t_demo_praxis_tierphys_6771f0_migrations` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `version`    INT NOT NULL UNIQUE,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `t_demo_praxis_tierphys_6771f0_migrations` (`version`) VALUES
(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),
(11),(12),(13),(14),(15),(16),(17),(18),(19),(20),
(21),(22),(23);

-- settings aktualisieren
INSERT IGNORE INTO `t_demo_praxis_tierphys_6771f0_settings` (`key`, `value`) VALUES
('company_street',                  ''),
('company_zip',                     ''),
('company_city',                    ''),
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
('email_payment_reminder_subject',  'Zahlungserinnerung: Rechnung {{invoice_number}}'),
('email_payment_reminder_body',     ''),
('email_dunning_subject',           '{{dunning_level}}. Mahnung: Rechnung {{invoice_number}}'),
('email_dunning_body',              ''),
('dunning_default_fee',             '5.00'),
('reminder_default_days',           '7');

UPDATE `t_demo_praxis_tierphys_6771f0_settings` SET `value` = '23' WHERE `key` = 'db_version';

SET FOREIGN_KEY_CHECKS = 1;
