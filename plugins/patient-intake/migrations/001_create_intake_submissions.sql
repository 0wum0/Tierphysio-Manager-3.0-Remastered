CREATE TABLE IF NOT EXISTS `{{prefix}}patient_intake_submissions` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `status`            ENUM('neu','in_bearbeitung','uebernommen','abgelehnt') NOT NULL DEFAULT 'neu',

    -- Besitzer
    `owner_first_name`  VARCHAR(100) NOT NULL DEFAULT '',
    `owner_last_name`   VARCHAR(100) NOT NULL DEFAULT '',
    `owner_email`       VARCHAR(255) NOT NULL DEFAULT '',
    `owner_phone`       VARCHAR(60)  NOT NULL DEFAULT '',
    `owner_street`      VARCHAR(200) NOT NULL DEFAULT '',
    `owner_zip`         VARCHAR(20)  NOT NULL DEFAULT '',
    `owner_city`        VARCHAR(100) NOT NULL DEFAULT '',

    -- Tier / Patient
    `patient_name`      VARCHAR(100) NOT NULL DEFAULT '',
    `patient_species`   VARCHAR(80)  NOT NULL DEFAULT '',
    `patient_breed`     VARCHAR(100) NOT NULL DEFAULT '',
    `patient_gender`    VARCHAR(20)  NOT NULL DEFAULT '',
    `patient_birth_date`DATE         NULL,
    `patient_color`     VARCHAR(80)  NOT NULL DEFAULT '',
    `patient_chip`      VARCHAR(60)  NOT NULL DEFAULT '',
    `patient_photo`     VARCHAR(255) NOT NULL DEFAULT '',

    -- Anliegen
    `reason`            TEXT         NOT NULL,
    `appointment_wish`  VARCHAR(255) NOT NULL DEFAULT '',
    `notes`             TEXT         NOT NULL,

    -- Metadaten
    `ip_address`        VARCHAR(45)  NOT NULL DEFAULT '',
    `accepted_patient_id` INT UNSIGNED NULL DEFAULT NULL,
    `accepted_owner_id`   INT UNSIGNED NULL DEFAULT NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
