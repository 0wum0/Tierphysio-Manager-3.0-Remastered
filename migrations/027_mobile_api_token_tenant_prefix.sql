-- Migration 027: Add tenant_prefix column to mobile_api_tokens
-- Compatible with MySQL 5.7, MariaDB, and MySQL 8.0+
-- The column stores the resolved tenant table prefix so the Mobile API
-- can be fully stateless (no PHP session) in multi-tenant setups.

-- We use a procedure to safely add the column only if it doesn't exist yet.
DROP PROCEDURE IF EXISTS _add_tenant_prefix_col;

DELIMITER ;;
CREATE PROCEDURE _add_tenant_prefix_col()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'mobile_api_tokens'
          AND COLUMN_NAME  = 'tenant_prefix'
    ) THEN
        ALTER TABLE mobile_api_tokens
            ADD COLUMN tenant_prefix VARCHAR(64) NOT NULL DEFAULT ''
                COMMENT 'Tenant table prefix resolved at login (e.g. t_abc123_)'
                AFTER device_name;
    END IF;
END;;
DELIMITER ;

CALL _add_tenant_prefix_col();
DROP PROCEDURE IF EXISTS _add_tenant_prefix_col;
