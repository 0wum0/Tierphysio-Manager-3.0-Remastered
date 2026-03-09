-- ============================================================
-- Tierphysio Tenant Schema
-- Applied when a new Praxis instance is provisioned
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name`   VARCHAR(100) NOT NULL,
  `last_name`    VARCHAR(100) NOT NULL DEFAULT '',
  `email`        VARCHAR(200) NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL,
  `role`         ENUM('admin','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `last_login`   DATETIME,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100) PRIMARY KEY,
  `value`      LONGTEXT,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `owners` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name`  VARCHAR(100) NOT NULL,
  `email`      VARCHAR(200),
  `phone`      VARCHAR(50),
  `address`    VARCHAR(300),
  `city`       VARCHAR(100),
  `zip`        VARCHAR(20),
  `country`    CHAR(2) NOT NULL DEFAULT 'DE',
  `notes`      TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patients` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id`    INT UNSIGNED,
  `name`        VARCHAR(200) NOT NULL,
  `species`     VARCHAR(100),
  `breed`       VARCHAR(100),
  `gender`      ENUM('männlich','weiblich','kastriert','sterilisiert','unbekannt') NOT NULL DEFAULT 'unbekannt',
  `birth_date`  DATE,
  `chip_number` VARCHAR(100),
  `notes`       TEXT,
  `photo_path`  VARCHAR(500),
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_patient_owner` FOREIGN KEY (`owner_id`) REFERENCES `owners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appointments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `patient_id`  INT UNSIGNED,
  `user_id`     INT UNSIGNED,
  `title`       VARCHAR(300) NOT NULL,
  `description` TEXT,
  `start_at`    DATETIME NOT NULL,
  `end_at`      DATETIME NOT NULL,
  `status`      ENUM('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `color`       VARCHAR(20) DEFAULT '#2563eb',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_apt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoices` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id`     INT UNSIGNED,
  `patient_id`   INT UNSIGNED,
  `invoice_nr`   VARCHAR(50) NOT NULL,
  `status`       ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `issue_date`   DATE NOT NULL,
  `due_date`     DATE,
  `subtotal`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate`     DECIMAL(5,2) NOT NULL DEFAULT 19.00,
  `tax_amount`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes`        TEXT,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_inv_owner`   FOREIGN KEY (`owner_id`)   REFERENCES `owners` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_id`  INT UNSIGNED NOT NULL,
  `description` VARCHAR(500) NOT NULL,
  `quantity`    DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_item_inv` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `waitlist` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id`    INT UNSIGNED,
  `patient_id`  INT UNSIGNED,
  `notes`       TEXT,
  `priority`    TINYINT NOT NULL DEFAULT 0,
  `status`      ENUM('waiting','scheduled','removed') NOT NULL DEFAULT 'waiting',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_preferences` (
  `id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `key`     VARCHAR(100) NOT NULL,
  `value`   LONGTEXT,
  UNIQUE KEY `uq_user_pref` (`user_id`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `version`    INT NOT NULL UNIQUE,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('company_name', ''),
('company_address', ''),
('company_phone', ''),
('company_email', ''),
('invoice_prefix', 'RE-'),
('invoice_next_nr', '1'),
('tax_rate', '19'),
('currency', 'EUR'),
('tenant_uuid', ''),
('license_token', ''),
('license_checked_at', ''),
('db_version', '1');

INSERT IGNORE INTO `migrations` (`version`) VALUES (1);

SET FOREIGN_KEY_CHECKS = 1;
