-- Migration 003: Ensure all owner-portal tables exist (safe re-run)
-- Runs CREATE TABLE IF NOT EXISTS for all tables so missing tables are created
-- without affecting existing data.

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
    INDEX `idx_pet_exercises_patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portal_homework_plans` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`          INT UNSIGNED NOT NULL,
    `owner_id`            INT UNSIGNED NOT NULL,
    `plan_date`           DATE NOT NULL,
    `physio_principles`   TEXT NULL,
    `short_term_goals`    TEXT NULL,
    `long_term_goals`     TEXT NULL,
    `therapy_means`       TEXT NULL,
    `general_notes`       TEXT NULL,
    `next_appointment`    VARCHAR(255) NULL,
    `therapist_name`      VARCHAR(255) NULL,
    `status`              ENUM('active','archived') NOT NULL DEFAULT 'active',
    `pdf_sent_at`         DATETIME NULL,
    `pdf_sent_to`         VARCHAR(255) NULL,
    `created_by`          INT UNSIGNED NULL,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_php_patient_id` (`patient_id`),
    INDEX `idx_php_owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portal_homework_plan_tasks` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plan_id`           INT UNSIGNED NOT NULL,
    `template_id`       INT UNSIGNED NULL,
    `title`             VARCHAR(255) NOT NULL,
    `description`       TEXT NULL,
    `frequency`         VARCHAR(255) NULL,
    `duration`          VARCHAR(255) NULL,
    `therapist_notes`   TEXT NULL,
    `sort_order`        INT NOT NULL DEFAULT 0,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_phpt_plan_id` (`plan_id`),
    CONSTRAINT `fk_phpt_plan_003` FOREIGN KEY (`plan_id`) REFERENCES `portal_homework_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
