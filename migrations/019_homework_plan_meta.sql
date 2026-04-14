-- Hausaufgaben Plan-Metadaten pro Patient
CREATE TABLE IF NOT EXISTS `homework_plan_meta` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id` INT UNSIGNED NOT NULL,
    `physiotherapeutische_grundsaetze` TEXT NULL,
    `kurzfristige_ziele` TEXT NULL,
    `langfristige_ziele` TEXT NULL,
    `therapiemittel` VARCHAR(500) NULL,
    `beachte_hinweise` TEXT NULL,
    `wiedervorstellung_date` DATE NULL,
    `therapist_name` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_homework_plan_meta_patient` (`patient_id`),
    CONSTRAINT `fk_homework_plan_meta_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
