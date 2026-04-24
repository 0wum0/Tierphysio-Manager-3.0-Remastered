-- Fügt Soft-Delete-Spalte für "Aus der Anmeldungs-Liste ausblenden" hinzu.
-- Der Eintrag wird NICHT gelöscht – Besitzer/Patient bleiben erhalten.
ALTER TABLE `{{prefix}}patient_intake_submissions`
    ADD COLUMN IF NOT EXISTS `hidden_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`,
    ADD INDEX IF NOT EXISTS `idx_hidden_at` (`hidden_at`);
