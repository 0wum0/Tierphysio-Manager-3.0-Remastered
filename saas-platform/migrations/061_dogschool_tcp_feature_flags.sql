-- ═══════════════════════════════════════════════════════════════
--  Migration 061: Zusätzliche Feature-Flags für TCP, Rechnungen,
--                 DATEV-Export und Trainer-Team-Management
--
--  Verwendet dasselbe Schema wie 060 (siehe 050_feature_gating.sql):
--    • Tabelle `saas_feature_flags` (Spalten: feature_key, label,
--      description, required_plan, global_enabled)
--    • Tabelle `plans` (Spalte `features` = JSON-Array)
-- ═══════════════════════════════════════════════════════════════

-- ────────────────────────────────────────────────────────────
-- 1. Neue Feature-Keys registrieren (idempotent)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `saas_feature_flags`
    (`feature_key`, `label`, `description`, `required_plan`, `global_enabled`)
VALUES
    ('dogschool_exercises',      'Übungen-Katalog',          'Standard- und eigene Übungen verwalten',                              'basic', 1),
    ('dogschool_progress',       'Trainingsfortschritt',     'Mastery-Level-Tracking pro Hund und Übung',                           'basic', 1),
    ('dogschool_homework',       'Hausaufgaben',             'Hausaufgaben-Management für Halter',                                   'pro',   1),
    ('dogschool_invoicing',      'Hundeschul-Rechnungen',    'Automatische Rechnungserstellung aus Kursen und Paketen',             'basic', 1),
    ('dogschool_datev_export',   'DATEV-Steuerexport',       'CSV-Export für Steuerberater / DATEV-Import',                         'pro',   1),
    ('dogschool_trainers',       'Trainer-Team-Management',  'Trainer-Profile und Verfügbarkeiten verwalten',                       'pro',   1),
    ('dogschool_categories',     'Kursarten-Editor',         'Eigene Kursart-Katalog-Einträge verwalten',                            'basic', 1);

-- ────────────────────────────────────────────────────────────
-- 2. Features auf passende Pläne verteilen (idempotent)
--    Das JSON_MERGE_PRESERVE + NOT JSON_CONTAINS-Muster
--    verhindert Doppelung bei mehrfachem Ausführen.
-- ────────────────────────────────────────────────────────────

-- ─── Basic-Pläne: Kernmodule (Übungen, Fortschritt, Rechnungen, Kursarten) ───
UPDATE `plans`
   SET `features` = JSON_MERGE_PRESERVE(
         COALESCE(`features`, JSON_ARRAY()),
         JSON_ARRAY(
             'dogschool_exercises',
             'dogschool_progress',
             'dogschool_invoicing',
             'dogschool_categories'
         )
       )
 WHERE `slug` LIKE '%basic%'
   AND NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"dogschool_exercises"');

-- ─── Pro-Pläne: Basic-Set + Hausaufgaben + Trainer + DATEV ───
UPDATE `plans`
   SET `features` = JSON_MERGE_PRESERVE(
         COALESCE(`features`, JSON_ARRAY()),
         JSON_ARRAY(
             'dogschool_exercises',
             'dogschool_progress',
             'dogschool_homework',
             'dogschool_invoicing',
             'dogschool_datev_export',
             'dogschool_trainers',
             'dogschool_categories'
         )
       )
 WHERE `slug` LIKE '%pro%'
   AND NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"dogschool_homework"');

-- ─── Ultra / Praxis / Enterprise / Business: alles ───
UPDATE `plans`
   SET `features` = JSON_MERGE_PRESERVE(
         COALESCE(`features`, JSON_ARRAY()),
         JSON_ARRAY(
             'dogschool_exercises',
             'dogschool_progress',
             'dogschool_homework',
             'dogschool_invoicing',
             'dogschool_datev_export',
             'dogschool_trainers',
             'dogschool_categories'
         )
       )
 WHERE (`slug` LIKE '%ultra%' OR `slug` LIKE '%praxis%'
     OR `slug` LIKE '%enterprise%' OR `slug` LIKE '%business%')
   AND NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"dogschool_homework"');
