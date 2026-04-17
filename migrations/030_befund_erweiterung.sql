-- ═══════════════════════════════════════════════════════════════
--  Migration 030: Befund-Erweiterung (Interaktive Anatomie,
--                 Textbausteine, Vorlagen)
--
--  ALLES IST ADDITIV. Bestehende Tabellen werden NICHT verändert.
--  Interaktive Anatomie-Marker werden in bestehender Tabelle
--  `befundbogen_felder` als JSON unter neuen Feldnamen gespeichert:
--    - anatomy_species
--    - anatomy_markers
--    - anatomy_drawings
--    - physio_bereiche
--
--  Keine Schema-Änderung an `befundboegen` / `befundbogen_felder`.
-- ═══════════════════════════════════════════════════════════════

-- Tenant-spezifische Textbausteine
CREATE TABLE IF NOT EXISTS `befund_textbausteine` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope`       VARCHAR(50)  NOT NULL DEFAULT 'allgemein'
                COMMENT 'anamnese|befund|therapie|allgemein',
  `title`       VARCHAR(200) NOT NULL,
  `content`     TEXT         NOT NULL,
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT current_timestamp(),
  `updated_at`  DATETIME     NOT NULL DEFAULT current_timestamp()
                ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tenant-spezifische Befund-Vorlagen (JSON-Struktur mit Feld-Defaults)
CREATE TABLE IF NOT EXISTS `befund_vorlagen` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(200) NOT NULL,
  `species`     VARCHAR(50)  DEFAULT NULL
                COMMENT 'dog|cat|horse|null=generic',
  `felder`      MEDIUMTEXT   DEFAULT NULL COMMENT 'JSON: { feldname: wert }',
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT current_timestamp(),
  `updated_at`  DATETIME     NOT NULL DEFAULT current_timestamp()
                ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_species` (`species`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
