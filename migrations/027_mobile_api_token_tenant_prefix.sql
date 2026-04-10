-- Migration 027: Add tenant_prefix column to mobile_api_tokens
-- The column stores the resolved tenant table prefix so the Mobile API
-- can be fully stateless (no PHP session) in multi-tenant setups.

-- Note: Because PHP PDO does not support the 'DELIMITER' keyword,
-- we use a standard ALTER TABLE statement. If you run this file multiple
-- times, it might throw an error if the column already exists.
-- You can safely ignore that error.

ALTER TABLE mobile_api_tokens
    ADD COLUMN tenant_prefix VARCHAR(64) NOT NULL DEFAULT ''
    COMMENT 'Tenant table prefix resolved at login (e.g. t_abc123_)'
    AFTER device_name;
