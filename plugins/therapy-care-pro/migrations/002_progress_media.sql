-- ══════════════════════════════════════════════════════════════════════
-- TherapyCare Pro — Migration 002: Progress Media (Vorher / Nachher)
-- ──────────────────────────────────────────────────────────────────────
-- Erweitert das bestehende Fortschrittssystem um Foto-/Video-Anhänge.
-- Jeder Eintrag in `tcp_progress_entries` kann beliebig viele Medien
-- haben, optional als "vorher" oder "nachher" gelabelt — das ermöglicht
-- den Vorher/Nachher-Vergleich im Portal und in der Therapie-Story.
--
-- Self-healing: idempotent (IF NOT EXISTS), keine Daten-Migration nötig.
-- Tenant-Isolation: {{PREFIX}}-Platzhalter wird vom PluginMigrationService
-- durch den aktuellen Tenant-Prefix ersetzt.
-- ══════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `{{PREFIX}}tcp_progress_media` (
    `id`                INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    `progress_entry_id` INT UNSIGNED       NOT NULL,
    `patient_id`        INT UNSIGNED       NOT NULL COMMENT 'Denormalisiert für schnelle Story-Queries pro Patient',
    `file_path`         VARCHAR(500)       NOT NULL COMMENT 'Relativer Pfad unter storage/tenants/{prefix}/progress_media/',
    `mime_type`         VARCHAR(100)       NOT NULL DEFAULT 'application/octet-stream',
    `file_size`         INT UNSIGNED       NOT NULL DEFAULT 0,
    `original_name`     VARCHAR(255)       DEFAULT NULL,
    `media_type`        ENUM('image','video','audio','other') NOT NULL DEFAULT 'image',
    `phase_label`       ENUM('vorher','nachher','verlauf') NOT NULL DEFAULT 'verlauf'
                          COMMENT 'Steuert Anzeige im Vorher/Nachher-Vergleich',
    `caption`           VARCHAR(500)       DEFAULT NULL,
    `sort_order`        INT UNSIGNED       NOT NULL DEFAULT 0,
    `uploaded_by`       INT UNSIGNED       DEFAULT NULL,
    `uploaded_via`      ENUM('practice','portal','mobile_api') NOT NULL DEFAULT 'practice',
    `created_at`        DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tcp_pm_entry`      (`progress_entry_id`),
    INDEX `idx_tcp_pm_patient`    (`patient_id`),
    INDEX `idx_tcp_pm_phase`      (`phase_label`),
    INDEX `idx_tcp_pm_created`    (`created_at`),
    CONSTRAINT `{{PREFIX}}fk_tcp_pm_entry`
        FOREIGN KEY (`progress_entry_id`)
        REFERENCES `{{PREFIX}}tcp_progress_entries` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `{{PREFIX}}fk_tcp_pm_patient`
        FOREIGN KEY (`patient_id`)
        REFERENCES `{{PREFIX}}patients` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `{{PREFIX}}fk_tcp_pm_user`
        FOREIGN KEY (`uploaded_by`)
        REFERENCES `{{PREFIX}}users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
