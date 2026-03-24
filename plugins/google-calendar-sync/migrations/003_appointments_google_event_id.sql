-- Migration 003: google_event_id in appointments Tabelle
-- Google-Termine aus imported_events in die appointments-Tabelle übertragen
-- damit sie in der Flutter App sichtbar sind

ALTER TABLE `appointments`
    ADD COLUMN IF NOT EXISTS `google_event_id` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Google Calendar Event ID – NULL = kein Google-Termin'
        AFTER `invoice_id`,
    ADD UNIQUE KEY IF NOT EXISTS `uq_google_event_id` (`google_event_id`);

-- Backfill: Bestehende imported_events → appointments
INSERT INTO `appointments`
    (`title`, `description`, `start_at`, `end_at`, `status`,
     `google_event_id`, `color`, `all_day`, `created_at`, `updated_at`)
SELECT
    SUBSTRING(COALESCE(NULLIF(e.event_title, ''), '(Google Termin)'), 1, 255),
    COALESCE(NULLIF(e.event_description, ''), NULL),
    e.event_start,
    e.event_end,
    'scheduled',
    e.google_event_id,
    '#4285F4',
    e.is_all_day,
    e.created_at,
    NOW()
FROM `google_calendar_imported_events` e
WHERE e.google_status != 'cancelled'
  AND e.appointment_id IS NULL
  AND e.event_start IS NOT NULL
  AND e.event_end IS NOT NULL
  AND e.event_start < e.event_end
ON DUPLICATE KEY UPDATE
    title      = VALUES(title),
    updated_at = NOW();

-- Rückverlinkung: appointment_id in imported_events setzen
UPDATE `google_calendar_imported_events` e
INNER JOIN `appointments` a ON a.google_event_id = e.google_event_id
SET e.appointment_id = a.id
WHERE e.appointment_id IS NULL;
