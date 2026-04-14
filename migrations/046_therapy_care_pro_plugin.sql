-- TherapyCare Pro Plugin — Migration 001
-- All tables are additive; no existing tables are modified.

-- ══════════════════════════════════════════════════════════
-- MODULE 1: THERAPY PROGRESS TRACKING
-- ══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `tcp_progress_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `scale_min`   TINYINT NOT NULL DEFAULT 1,
    `scale_max`   TINYINT NOT NULL DEFAULT 10,
    `scale_label_min` VARCHAR(50) NULL COMMENT 'e.g. Sehr schlecht',
    `scale_label_max` VARCHAR(50) NULL COMMENT 'e.g. Sehr gut',
    `color`       VARCHAR(7) NOT NULL DEFAULT '#4f7cff',
    `icon`        VARCHAR(50) NULL,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tcp_progress_entries` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`      INT UNSIGNED NOT NULL,
    `category_id`     INT UNSIGNED NOT NULL,
    `appointment_id`  INT UNSIGNED NULL,
    `score`           TINYINT NOT NULL,
    `notes`           TEXT NULL,
    `recorded_by`     INT UNSIGNED NULL,
    `entry_date`      DATE NOT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tcp_pe_patient`     (`patient_id`),
    INDEX `idx_tcp_pe_category`    (`category_id`),
    INDEX `idx_tcp_pe_date`        (`entry_date`),
    INDEX `idx_tcp_pe_appointment` (`appointment_id`),
    CONSTRAINT `fk_tcp_pe_patient`  FOREIGN KEY (`patient_id`)  REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_pe_category` FOREIGN KEY (`category_id`) REFERENCES `tcp_progress_categories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_pe_user`     FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default progress categories
INSERT IGNORE INTO `tcp_progress_categories` (`id`, `name`, `description`, `scale_min`, `scale_max`, `scale_label_min`, `scale_label_max`, `color`, `sort_order`) VALUES
(1,  'Gangbild',       'Qualität des Gangbildes und der Bewegungskoordination',   1, 10, 'Stark eingeschränkt', 'Vollständig normal',  '#4f7cff', 1),
(2,  'Beweglichkeit',  'Gelenkbeweglichkeit und Flexibilität',                    1, 10, 'Sehr steif',          'Vollständig beweglich','#22c55e', 2),
(3,  'Schmerzreaktion','Reaktion auf Palpation und Bewegung',                     1, 10, 'Starker Schmerz',     'Schmerzfrei',         '#ef4444', 3),
(4,  'Muskelspannung', 'Tonus und Verspannungsgrad der Muskulatur',               1, 10, 'Hochgradig verspannt','Entspannt',           '#f59e0b', 4),
(5,  'Belastbarkeit',  'Belastung der betroffenen Gliedmaßen',                   1, 10, 'Keine Belastung',     'Volle Belastung',     '#8b5cf6', 5),
(6,  'Allgemeinzustand','Allgemeines Wohlbefinden und Vitalität',                 1, 10, 'Sehr schlecht',       'Ausgezeichnet',       '#06b6d4', 6);

-- ══════════════════════════════════════════════════════════
-- MODULE 2: OWNER FEEDBACK FOR EXERCISES
-- ══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `tcp_exercise_feedback` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `homework_id`  INT UNSIGNED NOT NULL COMMENT 'References patient_homework.id',
    `patient_id`   INT UNSIGNED NOT NULL,
    `owner_id`     INT UNSIGNED NOT NULL,
    `status`       ENUM('done','not_done','pain','difficult') NOT NULL DEFAULT 'done',
    `comment`      TEXT NULL,
    `feedback_date` DATE NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tcp_ef_homework`    (`homework_id`),
    INDEX `idx_tcp_ef_patient`     (`patient_id`),
    INDEX `idx_tcp_ef_owner`       (`owner_id`),
    INDEX `idx_tcp_ef_date`        (`feedback_date`),
    CONSTRAINT `fk_tcp_ef_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_ef_owner`   FOREIGN KEY (`owner_id`)   REFERENCES `owners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════
-- MODULE 3: REMINDERS
-- ══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `tcp_reminder_templates` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`         ENUM('appointment','homework','followup') NOT NULL DEFAULT 'appointment',
    `name`         VARCHAR(150) NOT NULL,
    `subject`      VARCHAR(255) NOT NULL,
    `body`         TEXT NOT NULL,
    `trigger_hours` INT NOT NULL DEFAULT 24 COMMENT 'Hours before event to send',
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure columns exist if table was already created (Self-healing)
ALTER TABLE `tcp_reminder_templates` ADD COLUMN `type` ENUM('appointment','homework','followup') NOT NULL DEFAULT 'appointment' AFTER `id`;
ALTER TABLE `tcp_reminder_templates` ADD COLUMN `name` VARCHAR(150) NOT NULL AFTER `type`;
ALTER TABLE `tcp_reminder_templates` ADD COLUMN `subject` VARCHAR(255) NOT NULL AFTER `name`;
ALTER TABLE `tcp_reminder_templates` ADD COLUMN `body` TEXT NOT NULL AFTER `subject`;
ALTER TABLE `tcp_reminder_templates` ADD COLUMN `trigger_hours` INT NOT NULL DEFAULT 24 AFTER `body`;
ALTER TABLE `tcp_reminder_templates` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `trigger_hours`;

