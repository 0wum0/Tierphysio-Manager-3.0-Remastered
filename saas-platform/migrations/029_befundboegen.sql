CREATE TABLE IF NOT EXISTS `befundboegen` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id`       INT UNSIGNED NOT NULL,
  `owner_id`         INT UNSIGNED DEFAULT NULL,
  `created_by`       INT UNSIGNED DEFAULT NULL,
  `status`           ENUM('entwurf','abgeschlossen','versendet') NOT NULL DEFAULT 'entwurf',
  `datum`            DATE NOT NULL,
  `naechster_termin` DATE DEFAULT NULL,
  `notizen`          TEXT DEFAULT NULL,
  `pdf_path`         VARCHAR(500) DEFAULT NULL,
  `pdf_sent_at`      DATETIME DEFAULT NULL,
  `pdf_sent_to`      VARCHAR(255) DEFAULT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT current_timestamp(),
  `updated_at`       DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_owner`   (`owner_id`),
  KEY `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `befundbogen_felder` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `befundbogen_id` INT UNSIGNED NOT NULL,
  `feldname`       VARCHAR(100) NOT NULL COMMENT 'z.B. hauptbeschwerde, bachblueten_auswahl, schmerz_nrs',
  `feldwert`       MEDIUMTEXT DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_befundbogen` (`befundbogen_id`),
  FOREIGN KEY (`befundbogen_id`) REFERENCES `befundboegen`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
