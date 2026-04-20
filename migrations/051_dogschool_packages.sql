-- ═══════════════════════════════════════════════════════════════
--  Migration 051: Pakete / Mehrfachkarten für Hundeschulen
--
--  Fachliche Logik:
--    dogschool_packages        – Paket-Katalog (z.B. "10er Karte Gruppe")
--    dogschool_package_balances – Gekaufter Paket-Kontostand pro Halter
--    dogschool_package_redemptions – Einlösung pro Termin/Einschreibung
--
--  Integriert mit dogschool_enrollments.package_id (bereits in 050).
-- ═══════════════════════════════════════════════════════════════

-- Paket-Katalog (Admin-Konfiguration)
CREATE TABLE IF NOT EXISTS `dogschool_packages` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`                VARCHAR(200) NOT NULL,
    `description`         TEXT         NULL,
    `type`                ENUM('single','multi','subscription','unlimited')
                          NOT NULL DEFAULT 'multi'
                          COMMENT 'single=1 Stunde, multi=N-er-Karte, subscription=Abo, unlimited=Flatrate',
    `total_units`         INT UNSIGNED NOT NULL DEFAULT 1
                          COMMENT 'Anzahl Einheiten (z.B. 10 für 10er-Karte)',
    `valid_days`          INT UNSIGNED NULL
                          COMMENT 'Gültigkeitsdauer in Tagen ab Kauf. NULL = unbegrenzt',
    `price_cents`         INT UNSIGNED NOT NULL DEFAULT 0,
    `applies_to_types`    VARCHAR(500) NULL
                          COMMENT 'Komma-separierte Kurstypen (welpen,junghunde,…) oder NULL=alle',
    `is_active`           TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontostand eines Halters auf einem gekauften Paket
CREATE TABLE IF NOT EXISTS `dogschool_package_balances` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `package_id`          INT UNSIGNED NOT NULL,
    `owner_id`            INT UNSIGNED NOT NULL,
    `patient_id`          INT UNSIGNED NULL
                          COMMENT 'Optional an einen Hund gebunden; NULL=für alle Hunde des Halters',
    `units_total`         INT UNSIGNED NOT NULL DEFAULT 0
                          COMMENT 'Gekauft (Start)',
    `units_used`          INT UNSIGNED NOT NULL DEFAULT 0,
    `purchased_at`        DATE         NOT NULL,
    `expires_at`          DATE         NULL,
    `invoice_id`          INT UNSIGNED NULL COMMENT 'FK invoices.id falls berechnet',
    `status`              ENUM('active','expired','used_up','refunded')
                          NOT NULL DEFAULT 'active',
    `notes`               TEXT         NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_owner`       (`owner_id`),
    INDEX `idx_patient`     (`patient_id`),
    INDEX `idx_package`     (`package_id`),
    INDEX `idx_status`      (`status`),
    INDEX `idx_expires`     (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Einlösungen (Welche Einheit wurde wann eingelöst)
CREATE TABLE IF NOT EXISTS `dogschool_package_redemptions` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `balance_id`          INT UNSIGNED NOT NULL,
    `enrollment_id`       INT UNSIGNED NULL COMMENT 'FK enrollments.id (wenn Kurs-Einschreibung)',
    `session_id`          INT UNSIGNED NULL COMMENT 'FK course_sessions.id (wenn einzelner Termin)',
    `units`               INT UNSIGNED NOT NULL DEFAULT 1,
    `redeemed_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `redeemed_by_user_id` INT UNSIGNED NULL,
    `notes`               VARCHAR(500) NULL,
    INDEX `idx_balance`   (`balance_id`),
    INDEX `idx_enrollment`(`enrollment_id`),
    INDEX `idx_session`   (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
