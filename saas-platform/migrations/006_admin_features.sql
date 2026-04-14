-- ═══════════════════════════════════════════════════════════════════════════
-- Migration 004: Admin-Features – Einstellungen, Benachrichtigungen, Updates
-- ═══════════════════════════════════════════════════════════════════════════

-- Plattform-Einstellungen (Key-Value)
CREATE TABLE IF NOT EXISTS `saas_settings` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`        VARCHAR(128) NOT NULL,
    `value`      TEXT         NULL,
    `type`       ENUM('string','boolean','integer','json','secret') NOT NULL DEFAULT 'string',
    `group`      VARCHAR(64)  NOT NULL DEFAULT 'general',
    `label`      VARCHAR(255) NULL,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_saas_settings_key` (`key`),
    INDEX `idx_saas_settings_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Basis-Einstellungen einfügen
INSERT IGNORE INTO `saas_settings` (`key`, `value`, `type`, `group`, `label`) VALUES
('company_name',        'TheraPano SaaS',       'string',  'company',  'Firmenname'),
('company_email',       '',                     'string',  'company',  'E-Mail'),
('company_address',     '',                     'string',  'company',  'Adresse'),
('company_zip',         '',                     'string',  'company',  'PLZ'),
('company_city',        '',                     'string',  'company',  'Stadt'),
('company_country',     'Deutschland',          'string',  'company',  'Land'),
('company_phone',       '',                     'string',  'company',  'Telefon'),
('company_website',     '',                     'string',  'company',  'Website'),
('tax_id',              '',                     'string',  'company',  'Steuernummer'),
('vat_id',              '',                     'string',  'company',  'USt-IdNr.'),
('bank_iban',           '',                     'string',  'billing',  'IBAN'),
('bank_bic',            '',                     'string',  'billing',  'BIC'),
('bank_name',           '',                     'string',  'billing',  'Bankname'),
('invoice_prefix',      'TP',                   'string',  'billing',  'Rechnungsnummer-Präfix'),
('invoice_start_number','1000',                 'integer', 'billing',  'Startnummer'),
('invoice_payment_days','14',                   'integer', 'billing',  'Zahlungsziel (Tage)'),
('kleinunternehmer',    '0',                    'boolean', 'billing',  'Kleinunternehmer §19 UStG'),
('smtp_host',           'localhost',            'string',  'mail',     'SMTP Host'),
('smtp_port',           '587',                  'integer', 'mail',     'SMTP Port'),
('smtp_encryption',     'tls',                  'string',  'mail',     'Verschlüsselung'),
('smtp_username',       '',                     'string',  'mail',     'SMTP Benutzername'),
('smtp_password',       '',                     'secret',  'mail',     'SMTP Passwort'),
('mail_from_name',      'TheraPano SaaS',       'string',  'mail',     'Absendername'),
('mail_from_address',   '',                     'string',  'mail',     'Absender-E-Mail'),
('notify_new_tenant',   '1',                    'boolean', 'notifications', 'Benachrichtigung: Neue Praxis'),
('notify_payment',      '1',                    'boolean', 'notifications', 'Benachrichtigung: Zahlung'),
('notify_overdue',      '1',                    'boolean', 'notifications', 'Benachrichtigung: Überfällig'),
('notify_trial_expiry', '1',                    'boolean', 'notifications', 'Benachrichtigung: Trial läuft ab'),
('notify_email',        '',                     'string',  'notifications', 'Benachrichtigungs-E-Mail'),
('platform_version',    '1.0.0',               'string',  'system',   'Plattform-Version'),
('update_channel',      'stable',               'string',  'system',   'Update-Kanal'),
('update_check_url',    'https://api.github.com/repos/tierphysio/saas-platform/releases/latest', 'string', 'system', 'Update-Check URL'),
('maintenance_mode',    '0',                    'boolean', 'system',   'Wartungsmodus'),
('registration_open',   '1',                    'boolean', 'system',   'Registrierung offen'),
('max_tenants',         '0',                    'integer', 'system',   'Max. Praxen (0=unbegrenzt)');

-- Admin-Benutzer (mehrere Admins)
CREATE TABLE IF NOT EXISTS `saas_admin_users` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`            VARCHAR(128) NOT NULL,
    `email`           VARCHAR(191) NOT NULL,
    `password_hash`   VARCHAR(255) NOT NULL,
    `role`            ENUM('superadmin','admin','support') NOT NULL DEFAULT 'admin',
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at`   DATETIME NULL,
    `last_login_ip`   VARCHAR(64) NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Benachrichtigungen
CREATE TABLE IF NOT EXISTS `saas_notifications` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type`       VARCHAR(64)  NOT NULL,
    `title`      VARCHAR(255) NOT NULL,
    `message`    TEXT         NOT NULL,
    `data`       JSON         NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notif_read`    (`is_read`),
    INDEX `idx_notif_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aktivitäts-Log / Audit-Trail
CREATE TABLE IF NOT EXISTS `saas_activity_log` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `actor`      VARCHAR(128) NOT NULL DEFAULT 'system',
    `action`     VARCHAR(128) NOT NULL,
    `subject`    VARCHAR(128) NULL,
    `subject_id` INT UNSIGNED NULL,
    `detail`     TEXT         NULL,
    `ip`         VARCHAR(64)  NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_action`  (`action`),
    INDEX `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update-Historie
CREATE TABLE IF NOT EXISTS `saas_update_log` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `from_version` VARCHAR(32) NOT NULL,
    `to_version`   VARCHAR(32) NOT NULL,
    `channel`      VARCHAR(32) NOT NULL DEFAULT 'stable',
    `status`       ENUM('success','failed','rolled_back') NOT NULL DEFAULT 'success',
    `notes`        TEXT NULL,
    `performed_by` VARCHAR(128) NULL,
    `performed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Support-Tickets
CREATE TABLE IF NOT EXISTS `saas_support_tickets` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`   INT UNSIGNED NULL,
    `subject`     VARCHAR(255) NOT NULL,
    `message`     TEXT         NOT NULL,
    `status`      ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    `priority`    ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    `assigned_to` VARCHAR(128) NULL,
    `reply`       TEXT         NULL,
    `replied_at`  DATETIME     NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ticket_status`   (`status`),
    INDEX `idx_ticket_tenant`   (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
