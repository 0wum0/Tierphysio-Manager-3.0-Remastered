-- ============================================================
-- Migration 079 — Push Notification Tracking Columns
-- ============================================================
-- Tracks which appointments and invoices have already had
-- push notifications sent to avoid duplicates.

-- Appointment push tracking
ALTER TABLE `appointments`
    ADD COLUMN IF NOT EXISTS `push_1h_sent`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_sent`,
    ADD COLUMN IF NOT EXISTS `push_24h_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `push_1h_sent`;

-- Invoice overdue push tracking
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `push_overdue_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `due_date`;
