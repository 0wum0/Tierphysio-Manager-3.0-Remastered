<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * DogschoolSchemaService — Self-Healing für alle Hundeschul-Tabellen.
 *
 * Motivation:
 *   Auch wenn die SQL-Migrations 050/051/052/053 via MigrationService laufen,
 *   kann ein Tenant aus älterem Stand starten oder eine Migration scheitert.
 *   Damit die Hundeschul-Module NIEMALS "Table not found"-Fehler werfen,
 *   garantiert dieser Service dass alle Tabellen existieren — idempotent,
 *   atomar und ohne Daten-Verlust.
 *
 * Kritisches Prinzip:
 *   - Nur CREATE TABLE IF NOT EXISTS — keine DROP, keine ALTER die Daten zerstören
 *   - Praxis-Tabellen (patients, owners, invoices) werden NICHT angefasst
 *   - Fremd-Keys werden weich verknüpft (INT UNSIGNED + Index, kein FK-Constraint)
 *     damit bei Tenant-Cleanup nichts kaskadiert löscht
 *   - Tenant-Isolation via `$db->prefix()` — MUSS vor ensure() gesetzt sein
 */
class DogschoolSchemaService
{
    /**
     * Per-Prozess-Cache: wurde für diesen Tenant-Prefix schon ensured?
     * Verhindert redundante CREATE-Queries bei mehreren Controller-Aufrufen
     * im selben Request.
     */
    private static array $ensuredPrefixes = [];

    public function __construct(
        private readonly Database $db,
    ) {}

