-- ============================================================
-- SaaS Platform Global Migration 001: Dynamic Plan System
-- Runs against the SHARED SaaS database (not per-tenant).
-- Safe, fully additive – no existing data is removed or renamed.
-- Tracked in: saas_migrations table
-- ============================================================

SET NAMES utf8mb4;

-- ────────────────────────────────────────────────────────────────
-- 1. plans – neue Spalten hinzufügen
-- ────────────────────────────────────────────────────────────────
ALTER TABLE `plans`
  ADD COLUMN IF NOT EXISTS `trial_days`
    TINYINT UNSIGNED NOT NULL DEFAULT 14
    COMMENT 'Kostenlose Testtage für diesen Plan'
    AFTER `is_active`,

  ADD COLUMN IF NOT EXISTS `is_public`
    TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Im öffentlichen Registrierungsdialog anzeigen'
    AFTER `trial_days`,

  ADD COLUMN IF NOT EXISTS `currency`
    CHAR(3) NOT NULL DEFAULT 'EUR'
    AFTER `is_public`,

  ADD COLUMN IF NOT EXISTS `stripe_price_id`
    VARCHAR(100) NULL
    COMMENT 'Stripe Price ID (monatlich)'
    AFTER `currency`,

  ADD COLUMN IF NOT EXISTS `stripe_price_id_yearly`
    VARCHAR(100) NULL
    COMMENT 'Stripe Price ID (jährlich)'
    AFTER `stripe_price_id`;

-- ────────────────────────────────────────────────────────────────
-- 2. plans – Standardwerte befüllen
-- ────────────────────────────────────────────────────────────────
UPDATE `plans` SET `trial_days` = 14  WHERE `trial_days` = 0 AND `slug` != 'free';
UPDATE `plans` SET `is_public`  = 1   WHERE `is_public`  IS NULL OR `is_public` = 0;

-- ────────────────────────────────────────────────────────────────
-- 3. subscriptions – Status-ENUM erweitern
-- ────────────────────────────────────────────────────────────────
ALTER TABLE `subscriptions`
  MODIFY COLUMN IF EXISTS `status`
    ENUM('trial','trialing','active','past_due','cancelled','expired','suspended')
    NOT NULL DEFAULT 'trial';

-- ────────────────────────────────────────────────────────────────
-- 4. subscriptions – neue Lebenszyklus-Spalten
-- ────────────────────────────────────────────────────────────────
ALTER TABLE `subscriptions`
  ADD COLUMN IF NOT EXISTS `trial_starts_at`
    DATETIME NULL
    COMMENT 'Beginn der Testphase'
    AFTER `cancelled_at`,

  ADD COLUMN IF NOT EXISTS `trial_ends_at`
    DATETIME NULL
    COMMENT 'Ende der Testphase'
    AFTER `trial_starts_at`,

  ADD COLUMN IF NOT EXISTS `billing_starts_at`
    DATETIME NULL
    COMMENT 'Beginn der kostenpflichtigen Abrechnung'
    AFTER `trial_ends_at`,

  ADD COLUMN IF NOT EXISTS `grandfathered_price`
    DECIMAL(10,2) NULL
    COMMENT 'Eingefrierter Sonderpreis (Early-Adopter)'
    AFTER `billing_starts_at`,

  ADD COLUMN IF NOT EXISTS `grandfathered_reason`
    VARCHAR(100) NULL
    COMMENT 'Begründung für Sonderpreis (early-adopter, contract, …)'
    AFTER `grandfathered_price`,

  ADD COLUMN IF NOT EXISTS `pricing_note`
    VARCHAR(255) NULL
    COMMENT 'Freitext-Notiz zum Preis'
    AFTER `grandfathered_reason`,

  ADD COLUMN IF NOT EXISTS `stripe_price_id`
    VARCHAR(100) NULL
    COMMENT 'Stripe Price ID des aktiven Abos'
    AFTER `pricing_note`,

  ADD COLUMN IF NOT EXISTS `last_webhook_sync_at`
    DATETIME NULL
    COMMENT 'Letzter erfolgreicher Stripe-Webhook-Sync'
    AFTER `stripe_price_id`;

-- ────────────────────────────────────────────────────────────────
-- 5. subscription_events – Audit-Log für Abo-Ereignisse
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `subscription_events` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED    NOT NULL,
  `event`      VARCHAR(80)     NOT NULL COMMENT 'z.B. trial_started, activated, grandfathered_set',
  `details`    JSON            NULL,
  `actor`      VARCHAR(100)    NULL     COMMENT 'Admin-Name oder system/cron',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_se_tenant` (`tenant_id`),
  KEY `idx_se_event`  (`event`),
  KEY `idx_se_created`(`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit-Log für Subscription-Lifecycle-Ereignisse';

-- ────────────────────────────────────────────────────────────────
-- 6. Backfill: trial_starts_at / trial_ends_at für bestehende Trials
-- ────────────────────────────────────────────────────────────────
UPDATE `subscriptions` s
  JOIN `tenants`        t ON t.id = s.tenant_id
SET
  s.trial_starts_at = COALESCE(s.started_at, t.created_at, NOW()),
  s.trial_ends_at   = COALESCE(t.trial_ends_at, s.ends_at,
                                DATE_ADD(COALESCE(s.started_at, NOW()), INTERVAL 14 DAY))
WHERE s.status IN ('trial', 'trialing')
  AND s.trial_starts_at IS NULL;
