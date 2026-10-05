-- Migration 082: Repariert fehlendes AUTO_INCREMENT auf homework_templates
-- Gleiches Muster wie Migration 081 (portal_homework_plans/tasks): Auf
-- manchen Tenants fehlt AUTO_INCREMENT auf der id-Spalte, wodurch jeder
-- INSERT ohne explizite id stillschweigend id=0 bekommt. Das neue
-- "Vorlage automatisch speichern"-Feature beschreibt homework_templates
-- erstmals aus dem Patientenakte-/Besitzerportal-Flow heraus und deckte
-- den Fehler auf: "Duplicate entry '0' for key 'PRIMARY'".
ALTER TABLE `homework_templates`
    MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;
