-- ============================================================
-- TheraPano SaaS Platform – Migration 002
-- Tenant-Auth-Spalten: TID, Passwort-Hash, Login-Timestamp,
--                      Password-Reset-Token
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- tenants: Tenant-Identifikator (kurze, lesbare ID)
-- ------------------------------------------------------------
ALTER TABLE `tenants`
  ADD COLUMN IF NOT EXISTS `tid`
    VARCHAR(32) NULL UNIQUE
    COMMENT 'Kurze eindeutige Tenant-ID (z.B. praxis-mueller-berlin)'
    AFTER `uuid`;

-- ------------------------------------------------------------
-- tenants: Passwort-Hash für den Portal-Login (therapano.de/login)
-- ------------------------------------------------------------
ALTER TABLE `tenants`
  ADD COLUMN IF NOT EXISTS `password_hash`
    VARCHAR(255) NULL
    COMMENT 'bcrypt-Hash des Portal-Passworts'
    AFTER `tid`;

-- ------------------------------------------------------------
-- tenants: Letzter Login-Zeitstempel
-- ------------------------------------------------------------
ALTER TABLE `tenants`
  ADD COLUMN IF NOT EXISTS `last_login_at`
    DATETIME NULL
    COMMENT 'Letzter erfolgreicher Login auf therapano.de'
    AFTER `admin_created`;

-- ------------------------------------------------------------
-- tenants: Password-Reset-Token
-- ------------------------------------------------------------
ALTER TABLE `tenants`
  ADD COLUMN IF NOT EXISTS `reset_token`
    VARCHAR(64) NULL UNIQUE
    COMMENT 'Token für Passwort-Zurücksetzen'
    AFTER `last_login_at`;

ALTER TABLE `tenants`
  ADD COLUMN IF NOT EXISTS `reset_token_expires_at`
    DATETIME NULL
    COMMENT 'Ablaufzeit des Reset-Tokens (1 Stunde)'
    AFTER `reset_token`;

-- ------------------------------------------------------------
-- tenants: status ENUM erweitern um 'trial'
-- (Freie Testphase ohne Zahlungsinfo)
-- ------------------------------------------------------------
ALTER TABLE `tenants`
  MODIFY COLUMN `status`
    ENUM('pending','trial','active','paused','cancelled','suspended')
    NOT NULL DEFAULT 'pending';

-- ------------------------------------------------------------
-- Index auf tid für schnelle Lookups
-- ------------------------------------------------------------
CREATE INDEX IF NOT EXISTS `idx_tenants_tid`
  ON `tenants` (`tid`);

-- ------------------------------------------------------------
-- TID für bestehende Tenants nachfüllen (UUID-basiert, sicher)
-- ------------------------------------------------------------
UPDATE `tenants`
SET `tid` = LOWER(CONCAT(
    SUBSTRING(`uuid`, 1, 8), '-',
    SUBSTRING(`uuid`, 10, 4)
  ))
WHERE `tid` IS NULL;
