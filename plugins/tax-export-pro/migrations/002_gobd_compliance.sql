-- GoBD: Audit-Log for all invoice mutations
CREATE TABLE IF NOT EXISTS `invoice_audit_log` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `invoice_id`     INT UNSIGNED    NOT NULL,
    `invoice_number` VARCHAR(60)     NOT NULL DEFAULT '',
    `action`         ENUM('created','updated','status_changed','deleted','finalized','cancelled','email_sent','pdf_downloaded') NOT NULL,
    `old_values`     JSON            NULL,
    `new_values`     JSON            NULL,
    `user_id`        INT UNSIGNED    NULL,
    `ip_address`     VARCHAR(45)     NOT NULL DEFAULT '',
    `user_agent`     VARCHAR(255)    NOT NULL DEFAULT '',
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_audit_invoice_id`  (`invoice_id`),
    KEY `idx_audit_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GoBD: finalized_at timestamp — once set, invoice is immutable
ALTER TABLE `invoices`
    ADD COLUMN IF NOT EXISTS `finalized_at` DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `cancelled_at` DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `cancels_invoice_id` INT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `service_date` DATE NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `gobd_hash` VARCHAR(64) NULL DEFAULT NULL;

-- DATEV settings
INSERT IGNORE INTO `tax_export_settings` (`setting_key`, `setting_value`) VALUES
    ('datev_konto_erloese',     '8400'),
    ('datev_konto_kasse',       '1000'),
    ('datev_konto_forderungen', '1400'),
    ('datev_berater_nummer',    ''),
    ('datev_mandanten_nummer',  ''),
    ('datev_wirtschaftsjahr',   '01')
