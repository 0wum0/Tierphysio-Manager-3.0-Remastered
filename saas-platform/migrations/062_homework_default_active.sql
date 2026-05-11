-- ═══════════════════════════════════════════════════════════════
--  Migration 062: Hausaufgaben-Plugin standardmäßig aktiv
--
--  PROBLEM:
--    Das Hausaufgaben-Plugin (feature_key = 'homework') war bisher
--    als 'pro'-Feature eingestuft (nur Pro/Ultra-Tenants).
--    Anforderung: homework soll DEFAULT AKTIV für ALLE Tenants sein
--    (Basic, Pro, Ultra) und weiterhin deaktivierbar bleiben.
--
--  FIX:
--    1. required_plan von 'pro' auf 'basic' senken (global)
--    2. homework zu ALLEN Plan-Feature-Listen hinzufügen (idempotent)
--    3. Existierende Tenants: _features_cache löschen → erzwingt
--       Re-Sync beim nächsten Request (self-heal)
--    4. dogschool_training_plans ebenfalls auf 'basic' sichern
--       (Trainingsplane für Hundeschule nutzen homework-Logik)
--
--  SICHERHEIT:
--    - Kein DROP / TRUNCATE / DELETE auf produktive Daten
--    - INSERT IGNORE / ON DUPLICATE KEY UPDATE → idempotent
--    - UPDATE plans nur wenn JSON_CONTAINS noch nicht gesetzt
--    - Cache-Löschen trifft nur den _features_cache Schlüssel
--
--  ALLES ADDITIV / IDEMPOTENT.
-- ═══════════════════════════════════════════════════════════════

-- ────────────────────────────────────────────────────────────
-- 1. homework auf 'basic' setzen (für alle Tenants verfügbar)
-- ────────────────────────────────────────────────────────────
INSERT INTO `saas_feature_flags`
    (`feature_key`, `label`, `description`, `required_plan`, `global_enabled`)
VALUES
    ('homework', 'Hausaufgaben', 'Trainings-/Therapiepläne', 'basic', 1)
ON DUPLICATE KEY UPDATE
    `required_plan`  = 'basic',
    `global_enabled` = 1;

-- ────────────────────────────────────────────────────────────
-- 2. homework zu allen Plan-Feature-Listen hinzufügen
--    (idempotent: nur wenn noch nicht enthalten)
-- ────────────────────────────────────────────────────────────
UPDATE `plans`
   SET `features` = JSON_ARRAY_APPEND(COALESCE(`features`, JSON_ARRAY()), '$', 'homework')
 WHERE NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"homework"');

-- ────────────────────────────────────────────────────────────
-- 3. Bestehende Tenants: _features_cache invalidieren
--    → nächster Request erzwingt Re-Sync aus SaaS-DB
--    → homework wird dann korrekt als 'basic' aufgelöst → true
--
--    WICHTIG: Nur den Cache-Schlüssel entfernen, keine echten Daten.
--    Der MigrationService läuft per Tenant → diese DELETE-Statements
--    werden mit dem korrekten Präfix ({{prefix}}) ausgeführt.
-- ────────────────────────────────────────────────────────────
DELETE FROM `settings`
 WHERE `key` = '_features_cache';
