-- Migration 025: Performance Indexes
-- Adds missing indexes on high-traffic columns.
-- Each statement is separate so the MigrationService try/catch silently
-- ignores "Duplicate key name" errors (MySQL 5.7+/8+) — safe to re-run.

-- ── appointments ──────────────────────────────────────────────────────────────
ALTER TABLE `appointments` ADD INDEX `idx_apt_start_at`    (`start_at`);
ALTER TABLE `appointments` ADD INDEX `idx_apt_end_at`      (`end_at`);
ALTER TABLE `appointments` ADD INDEX `idx_apt_patient_id`  (`patient_id`);
ALTER TABLE `appointments` ADD INDEX `idx_apt_owner_id`    (`owner_id`);
ALTER TABLE `appointments` ADD INDEX `idx_apt_status`      (`status`);
ALTER TABLE `appointments` ADD INDEX `idx_apt_start_status`(`start_at`, `status`);
ALTER TABLE `appointments` ADD INDEX `idx_apt_reminder`    (`reminder_sent`, `status`, `start_at`);

-- ── patients ──────────────────────────────────────────────────────────────────
ALTER TABLE `patients` ADD INDEX `idx_pat_owner_id` (`owner_id`);
ALTER TABLE `patients` ADD INDEX `idx_pat_status`   (`status`);
ALTER TABLE `patients` ADD INDEX `idx_pat_name`     (`name`);

-- ── patient_timeline ──────────────────────────────────────────────────────────
ALTER TABLE `patient_timeline` ADD INDEX `idx_timeline_patient_id` (`patient_id`);
ALTER TABLE `patient_timeline` ADD INDEX `idx_timeline_entry_date` (`entry_date`);
ALTER TABLE `patient_timeline` ADD INDEX `idx_timeline_type`       (`type`);

-- ── invoices ──────────────────────────────────────────────────────────────────
ALTER TABLE `invoices` ADD INDEX `idx_inv_owner_id`   (`owner_id`);
ALTER TABLE `invoices` ADD INDEX `idx_inv_patient_id` (`patient_id`);
ALTER TABLE `invoices` ADD INDEX `idx_inv_status`     (`status`);
ALTER TABLE `invoices` ADD INDEX `idx_inv_issue_date` (`issue_date`);
ALTER TABLE `invoices` ADD INDEX `idx_inv_due_date`   (`due_date`);

-- ── invoice_positions ─────────────────────────────────────────────────────────
ALTER TABLE `invoice_positions` ADD INDEX `idx_invpos_invoice_id` (`invoice_id`);

-- ── owners ────────────────────────────────────────────────────────────────────
ALTER TABLE `owners` ADD INDEX `idx_owners_last_name` (`last_name`);
ALTER TABLE `owners` ADD INDEX `idx_owners_email`     (`email`);
