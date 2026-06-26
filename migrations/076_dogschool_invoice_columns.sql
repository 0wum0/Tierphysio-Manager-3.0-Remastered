-- ============================================================
-- Migration 076 — Hundeschul-Dashboard KPI-Robustheit
--
-- Stellt sicher, dass invoice_type und total_gross in der
-- Rechnungstabelle aller Tenants existieren.
--
-- Hintergrund: DogschoolInvoiceService::getStats() referenziert
-- beide Spalten. Auf Tenants, bei denen Migration 035/005 noch
-- nicht gelaufen ist, schlagen alle KPI-Queries mit
-- "Unknown column" fehl → Dashboard zeigt 0 für alle Werte.
-- ============================================================

-- invoice_type: trennt Normal- von Storno-Rechnungen (seit 035)
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `invoice_type` ENUM('normal','cancellation') NOT NULL DEFAULT 'normal' AFTER `invoice_number`;

-- total_gross: denormalisierter Brutto-Betrag (seit 005/provisioning)
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `total_gross` DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- paid_at: Zahlungsdatum für korrekte Monats-Umsatz-Zuordnung (seit 013)
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `paid_at` DATETIME NULL;
