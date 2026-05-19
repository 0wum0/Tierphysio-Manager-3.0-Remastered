-- Migration 070: feedback_replies — bidirektionaler Support-Chat
-- Jede Antwort (Admin oder Praxis-Nutzer) wird als eigene Zeile gespeichert.
-- sender_type: 'admin' | 'tenant'

CREATE TABLE IF NOT EXISTS `feedback_replies` (
    `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `feedback_id` INT UNSIGNED      NOT NULL,
    `sender_type` ENUM('admin','tenant') NOT NULL DEFAULT 'admin',
    `sender_name` VARCHAR(200)       DEFAULT NULL,
    `message`     TEXT              NOT NULL,
    `is_read`     TINYINT(1)        NOT NULL DEFAULT 0,
    `read_at`     DATETIME          DEFAULT NULL,
    `created_at`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_feedback_id` (`feedback_id`),
    KEY `idx_feedback_unread` (`feedback_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Spalte für ungelesene Admin-Antworten in feedback (schneller Badge-Count ohne JOIN)
ALTER TABLE `feedback` ADD COLUMN `unread_replies` TINYINT(1) NOT NULL DEFAULT 0;
