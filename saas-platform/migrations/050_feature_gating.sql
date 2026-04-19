-- ═══════════════════════════════════════════════════════════════
--  Migration 050: Zentrales Feature-Gating-System
--
--  Ziel: Alle Funktionen der Praxis-Software zentral über die SaaS-
--  Plattform deaktivierbar — global + per Plan + per Tenant.
--
--  ALLES ADDITIV. Keine bestehende Struktur wird verändert.
-- ═══════════════════════════════════════════════════════════════

-- Globale Kill-Switches je Feature
CREATE TABLE IF NOT EXISTS `saas_feature_flags` (
  `feature_key`     VARCHAR(64) NOT NULL,
  `label`           VARCHAR(200) NOT NULL,
  `description`     VARCHAR(500) DEFAULT NULL,
  `required_plan`   ENUM('basic','pro','ultra') NOT NULL DEFAULT 'basic',
  `global_enabled`  TINYINT(1) NOT NULL DEFAULT 1
                    COMMENT 'Master kill switch. 0 = für ALLE Tenants aus',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`feature_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-Tenant Feature-Override (nullable → nutzt Plan-Default)
ALTER TABLE `tenants`
    ADD COLUMN IF NOT EXISTS `features_override` JSON DEFAULT NULL
        COMMENT 'Map feature_key => bool. NULL = Plan-Default, true = force on, false = force off';

-- Seed: alle bekannten Features (idempotent via INSERT IGNORE)
INSERT IGNORE INTO `saas_feature_flags` (`feature_key`, `label`, `description`, `required_plan`, `global_enabled`) VALUES
  ('patients',              'Patientenakte',                'Patienten anlegen, bearbeiten, anzeigen',     'basic', 1),
  ('owners',                'Tierhalter',                   'Tierhalter verwalten',                        'basic', 1),
  ('appointments',          'Termine',                      'Terminplanung',                               'basic', 1),
  ('calendar',              'Kalender-Ansicht',             'Interaktiver Terminkalender',                 'basic', 1),
  ('befunde',               'Befundbögen',                  'Interaktive Befundung inkl. Anatomie',        'basic', 1),
  ('invoices',              'Rechnungen',                   'Rechnungsstellung',                           'basic', 1),
  ('uploads',               'Datei-Uploads',                'Dokumente & Bilder',                          'basic', 1),
  ('notifications',         'Benachrichtigungen',           'System-Benachrichtigungen',                   'basic', 1),

  ('homework',              'Hausaufgaben',                 'Trainings-/Therapiepläne',                    'pro',   1),
  ('reminders',             'Erinnerungen',                 'Automatische Erinnerungen',                   'pro',   1),
  ('templates',             'Vorlagen & Textbausteine',     'Befund-/Therapie-Vorlagen',                   'pro',   1),
  ('mobile_api',            'Mobile API',                   'REST-API für Mobile/Desktop-App',             'pro',   1),
  ('exports',               'Daten-Export',                 'CSV/PDF Export',                              'pro',   1),

  ('dunning',               'Mahnwesen',                    'Automatisches Mahnverfahren',                 'ultra', 1),
  ('expenses',              'Ausgaben',                     'Ausgaben-Verwaltung',                         'ultra', 1),
  ('google_calendar_sync',  'Google Calendar Sync',         'Google Kalender Integration',                 'ultra', 1),
  ('patient_portal',        'Tierhalter-Portal',            'Kundenportal mit Login',                      'ultra', 1),
  ('patient_intake',        'Online-Anmeldung',             'Online-Patientenanmeldung',                   'ultra', 1),
  ('bulk_mail',             'Serienmails',                  'Massen-/Geburtstags-Mails',                   'ultra', 1),
  ('ki_assistance',         'KI-Unterstützung',             'KI-Befund-Strukturierung',                    'ultra', 1),
  ('analytics',             'Analysen',                     'Erweitertes Reporting',                       'ultra', 1);
