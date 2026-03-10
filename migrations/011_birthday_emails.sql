-- Migration 011: Birthday emails sent tracking table
CREATE TABLE IF NOT EXISTS `birthday_emails_sent` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id` INT UNSIGNED NOT NULL,
    `year_sent`  SMALLINT UNSIGNED NOT NULL,
    `sent_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_patient_year` (`patient_id`, `year_sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings for birthday mails
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('birthday_mail_enabled', '0'),
('birthday_mail_subject', '\uD83C\uDF82 Alles Gute zum Geburtstag, {{patient_name}}!'),
('birthday_cron_token',   '');
