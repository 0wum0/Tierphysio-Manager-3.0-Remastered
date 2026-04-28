-- ============================================================
-- SaaS Platform Migration 003: Early Tester / Founders System
-- Erweitert subscriptions um founders_discount Status
-- ============================================================

SET NAMES utf8mb4;

-- 1. founders_discount Status zur subscriptions-Enum hinzufügen
ALTER TABLE `subscriptions`
  MODIFY COLUMN IF EXISTS `status`
    ENUM('trial','trialing','active','past_due','cancelled','expired','suspended','grace','founders_discount')
    NOT NULL DEFAULT 'trial';

-- 2. is_founder Flag in tenants für schnellen Zugriff
ALTER TABLE `tenants`
  ADD COLUMN IF NOT EXISTS `is_founder` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Früh-Tester / Gründer-Rabatt (€39/Monat statt €99)'
    AFTER `status`;

-- 3. founder_since Timestamp
ALTER TABLE `tenants`
  ADD COLUMN IF NOT EXISTS `founder_since` DATETIME NULL
    COMMENT 'Datum seit dem der Founder-Status gilt'
    AFTER `is_founder`;

-- 4. max_founders Setting (Standard: 20)
INSERT IGNORE INTO `saas_settings` (`key`, `value`, `type`, `group`, `label`)
VALUES ('max_founders', '20', 'integer', 'billing', 'Max. Early Tester / Founders (0=unbegrenzt)');

-- 5. founders_price Setting (Sonderpreis in €)
INSERT IGNORE INTO `saas_settings` (`key`, `value`, `type`, `group`, `label`)
VALUES ('founders_price', '39.00', 'string', 'billing', 'Founders-Preis (€/Monat)');
