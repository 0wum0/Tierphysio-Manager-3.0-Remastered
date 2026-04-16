-- ============================================================
-- SaaS Platform Global Migration 002: Lifecycle E-Mail System
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tenant_lifecycle_emails` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED  NOT NULL,
  `email_key`   VARCHAR(80)   NOT NULL
    COMMENT 'welcome|trial_warning_4d|trial_expired|activated|backup_ready',
  `sent_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `to_email`    VARCHAR(255)  NOT NULL,
  `subject`     VARCHAR(255)  NOT NULL DEFAULT '',
  `status`      ENUM('sent','failed','skipped') NOT NULL DEFAULT 'sent',
  `error`       TEXT          NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY  `uq_tenant_key` (`tenant_id`, `email_key`),
  KEY `idx_le_tenant`  (`tenant_id`),
  KEY `idx_le_key`     (`email_key`),
  KEY `idx_le_sent`    (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks which lifecycle emails have been sent per tenant';
