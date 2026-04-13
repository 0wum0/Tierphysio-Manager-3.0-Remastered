-- ============================================================
-- Migration 029 – GoBD-Storno-Härtung
-- Original bleibt immer erhalten; Storno = eigener Gegenbeleg
-- ============================================================

-- 1. invoice_type: unterscheidet Normal- von Storno-Rechnungen
ALTER TABLE `invoices`
    ADD COLUMN `invoice_type` ENUM('normal','cancellation') NOT NULL DEFAULT 'normal' AFTER `invoice_number`;

-- 2. Status um 'cancellation' + 'mahnung' erweitern (mahnung war ggf. bereits genutzt,
--    muss im ENUM bleiben um MySQL Error 1265 "Data truncated" zu vermeiden)
ALTER TABLE `invoices`
    MODIFY COLUMN `status` ENUM('draft','open','paid','overdue','mahnung','cancelled','cancellation') NOT NULL DEFAULT 'draft';

-- 3. cancelled_at sicherstellen (muss vor cancelled_by existieren)
ALTER TABLE `invoices`
    ADD COLUMN `cancelled_at` DATETIME NULL DEFAULT NULL;

-- 4. cancelled_by: wer hat storniert (User-ID)
ALTER TABLE `invoices`
    ADD COLUMN `cancelled_by` INT UNSIGNED NULL DEFAULT NULL AFTER `cancelled_at`;

-- 5. cancels_invoice_id sicherstellen (muss vor cancellation_invoice_id existieren)
ALTER TABLE `invoices`
    ADD COLUMN `cancels_invoice_id` INT UNSIGNED NULL DEFAULT NULL;

-- 6. cancellation_invoice_id: Zeiger vom Original auf den Storno-Beleg
ALTER TABLE `invoices`
    ADD COLUMN `cancellation_invoice_id` INT UNSIGNED NULL DEFAULT NULL AFTER `cancels_invoice_id`;

-- 7. Index für Verknüpfungs-Lookups (einzeln, damit jeder unabhängig idempotent ist)
ALTER TABLE `invoices` ADD INDEX `idx_cancels_invoice_id` (`cancels_invoice_id`);
ALTER TABLE `invoices` ADD INDEX `idx_cancellation_invoice_id` (`cancellation_invoice_id`);
ALTER TABLE `invoices` ADD INDEX `idx_invoice_type` (`invoice_type`);

-- 8. Audit-Log action-ENUM um neuen Typ erweitern (safe, da additive)
ALTER TABLE `invoice_audit_log`
    MODIFY COLUMN `action`
        ENUM('created','updated','status_changed','deleted','finalized',
             'cancelled','cancellation_created','email_sent','pdf_downloaded') NOT NULL;
