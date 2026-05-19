-- ============================================================
-- Migration 069: Feedback-Tabelle um Support-Felder erweitern
-- Fügt subject, type, status, current_url, user_id, user_agent,
-- priority und attachment_path hinzu.
-- Idempotent: MigrationService toleriert 1060 (Duplicate column).
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `feedback`
  ADD COLUMN `subject`      VARCHAR(255) DEFAULT NULL AFTER `category`,
  ADD COLUMN `type`         ENUM('bug','feature','support','question','praise','other') NOT NULL DEFAULT 'other' AFTER `subject`,
  ADD COLUMN `status`       ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open' AFTER `type`,
  ADD COLUMN `priority`     ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal' AFTER `status`,
  ADD COLUMN `current_url`  VARCHAR(500) DEFAULT NULL AFTER `priority`,
  ADD COLUMN `user_id`      INT UNSIGNED DEFAULT NULL AFTER `current_url`,
  ADD COLUMN `user_name`    VARCHAR(200) DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `user_agent`   TEXT DEFAULT NULL AFTER `user_name`,
  ADD COLUMN `attachment_path` VARCHAR(500) DEFAULT NULL AFTER `user_agent`,
  ADD COLUMN `admin_note`   TEXT DEFAULT NULL AFTER `attachment_path`,
  ADD COLUMN `resolved_at`  DATETIME DEFAULT NULL AFTER `admin_note`;
