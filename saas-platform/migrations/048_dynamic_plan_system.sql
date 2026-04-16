-- ============================================================
-- Migration 048: Dynamic Plan System
-- Safe additive upgrade – no existing data is removed or renamed.
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
-- 2. plans – Standardwerte befüllen & Preis des Basic-Plans korrigieren
-- ────────────────────────────────────────────────────────────────
UPDATE `plans` SET `trial_days` = 14, `is_public` = 1 WHERE 1=1;

-- Basic-Plan: Preis auf 39 € (Starter-Preismodell).
-- Nur wenn aktueller Preis noch unter dem neuen Wert liegt.
UPDATE `plans`
  SET `price_month` = 39.00,
      `price_year`  = 390.00
WHERE `slug` = 'basic'
  AND `price_month` < 39.00;

-- ────────────────────────────────────────────────────────────────
-- 3. subscriptions – status ENUM um neue Zustände erweitern
-- ────────────────────────────────────────────────────────────────
ALTER TABLE `subscriptions`
  MODIFY COLUMN `status`
    ENUM('trial','trialing','active','past_due','cancelled','expired','suspended')
    NOT NULL DEFAULT 'active';

-- ────────────────────────────────────────────────────────────────
-- 4. subscriptions – neue Lebenszyklus-Spalten additive hinzufügen
-- ────────────────────────────────────────────────────────────────
ALTER TABLE `subscriptions`
  ADD COLUMN IF NOT EXISTS `trial_starts_at`
    DATETIME NULL
    COMMENT 'Beginn der Testphase'
    AFTER `status`,

  ADD COLUMN IF NOT EXISTS `trial_ends_at`
    DATETIME NULL
    COMMENT 'Ende der Testphase'
    AFTER `trial_starts_at`,

  ADD COLUMN IF NOT EXISTS `billing_starts_at`
    DATETIME NULL
    COMMENT 'Zeitpunkt der ersten echten Abbuchung'
    AFTER `trial_ends_at`,

  ADD COLUMN IF NOT EXISTS `grandfathered_price`
    DECIMAL(10,2) NULL
    COMMENT 'Eingefrierter Sonderpreis (Early Adopter / Vertrag)'
    AFTER `billing_starts_at`,

  ADD COLUMN IF NOT EXISTS `grandfathered_reason`
    VARCHAR(255) NULL
    COMMENT 'Begründung des Sonderpreises (z.B. early-adopter-tester-01)'
    AFTER `grandfathered_price`,

  ADD COLUMN IF NOT EXISTS `pricing_note`
    VARCHAR(255) NULL
    COMMENT 'Admin-Notiz zur Preisgestaltung (frei textuell)'
    AFTER `grandfathered_reason`,

  ADD COLUMN IF NOT EXISTS `stripe_price_id`
    VARCHAR(100) NULL
    COMMENT 'Stripe Price ID (gesetzt beim Checkout, plan-spezifisch)'
    AFTER `pricing_note`,

  ADD COLUMN IF NOT EXISTS `last_webhook_sync_at`
    DATETIME NULL
    COMMENT 'Zeitstempel des letzten Stripe-Webhook-Syncs'
    AFTER `stripe_price_id`;

-- ────────────────────────────────────────────────────────────────
-- 5. subscription_events – Audit-Log für Abonnementereignisse
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `subscription_events` (
  `id`         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `tenant_id`  INT UNSIGNED  NOT NULL,
  `sub_id`     INT UNSIGNED  NULL,
  `event`      VARCHAR(100)  NOT NULL
    COMMENT 'plan_assigned|trial_started|trial_ended|activated|expired|canceled|grandfathered|stripe_sync|self_healed|plan_changed',
  `details`    JSON          NULL,
  `actor`      VARCHAR(100)  NULL DEFAULT 'system'
    COMMENT 'system|admin|stripe|cron',
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_subev_tenant`  (`tenant_id`),
  INDEX `idx_subev_event`   (`event`),
  INDEX `idx_subev_created` (`created_at`),
  CONSTRAINT `fk_subev_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────
-- 6. Bestehende trial-Abos rückwirkend mit Zeitstempeln befüllen
--    (nur wo trial_starts_at noch fehlt)
-- ────────────────────────────────────────────────────────────────
UPDATE `subscriptions` s
  JOIN `tenants` t ON t.id = s.tenant_id
SET
  s.`trial_starts_at` = t.`created_at`,
  s.`trial_ends_at`   = COALESCE(
    t.`trial_ends_at`,
    DATE_ADD(t.`created_at`, INTERVAL 14 DAY)
  )
WHERE s.`trial_starts_at` IS NULL
  AND t.`status` IN ('trial', 'pending')
  AND t.`created_at` IS NOT NULL;
