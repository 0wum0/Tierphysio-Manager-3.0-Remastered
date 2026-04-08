<?php

declare(strict_types=1);

/**
 * Returns the tenant-isolated storage path.
 *
 * When a tenant table-prefix is set the path is:
 *   STORAGE_PATH/tenants/{slug}/{subPath}
 * For single-tenant / dev (no prefix) it falls back to:
 *   STORAGE_PATH/{subPath}
 *
 * Usage:
 *   tenant_storage_path()                    → base storage dir for this tenant
 *   tenant_storage_path('patients/42')       → patient dir
 *   tenant_storage_path('patients/42/photo') → photo sub-dir
 */
function tenant_storage_path(string $subPath = ''): string
{
    try {
        $db = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
        return $db->storagePath($subPath);
    } catch (\Throwable) {
        $base = defined('STORAGE_PATH') ? STORAGE_PATH : (dirname(__DIR__) . '/storage');
        return $subPath !== '' ? $base . '/' . ltrim($subPath, '/') : $base;
    }
}
