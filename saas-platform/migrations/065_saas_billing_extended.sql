-- ═══════════════════════════════════════════════════════════════════════════
-- Migration 065: SaaS Billing Extended
-- Stripe-Auto-Rechnungen, Storno/Gutschrift, Mahnwesen, DATEV-Felder
-- ═══════════════════════════════════════════════════════════════════════════

-- Neue Spalten für saas_invoices (Stripe-Referenz, Typ, Storno)
ALTER TABLE `saas_invoices`
    ADD COLUMN `type` ENUM('invoice','credit_note','correction') NOT NULL DEFAULT 'invoice' AFTER `status`,
    ADD COLUMN `credit_note_for` INT UNSIGNED NULL AFTER `type`,
    ADD COLUMN `storno_reason` VARCHAR(500) NULL AFTER `credit_note_for`,
    ADD COLUMN `stripe_invoice_id` VARCHAR(128) NULL AFTER `storno_reason`,
    ADD COLUMN `stripe_subscription_id` VARCHAR(128) NULL AFTER `stripe_invoice_id`,
    ADD COLUMN `billing_period_start` DATE NULL AFTER `stripe_subscription_id`,
    ADD COLUMN `billing_period_end` DATE NULL AFTER `billing_period_start`,
    ADD COLUMN `invoice_type_label` VARCHAR(64) NULL AFTER `billing_period_end`;

-- Unique auf stripe_invoice_id (Idempotenz-Schutz)
ALTER TABLE `saas_invoices`
    ADD UNIQUE KEY `uq_stripe_invoice_id` (`stripe_invoice_id`);

-- Mahnungen-Tabelle für SaaS (getrennt von Praxis!)
CREATE TABLE IF NOT EXISTS `saas_invoice_dunnings` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id`      INT UNSIGNED NOT NULL,
    `level`           TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=Erinnerung, 2=1.Mahnung, 3=2.Mahnung, 4=Letzte Mahnung',
    `fee`             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `due_date`        DATE NULL,
    `sent_at`         DATETIME NULL,
    `sent_to`         VARCHAR(255) NULL,
    `notes`           TEXT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sid_invoice` (`invoice_id`),
    CONSTRAINT `fk_sid_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `saas_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook-Idempotenz-Log (verhindert doppelte Rechnungen bei doppelten Webhooks)
CREATE TABLE IF NOT EXISTS `saas_webhook_events` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id`        VARCHAR(128) NOT NULL,
    `event_type`      VARCHAR(128) NOT NULL,
    `processed_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `invoice_id`      INT UNSIGNED NULL,
    UNIQUE KEY `uq_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dunning-Einstellungen in saas_settings
INSERT IGNORE INTO `saas_settings` (`key`, `value`) VALUES
('saas_dunning_level1_days',  '7'),
('saas_dunning_level2_days',  '14'),
('saas_dunning_level3_days',  '21'),
('saas_dunning_level1_fee',   '0.00'),
('saas_dunning_level2_fee',   '5.00'),
('saas_dunning_level3_fee',   '10.00'),
('saas_dunning_email_subject_1', 'Zahlungserinnerung: Rechnung {{invoice_number}}'),
('saas_dunning_email_subject_2', '1. Mahnung: Rechnung {{invoice_number}} – Zahlung ausstehend'),
('saas_dunning_email_subject_3', '2. Mahnung: Rechnung {{invoice_number}} – dringende Zahlungsaufforderung'),
('saas_invoice_auto_create',  '1'),
('saas_invoice_prefix',       'TP'),
('saas_invoice_start_number', '1000'),
('saas_invoice_payment_terms','Zahlbar sofort ohne Abzug.'),
('saas_invoice_vat_rate',     '19');
