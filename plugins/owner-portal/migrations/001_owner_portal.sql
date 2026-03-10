-- Migration 001: Owner Portal tables

CREATE TABLE IF NOT EXISTS `owner_portal_users` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_id`        INT UNSIGNED NOT NULL,
    `email`           VARCHAR(255) NOT NULL,
    `password_hash`   VARCHAR(255) NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `invite_token`    VARCHAR(64) NULL,
    `invite_expires`  DATETIME NULL,
    `invite_used_at`  DATETIME NULL,
    `last_login`      DATETIME NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`),
    UNIQUE KEY `uq_invite_token` (`invite_token`),
    INDEX `idx_owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `owner_portal_login_attempts` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(255) NOT NULL,
    `ip`         VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email_ip` (`email`, `ip`),
    INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pet_exercises` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`  INT UNSIGNED NOT NULL,
    `title`       VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `video_url`   VARCHAR(500) NULL,
    `image`       VARCHAR(255) NULL,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
