-- Fügt Soft-Delete-Spalte für "Aus der Einladungs-Liste ausblenden" hinzu.
-- Der Eintrag wird NICHT gelöscht – Besitzer/Tierhalter/Hund bleiben erhalten.
--
-- Einzelne ALTER-Statements für MySQL < 8.0.21 / MariaDB < 10.5.6 Kompatibilität
-- (IF NOT EXISTS auf ADD COLUMN nicht unterstützt → Errno 1060/1061 toleriert).
ALTER TABLE `{{prefix}}patient_invite_tokens`
    ADD COLUMN `hidden_at` DATETIME NULL DEFAULT NULL AFTER `created_at`;

ALTER TABLE `{{prefix}}patient_invite_tokens`
    ADD INDEX `idx_hidden_at` (`hidden_at`);
