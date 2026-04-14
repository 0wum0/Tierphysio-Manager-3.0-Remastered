-- Migration 027: Add tenant_prefix column to mobile_api_tokens
-- The column stores the resolved tenant table prefix so the Mobile API
-- can be fully stateless (no PHP session) in multi-tenant setups.

-- Note: Because PHP PDO does not support the 'DELIMITER' keyword,
-- we use a standard ALTER TABLE statement. If you run this file multiple
-- times, it might throw an error if the column already exists.
-- You can safely ignore that error.

CREATE TABLE IF NOT EXISTS mobile_api_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token       VARCHAR(64)  NOT NULL UNIQUE,
    device_name VARCHAR(100) NOT NULL DEFAULT '',
    tenant_prefix VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'Tenant table prefix resolved at login (e.g. t_abc123_)',
    last_used   DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME     NULL,
    INDEX idx_token (token),
    INDEX idx_user  (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
