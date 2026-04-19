-- ═══════════════════════════════════════════════════════════════
--  Migration 052: Fehlende Feature-Keys für Plugins registrieren
--
--  Problem:
--    Die Template-/Plugin-Layer verwendet Feature-Keys, die bisher
--    nicht in `saas_feature_flags` registriert waren. Der
--    FeatureGateService ignoriert unbekannte Keys → Twig bekommt
--    `false` zurück → UI-Elemente sind versteckt, selbst wenn der
--    Tenant einen Ultra-Plan hat.
--
--  Fix:
--    1. Neue Keys als Kill-Switch registrieren (INSERT IGNORE)
--    2. Automatisch zur Ultra-/Pro-Plan-Feature-Liste hinzufügen,
--       damit bestehende Ultra-Tenants sofort Zugriff haben.
--
--  ALLES ADDITIV / IDEMPOTENT.
-- ═══════════════════════════════════════════════════════════════

-- 1. Neue Feature-Flags registrieren
INSERT IGNORE INTO `saas_feature_flags`
    (`feature_key`, `label`, `description`, `required_plan`, `global_enabled`)
VALUES
    ('patient_invite', 'Einladungslinks',        'Patienten per Link einladen & Stammdaten erfassen', 'pro',   1),
    ('therapy_care',   'TherapyCare Pro',        'Fortschritts-Tracking, Therapieberichte',           'ultra', 1),
    ('tax_export',     'Steuerexport (GoBD)',    'DATEV/ELSTER-Export, GoBD-konformes Audit-Log',     'ultra', 1),
    ('vet_report',     'Tierarztberichte',       'Strukturierte Tierarztberichte',                    'pro',   1);

-- 2. Ultra/Praxis-Plans: alle neuen Keys anhängen (idempotent mit JSON_CONTAINS-Check)
UPDATE `plans`
   SET `features` = JSON_ARRAY_APPEND(COALESCE(`features`, JSON_ARRAY()), '$', 'patient_invite')
 WHERE `slug` IN ('ultra','praxis','enterprise','business','pro')
   AND NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"patient_invite"');

UPDATE `plans`
   SET `features` = JSON_ARRAY_APPEND(COALESCE(`features`, JSON_ARRAY()), '$', 'therapy_care')
 WHERE `slug` IN ('ultra','praxis','enterprise','business')
   AND NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"therapy_care"');

UPDATE `plans`
   SET `features` = JSON_ARRAY_APPEND(COALESCE(`features`, JSON_ARRAY()), '$', 'tax_export')
 WHERE `slug` IN ('ultra','praxis','enterprise','business')
   AND NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"tax_export"');

UPDATE `plans`
   SET `features` = JSON_ARRAY_APPEND(COALESCE(`features`, JSON_ARRAY()), '$', 'vet_report')
 WHERE `slug` IN ('ultra','praxis','enterprise','business','pro')
   AND NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"vet_report"');
