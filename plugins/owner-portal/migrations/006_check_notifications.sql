-- Migration 006: Benachrichtigungen wenn Besitzer Aufgaben abhakt
-- Wird von der Webseite und Flutter abgerufen

CREATE TABLE IF NOT EXISTS `portal_check_notifications` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_id`    INT UNSIGNED NOT NULL,
    `patient_id`  INT UNSIGNED NOT NULL,
    `task_id`     INT UNSIGNED NULL COMMENT 'portal_homework_plan_tasks.id (NULL = Übung)',
    `exercise_id` INT UNSIGNED NULL COMMENT 'pet_exercises.id (NULL = Hausaufgabe)',
    `plan_id`     INT UNSIGNED NULL,
    `task_title`  VARCHAR(255) NOT NULL,
    `owner_name`  VARCHAR(255) NOT NULL,
    `pet_name`    VARCHAR(255) NOT NULL,
    `type`        ENUM('homework','exercise') NOT NULL DEFAULT 'homework',
    `checked`     TINYINT(1) NOT NULL DEFAULT 1,
    `read_at`     DATETIME NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pcn_owner_id`   (`owner_id`),
    INDEX `idx_pcn_patient_id` (`patient_id`),
    INDEX `idx_pcn_read_at`    (`read_at`),
    INDEX `idx_pcn_created`    (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
