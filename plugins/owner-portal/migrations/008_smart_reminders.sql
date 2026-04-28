-- Migration 008: Smart Erinnerungen (Übungs-Erinnerung + Inaktivitäts-Erkennung)
-- Speichert gesendete Portal-Erinnerungen zum Deduplizieren und Tracken

CREATE TABLE IF NOT EXISTS `{PREFIX}portal_smart_reminders` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_id`    INT UNSIGNED NOT NULL,
    `type`        ENUM('exercise','inactivity','homework') NOT NULL,
    `ref_id`      INT UNSIGNED NULL COMMENT 'plan_id / exercise_id (optional)',
    `email`       VARCHAR(255) NOT NULL,
    `sent_at`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status`      ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    `error`       TEXT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_psr_owner`   (`owner_id`),
    INDEX `idx_psr_type`    (`type`),
    INDEX `idx_psr_sent`    (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Einstellungen für Smart Erinnerungen in Settings (Standardwerte)
INSERT IGNORE INTO `{PREFIX}settings` (`key`, `value`)
VALUES
    ('portal_exercise_reminder_days', '3'),
    ('portal_inactivity_days', '14');
