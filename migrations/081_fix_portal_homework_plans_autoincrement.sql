-- Migration 081: Repariert fehlendes AUTO_INCREMENT auf portal_homework_plans / tasks
-- Auf manchen Tenants wurden diese Tabellen durch eine alte Codepfad-Variante
-- (ensurePlansTablesExist) ohne AUTO_INCREMENT auf `id` angelegt, bevor
-- Migration 020/075 griff (CREATE TABLE IF NOT EXISTS ändert eine bereits
-- bestehende Tabelle nicht). Jeder INSERT ohne explizite id bekam dadurch
-- stillschweigend id=0 — der zweite Insert scheiterte dann mit
-- "Duplicate entry '0' for key 'PRIMARY'".
ALTER TABLE `portal_homework_plans`
    MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `portal_homework_plan_tasks`
    MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `homework_plan_meta`
    MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;
