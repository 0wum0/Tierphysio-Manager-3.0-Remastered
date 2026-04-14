-- ═══════════════════════════════════════════════════════════════════════════
-- Migration 003: SaaS Rechnungsverwaltung
-- Rechnungen für SaaS-Kunden (Tenants) – ohne Patientendaten
-- ═══════════════════════════════════════════════════════════════════════════

-- Rechnungsköpfe
CREATE TABLE IF NOT EXISTS `saas_invoices` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`      INT UNSIGNED NOT NULL,
    `invoice_number` VARCHAR(64)  NOT NULL,
    `status`         ENUM('draft','open','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    `payment_method` ENUM('rechnung','ueberweisung','lastschrift','bar') NOT NULL DEFAULT 'rechnung',
    `issue_date`     DATE         NOT NULL,
    `due_date`       DATE         NULL,
    `total_net`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_tax`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_gross`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes`          TEXT         NULL,
    `payment_terms`  VARCHAR(255) NULL,
    `email_sent_at`  DATETIME     NULL,
    `paid_at`        DATETIME     NULL,
    `finalized_at`   DATETIME     NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_saas_inv_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uq_invoice_number` (`invoice_number`),
    INDEX `idx_saas_inv_tenant`  (`tenant_id`),
    INDEX `idx_saas_inv_status`  (`status`),
    INDEX `idx_saas_inv_date`    (`issue_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rechnungspositionen
CREATE TABLE IF NOT EXISTS `saas_invoice_positions` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id`  INT UNSIGNED NOT NULL,
    `description` TEXT         NOT NULL,
    `quantity`    DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    `unit_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_rate`    DECIMAL(5,2)  NOT NULL DEFAULT 19.00,
    `total`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT `fk_saas_pos_inv` FOREIGN KEY (`invoice_id`) REFERENCES `saas_invoices`(`id`) ON DELETE CASCADE,
    INDEX `idx_saas_pos_inv` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
