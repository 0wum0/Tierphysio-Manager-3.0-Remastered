-- Migration 002: Hausaufgaben-Pläne für das Besitzerportal

CREATE TABLE IF NOT EXISTS `{PREFIX}portal_homework_plans` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`          INT UNSIGNED NOT NULL,
    `owner_id`            INT UNSIGNED NOT NULL,
    `plan_date`           DATE NOT NULL,
    `physio_principles`   TEXT NULL COMMENT 'Physiotherapeutische Grundsätze',
    `short_term_goals`    TEXT NULL COMMENT 'Kurzfristige Ziele',
    `long_term_goals`     TEXT NULL COMMENT 'Langfristige Ziele',
    `therapy_means`       TEXT NULL COMMENT 'Therapiemittel',
    `general_notes`       TEXT NULL COMMENT 'Beachte / Hinweise',
    `next_appointment`    VARCHAR(255) NULL COMMENT 'Wiedervorstellungsdatum',
    `therapist_name`      VARCHAR(255) NULL COMMENT 'Name der Therapeutin',
    `status`              ENUM('active','archived') NOT NULL DEFAULT 'active',
    `pdf_sent_at`         DATETIME NULL,
    `pdf_sent_to`         VARCHAR(255) NULL,
    `created_by`          INT UNSIGNED NULL,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}portal_homework_plan_tasks` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plan_id`           INT UNSIGNED NOT NULL,
    `template_id`       INT UNSIGNED NULL COMMENT 'Referenz auf homework_templates (optional)',
    `title`             VARCHAR(255) NOT NULL,
    `description`       TEXT NULL,
    `frequency`         VARCHAR(255) NULL COMMENT 'z.B. 2-3x täglich',
    `duration`          VARCHAR(255) NULL COMMENT 'z.B. 10-20 Minuten',
    `therapist_notes`   TEXT NULL COMMENT 'Zusätzliche Hinweise',
    `sort_order`        INT NOT NULL DEFAULT 0,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_plan_id` (`plan_id`),
    CONSTRAINT `fk_plan_tasks_plan` FOREIGN KEY (`plan_id`) REFERENCES `{PREFIX}portal_homework_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
