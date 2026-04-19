-- ═══════════════════════════════════════════════════════════════
--  Migration 051: Plan-Feature-Listen mit saas_feature_flags synchronisieren
--
--  Problem (vor 050):
--    Der ursprüngliche Seed in 001_initial_schema.sql hat in plans.features
--    fiktive Keys hinterlegt ("dashboard","waitlist","intake","staff",
--    "reports","premium") — diese existieren NICHT in saas_feature_flags.
--    Nach Aktivierung der authoritative Plan-Matrix-Resolution würden
--    Pro und Praxis dadurch auf die 4 zufällig validen Keys
--    (patients/owners/appointments/invoices) degradieren.
--
--  Fix: Sinnvolle Default-Belegung je Plan setzen, aber NUR wenn noch der
--       originale Legacy-Seed vorhanden ist (= JSON enthält "waitlist" oder
--       "dashboard" oder ist NULL/leer). Admin-eigene Belegungen bleiben
--       dadurch unberührt.
--
--  ALLES ADDITIV / IDEMPOTENT.
-- ═══════════════════════════════════════════════════════════════

-- Basic: nur Kernfunktionen des kleinsten Modells
UPDATE `plans`
   SET `features` = JSON_ARRAY(
         'patients','owners','appointments','invoices'
       )
 WHERE `slug` = 'basic'
   AND (
         `features` IS NULL
         OR JSON_LENGTH(`features`) = 0
         OR JSON_CONTAINS(`features`, '"waitlist"')
         OR JSON_CONTAINS(`features`, '"dashboard"')
       );

-- Pro: alle basic + pro-tier Features
UPDATE `plans`
   SET `features` = JSON_ARRAY(
         'patients','owners','appointments','calendar','befunde','invoices',
         'uploads','notifications',
         'homework','reminders','templates','mobile_api','exports'
       )
 WHERE `slug` = 'pro'
   AND (
         `features` IS NULL
         OR JSON_LENGTH(`features`) = 0
         OR JSON_CONTAINS(`features`, '"waitlist"')
         OR JSON_CONTAINS(`features`, '"dashboard"')
       );

-- Ultra / Praxis: vollständig
UPDATE `plans`
   SET `features` = JSON_ARRAY(
         'patients','owners','appointments','calendar','befunde','invoices',
         'uploads','notifications',
         'homework','reminders','templates','mobile_api','exports',
         'dunning','expenses','google_calendar_sync','patient_portal',
         'patient_intake','bulk_mail','ki_assistance','analytics'
       )
 WHERE `slug` IN ('ultra','praxis','enterprise','business')
   AND (
         `features` IS NULL
         OR JSON_LENGTH(`features`) = 0
         OR JSON_CONTAINS(`features`, '"waitlist"')
         OR JSON_CONTAINS(`features`, '"dashboard"')
       );
