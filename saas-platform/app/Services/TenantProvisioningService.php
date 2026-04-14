<?php

declare(strict_types=1);

namespace Saas\Services;

use PDO;
use Saas\Core\Config;
use Saas\Core\Database;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\SubscriptionRepository;
use Saas\Repositories\PlanRepository;
use Ramsey\Uuid\Uuid;

class TenantProvisioningService
{
    public function __construct(
        private Config                 $config,
        private Database               $db,
        private TenantRepository       $tenantRepo,
        private SubscriptionRepository $subRepo,
        private PlanRepository         $planRepo,
        private LicenseService         $licenseService,
        private MailService            $mailService
    ) {}

    /**
     * Full provisioning: create tenant record → prefixed tables → admin user → subscription → license token → welcome mail
     *
     * On shared hosting (Hostinger etc.) CREATE DATABASE is not permitted.
     * Instead we create all tenant tables in the *same* SaaS database using
     * a per-tenant table prefix:  t_<tid>_users,  t_<tid>_patients, …
     * The tenants.db_name column stores that prefix (not a database name).
     */
    public function provision(array $data): array
    {
        $plan = $this->planRepo->findBySlug($data['plan_slug'] ?? 'basic');
        if (!$plan) {
            throw new \RuntimeException('Ungültiger Abo-Plan');
        }

        // Pre-compute identifiers
        $uuid        = Uuid::uuid4()->toString();
        $tid         = $this->generateTid($data['practice_name']);
        $tablePrefix = substr('t_' . preg_replace('/[^a-z0-9]/', '_', $tid) . '_', 0, 48);
        $adminPassword = $data['admin_password'] ?? bin2hex(random_bytes(8));

        // Step 1: DDL — create prefixed tables BEFORE opening a transaction.
        // MySQL DDL causes an implicit commit which would break an open transaction.
        $this->createTenantTables($tablePrefix, $uuid);

        // Step 2: All DML inside a transaction (tenant record, admin user, subscription, license).
        try {
            return $this->db->transaction(function (Database $db) use ($data, $plan, $uuid, $tid, $tablePrefix, $adminPassword): array {

                $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $trialEndsAt  = date('Y-m-d H:i:s', strtotime('+14 days'));

                $tenantId = $this->tenantRepo->createWithAuth([
                    'uuid'          => $uuid,
                    'tid'           => $tid,
                    'practice_name' => $data['practice_name'],
                    'owner_name'    => $data['owner_name'],
                    'email'         => $data['email'],
                    'phone'         => $data['phone'] ?? null,
                    'address'       => $data['address'] ?? null,
                    'city'          => $data['city'] ?? null,
                    'zip'           => $data['zip'] ?? null,
                    'country'       => $data['country'] ?? 'DE',
                    'plan_id'       => (int)$plan['id'],
                    'status'        => 'trial',
                    'password_hash' => $passwordHash,
                    'trial_ends_at' => $trialEndsAt,
                ]);

                $this->tenantRepo->setDbCreated($tenantId, $tablePrefix);

                // Create admin user in the prefixed tables
                $this->createTenantAdmin(
                    $tablePrefix,
                    $data['practice_name'],
                    $data['owner_name'],
                    $data['email'],
                    $adminPassword
                );
                // Save practice_type setting for this tenant
                $practiceType = in_array($data['practice_type'] ?? '', ['therapeut', 'trainer'], true)
                    ? $data['practice_type'] : 'therapeut';
                $this->saveTenantSetting($tablePrefix, 'practice_type', $practiceType);
                $this->tenantRepo->setAdminCreated($tenantId);
                $this->tenantRepo->setStatus($tenantId, 'trial');

                // ── Storage-Ordner anlegen ─────────────────────────────────────────
                $this->createTenantStorageDir($tablePrefix);

                // Create subscription
                $billingCycle = $data['billing_cycle'] ?? 'monthly';
                $amount       = $billingCycle === 'yearly' ? $plan['price_year'] : $plan['price_month'];
                $startedAt    = date('Y-m-d H:i:s');
                $endsAt       = $billingCycle === 'yearly'
                                ? date('Y-m-d H:i:s', strtotime('+1 year'))
                                : date('Y-m-d H:i:s', strtotime('+1 month'));

                $this->subRepo->create([
                    'tenant_id'      => $tenantId,
                    'plan_id'        => (int)$plan['id'],
                    'billing_cycle'  => $billingCycle,
                    'status'         => 'active',
                    'started_at'     => $startedAt,
                    'ends_at'        => $endsAt,
                    'next_billing'   => $endsAt,
                    'amount'         => $amount,
                    'currency'       => 'EUR',
                    'payment_method' => $data['payment_method'] ?? null,
                    'external_id'    => $data['payment_external_id'] ?? null,
                ]);

                // Issue license token
                $licenseToken = $this->licenseService->issueToken($tenantId);

                // Send welcome email (non-blocking)
                try {
                    $this->mailService->sendWelcome(
                        $data['email'],
                        $data['owner_name'],
                        $data['practice_name'],
                        $data['email'],
                        $adminPassword,
                        $licenseToken
                    );
                } catch (\Throwable) {}

                return [
                    'tenant_id'      => $tenantId,
                    'tenant_uuid'    => $uuid,
                    'tenant_tid'     => $tid,
                    'db_name'        => $tablePrefix,
                    'admin_email'    => $data['email'],
                    'admin_password' => $adminPassword,
                    'license_token'  => $licenseToken,
                    'plan'           => $plan['slug'],
                    'trial_ends_at'  => $trialEndsAt,
                ];
            });
        } catch (\Throwable $e) {
            // If the DML transaction fails, clean up the already-created DDL tables
            $this->dropTenantTables($tablePrefix);
            throw $e;
        }
    }

