-- ═══════════════════════════════════════════════════════════════
--  Migration 053: TCP / Trainingsplan-Modul
--
--  Tabellen:
--    dogschool_exercises           – Übungen-Katalog (geseedet)
--    dogschool_training_plans      – Plan-Vorlagen + individuelle Instanzen
--    dogschool_plan_exercises      – Curriculum (Plan → Übungen)
--    dogschool_plan_assignments    – Plan einem Hund zugewiesen
--    dogschool_training_progress   – Fortschritts-Datenpunkte (6-stuf. Mastery)
--    dogschool_homework            – Hausaufgaben für Halter
--    dogschool_course_categories   – Kursarten-Katalog (geseedet)
--    dogschool_trainer_profiles    – Trainer-Profile (Phase 7)
--    dogschool_trainer_availability– Verfügbarkeiten (Phase 7)
--    dogschool_booking_requests    – Online-Buchungsanfragen (Phase 8)
--
--  Alle Tabellen sind multi-tenant durch den MigrationService prefixed.
--  Zusätzlich sichert `DogschoolSchemaService` die Tabellen self-healed
--  on-the-fly — falls diese Migration nicht läuft, werden sie beim
--  ersten Zugriff idempotent erstellt.
-- ═══════════════════════════════════════════════════════════════

-- ─── Zusatz-Spalten für bestehende Tabellen (050/051) ───────────
ALTER TABLE `dogschool_courses`   ADD COLUMN IF NOT EXISTS `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 19.00;
ALTER TABLE `dogschool_packages`  ADD COLUMN IF NOT EXISTS `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 19.00;

-- ─── Kursarten-Katalog ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_course_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(50) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `icon` VARCHAR(50) NULL,
    `color` VARCHAR(20) NULL DEFAULT '#60a5fa',
    `default_duration_min` INT UNSIGNED NOT NULL DEFAULT 60,
    `default_max_participants` INT UNSIGNED NOT NULL DEFAULT 8,
    `default_price_cents` INT UNSIGNED NOT NULL DEFAULT 0,
    `default_tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 19.00,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Übungen-Katalog ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_exercises` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(80) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'basics',
    `description` TEXT NULL,
    `instructions` MEDIUMTEXT NULL,
    `difficulty` ENUM('easy','medium','hard','expert') NOT NULL DEFAULT 'easy',
    `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 10,
    `min_age_months` INT UNSIGNED NULL,
    `required_equipment` VARCHAR(500) NULL,
    `video_url` VARCHAR(500) NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_slug` (`slug`),
    INDEX `idx_category` (`category`),
    INDEX `idx_difficulty` (`difficulty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Trainingspläne ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_training_plans` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `target_audience` VARCHAR(100) NULL,
    `duration_weeks` INT UNSIGNED NOT NULL DEFAULT 8,
    `sessions_per_week` INT UNSIGNED NOT NULL DEFAULT 1,
    `difficulty` ENUM('easy','medium','hard','expert') NOT NULL DEFAULT 'easy',
    `is_template` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_target` (`target_audience`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Plan-Übungs-Zuordnung ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_plan_exercises` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `plan_id` INT UNSIGNED NOT NULL,
    `exercise_id` INT UNSIGNED NOT NULL,
    `week_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `session_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `target_repetitions` INT UNSIGNED NULL,
    `target_duration_minutes` INT UNSIGNED NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_plan` (`plan_id`),
    INDEX `idx_exercise` (`exercise_id`),
    INDEX `idx_week_session` (`plan_id`,`week_number`,`session_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Plan-Zuweisungen ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_plan_assignments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `plan_id` INT UNSIGNED NOT NULL,
    `patient_id` INT UNSIGNED NOT NULL,
    `owner_id` INT UNSIGNED NULL,
    `course_id` INT UNSIGNED NULL,
    `trainer_user_id` INT UNSIGNED NULL,
    `start_date` DATE NOT NULL,
    `target_end_date` DATE NULL,
    `completed_at` DATE NULL,
    `status` ENUM('active','paused','completed','cancelled') NOT NULL DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_patient` (`patient_id`),
    INDEX `idx_plan` (`plan_id`),
    INDEX `idx_course` (`course_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Fortschritts-Erfassung ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_training_progress` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT UNSIGNED NULL,
    `patient_id` INT UNSIGNED NOT NULL,
    `exercise_id` INT UNSIGNED NOT NULL,
    `session_id` INT UNSIGNED NULL,
    `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `mastery_level` TINYINT NOT NULL DEFAULT 0
        COMMENT '0=nicht geübt, 1=eingeführt, 2=geübt, 3=sicher, 4=gemeistert, 5=abgeschlossen',
    `repetitions` INT UNSIGNED NULL,
    `duration_minutes` INT UNSIGNED NULL,
    `success_rate_pct` TINYINT UNSIGNED NULL,
    `notes` TEXT NULL,
    `recorded_by_user_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_patient` (`patient_id`),
    INDEX `idx_exercise` (`exercise_id`),
    INDEX `idx_assignment` (`assignment_id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_recorded_at` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Hausaufgaben ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_homework` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT UNSIGNED NULL,
    `patient_id` INT UNSIGNED NOT NULL,
    `exercise_id` INT UNSIGNED NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `due_date` DATE NULL,
    `status` ENUM('open','done','skipped') NOT NULL DEFAULT 'open',
    `owner_feedback` TEXT NULL,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_patient_status` (`patient_id`,`status`),
    INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Trainer-Profile (Phase 7) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_trainer_profiles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `display_name` VARCHAR(200) NULL,
    `bio` TEXT NULL,
    `qualifications` TEXT NULL,
    `specializations` VARCHAR(500) NULL,
    `color` VARCHAR(20) NULL DEFAULT '#60a5fa',
    `avatar_url` VARCHAR(500) NULL,
    `phone` VARCHAR(50) NULL,
    `email_public` VARCHAR(200) NULL,
    `public_profile` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dogschool_trainer_availability` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `weekday` TINYINT NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `valid_from` DATE NULL,
    `valid_until` DATE NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_weekday` (`user_id`,`weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Online-Buchung (Phase 8) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_booking_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `token` VARCHAR(64) NOT NULL,
    `course_id` INT UNSIGNED NULL,
    `lead_id` INT UNSIGNED NULL,
    `first_name` VARCHAR(100) NOT NULL DEFAULT '',
    `last_name` VARCHAR(100) NOT NULL DEFAULT '',
    `email` VARCHAR(200) NULL,
    `phone` VARCHAR(50) NULL,
    `dog_name` VARCHAR(100) NULL,
    `dog_breed` VARCHAR(100) NULL,
    `dog_age_months` INT UNSIGNED NULL,
    `message` TEXT NULL,
    `requested_for` VARCHAR(50) NULL,
    `status` ENUM('pending','approved','declined','spam') NOT NULL DEFAULT 'pending',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_token` (`token`),
    INDEX `idx_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
