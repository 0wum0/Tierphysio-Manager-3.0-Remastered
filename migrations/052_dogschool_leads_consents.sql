-- ═══════════════════════════════════════════════════════════════
--  Migration 052: Interessenten-CRM + digitale Einwilligungen
-- ═══════════════════════════════════════════════════════════════

-- ─── Leads / Interessenten ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_leads` (
    `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `source`                  VARCHAR(50)  NULL COMMENT 'Google, Instagram, Empfehlung, Flyer, Probetraining, …',
    `first_name`              VARCHAR(100) NOT NULL DEFAULT '',
    `last_name`               VARCHAR(100) NOT NULL DEFAULT '',
    `email`                   VARCHAR(200) NULL,
    `phone`                   VARCHAR(50)  NULL,
    `dog_name`                VARCHAR(100) NULL,
    `dog_breed`               VARCHAR(100) NULL,
    `dog_age_months`          INT UNSIGNED NULL,
    `interest`                VARCHAR(200) NULL COMMENT 'z.B. Welpenkurs, Rückruftraining',
    `message`                 TEXT         NULL,
    `status`                  ENUM('new','contacted','trial_scheduled','trial_done','converted','lost','archived')
                              NOT NULL DEFAULT 'new',
    `next_followup_at`        DATETIME     NULL,
    `converted_owner_id`      INT UNSIGNED NULL COMMENT 'FK owners.id nach Konversion',
    `converted_patient_id`    INT UNSIGNED NULL COMMENT 'FK patients.id nach Konversion',
    `notes`                   TEXT         NULL,
    `assigned_user_id`        INT UNSIGNED NULL,
    `created_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status`         (`status`),
    INDEX `idx_followup`       (`next_followup_at`),
    INDEX `idx_assigned`       (`assigned_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Einwilligungen-Katalog ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_consents` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(200) NOT NULL,
    `content`       TEXT         NOT NULL,
    `version`       VARCHAR(20)  NOT NULL DEFAULT '1.0',
    `type`          ENUM('participation','photo_video','liability','data_protection','vaccination','other')
                    NOT NULL DEFAULT 'participation',
    `is_required`   TINYINT(1)   NOT NULL DEFAULT 1,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Einwilligungs-Signaturen ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `dogschool_consent_signatures` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `consent_id`        INT UNSIGNED NOT NULL,
    `owner_id`          INT UNSIGNED NOT NULL,
    `patient_id`        INT UNSIGNED NULL COMMENT 'Optional: hundspezifische Einwilligung',
    `status`            ENUM('pending','signed','revoked')
                        NOT NULL DEFAULT 'pending',
    `signed_at`         DATETIME     NULL,
    `revoked_at`        DATETIME     NULL,
    `signature_name`    VARCHAR(200) NULL COMMENT 'Name wie auf Papier gesetzt',
    `signature_data`    MEDIUMTEXT   NULL COMMENT 'Base64-Bild der Signatur (optional)',
    `ip_address`        VARCHAR(45)  NULL,
    `user_agent`        VARCHAR(500) NULL,
    `notes`             TEXT         NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_consent_owner` (`consent_id`,`owner_id`),
    INDEX `idx_status`        (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
