-- Migration 004: Normalize google_calendar_sync_map unique constraint name.
--
-- Background: the constraint was historically created as 'uq_appin' on some
-- tenants. ON DUPLICATE KEY UPDATE in createSyncEntry() requires a stable
-- unique key on (appointment_id, connection_id). We drop the old name and
-- ensure the canonical 'uq_appointment_connection' key exists.
--
-- The ServiceProvider runs each SQL statement inside try/catch, so a
-- "Can't DROP ... check that column/key exists" error is silently swallowed.
-- That makes these three statements safe to run on any tenant regardless of
-- which key name already exists.

ALTER TABLE `google_calendar_sync_map` DROP KEY `uq_appin`;

ALTER TABLE `google_calendar_sync_map` DROP KEY `uq_appointment_connection`;

ALTER TABLE `google_calendar_sync_map`
    ADD UNIQUE KEY `uq_appointment_connection` (`appointment_id`, `connection_id`);