CREATE TABLE IF NOT EXISTS `tcp_reminder_queue` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id`   INT UNSIGNED NULL,
    `type`          ENUM('appointment','homework','followup','custom') NOT NULL,
    `patient_id`    INT UNSIGNED NULL,
    `owner_id`      INT UNSIGNED NOT NULL,
    `appointment_id` INT UNSIGNED NULL,
    `subject`       VARCHAR(255) NOT NULL,
    `body`          TEXT NOT NULL,
    `send_at`       DATETIME NOT NULL,
    `sent_at`       DATETIME NULL,
    `status`        ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    `error_message` TEXT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tcp_rq_send_at`    (`send_at`),
    INDEX `idx_tcp_rq_status`     (`status`),
    INDEX `idx_tcp_rq_patient`    (`patient_id`),
    INDEX `idx_tcp_rq_owner`      (`owner_id`),
    CONSTRAINT `fk_tcp_rq_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_rq_owner`   FOREIGN KEY (`owner_id`)   REFERENCES `owners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure columns exist
ALTER TABLE `tcp_reminder_queue` ADD COLUMN `type` ENUM('appointment','homework','followup','custom') NOT NULL AFTER `template_id`;
ALTER TABLE `tcp_reminder_queue` ADD COLUMN `status` ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending' AFTER `sent_at`;

CREATE TABLE IF NOT EXISTS `tcp_reminder_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_id`   INT UNSIGNED NULL,
    `type`       VARCHAR(50) NOT NULL,
    `recipient`  VARCHAR(255) NOT NULL,
    `subject`    VARCHAR(255) NOT NULL,
    `status`     ENUM('sent','failed') NOT NULL,
    `error`      TEXT NULL,
    `sent_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tcp_rl_queue`  (`queue_id`),
    INDEX `idx_tcp_rl_sent`   (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default reminder templates
INSERT IGNORE INTO `tcp_reminder_templates` (`id`, `type`, `name`, `subject`, `body`, `trigger_hours`, `is_active`) VALUES
(1, 'appointment', 'Terminerinnerung 24h vorher',
   'Erinnerung: Ihr Termin für {{patient_name}} morgen',
   "Hallo {{owner_name}},\n\nwir möchten Sie an den Termin für {{patient_name}} morgen, {{appointment_date}} um {{appointment_time}} Uhr erinnern.\n\nBitte teilen Sie uns mit, falls Sie den Termin nicht wahrnehmen können.\n\nMit freundlichen Grüßen\n{{company_name}}",
   24, 1),
(2, 'appointment', 'Terminerinnerung 2h vorher',
   'Heute: Ihr Termin für {{patient_name}} um {{appointment_time}} Uhr',
   "Hallo {{owner_name}},\n\nnur eine kurze Erinnerung — der Termin für {{patient_name}} findet heute um {{appointment_time}} Uhr statt.\n\nBis gleich!\n{{company_name}}",
   2, 1),
(3, 'homework', 'Tägliche Übungserinnerung',
   'Heute: Heimübungen für {{patient_name}} nicht vergessen!',
   "Hallo {{owner_name}},\n\nbitte denken Sie heute an die Heimübungen für {{patient_name}}. Regelmäßiges Üben ist entscheidend für den Heilungserfolg!\n\nSie können den Fortschritt im Besitzerportal dokumentieren.\n\nMit freundlichen Grüßen\n{{company_name}}",
   0, 1),
(4, 'followup', 'Wiedervorstellungserinnerung',
   'Empfehlung: Wiedervorstellung für {{patient_name}}',
   "Hallo {{owner_name}},\n\nwir empfehlen eine Wiedervorstellung für {{patient_name}} in Kürze, um den Therapiefortschritt zu überprüfen.\n\nBitte vereinbaren Sie einen Termin.\n\nMit freundlichen Grüßen\n{{company_name}}",
   0, 1);

-- ══════════════════════════════════════════════════════════
-- MODULE 4: THERAPY REPORTS
-- ══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `tcp_therapy_reports` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`   INT UNSIGNED NOT NULL,
    `created_by`   INT UNSIGNED NULL,
    `title`        VARCHAR(255) NOT NULL,
    `diagnosis`    TEXT NULL,
    `therapies_used` TEXT NULL,
    `recommendations` TEXT NULL,
    `followup_recommendation` TEXT NULL,
    `include_progress` TINYINT(1) NOT NULL DEFAULT 1,
    `include_homework` TINYINT(1) NOT NULL DEFAULT 1,
    `include_natural`  TINYINT(1) NOT NULL DEFAULT 1,
    `include_timeline` TINYINT(1) NOT NULL DEFAULT 1,
    `filename`     VARCHAR(255) NULL,
    `sent_at`      DATETIME NULL,
    `sent_to`      VARCHAR(255) NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tcp_tr_patient` (`patient_id`),
    CONSTRAINT `fk_tcp_tr_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_tr_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════
