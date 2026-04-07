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

        return $this->db->transaction(function (Database $db) use ($data, $plan): array {

            // 1. Create tenant record
            $uuid         = Uuid::uuid4()->toString();
            $tid          = $this->generateTid($data['practice_name']);
            // db_name now stores the TABLE PREFIX used inside the shared DB
            $tablePrefix  = 't_' . preg_replace('/[^a-z0-9]/', '_', $tid) . '_';
            $tablePrefix  = substr($tablePrefix, 0, 48);
            $passwordHash = password_hash($data['admin_password'] ?? bin2hex(random_bytes(8)), PASSWORD_BCRYPT, ['cost' => 12]);
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

            // 2. Create prefixed tables in the shared SaaS database
            $this->createTenantTables($tablePrefix, $uuid);
            $this->tenantRepo->setDbCreated($tenantId, $tablePrefix);

            // 3. Create admin user in the prefixed tables
            $adminPassword = $data['admin_password'] ?? bin2hex(random_bytes(8));
            $this->createTenantAdmin(
                $tablePrefix,
                $data['practice_name'],
                $data['owner_name'],
                $data['email'],
                $adminPassword
            );
            $this->tenantRepo->setAdminCreated($tenantId);

            // 4. Activate tenant (stays in trial until payment confirmed)
            $this->tenantRepo->setStatus($tenantId, 'trial');

            // 5. Create subscription
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

            // 6. Issue license token
            $licenseToken = $this->licenseService->issueToken($tenantId);

            // 7. Send welcome email
            try {
                $this->mailService->sendWelcome(
                    $data['email'],
                    $data['owner_name'],
                    $data['practice_name'],
                    $data['email'],
                    $adminPassword,
                    $licenseToken
                );
            } catch (\Throwable) {
                // Mail failure should not block provisioning
            }

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
        $sql = file_exists($schemaPath) ? (string)file_get_contents($schemaPath) : '';

        // Replace every bare table name with the prefixed version.
        // Matches: CREATE TABLE [IF NOT EXISTS] `name`  or  INSERT INTO `name`
        // Also handles FK references like REFERENCES `name`
        $sql = $this->applyPrefixToSchema($sql, $prefix);

        $pdo = $this->db->getPdo();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '' && !preg_match('/^\s*(--.*)$/m', $stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (\PDOException $e) {
                    // Skip duplicate-table errors (idempotent re-provisioning)
                    if ($e->getCode() !== '42S01') {
                        throw $e;
                    }
                }
            }
        }

        // Write tenant identity into prefixed settings table
        $st = $pdo->prepare("INSERT IGNORE INTO `{$prefix}settings` (`key`, `value`) VALUES ('tenant_uuid', ?)");
        $st->execute([$tenantUuid]);
    }

    /**
     * Rewrite a schema SQL string to use the given table prefix.
     */
    private function applyPrefixToSchema(string $sql, string $prefix): string
    {
        // Known tenant table names from tenant_schema.sql
        $tables = ['users','settings','owners','patients','appointments','invoices','invoice_items','waitlist','user_preferences','migrations'];

        foreach ($tables as $table) {
            // Table definitions and references with backticks
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
        $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $parts = explode(' ', trim($ownerName), 2);
        $first = $parts[0];
        $last  = $parts[1] ?? '';

        $pdo = $this->db->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO `{$prefix}users` (first_name, last_name, email, password, role, is_active, created_at)
             VALUES (?, ?, ?, ?, 'admin', 1, NOW())"
        );
        $stmt->execute([$first, $last, $email, $hash]);

        $pdo->prepare("INSERT INTO `{$prefix}settings` (`key`, `value`) VALUES ('company_name', ?) ON DUPLICATE KEY UPDATE `value` = ?")
            ->execute([$practiceName, $practiceName]);
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
