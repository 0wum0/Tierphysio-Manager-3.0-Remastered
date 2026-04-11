-- ============================================================
-- Migration 029 – GoBD-Storno-Härtung
-- Original bleibt immer erhalten; Storno = eigener Gegenbeleg
-- ============================================================

-- 1. invoice_type: unterscheidet Normal- von Storno-Rechnungen
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `invoice_type` ENUM('normal','cancellation') NOT NULL DEFAULT 'normal' AFTER `invoice_number`;

-- 2. Status um 'cancellation' erweitern (für den Storno-Beleg selbst)
ALTER TABLE `invoices`
    MODIFY COLUMN `status` ENUM('draft','open','paid','overdue','cancelled','cancellation') NOT NULL DEFAULT 'draft';

-- 3. cancelled_by: wer hat storniert (User-ID)
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `cancelled_by` INT UNSIGNED NULL DEFAULT NULL AFTER `cancelled_at`;

-- 4. cancellation_invoice_id: Zeiger vom Original auf den Storno-Beleg
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `cancellation_invoice_id` INT UNSIGNED NULL DEFAULT NULL AFTER `cancels_invoice_id`;

-- 5. cancels_invoice_id sicherstellen (falls es fehlt – war bisher nur per Plugin)
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `cancels_invoice_id` INT UNSIGNED NULL DEFAULT NULL;

-- 6. cancelled_at sicherstellen
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `cancelled_at` DATETIME NULL DEFAULT NULL;

-- 7. Index für Verknüpfungs-Lookups
ALTER TABLE `invoices`
    ADD INDEX IF NOT EXISTS `idx_cancels_invoice_id`      (`cancels_invoice_id`),
    ADD INDEX IF NOT EXISTS `idx_cancellation_invoice_id` (`cancellation_invoice_id`),
    ADD INDEX IF NOT EXISTS `idx_invoice_type`            (`invoice_type`);

-- 8. Audit-Log action-ENUM um neuen Typ erweitern (safe, da additive)
ALTER TABLE `invoice_audit_log`
    MODIFY COLUMN `action`
        ENUM('created','updated','status_changed','deleted','finalized',
             'cancelled','cancellation_created','email_sent','pdf_downloaded') NOT NULL;