    /**
     * Provision only the SaaS meta-data (tenant row, subscription, license).
     * Does NOT call createTenantTables() — use this when the schema will be
     * provided by an external SQL import (DataMigrationController).
     */
    public function provisionTenantOnly(array $data): array
    {
        $plan = null;
        if (!empty($data['plan_slug'])) {
            $plan = $this->planRepo->findBySlug($data['plan_slug']);
        }
        if (!$plan) {
            $plans = $this->planRepo->allActive();
            $plan  = $plans[0] ?? null;
        }
        if (!$plan) {
            throw new \RuntimeException('Kein aktiver Abo-Plan gefunden.');
        }

        $uuid          = Uuid::uuid4()->toString();
        $tid           = $this->generateTid($data['practice_name']);
        $tablePrefix   = substr('t_' . preg_replace('/[^a-z0-9]/', '_', $tid) . '_', 0, 48);
        $adminPassword = $data['admin_password'] ?? bin2hex(random_bytes(8));
        $passwordHash  = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $trialEndsAt   = date('Y-m-d H:i:s', strtotime('+14 days'));

        return $this->db->transaction(function (Database $db) use (
            $data, $plan, $uuid, $tid, $tablePrefix, $passwordHash, $adminPassword, $trialEndsAt
        ): array {
            $tenantId = $this->tenantRepo->createWithAuth([
                'uuid'          => $uuid,
                'tid'           => $tid,
                'practice_name' => $data['practice_name'],
                'owner_name'    => $data['owner_name'] ?? $data['practice_name'],
                'email'         => $data['email'],
                'phone'         => $data['phone'] ?? null,
                'address'       => $data['address'] ?? null,
                'city'          => $data['city'] ?? null,
                'zip'           => $data['zip'] ?? null,
                'country'       => $data['country'] ?? 'DE',
                'plan_id'       => (int)$plan['id'],
                'status'        => 'trial',
                'password_hash' => $passwordHash,
                'trial_ends_at' => $trialEndsAt,
            ]);

            // Prefix in tenants.db_name speichern (db_created=0 — Tabellen noch nicht da)
            $this->tenantRepo->setDbCreated($tenantId, $tablePrefix);

            // Subscription anlegen
            $billingCycle = $data['billing_cycle'] ?? 'monthly';
            $amount       = $billingCycle === 'yearly' ? $plan['price_year'] : $plan['price_month'];
            $startedAt    = date('Y-m-d H:i:s');
            $endsAt       = $billingCycle === 'yearly'
                            ? date('Y-m-d H:i:s', strtotime('+1 year'))
                            : date('Y-m-d H:i:s', strtotime('+1 month'));

            $this->subRepo->create([
                'tenant_id'      => $tenantId,
                'plan_id'        => (int)$plan['id'],
                'billing_cycle'  => $billingCycle,
                'status'         => 'active',
                'started_at'     => $startedAt,
                'ends_at'        => $endsAt,
                'next_billing'   => $endsAt,
                'amount'         => $amount,
                'currency'       => 'EUR',
                'payment_method' => $data['payment_method'] ?? null,
                'external_id'    => null,
            ]);

            $licenseToken = $this->licenseService->issueToken($tenantId);
            $this->tenantRepo->setStatus($tenantId, 'trial');

            return [
                'tenant_id'      => $tenantId,
                'tenant_uuid'    => $uuid,
                'tenant_tid'     => $tid,
                'db_name'        => $tablePrefix,
                'admin_email'    => $data['email'],
                'admin_password' => $adminPassword,
                'license_token'  => $licenseToken,
                'plan'           => $plan['slug'],
                'trial_ends_at'  => $trialEndsAt,
            ];
        });
    }

