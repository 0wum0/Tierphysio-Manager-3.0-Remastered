-- Migration 017: Invoice Reminders & Dunnings
-- Additive only — no existing tables modified

CREATE TABLE IF NOT EXISTS `invoice_reminders` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`     INT UNSIGNED NOT NULL,
    `sent_at`        DATETIME NULL,
    `sent_to`        VARCHAR(255) NULL,
    `due_date`       DATE NULL,
    `fee`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes`          TEXT NULL,
    `pdf_generated`  TINYINT(1) NOT NULL DEFAULT 0,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ir_invoice` (`invoice_id`),
    CONSTRAINT `fk_ir_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ir_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_dunnings` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`     INT UNSIGNED NOT NULL,
    `level`          TINYINT NOT NULL DEFAULT 1 COMMENT '1=1. Mahnung, 2=2. Mahnung, 3=Letzte Mahnung',
    `sent_at`        DATETIME NULL,
    `sent_to`        VARCHAR(255) NULL,
    `due_date`       DATE NULL,
    `fee`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes`          TEXT NULL,
    `pdf_generated`  TINYINT(1) NOT NULL DEFAULT 0,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_id_invoice` (`invoice_id`),
    CONSTRAINT `fk_id_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_id_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default email settings for reminders/dunnings
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('email_reminder_subject', 'Zahlungserinnerung: Rechnung {{invoice_number}}'),
('email_reminder_body',    "Hallo {{owner_name}},\n\nwir möchten Sie freundlich daran erinnern, dass die Rechnung {{invoice_number}} vom {{issue_date}} über {{total_gross}} noch aussteht.\n\nBitte überweisen Sie den Betrag bis zum {{due_date}} auf unser Konto.\n\nFalls Sie die Zahlung bereits veranlasst haben, bitten wir Sie, dieses Schreiben als gegenstandslos zu betrachten.\n\nMit freundlichen Grüßen\n{{company_name}}"),
('email_dunning_subject',  '{{dunning_level}}. Mahnung: Rechnung {{invoice_number}}'),
('email_dunning_body',     "Hallo {{owner_name}},\n\ntrotz unserer Zahlungserinnerung ist die Rechnung {{invoice_number}} vom {{issue_date}} über {{total_gross}} noch nicht beglichen worden.\n\nWir fordern Sie hiermit auf, den ausstehenden Betrag zuzüglich einer Mahngebühr von {{fee}} bis zum {{due_date}} zu begleichen.\n\nGesamtbetrag: {{total_with_fee}}\n\nBei weiterer Nichtzahlung sind wir gezwungen, rechtliche Schritte einzuleiten.\n\nMit freundlichen Grüßen\n{{company_name}}"),
('dunning_default_fee',    '5.00'),
('reminder_default_days',  '7');
