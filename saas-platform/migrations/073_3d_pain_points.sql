-- Migration 073: 3D Anatomie-Schmerzanalyse
-- Speichert interaktive Schmerzpunkte aus dem 3D-Viewer pro Patient.
-- Prefix-Platzhalter: {{prefix}} (wird vom SaaS-MigrationService ersetzt)

CREATE TABLE IF NOT EXISTS `{{prefix}}patient_3d_pain_points` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `patient_id`         INT UNSIGNED  NOT NULL,
    `animal_type`        VARCHAR(20)   NOT NULL DEFAULT 'dog',
    `muscle_group_id`    VARCHAR(100)  NOT NULL,
    `muscle_group_label` VARCHAR(255)  NOT NULL DEFAULT '',
    `region`             VARCHAR(100)  NOT NULL DEFAULT '',
    `side`               VARCHAR(20)   NOT NULL DEFAULT 'midline',
    `pain_level`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `pain_type`          VARCHAR(50)   NOT NULL DEFAULT '',
    `notes`              TEXT          NULL,
    `created_by`         INT UNSIGNED  NULL,
    `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_patient_id`  (`patient_id`),
    INDEX `idx_animal_type` (`animal_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