    /**
     * Generate a short, URL-safe tenant identifier from the practice name.
     */
    private function generateTid(string $practiceName): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $practiceName));
        $base = trim(substr($base, 0, 20), '-');
        $suffix = substr(bin2hex(random_bytes(3)), 0, 6);
        return $base . '-' . $suffix;
    }

    /**
     * Create prefixed tenant tables inside the shared SaaS database.
     * No CREATE DATABASE required — works on Hostinger shared hosting.
     */
    private function createTenantTables(string $prefix, string $tenantUuid): void
    {
        $schemaPath = $this->config->getRootPath() . '/provisioning/tenant_schema.sql';
        if (!file_exists($schemaPath)) {
            throw new \RuntimeException('tenant_schema.sql not found at: ' . $schemaPath);
        }
        $sql = (string)file_get_contents($schemaPath);

        $sql = $this->applyPrefixToSchema($sql, $prefix);

        $pdo = $this->db->getPdo();

        // Split on semicolons that are NOT inside quoted strings or comments.
        // Simple but effective: strip full-line comments first, then split.
        $lines   = explode("\n", $sql);
        $cleaned = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '--')) {
                continue;
            }
            $cleaned[] = $line;
        }
        $cleanSql = implode("\n", $cleaned);

        $statements = array_filter(
            array_map('trim', explode(';', $cleanSql)),
            fn($s) => $s !== ''
        );

        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (\PDOException $e) {
                // 42S01 = table already exists — safe to skip for idempotent re-runs
                if ($e->getCode() !== '42S01') {
                    throw new \RuntimeException(
                        'Schema error on statement [' . substr($stmt, 0, 120) . ']: ' . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }
        }

        // Write tenant identity into prefixed settings table
        $pdo->prepare("INSERT IGNORE INTO `{$prefix}settings` (`key`, `value`) VALUES ('tenant_uuid', ?)")
            ->execute([$tenantUuid]);

        // ── Plugin-Migrations ausführen ────────────────────────────────────
        // Führe alle Plugin-Migrations aus damit neue Tenants sofort
        // vollständig sind ohne dass sie die App aufrufen müssen.
        $this->runPluginMigrations($prefix);
    }

    /**
     * Run all plugin migration SQL files for a newly provisioned tenant.
     * This ensures plugin tables exist immediately after provisioning
     * without requiring the tenant to visit the app first.
     */
    private function runPluginMigrations(string $prefix): void
    {
        $pluginsDir = $this->config->getRootPath() . '/../app/plugins';
        // Fallback: check relative to the saas-platform root
        if (!is_dir($pluginsDir)) {
            $pluginsDir = dirname($this->config->getRootPath()) . '/plugins';
        }
        if (!is_dir($pluginsDir)) {
            // Plugins directory not found — skip silently
            return;
        }

        $pdo = $this->db->getPdo();

        // Find all plugin migration directories
        $pluginDirs = glob($pluginsDir . '/*/migrations', GLOB_ONLYDIR) ?: [];

        foreach ($pluginDirs as $migrationDir) {
            $files = glob($migrationDir . '/*.sql') ?: [];
            sort($files);

            foreach ($files as $file) {
                $sql = (string)file_get_contents($file);

                // Apply prefix to ALL table names (not just Google)
                // Use the same logic as applyPrefixToSchema
                $tables = [
                    'users','settings','owners','patients','appointments','appointment_waitlist',
                    'invoices','invoice_items','invoice_positions','invoice_reminders','invoice_dunnings',
                    'waitlist','user_preferences','migrations',
                    'patient_timeline','treatment_types',
                    'mobile_api_tokens','cron_job_log',
                    'befundboegen','befundbogen_felder',
                    'google_calendar_connections',
                    'google_calendar_sync_map',
                    'google_calendar_sync_log',
                    'google_calendar_imported_events',
                    // TherapyCare Pro tables
                    'tcp_progress_categories', 'tcp_progress_entries', 'tcp_exercise_feedback',
                    'tcp_reminder_templates', 'tcp_reminder_queue', 'tcp_reminder_logs',
                    'tcp_therapy_reports', 'tcp_exercise_library',
                    'tcp_natural_therapy_types', 'tcp_natural_therapy_entries',
                    'tcp_timeline_meta', 'tcp_portal_visibility',
                ];

                foreach ($tables as $table) {
                    $sql = preg_replace('/`' . preg_quote($table, '/') . '`/', '`' . $prefix . $table . '`', $sql);
                }

                // Also apply constraint prefixing
                $sql = preg_replace_callback(
                    '/\bCONSTRAINT\s+`([^`]+)`/i',
                    fn($m) => 'CONSTRAINT `' . $prefix . $m[1] . '`',
                    $sql
                );

                // Split and execute statements
                $lines   = explode("\n", $sql);
                $cleaned = [];
                foreach ($lines as $line) {
                    if (!str_starts_with(ltrim($line), '--')) {
                        $cleaned[] = $line;
                    }
                }
                $statements = array_filter(
                    array_map('trim', explode(';', implode("\n", $cleaned))),
                    fn($s) => $s !== ''
                );

                foreach ($statements as $stmt) {
                    try {
                        $pdo->exec($stmt);
                    } catch (\PDOException $e) {
                        // Skip: table exists (42S01), duplicate column (1060),
                        // duplicate key (1061), duplicate entry (1062)
                        $code = (int)($e->errorInfo[1] ?? 0);
                        if (!in_array($code, [1050, 1060, 1061, 1062], true)
                            && $e->getCode() !== '42S01') {
                            // Non-fatal: log but don't crash provisioning
                            // Plugin migrations should never prevent tenant creation
                        }
                    }
                }
            }
        }
    }

    /**
     * Create the tenant's storage directory structure under the main app's storage path.
     * Called during provisioning so uploads work immediately after account creation.
     */
    private function createTenantStorageDir(string $tablePrefix): void
    {
        $slug = rtrim($tablePrefix, '_');

        $practicePath = rtrim($this->config->get('practice.path', ''), '/');
        $storageRoot  = $practicePath !== ''
            ? $practicePath . '/storage/tenants'
            : dirname($this->config->getRootPath()) . '/storage/tenants';

        $tenantDir = $storageRoot . '/' . $slug;

        foreach ([$storageRoot, $tenantDir] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }

        foreach (['patients', 'uploads', 'vet-reports', 'intake'] as $sub) {
            $subDir = $tenantDir . '/' . $sub;
            if (!is_dir($subDir)) {
                @mkdir($subDir, 0755, true);
            }
        }
    }

    /**
     * Rewrite a schema SQL string to use the given table prefix.
     * Prefixes both table names AND constraint names to avoid errno 121
     * (duplicate foreign key name) when multiple tenants share one database.
     */
    private function applyPrefixToSchema(string $sql, string $prefix): string
    {
        // 1. Constraint-Namen prefixen: CONSTRAINT `fk_xyz` → CONSTRAINT `{prefix}fk_xyz`
        $sql = preg_replace_callback(
            '/\bCONSTRAINT\s+`([^`]+)`/i',
            fn($m) => 'CONSTRAINT `' . $prefix . $m[1] . '`',
            $sql
        );

        // 2. Tabellennamen prefixen (alle Tenant-Tabellen)
        $tables = [
            'users','settings','owners','patients','appointments','appointment_waitlist',
            'invoices','invoice_items','invoice_positions','invoice_reminders','invoice_dunnings',
            'waitlist','user_preferences','migrations',
            'patient_timeline','treatment_types',
            'mobile_api_tokens','cron_job_log',
            'befundboegen','befundbogen_felder',
            'google_calendar_connections',
            'google_calendar_sync_map',
            'google_calendar_sync_log',
            'google_calendar_imported_events',
            // TherapyCare Pro tables
            'tcp_progress_categories', 'tcp_progress_entries', 'tcp_exercise_feedback',
            'tcp_reminder_templates', 'tcp_reminder_queue', 'tcp_reminder_logs',
            'tcp_therapy_reports', 'tcp_exercise_library',
            'tcp_natural_therapy_types', 'tcp_natural_therapy_entries',
            'tcp_timeline_meta', 'tcp_portal_visibility',
        ];

        foreach ($tables as $table) {
            $sql = preg_replace('/`' . preg_quote($table, '/') . '`/', '`' . $prefix . $table . '`', $sql);
        }

        return $sql;
    }

    /**
     * Create the initial admin user in the tenant's prefixed tables.
     */
    private function createTenantAdmin(
        string $prefix,
        string $practiceName,
        string $ownerName,
        string $email,
        string $password
    ): void {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo  = $this->db->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO `{$prefix}users` (name, email, password, role, active, created_at)
             VALUES (?, ?, ?, 'admin', 1, NOW())"
        );
        $stmt->execute([$ownerName, $email, $hash]);

        $pdo->prepare("INSERT INTO `{$prefix}settings` (`key`, `value`) VALUES ('company_name', ?) ON DUPLICATE KEY UPDATE `value` = ?")
            ->execute([$practiceName, $practiceName]);
    }

    private function saveTenantSetting(string $prefix, string $key, string $value): void
    {
        $this->db->getPdo()
            ->prepare("INSERT INTO `{$prefix}settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
            ->execute([$key, $value]);
    }

    /**
     * Drop all prefixed tenant tables (cleanup on failed provisioning).
     */
    private function dropTenantTables(string $prefix): void
    {
        $tables = [
            'google_calendar_imported_events',
            'google_calendar_sync_log',
            'google_calendar_sync_map',
            'google_calendar_connections',
            'befundbogen_felder','befundboegen',
            'invoice_reminders','invoice_dunnings','invoice_positions','invoice_items','invoices',
            'mobile_api_tokens','cron_job_log',
            'appointment_waitlist','appointments',
            'patient_timeline','treatment_types',
            'patients','owners',
            'waitlist','user_preferences','users','migrations','settings',
        ];
        $pdo = $this->db->getPdo();
        foreach ($tables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `{$prefix}{$table}`");
            } catch (\Throwable) {}
        }
    }

    /**
     * Deprovision: revoke license, optionally drop DB.
     */
    public function suspend(int $tenantId): void
    {
        $this->tenantRepo->setStatus($tenantId, 'suspended');
        $this->licenseService->revokeAllTokens($tenantId);
    }

    public function reactivate(int $tenantId): void
    {
        $this->tenantRepo->setStatus($tenantId, 'active');
        $this->licenseService->issueToken($tenantId);
    }

    public function cancel(int $tenantId): void
    {
        $this->tenantRepo->setStatus($tenantId, 'cancelled');
        $this->licenseService->revokeAllTokens($tenantId);
    }
}