-- MODULE 5: EXERCISE LIBRARY
-- ══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `tcp_exercise_library` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`           VARCHAR(255) NOT NULL,
    `category`        VARCHAR(100) NOT NULL DEFAULT 'sonstiges',
    `description`     TEXT NOT NULL,
    `instructions`    TEXT NULL,
    `contraindications` TEXT NULL,
    `frequency`       VARCHAR(100) NULL,
    `duration`        VARCHAR(100) NULL,
    `species_tags`    VARCHAR(255) NULL COMMENT 'Comma-separated: hund,katze,pferd',
    `therapy_tags`    VARCHAR(255) NULL COMMENT 'Comma-separated: physiotherapie,osteopathie',
    `has_image`       TINYINT(1) NOT NULL DEFAULT 0,
    `image_file`      VARCHAR(255) NULL,
    `has_video`       TINYINT(1) NOT NULL DEFAULT 0,
    `video_file`      VARCHAR(255) NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_by`      INT UNSIGNED NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tcp_el_category` (`category`),
    CONSTRAINT `fk_tcp_el_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════
-- MODULE 6: NATURAL THERAPY / NATUROPATH EXTENSION
-- ══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `tcp_natural_therapy_types` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `category`    VARCHAR(100) NOT NULL DEFAULT 'sonstiges',
    `description` TEXT NULL,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tcp_natural_therapy_entries` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`   INT UNSIGNED NOT NULL,
    `type_id`      INT UNSIGNED NULL,
    `therapy_type` VARCHAR(100) NOT NULL,
    `agent`        VARCHAR(255) NULL COMMENT 'Mittel / Präparat',
    `dosage`       VARCHAR(255) NULL,
    `frequency`    VARCHAR(100) NULL,
    `duration`     VARCHAR(100) NULL,
    `notes`        TEXT NULL,
    `show_in_portal` TINYINT(1) NOT NULL DEFAULT 0,
    `recorded_by`  INT UNSIGNED NULL,
    `entry_date`   DATE NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tcp_nte_patient` (`patient_id`),
    INDEX `idx_tcp_nte_date`    (`entry_date`),
    CONSTRAINT `fk_tcp_nte_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tcp_nte_type`    FOREIGN KEY (`type_id`)    REFERENCES `tcp_natural_therapy_types` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tcp_nte_user`    FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default natural therapy types
INSERT IGNORE INTO `tcp_natural_therapy_types` (`id`, `name`, `category`, `sort_order`) VALUES
(1,  'Bachblüten',           'Bachblüten',     1),
(2,  'Rescue Remedy',        'Bachblüten',     2),
(3,  'Homöopathie',          'Homöopathie',    3),
(4,  'Schüssler Salze',      'Homöopathie',    4),
(5,  'Kräutertherapie',      'Kräuter',        5),
(6,  'Phytotherapie',        'Kräuter',        6),
(7,  'Akupunktur',           'Akupunktur',     7),
(8,  'Akupressur',           'Akupunktur',     8),
(9,  'Bioresonanz',          'Bioresonanz',    9),
(10, 'Ernährungsberatung',   'Ernährung',      10),
(11, 'Diättherapie',         'Ernährung',      11),
(12, 'Osteopathie',          'Manuelle Therapie', 12),
(13, 'Chiropraktik',         'Manuelle Therapie', 13),
(14, 'Magnetfeldtherapie',   'Physikalisch',   14),
(15, 'Lasertherapie',        'Physikalisch',   15),
(16, 'Sonstige Naturheilkunde', 'Sonstiges',   99);

-- ══════════════════════════════════════════════════════════
-- MODULE 7: ENHANCED TIMELINE (extend patient_timeline type ENUM)
-- ══════════════════════════════════════════════════════════

-- We use a separate meta table to extend timeline entries without altering the core table
CREATE TABLE IF NOT EXISTS `tcp_timeline_meta` (
    `timeline_id`    INT UNSIGNED NOT NULL,
    `event_type`     VARCHAR(50) NOT NULL DEFAULT 'note'
                         COMMENT 'progress|feedback|therapy_report|natural_therapy|reminder_sent|exercise_created',
    `ref_id`         INT UNSIGNED NULL COMMENT 'ID in the referenced plugin table',
    `ref_table`      VARCHAR(100) NULL,
    `icon`           VARCHAR(50) NULL,
    `badge_color`    VARCHAR(20) NULL,
    PRIMARY KEY (`timeline_id`),
    INDEX `idx_tcp_tm_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════
-- MODULE 8: PORTAL VISIBILITY SETTINGS (per patient)
-- ══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `tcp_portal_visibility` (
    `patient_id`       INT UNSIGNED NOT NULL,
    `show_progress`    TINYINT(1) NOT NULL DEFAULT 0,
    `show_natural`     TINYINT(1) NOT NULL DEFAULT 0,
    `show_reports`     TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`patient_id`),
    CONSTRAINT `fk_tcp_pv_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
