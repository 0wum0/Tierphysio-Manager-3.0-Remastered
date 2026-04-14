CREATE TABLE IF NOT EXISTS `cron_job_log` (
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT,
  `job_key`    varchar(64)      NOT NULL COMMENT 'Unique job identifier e.g. birthday, calendar_reminders',
  `status`     enum('success','error','skipped') NOT NULL DEFAULT 'success',
  `message`    text             DEFAULT NULL,
  `duration_ms` int(11) unsigned DEFAULT NULL,
  `triggered_by` enum('cron','manual','pixel') NOT NULL DEFAULT 'cron',
  `created_at` datetime         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_key` (`job_key`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
