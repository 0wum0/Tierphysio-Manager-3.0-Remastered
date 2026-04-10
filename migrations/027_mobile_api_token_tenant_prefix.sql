-- Migration 027: Add tenant_prefix column to mobile_api_tokens
-- This allows the Mobile API to be fully stateless (no PHP session required)
-- by storing the resolved tenant table prefix alongside each token.
-- The prefix is set at login time and read back on every API request.

ALTER TABLE mobile_api_tokens
    ADD COLUMN IF NOT EXISTS tenant_prefix VARCHAR(64) NOT NULL DEFAULT ''
        COMMENT 'Tenant table prefix resolved at login (e.g. t_abc123_). Allows stateless multi-tenant API.' 
        AFTER device_name;
