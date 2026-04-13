-- Migration 015: Tierarztbericht-Verlauf
-- Stores generated vet report PDFs per patient

CREATE TABLE IF NOT EXISTS `vet_reports` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`   INT UNSIGNED NOT NULL,
    `created_by`   INT UNSIGNED NULL,
    `filename`     VARCHAR(255) NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_vr_patient` (`patient_id`),
    CONSTRAINT `fk_vr_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vr_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
