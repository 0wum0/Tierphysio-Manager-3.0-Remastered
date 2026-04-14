-- ============================================================
-- Migration 006: Feedback-Tabelle + Zahlungsanbieter-Felder
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Feedback von Tenants (aus TierPhysio App)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feedback` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`   INT UNSIGNED,
  `tenant_name` VARCHAR(200),
  `email`       VARCHAR(200),
  `category`    ENUM('bug','feature','praise','other') NOT NULL DEFAULT 'other',
  `message`     TEXT NOT NULL,
  `rating`      TINYINT UNSIGNED DEFAULT NULL COMMENT '1-5 Sterne',
  `app_version` VARCHAR(50),
  `platform`    VARCHAR(50) COMMENT 'web, android, ios',
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `read_at`     DATETIME DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_feedback_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Stripe/PayPal Felder in tenants
-- ------------------------------------------------------------
ALTER TABLE `tenants`
  ADD COLUMN IF NOT EXISTS `stripe_customer_id` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `paypal_customer_id` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `payment_provider`   ENUM('stripe','paypal','manual') DEFAULT 'manual',
  ADD COLUMN IF NOT EXISTS `tid`                VARCHAR(50)  DEFAULT NULL UNIQUE COMMENT 'Kurzkennung für Tenant-Prefix';

-- ------------------------------------------------------------
-- Stripe/PayPal Felder in subscriptions
-- ------------------------------------------------------------
ALTER TABLE `subscriptions`
  ADD COLUMN IF NOT EXISTS `stripe_sub_id`      VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `paypal_sub_id`       VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `last_payment_at`     DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `last_payment_status` VARCHAR(50) DEFAULT NULL;

-- ------------------------------------------------------------
-- Monatliche Umsatzzahlen (für Charts)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `revenue_snapshots` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `year`        SMALLINT UNSIGNED NOT NULL,
  `month`       TINYINT UNSIGNED NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `count`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Anzahl aktiver Abos',
  `new_tenants` INT UNSIGNED NOT NULL DEFAULT 0,
  `churn`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_rev_ym` (`year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialdaten für letzten 12 Monate (werden per Cron überschrieben)
INSERT IGNORE INTO `saas_settings` (`key`, `value`) VALUES
  ('stripe_enabled',        '0'),
  ('stripe_public_key',     ''),
  ('stripe_secret_key',     ''),
  ('stripe_webhook_secret', ''),
  ('paypal_enabled',        '0'),
  ('paypal_client_id',      ''),
  ('paypal_client_secret',  ''),
  ('paypal_sandbox',        '1');
