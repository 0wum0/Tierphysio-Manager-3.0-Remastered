-- Fügt Soft-Delete-Spalte für "Aus der Einladungs-Liste ausblenden" hinzu.
-- Der Eintrag wird NICHT gelöscht – Besitzer/Tierhalter/Hund bleiben erhalten.
ALTER TABLE `{{prefix}}patient_invite_tokens`
    ADD COLUMN IF NOT EXISTS `hidden_at` DATETIME NULL DEFAULT NULL AFTER `created_at`,
    ADD INDEX IF NOT EXISTS `idx_hidden_at` (`hidden_at`);
