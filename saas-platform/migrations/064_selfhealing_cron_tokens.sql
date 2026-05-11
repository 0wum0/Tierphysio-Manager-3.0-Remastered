-- ═══════════════════════════════════════════════════════════════
--  Migration 064: Self-Healing Cron-Tokens für alle Tenants
--
--  PROBLEM:
--    Cron-Jobs scheiterten mit HTTP 401 / 403 / 500 weil die
--    Cron-Tokens in den Tenant-Settings nicht gesetzt waren:
--    - portal_smart_reminder_token  → 401 (Smart Erinnerungen)
--    - cron_secret                  → 403 (Feiertagsgrüße)
--    - birthday_cron_token          → 500 (Geburtstagsmail)
--    - cron_dispatcher_token        → 401 (Dispatcher)
--    - calendar_cron_secret         → 401 (Kalender-Erinnerungen)
--    - tcp_cron_token               → 401 (TCP-Erinnerungen)
--    - google_sync_cron_secret      → 403 (Google Sync)
--
--  FIX:
--    Setzt für alle fehlenden Tokens einen Platzhalter-Wert
--    ('__PENDING__'), der beim nächsten Cron-Aufruf sofort durch
--    einen kryptografisch sicheren Token ersetzt wird
--    (Self-Healing in CronController + HolidayController +
--     OwnerPortalAdminController).
--
--    Token 'missing' → Self-Healing generiert sofort echten Token
--    Token '__PENDING__' → idem (isValidCronToken = false)
--    Token '' (leer) → idem
--
--  SICHERHEIT:
--    - INSERT IGNORE → überschreibt niemals bestehende Tokens
--    - Kein DROP / DELETE auf produktive Daten
--    - Nur Tokens anlegen die noch nicht existieren
--    - Alle Tokens werden beim ersten echten Cron-Aufruf
--      sofort durch sichere 64-Hex-Tokens ersetzt
--
--  IDEMPOTENT: Mehrfaches Ausführen ist sicher.
--
--  HINWEIS ZU MIGRATIONSAUSFÜHRUNG:
--    Der MigrationService läuft pro Tenant mit gesetztem Prefix.
--    `settings` bezieht sich daher auf {prefix}settings.
--    Kein expliziter Prefix nötig – der Service setzt ihn.
-- ═══════════════════════════════════════════════════════════════

-- ────────────────────────────────────────────────────────────
-- 1. cron_dispatcher_token (Haupt-Dispatcher)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`key`, `value`, `updated_at`)
VALUES ('cron_dispatcher_token', '__PENDING__', NOW());

-- ────────────────────────────────────────────────────────────
-- 2. birthday_cron_token (Geburtstagsmail)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`key`, `value`, `updated_at`)
VALUES ('birthday_cron_token', '__PENDING__', NOW());

-- ────────────────────────────────────────────────────────────
-- 3. cron_secret (Feiertagsgrüße / holiday-cron)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`key`, `value`, `updated_at`)
VALUES ('cron_secret', '__PENDING__', NOW());

-- ────────────────────────────────────────────────────────────
-- 4. calendar_cron_secret (Kalender-Erinnerungen)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`key`, `value`, `updated_at`)
VALUES ('calendar_cron_secret', '__PENDING__', NOW());

-- ────────────────────────────────────────────────────────────
-- 5. tcp_cron_token (TherapyCare Erinnerungen)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`key`, `value`, `updated_at`)
VALUES ('tcp_cron_token', '__PENDING__', NOW());

-- ────────────────────────────────────────────────────────────
-- 6. google_sync_cron_secret (Google Kalender Sync)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`key`, `value`, `updated_at`)
VALUES ('google_sync_cron_secret', '__PENDING__', NOW());

-- ────────────────────────────────────────────────────────────
-- 7. portal_smart_reminder_token (Smart Erinnerungen Portal)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`key`, `value`, `updated_at`)
VALUES ('portal_smart_reminder_token', '__PENDING__', NOW());
