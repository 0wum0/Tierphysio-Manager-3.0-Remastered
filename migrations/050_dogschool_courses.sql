-- ═══════════════════════════════════════════════════════════════
--  Migration 050: Hundeschul-/Hundetrainer-Kernmodul — Kurse
--
--  Liefert:
--    * dogschool_courses           – Kurse/Workshops/Events
--    * dogschool_course_sessions   – einzelne Termine eines Kurses
--    * dogschool_enrollments       – Teilnehmer (Hund+Halter) pro Kurs
--    * dogschool_waitlist          – Wartelisten-Einträge
--    * dogschool_attendance        – Anwesenheit je Session+Teilnehmer (Phase 3)
--
--  Alle Tabellen sind tenant-prefixed durch den MigrationService.
--  Kompatibilität: nachnutzung bestehender `patients` + `owners`.
-- ═══════════════════════════════════════════════════════════════

-- ─── Kurse/Workshops ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_courses` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`                VARCHAR(200)  NOT NULL,
    `type`                VARCHAR(50)   NOT NULL DEFAULT 'group'
                          COMMENT 'group|welpen|junghunde|alltag|rueckruf|leinenfuehrigkeit|begegnung|social_walk|problem|agility|beschaeftigung|workshop|seminar|event',
    `description`         TEXT          NULL,
    `level`               VARCHAR(20)   NULL DEFAULT NULL
                          COMMENT 'anfaenger|fortgeschritten|experte',
    `trainer_user_id`     INT UNSIGNED  NULL
                          COMMENT 'FK users.id — Hauptverantwortlicher Trainer',
    `location`            VARCHAR(200)  NULL,
    `start_date`          DATE          NULL,
    `end_date`            DATE          NULL,
    `weekday`             TINYINT       NULL
                          COMMENT '0=So, 1=Mo, …, 6=Sa — für wiederkehrende Termine',
    `start_time`          TIME          NULL,
    `duration_minutes`    INT UNSIGNED  NOT NULL DEFAULT 60,
    `max_participants`    INT UNSIGNED  NOT NULL DEFAULT 8,
    `price_cents`         INT UNSIGNED  NOT NULL DEFAULT 0
                          COMMENT 'Kursgebühr in Cent pro Teilnehmer',
    `num_sessions`        INT UNSIGNED  NOT NULL DEFAULT 1
                          COMMENT 'Anzahl geplanter Einheiten (z.B. 8er-Kurs)',
    `status`              ENUM('draft','active','full','paused','completed','cancelled')
                          NOT NULL DEFAULT 'draft',
    `notes`               TEXT          NULL,
    `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status`        (`status`),
    INDEX `idx_start_date`    (`start_date`),
    INDEX `idx_trainer_user`  (`trainer_user_id`),
    INDEX `idx_type`          (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Kurs-Termine (einzelne Einheiten eines Kurses) ──────────────
CREATE TABLE IF NOT EXISTS `dogschool_course_sessions` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `course_id`         INT UNSIGNED NOT NULL,
    `session_number`    INT UNSIGNED NOT NULL DEFAULT 1
                        COMMENT 'Laufende Nr innerhalb des Kurses (1/8, 2/8, …)',
    `session_date`      DATE         NOT NULL,
    `start_time`        TIME         NULL,
    `duration_minutes`  INT UNSIGNED NOT NULL DEFAULT 60,
    `trainer_user_id`   INT UNSIGNED NULL
                        COMMENT 'Überschreibt trainer_user_id des Kurses wenn gesetzt',
    `topic`             VARCHAR(200) NULL COMMENT 'Thema/Lernziel der Einheit',
    `notes`             TEXT         NULL,
    `status`            ENUM('planned','held','cancelled')
                        NOT NULL DEFAULT 'planned',
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_course`      (`course_id`),
    INDEX `idx_session_date`(`session_date`),
    INDEX `idx_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Kurs-Einschreibungen (Enrollment) ───────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_enrollments` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `course_id`         INT UNSIGNED NOT NULL,
    `patient_id`        INT UNSIGNED NOT NULL COMMENT 'FK patients.id (= Hund)',
    `owner_id`          INT UNSIGNED NOT NULL COMMENT 'FK owners.id (= Halter)',
    `enrolled_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status`            ENUM('active','cancelled','completed','transferred','no_show')
                        NOT NULL DEFAULT 'active',
    `price_cents`       INT UNSIGNED NULL COMMENT 'Individueller Preis (falls abweichend)',
    `paid`              TINYINT(1)   NOT NULL DEFAULT 0,
    `invoice_id`        INT UNSIGNED NULL COMMENT 'FK invoices.id falls berechnet',
    `package_id`        INT UNSIGNED NULL COMMENT 'FK dogschool_packages.id falls über Paket gebucht',
    `notes`             TEXT         NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_course_patient` (`course_id`,`patient_id`),
    INDEX `idx_owner`       (`owner_id`),
    INDEX `idx_status`      (`status`),
    INDEX `idx_invoice`     (`invoice_id`),
    INDEX `idx_package`     (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Warteliste ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_waitlist` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `course_id`         INT UNSIGNED NOT NULL,
    `patient_id`        INT UNSIGNED NULL COMMENT 'FK patients — wenn Hund bereits erfasst',
    `owner_id`          INT UNSIGNED NULL COMMENT 'FK owners — wenn Halter bereits erfasst',
    `lead_name`         VARCHAR(200) NULL COMMENT 'Fallback für noch-nicht-Kunden',
    `lead_email`        VARCHAR(200) NULL,
    `lead_phone`        VARCHAR(50)  NULL,
    `position`          INT UNSIGNED NOT NULL DEFAULT 1,
    `status`            ENUM('waiting','offered','accepted','declined','expired')
                        NOT NULL DEFAULT 'waiting',
    `notified_at`       DATETIME     NULL,
    `expires_at`        DATETIME     NULL COMMENT 'Angebot läuft ab (bei offered)',
    `notes`             TEXT         NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_course_status` (`course_id`,`status`),
    INDEX `idx_position`      (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Anwesenheit (Phase 3 nutzt das) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_attendance` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `session_id`        INT UNSIGNED NOT NULL COMMENT 'FK dogschool_course_sessions.id',
    `enrollment_id`     INT UNSIGNED NOT NULL COMMENT 'FK dogschool_enrollments.id',
    `status`            ENUM('present','absent','excused','late','left_early','no_show')
                        NOT NULL DEFAULT 'present',
    `notes`             TEXT         NULL,
    `marked_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `marked_by_user_id` INT UNSIGNED NULL,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_session_enrollment` (`session_id`,`enrollment_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
