-- Migration 005: Hausaufgaben-Checkliste für Besitzerportal
-- Speichert pro Besitzer welche Aufgaben abgehakt wurden

CREATE TABLE IF NOT EXISTS `{PREFIX}portal_homework_task_checks` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id`    INT UNSIGNED NOT NULL COMMENT 'Referenz auf portal_homework_plan_tasks.id',
    `plan_id`    INT UNSIGNED NOT NULL COMMENT 'Referenz auf portal_homework_plans.id',
    `owner_id`   INT UNSIGNED NOT NULL,
    `checked`    TINYINT(1) NOT NULL DEFAULT 0,
    `checked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_task_owner` (`task_id`, `owner_id`),
    INDEX `idx_htc_plan_id`  (`plan_id`),
    INDEX `idx_htc_owner_id` (`owner_id`),
    CONSTRAINT `fk_htc_task` FOREIGN KEY (`task_id`)
        REFERENCES `{PREFIX}portal_homework_plan_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
