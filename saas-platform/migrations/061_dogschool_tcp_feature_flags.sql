-- ═══════════════════════════════════════════════════════════════
--  Migration 061: Zusätzliche Feature-Flags für TCP, Rechnungen
--                 und Trainer-Team-Management
-- ═══════════════════════════════════════════════════════════════

INSERT IGNORE INTO `saas_feature_flags` (`key`, `name`, `description`, `category`, `global_enabled`) VALUES
    ('dogschool_training_plans', 'Trainingspläne (TCP)',    'Trainingsplan-Vorlagen + individuelle Pläne + Fortschritt + Hausaufgaben', 'dogschool', 1),
    ('dogschool_exercises',      'Übungen-Katalog',          'Standard- und eigene Übungen verwalten',                                  'dogschool', 1),
    ('dogschool_progress',       'Trainingsfortschritt',     'Mastery-Level-Tracking pro Hund und Übung',                               'dogschool', 1),
    ('dogschool_homework',       'Hausaufgaben',             'Hausaufgaben-Management für Halter',                                       'dogschool', 1),
    ('dogschool_invoicing',      'Hundeschul-Rechnungen',    'Automatische Rechnungserstellung aus Kursen und Paketen',                 'dogschool', 1),
    ('dogschool_datev_export',   'DATEV-Steuerexport',       'CSV-Export für Steuerberater / DATEV-Import',                             'dogschool', 1),
    ('dogschool_trainers',       'Trainer-Team-Management',  'Trainer-Profile und Verfügbarkeiten verwalten',                           'dogschool', 1),
    ('dogschool_categories',     'Kursarten-Editor',         'Eigene Kursart-Katalog-Einträge verwalten',                                'dogschool', 1);

-- ─── Zuweisung zu Plänen (Trainer-Tarife) ──────────────────────
--   Annahme: Spalte `features` in saas_plans ist JSON-Array oder CSV.
--   Hier idempotent via UPDATE + REPLACE auf CSV-Feature-Liste.

-- Basic Trainer
UPDATE `saas_plans`
   SET `features` = CONCAT_WS(',', COALESCE(NULLIF(`features`,''), ''),
       'dogschool_training_plans','dogschool_exercises','dogschool_progress',
       'dogschool_invoicing','dogschool_categories')
 WHERE slug LIKE 'trainer_basic%' OR slug LIKE 'dogschool_basic%';

-- Pro Trainer
UPDATE `saas_plans`
   SET `features` = CONCAT_WS(',', COALESCE(NULLIF(`features`,''), ''),
       'dogschool_training_plans','dogschool_exercises','dogschool_progress',
       'dogschool_homework','dogschool_invoicing','dogschool_categories',
       'dogschool_trainers','dogschool_datev_export')
 WHERE slug LIKE 'trainer_pro%' OR slug LIKE 'dogschool_pro%';

-- Ultra Trainer
UPDATE `saas_plans`
   SET `features` = CONCAT_WS(',', COALESCE(NULLIF(`features`,''), ''),
       'dogschool_training_plans','dogschool_exercises','dogschool_progress',
       'dogschool_homework','dogschool_invoicing','dogschool_datev_export',
       'dogschool_trainers','dogschool_categories')
 WHERE slug LIKE 'trainer_ultra%' OR slug LIKE 'dogschool_ultra%' OR slug LIKE '%ultra%';
