-- Fügt Soft-Delete-Spalte für "Aus der Anmeldungs-Liste ausblenden" hinzu.
-- Der Eintrag wird NICHT gelöscht – Besitzer/Patient bleiben erhalten.
--
-- Hinweis: einzelne ALTER-Statements statt Multi-Clause, damit
-- MySQL < 8.0.21 / MariaDB < 10.5.6 (die IF NOT EXISTS auf ADD COLUMN
-- nicht unterstützen) über die Errno-Tolerance (1060/1061) idempotent bleiben.
ALTER TABLE `{{prefix}}patient_intake_submissions`
    ADD COLUMN `hidden_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`;

ALTER TABLE `{{prefix}}patient_intake_submissions`
    ADD INDEX `idx_hidden_at` (`hidden_at`);
