-- ═══════════════════════════════════════════════════════════════════════════
-- Migration 005: saas_settings – fehlende Spalten nachrüsten + Daten füllen
-- ═══════════════════════════════════════════════════════════════════════════

-- Spalte 'type' hinzufügen falls nicht vorhanden
ALTER TABLE `saas_settings`
    ADD COLUMN IF NOT EXISTS `type`  ENUM('string','boolean','integer','json','secret') NOT NULL DEFAULT 'string' AFTER `value`,
    ADD COLUMN IF NOT EXISTS `group` VARCHAR(64)  NOT NULL DEFAULT 'general'            AFTER `type`,
    ADD COLUMN IF NOT EXISTS `label` VARCHAR(255) NULL                                  AFTER `group`;

-- Index hinzufügen falls nicht vorhanden (Fehler ignorieren wenn schon da)
ALTER TABLE `saas_settings`
    ADD UNIQUE KEY IF NOT EXISTS `uq_saas_settings_key` (`key`);

ALTER TABLE `saas_settings`
    ADD INDEX IF NOT EXISTS `idx_saas_settings_group` (`group`);

-- Basis-Einstellungen einfügen (INSERT IGNORE überspringt bereits vorhandene Keys)
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
('platform_version',    '1.0.0',                'string',  'system',   'Plattform-Version'),
('update_channel',      'stable',               'string',  'system',   'Update-Kanal'),
('update_check_url',    'https://api.github.com/repos/tierphysio/saas-platform/releases/latest', 'string', 'system', 'Update-Check URL'),
('maintenance_mode',    '0',                    'boolean', 'system',   'Wartungsmodus'),
('registration_open',   '1',                    'boolean', 'system',   'Registrierung offen'),
('max_tenants',         '0',                    'integer', 'system',   'Max. Praxen (0=unbegrenzt)');
