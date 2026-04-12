-- ============================================================
-- Migration 031 – GoBD-Infrastruktur
-- Erstellt alle fehlenden Tabellen und Spalten, die von
-- InvoiceCancellationService, Migration 029 und dem
-- Tax-Export-System benötigt werden.
-- Vollständig idempotent (IF NOT EXISTS / IF NOT EXISTS).
-- ============================================================

-- 1. invoice_audit_log ───────────────────────────────────────
--    GoBD-konformes Revisionsprotokoll für alle Rechnungsoperationen.
--    Wird von InvoiceCancellationService.writeAuditLog() und
--    Migration 029 (ALTER ... MODIFY COLUMN action ...) benötigt.
CREATE TABLE IF NOT EXISTS `invoice_audit_log` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`     INT UNSIGNED NOT NULL,
    `invoice_number` VARCHAR(50)  NOT NULL DEFAULT '',
    `action`         ENUM('created','updated','status_changed','deleted','finalized',
                          'cancelled','cancellation_created','email_sent','pdf_downloaded') NOT NULL,
    `old_values`     JSON NULL,
    `new_values`     JSON NULL,
    `user_id`        INT UNSIGNED NULL,
    `ip_address`     VARCHAR(45)  NOT NULL DEFAULT '',
    `user_agent`     VARCHAR(255) NOT NULL DEFAULT '',
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ial_invoice_id` (`invoice_id`),
    INDEX `idx_ial_action`     (`action`),
    INDEX `idx_ial_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. gobd_audit_log ──────────────────────────────────────────
--    Protokoll für GoBD-Finalisierungen und Tax-Export-Operationen.
--    Wird von taxExportFinalize() und taxExportAuditLog() benötigt.
CREATE TABLE IF NOT EXISTS `gobd_audit_log` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`     INT UNSIGNED NOT NULL,
    `invoice_number` VARCHAR(50)  NOT NULL DEFAULT '',
    `action`         VARCHAR(50)  NOT NULL DEFAULT '',
    `user_id`        INT UNSIGNED NULL,
    `meta`           JSON NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_gal_invoice_id` (`invoice_id`),
    INDEX `idx_gal_action`     (`action`),
    INDEX `idx_gal_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. invoices.finalized_at + invoices.gobd_hash ──────────────
--    Werden von taxExportFinalize() gesetzt.
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `finalized_at` DATETIME   NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `gobd_hash`    VARCHAR(64) NULL DEFAULT NULL;

-- 4. Sicherheitsindex gegen doppelten Storno ──────────────────
--    Verhindert via DB-Unique, dass zwei Storno-Belege
--    dieselbe Originalrechnung referenzieren.
--    (UNIQUE auf cancels_invoice_id, nur für Nicht-NULL-Werte
--     — MySQL ignoriert NULL bei UNIQUE automatisch.)
ALTER TABLE `invoices`
    ADD UNIQUE INDEX IF NOT EXISTS `uq_cancels_invoice_id` (`cancels_invoice_id`);
