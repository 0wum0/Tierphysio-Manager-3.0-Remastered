CREATE TABLE IF NOT EXISTS `{PREFIX}portal_message_threads` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_id`     INT UNSIGNED NOT NULL,
    `subject`      VARCHAR(255)  NOT NULL DEFAULT '',
    `status`       ENUM('open','closed') NOT NULL DEFAULT 'open',
    `created_by`   ENUM('admin','owner') NOT NULL DEFAULT 'owner',
    `last_message_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_owner_id` (`owner_id`),
    KEY `idx_last_message_at` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}portal_messages` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `thread_id`    INT UNSIGNED NOT NULL,
    `sender_type`  ENUM('admin','owner') NOT NULL,
    `sender_id`    INT UNSIGNED NULL COMMENT 'user.id for admin, owner_portal_users.id for owner',
    `body`         TEXT NOT NULL,
    `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_thread_id` (`thread_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
