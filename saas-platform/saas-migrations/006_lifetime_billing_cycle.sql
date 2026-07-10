-- ────────────────────────────────────────────────────────────────
-- Migration 006: Lifetime-Lizenzen korrekt kennzeichnen
--
-- Problem: billing_cycle ENUM hatte kein 'lifetime', weshalb der
-- Cron-Runner Lifetime-Abos als normal-aktive Subscriptions behandelt
-- hat und sie nach Stripe-Sync als überfällig markiert hat.
-- ────────────────────────────────────────────────────────────────

-- 1. ENUM um 'lifetime' erweitern
ALTER TABLE `subscriptions`
    MODIFY COLUMN `billing_cycle`
        ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'monthly';

-- 2. Bestehende Lifetime-Abos kennzeichnen (ends_at = 2099-12-31)
UPDATE `subscriptions`
SET    `billing_cycle` = 'lifetime',
       `next_billing`  = NULL
WHERE  `ends_at` >= '2099-01-01'
   OR  `trial_ends_at` >= '2099-01-01';

-- 3. Dazugehörige Tenants sicherstellen: status = active, trial_ends_at weit in Zukunft
UPDATE `tenants`
SET    `status`        = 'active',
       `trial_ends_at` = '2099-12-31 23:59:59'
WHERE  `id` IN (
    SELECT `tenant_id` FROM `subscriptions` WHERE `billing_cycle` = 'lifetime'
);