    /**
     * Stellt sicher dass alle Hundeschul-Tabellen existieren.
     *
     * Kostet bei bereits vorhandenen Tabellen ~0ms (MySQL IF NOT EXISTS
     * ist cheap). Erster Aufruf legt an, weitere sind No-Ops.
     *
     * @param bool $force  Cache ignorieren (z.B. nach Prefix-Wechsel)
     */
    public function ensure(bool $force = false): void
    {
        $prefix = $this->db->prefix('');
        if (!$force && isset(self::$ensuredPrefixes[$prefix])) {
            return;
        }

        try {
            $this->createCourseTables();
            $this->createPackageTables();
            $this->createLeadAndConsentTables();
            $this->createTrainingPlanTables();
            $this->createTrainerTeamTables();
            $this->createOnlineBookingTables();

            self::$ensuredPrefixes[$prefix] = true;
        } catch (\Throwable $e) {
            /* Fehlgeschlagenes Self-Healing nicht maskieren — die Anwendung
             * würde sonst subtil weiterarbeiten und beim Lesen crashen. */
            error_log('[DogschoolSchemaService] ensure() failed: ' . $e->getMessage());
            throw new \RuntimeException(
                'Hundeschul-Tabellen konnten nicht erstellt werden: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
     *  Kurse / Sessions / Enrollments / Waitlist / Attendance (050)
     * ═══════════════════════════════════════════════════════════════════ */
    private function createCourseTables(): void
    {
        $t = fn(string $n) => $this->db->prefix($n);

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_courses')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `type` VARCHAR(50) NOT NULL DEFAULT 'group',
            `description` TEXT NULL,
            `level` VARCHAR(20) NULL,
            `trainer_user_id` INT UNSIGNED NULL,
            `location` VARCHAR(200) NULL,
            `start_date` DATE NULL,
            `end_date` DATE NULL,
            `weekday` TINYINT NULL,
            `start_time` TIME NULL,
            `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
            `max_participants` INT UNSIGNED NOT NULL DEFAULT 8,
            `price_cents` INT UNSIGNED NOT NULL DEFAULT 0,
            `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 19.00,
            `num_sessions` INT UNSIGNED NOT NULL DEFAULT 1,
            `status` ENUM('draft','active','full','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
            `notes` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_status` (`status`),
            INDEX `idx_start_date` (`start_date`),
            INDEX `idx_trainer_user` (`trainer_user_id`),
            INDEX `idx_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        /* Spalte tax_rate nachrüsten falls Tabelle aus Migration 050 stammt */
        try {
            $this->db->execute(
                "ALTER TABLE `{$t('dogschool_courses')}`
                    ADD COLUMN IF NOT EXISTS `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 19.00"
            );
        } catch (\Throwable) {}

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_course_sessions')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `course_id` INT UNSIGNED NOT NULL,
            `session_number` INT UNSIGNED NOT NULL DEFAULT 1,
            `session_date` DATE NOT NULL,
            `start_time` TIME NULL,
            `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
            `trainer_user_id` INT UNSIGNED NULL,
            `topic` VARCHAR(200) NULL,
            `notes` TEXT NULL,
            `status` ENUM('planned','held','cancelled') NOT NULL DEFAULT 'planned',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_course` (`course_id`),
            INDEX `idx_session_date` (`session_date`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_enrollments')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `course_id` INT UNSIGNED NOT NULL,
            `patient_id` INT UNSIGNED NOT NULL,
            `owner_id` INT UNSIGNED NOT NULL,
            `enrolled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('active','cancelled','completed','transferred','no_show') NOT NULL DEFAULT 'active',
            `price_cents` INT UNSIGNED NULL,
            `paid` TINYINT(1) NOT NULL DEFAULT 0,
            `invoice_id` INT UNSIGNED NULL,
            `package_id` INT UNSIGNED NULL,
            `notes` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_course_patient` (`course_id`,`patient_id`),
            INDEX `idx_owner` (`owner_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_invoice` (`invoice_id`),
            INDEX `idx_package` (`package_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_waitlist')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `course_id` INT UNSIGNED NOT NULL,
            `patient_id` INT UNSIGNED NULL,
            `owner_id` INT UNSIGNED NULL,
            `lead_name` VARCHAR(200) NULL,
            `lead_email` VARCHAR(200) NULL,
            `lead_phone` VARCHAR(50) NULL,
            `position` INT UNSIGNED NOT NULL DEFAULT 1,
            `status` ENUM('waiting','offered','accepted','declined','expired') NOT NULL DEFAULT 'waiting',
            `notified_at` DATETIME NULL,
            `expires_at` DATETIME NULL,
            `notes` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_course_status` (`course_id`,`status`),
            INDEX `idx_position` (`position`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_attendance')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `session_id` INT UNSIGNED NOT NULL,
            `enrollment_id` INT UNSIGNED NOT NULL,
            `status` ENUM('present','absent','excused','late','left_early','no_show') NOT NULL DEFAULT 'present',
            `notes` TEXT NULL,
            `marked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `marked_by_user_id` INT UNSIGNED NULL,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_session_enrollment` (`session_id`,`enrollment_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        /* Kursarten-Editor (Phase 6) */
        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_course_categories')}` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /* ═══════════════════════════════════════════════════════════════════
     *  Pakete (051)
     * ═══════════════════════════════════════════════════════════════════ */
    private function createPackageTables(): void
    {
        $t = fn(string $n) => $this->db->prefix($n);

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_packages')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `description` TEXT NULL,
            `type` ENUM('single','multi','subscription','unlimited') NOT NULL DEFAULT 'multi',
            `total_units` INT UNSIGNED NOT NULL DEFAULT 1,
            `valid_days` INT UNSIGNED NULL,
            `price_cents` INT UNSIGNED NOT NULL DEFAULT 0,
            `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 19.00,
            `applies_to_types` VARCHAR(500) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $this->db->execute(
                "ALTER TABLE `{$t('dogschool_packages')}`
                    ADD COLUMN IF NOT EXISTS `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 19.00"
            );
        } catch (\Throwable) {}

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_package_balances')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `package_id` INT UNSIGNED NOT NULL,
            `owner_id` INT UNSIGNED NOT NULL,
            `patient_id` INT UNSIGNED NULL,
            `units_total` INT UNSIGNED NOT NULL DEFAULT 0,
            `units_used` INT UNSIGNED NOT NULL DEFAULT 0,
            `purchased_at` DATE NOT NULL,
            `expires_at` DATE NULL,
            `invoice_id` INT UNSIGNED NULL,
            `status` ENUM('active','expired','used_up','refunded') NOT NULL DEFAULT 'active',
            `notes` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_owner` (`owner_id`),
            INDEX `idx_patient` (`patient_id`),
            INDEX `idx_package` (`package_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_package_redemptions')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `balance_id` INT UNSIGNED NOT NULL,
            `enrollment_id` INT UNSIGNED NULL,
            `session_id` INT UNSIGNED NULL,
            `units` INT UNSIGNED NOT NULL DEFAULT 1,
            `redeemed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `redeemed_by_user_id` INT UNSIGNED NULL,
            `notes` VARCHAR(500) NULL,
            INDEX `idx_balance` (`balance_id`),
            INDEX `idx_enrollment` (`enrollment_id`),
            INDEX `idx_session` (`session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /* ═══════════════════════════════════════════════════════════════════
     *  Leads / Consents (052)
     * ═══════════════════════════════════════════════════════════════════ */
    private function createLeadAndConsentTables(): void
    {
        $t = fn(string $n) => $this->db->prefix($n);

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_leads')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `source` VARCHAR(50) NULL,
            `first_name` VARCHAR(100) NOT NULL DEFAULT '',
            `last_name` VARCHAR(100) NOT NULL DEFAULT '',
            `email` VARCHAR(200) NULL,
            `phone` VARCHAR(50) NULL,
            `dog_name` VARCHAR(100) NULL,
            `dog_breed` VARCHAR(100) NULL,
            `dog_age_months` INT UNSIGNED NULL,
            `interest` VARCHAR(200) NULL,
            `message` TEXT NULL,
            `status` ENUM('new','contacted','trial_scheduled','trial_done','converted','lost','archived') NOT NULL DEFAULT 'new',
            `next_followup_at` DATETIME NULL,
            `converted_owner_id` INT UNSIGNED NULL,
            `converted_patient_id` INT UNSIGNED NULL,
            `notes` TEXT NULL,
            `assigned_user_id` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_status` (`status`),
            INDEX `idx_followup` (`next_followup_at`),
            INDEX `idx_assigned` (`assigned_user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_consents')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `content` TEXT NOT NULL,
            `version` VARCHAR(20) NOT NULL DEFAULT '1.0',
            `type` ENUM('participation','photo_video','liability','data_protection','vaccination','other') NOT NULL DEFAULT 'participation',
            `is_required` TINYINT(1) NOT NULL DEFAULT 1,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_consent_signatures')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `consent_id` INT UNSIGNED NOT NULL,
            `owner_id` INT UNSIGNED NOT NULL,
            `patient_id` INT UNSIGNED NULL,
            `status` ENUM('pending','signed','revoked') NOT NULL DEFAULT 'pending',
            `signed_at` DATETIME NULL,
            `revoked_at` DATETIME NULL,
            `signature_name` VARCHAR(200) NULL,
            `signature_data` MEDIUMTEXT NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(500) NULL,
            `notes` TEXT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_consent_owner` (`consent_id`,`owner_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /* ═══════════════════════════════════════════════════════════════════
     *  TCP / Trainingspläne (053) — NEU
     * ═══════════════════════════════════════════════════════════════════ */
    private function createTrainingPlanTables(): void
    {
        $t = fn(string $n) => $this->db->prefix($n);

        /* Übungen-Katalog */
        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_exercises')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(80) NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `category` VARCHAR(50) NOT NULL DEFAULT 'basics'
                COMMENT 'basics|obedience|tricks|social|agility|problem|recall|leash',
            `description` TEXT NULL,
            `instructions` MEDIUMTEXT NULL COMMENT 'Schritt-für-Schritt-Anleitung',
            `difficulty` ENUM('easy','medium','hard','expert') NOT NULL DEFAULT 'easy',
            `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 10,
            `min_age_months` INT UNSIGNED NULL,
            `required_equipment` VARCHAR(500) NULL,
            `video_url` VARCHAR(500) NULL,
            `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Standard-Übung (nicht löschbar)',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_slug` (`slug`),
            INDEX `idx_category` (`category`),
            INDEX `idx_difficulty` (`difficulty`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        /* Trainingsplan-Vorlagen (wiederverwendbar über Hunde) */
        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_training_plans')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL,
            `description` TEXT NULL,
            `target_audience` VARCHAR(100) NULL COMMENT 'welpen|junghunde|adult|senior|problem',
            `duration_weeks` INT UNSIGNED NOT NULL DEFAULT 8,
            `sessions_per_week` INT UNSIGNED NOT NULL DEFAULT 1,
            `difficulty` ENUM('easy','medium','hard','expert') NOT NULL DEFAULT 'easy',
            `is_template` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Vorlage, 0 = individueller Plan',
            `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Standard-Vorlage (geseedet)',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_target` (`target_audience`),
            INDEX `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        /* Plan-Übungs-Zuordnung (Curriculum) */
        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_plan_exercises')}` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        /* Plan-Zuweisung an Hund (individuelle Instanz) */
        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_plan_assignments')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `plan_id` INT UNSIGNED NOT NULL,
            `patient_id` INT UNSIGNED NOT NULL COMMENT 'Hund',
            `owner_id` INT UNSIGNED NULL,
            `course_id` INT UNSIGNED NULL COMMENT 'Optional: an einen Kurs gekoppelt',
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        /* Fortschritts-Einträge (pro Hund + Übung + Datum) */
        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_training_progress')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `assignment_id` INT UNSIGNED NULL,
            `patient_id` INT UNSIGNED NOT NULL,
            `exercise_id` INT UNSIGNED NOT NULL,
            `session_id` INT UNSIGNED NULL COMMENT 'dogschool_course_sessions.id',
            `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `mastery_level` TINYINT NOT NULL DEFAULT 0
                COMMENT '0=nicht geübt, 1=eingeführt, 2=geübt, 3=sicher, 4=gemeistert, 5=abgeschlossen',
            `repetitions` INT UNSIGNED NULL,
            `duration_minutes` INT UNSIGNED NULL,
            `success_rate_pct` TINYINT UNSIGNED NULL COMMENT '0-100%',
            `notes` TEXT NULL,
            `recorded_by_user_id` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_patient` (`patient_id`),
            INDEX `idx_exercise` (`exercise_id`),
            INDEX `idx_assignment` (`assignment_id`),
            INDEX `idx_session` (`session_id`),
            INDEX `idx_recorded_at` (`recorded_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        /* Hausaufgaben (Owner bekommt Übung zuhause) */
        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_homework')}` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /* ═══════════════════════════════════════════════════════════════════
     *  Trainer-Team (Phase 7)
     * ═══════════════════════════════════════════════════════════════════ */
    private function createTrainerTeamTables(): void
    {
        $t = fn(string $n) => $this->db->prefix($n);

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_trainer_profiles')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `display_name` VARCHAR(200) NULL,
            `bio` TEXT NULL,
            `qualifications` TEXT NULL,
            `specializations` VARCHAR(500) NULL COMMENT 'CSV: welpen,problem,agility,…',
            `color` VARCHAR(20) NULL DEFAULT '#60a5fa',
            `avatar_url` VARCHAR(500) NULL,
            `phone` VARCHAR(50) NULL,
            `email_public` VARCHAR(200) NULL,
            `public_profile` TINYINT(1) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_trainer_availability')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `weekday` TINYINT NOT NULL COMMENT '0=So, 1=Mo, …, 6=Sa',
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `valid_from` DATE NULL,
            `valid_until` DATE NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_weekday` (`user_id`,`weekday`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /* ═══════════════════════════════════════════════════════════════════
     *  Online-Buchung (Phase 8)
     * ═══════════════════════════════════════════════════════════════════ */
    private function createOnlineBookingTables(): void
    {
        $t = fn(string $n) => $this->db->prefix($n);

        $this->db->safeExecute("CREATE TABLE IF NOT EXISTS `{$t('dogschool_booking_requests')}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `token` VARCHAR(64) NOT NULL COMMENT 'Öffentliche Request-ID',
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
            `requested_for` VARCHAR(50) NULL COMMENT 'course|trial|consultation',
            `status` ENUM('pending','approved','declined','spam') NOT NULL DEFAULT 'pending',
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(500) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_token` (`token`),
            INDEX `idx_status_created` (`status`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Hebe den Cache explizit auf — nach Tenant-Wechsel im Prozess
     * (z.B. Cron über alle Tenants).
     */
    public function clearCache(): void
    {
        self::$ensuredPrefixes = [];
    }
}
