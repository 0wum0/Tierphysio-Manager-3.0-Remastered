-- Cron Dispatcher - Zentraler Dispatcher für alle Cronjobs
-- Der Dispatcher läuft alle 10 Minuten und führt fällige Jobs basierend auf Zeitplänen aus

CREATE TABLE IF NOT EXISTS `cron_dispatcher_log` (
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT,
  `job_key`    varchar(64)      NOT NULL COMMENT 'Job identifier: birthday, calendar_reminders, google_sync, tcp_reminders, holiday_greetings',
  `status`     enum('success','error','skipped') NOT NULL DEFAULT 'success',
  `message`    text             DEFAULT NULL,
  `duration_ms` int(11) unsigned DEFAULT NULL,
  `created_at` datetime         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_key` (`job_key`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dispatcher-Token in Settings Tabelle (für Authentifizierung)
-- ALTER TABLE statt INSERT, da ältere DBs keine description-Spalte haben
ALTER TABLE `settings` 
ADD COLUMN IF NOT EXISTS `description` VARCHAR(255) NULL DEFAULT NULL AFTER `value`;

INSERT INTO `settings` (`key`, `value`, `description`) 
VALUES ('cron_dispatcher_token', '', 'Token für den zentralen Cron Dispatcher (alle 10 Minuten)')
ON DUPLICATE KEY UPDATE `value` = '', `description` = 'Token für den zentralen Cron Dispatcher (alle 10 Minuten)';
