-- ═══════════════════════════════════════════════════════════════
--  Migration 060: Hundeschul-/Hundetrainer-Modus Feature-Flags
--
--  Führt die kompletten dogschool_* Feature-Keys ein.
--
--  ARCHITEKTUR-PRINZIPIEN:
--    * Diese Features sind NUR nutzbar wenn der Tenant den Typ
--      `practice_type = 'trainer'` hat (siehe FeatureGateService).
--    * Zusätzlich gilt die normale Plan-Gating-Logik (Basic/Pro/Ultra).
--    * Globaler Kill-Switch (`global_enabled = 0`) sperrt systemweit.
--
--  ALLES ADDITIV / IDEMPOTENT.
-- ═══════════════════════════════════════════════════════════════

-- ────────────────────────────────────────────────────────────
-- 1. Neue Feature-Flags registrieren
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `saas_feature_flags`
    (`feature_key`, `label`, `description`, `required_plan`, `global_enabled`)
VALUES
    -- Basic-Tier: Kern-Arbeitsfläche jeder Hundeschule
    ('dogschool_dashboard',           'Hundeschul-Dashboard',         'Startseite mit heutigen Kursen, Anfragen, Zahlungen', 'basic', 1),
    ('dogschool_courses',             'Kurse',                         'Gruppenkurse/Workshops anlegen und verwalten',         'basic', 1),
    ('dogschool_group_training',      'Gruppentraining',               'Einheiten mit mehreren Hunden pro Termin',             'basic', 1),
    ('dogschool_training_plans',      'Trainingspläne',                'Individuelle Übungen pro Hund (reused: homework)',     'basic', 1),
    ('dogschool_templates',           'Kurs-/Text-Vorlagen',           'Wiederverwendbare Kurs- und Nachrichtenvorlagen',      'basic', 1),
    ('dogschool_media',               'Medien-Dokumentation',          'Fotos/Videos zu Hunden, Kursen und Sessions',          'basic', 1),

    -- Pro-Tier: Organisatorische Module
    ('dogschool_attendance',          'Anwesenheitsverwaltung',        'Anwesenheit je Kurstermin dokumentieren',              'pro',   1),
    ('dogschool_waitlist',            'Warteliste',                    'Wartelisten + Nachrück-Logik',                         'pro',   1),
    ('dogschool_packages',            'Pakete / Mehrfachkarten',       '5er/10er-Karten, Guthabenstunden, Paketverkauf',       'pro',   1),
    ('dogschool_consents',            'Digitale Einwilligungen',       'Teilnahmebedingungen, Foto-/Video-Einverständnis',     'pro',   1),
    ('dogschool_trainer_management',  'Trainer-/Team-Verwaltung',      'Mehrere Trainer, Verfügbarkeit, Rollen',               'pro',   1),
    ('dogschool_events',              'Events / Social Walks',         'Offene Gruppenveranstaltungen mit Anmeldung',          'pro',   1),
    ('dogschool_leads',               'Interessenten / Leads',         'Probetrainings-Anfragen + Konversion',                 'pro',   1),

    -- Ultra-Tier: Premium-Analytik + Self-Service
    ('dogschool_online_booking',      'Online-Buchung',                'Öffentliches Buchungsportal für Kurse/Probetraining',  'ultra', 1),
    ('dogschool_progress_tracking',   'Fortschrittsverfolgung',        'Trainingsstand, Ziele, Verlaufskurven',                'ultra', 1),
    ('dogschool_reports',             'Auswertungen / Reports',        'Teilnahme-/Umsatz-/Paket-Reports',                     'ultra', 1);

-- ────────────────────────────────────────────────────────────
-- 2. Plan-Feature-Listen ergänzen
--    Hundeschul-Pläne haben evtl. ihren eigenen slug, deshalb
--    generische Namens-Muster mit LIKE matchen.
--    Wir aktualisieren ALLE bestehenden Pläne additiv, weil im
--    System aktuell keine separaten Hundeschul-Pläne existieren —
--    Hundeschul-Tenants bekommen ihr Gating über `practice_type`
--    (nicht über einen separaten Plan).
--
--    ACHTUNG: Das entsperrt die Features nur FEATURE-TECHNISCH.
--    Praxis-Tenants sehen sie trotzdem nicht, weil das
--    FeatureGateService-Tenant-Typ-Gate `practice_type = 'trainer'`
--    das verhindert.
-- ────────────────────────────────────────────────────────────

-- Basic: nur Kernmodule
UPDATE `plans`
   SET `features` = JSON_MERGE_PRESERVE(
         COALESCE(`features`, JSON_ARRAY()),
         JSON_ARRAY(
             'dogschool_dashboard', 'dogschool_courses', 'dogschool_group_training',
             'dogschool_training_plans', 'dogschool_templates', 'dogschool_media'
         )
       )
 WHERE `slug` LIKE '%basic%'
   AND NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"dogschool_dashboard"');

-- Pro: Basic + Organisatorisch
UPDATE `plans`
   SET `features` = JSON_MERGE_PRESERVE(
         COALESCE(`features`, JSON_ARRAY()),
         JSON_ARRAY(
             'dogschool_dashboard', 'dogschool_courses', 'dogschool_group_training',
             'dogschool_training_plans', 'dogschool_templates', 'dogschool_media',
             'dogschool_attendance', 'dogschool_waitlist', 'dogschool_packages',
             'dogschool_consents', 'dogschool_trainer_management', 'dogschool_events',
             'dogschool_leads'
         )
       )
 WHERE `slug` LIKE '%pro%'
   AND NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"dogschool_dashboard"');

-- Ultra / Praxis / Enterprise / Business: alles
UPDATE `plans`
   SET `features` = JSON_MERGE_PRESERVE(
         COALESCE(`features`, JSON_ARRAY()),
         JSON_ARRAY(
             'dogschool_dashboard', 'dogschool_courses', 'dogschool_group_training',
             'dogschool_training_plans', 'dogschool_templates', 'dogschool_media',
             'dogschool_attendance', 'dogschool_waitlist', 'dogschool_packages',
             'dogschool_consents', 'dogschool_trainer_management', 'dogschool_events',
             'dogschool_leads',
             'dogschool_online_booking', 'dogschool_progress_tracking', 'dogschool_reports'
         )
       )
 WHERE (`slug` LIKE '%ultra%' OR `slug` LIKE '%praxis%' OR `slug` LIKE '%enterprise%' OR `slug` LIKE '%business%')
   AND NOT JSON_CONTAINS(COALESCE(`features`, JSON_ARRAY()), '"dogschool_dashboard"');
