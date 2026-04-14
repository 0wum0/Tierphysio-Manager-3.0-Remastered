<?php

/**
 * TheraPano SaaS Cron Runner
 * ============================
 * Crontab (jede Stunde):
 *   0 * * * * php /path/to/saas-platform/cron/cron_runner.php >> /var/log/therapano_cron.log 2>&1
 *
 * Oder täglich um 08:00 Uhr:
 *   0 8 * * * php /path/to/saas-platform/cron/cron_runner.php daily >> /var/log/therapano_cron.log 2>&1
 */

declare(strict_types=1);

define('SAAS_CRON', true);
define('ROOT', dirname(__DIR__));

require_once ROOT . '/vendor/autoload.php';

// Load .env
if (file_exists(ROOT . '/.env')) {
    $lines = file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $val = trim($val, '"\'');
        $_ENV[trim($key)] = $val;
        putenv(trim($key) . '=' . $val);
    }
}

// Bootstrap DB
$host = $_ENV['DB_HOST']     ?? 'localhost';
$port = $_ENV['DB_PORT']     ?? '3306';
$name = $_ENV['DB_DATABASE'] ?? '';
$user = $_ENV['DB_USERNAME'] ?? '';
$pass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (\Throwable $e) {
    echo "[ERROR] DB connect failed: " . $e->getMessage() . "\n";
    exit(1);
}

$mode = $argv[1] ?? 'hourly';
echo "[" . date('Y-m-d H:i:s') . "] TheraPano Cron ({$mode}) gestartet\n";

// ── 1. Trial-Ablauf prüfen ────────────────────────────────────────────────
function checkTrialExpiry(PDO $pdo): void
{
    // Trials die gestern abgelaufen sind → suspend
    $expired = $pdo->query(
        "SELECT id, practice_name, email FROM tenants
         WHERE status = 'trial'
           AND trial_ends_at IS NOT NULL
           AND trial_ends_at < NOW()"
    )->fetchAll();

    foreach ($expired as $t) {
        $pdo->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ?")->execute([$t['id']]);
        notify($pdo, 'trial_expiry', 'Trial abgelaufen', "Praxis \"{$t['practice_name']}\" (#{$t['id']}) wurde gesperrt.");
        echo "  [TRIAL] Tenant #{$t['id']} '{$t['practice_name']}' suspended (trial ended)\n";
    }

    // Trials die in 3 Tagen ablaufen → Erinnerungsbenachrichtigung
    $soonExpiring = $pdo->query(
        "SELECT id, practice_name FROM tenants
         WHERE status = 'trial'
           AND trial_ends_at IS NOT NULL
           AND trial_ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)"
    )->fetchAll();

    foreach ($soonExpiring as $t) {
        // Nur einmal pro Tenant benachrichtigen (prüfen ob bereits gesendet)
        $exists = $pdo->prepare(
            "SELECT id FROM saas_notifications WHERE type = 'trial_expiry' AND message LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $exists->execute(["%#{$t['id']}%"]);
        if ($exists->fetch()) continue;

        notify($pdo, 'trial_expiry', 'Trial läuft bald ab', "Praxis \"{$t['practice_name']}\" (#{$t['id']}) Trial endet in < 3 Tagen.");
        echo "  [TRIAL] Tenant #{$t['id']} '{$t['practice_name']}' - Erinnerung (3 Tage)\n";
    }
}

// ── 2. Zahlungen prüfen (überfällige Abos) ──────────────────────────────
function checkOverduePayments(PDO $pdo): void
{
    // Abos die überfällig sind (next_billing überschritten, noch aktiv)
    $overdue = $pdo->query(
        "SELECT s.id, s.tenant_id, s.amount, s.billing_cycle, s.next_billing,
                t.practice_name, t.payment_provider, t.stripe_customer_id
         FROM subscriptions s
         JOIN tenants t ON t.id = s.tenant_id
         WHERE s.status = 'active'
           AND s.next_billing IS NOT NULL
           AND s.next_billing < DATE_SUB(NOW(), INTERVAL 3 DAY)"
    )->fetchAll();

    foreach ($overdue as $sub) {
        $pdo->prepare(
            "UPDATE subscriptions SET status = 'past_due', last_payment_status = 'overdue' WHERE id = ?"
        )->execute([$sub['id']]);

        $pdo->prepare(
            "UPDATE tenants SET status = 'paused' WHERE id = ?"
        )->execute([$sub['tenant_id']]);

        notify($pdo, 'overdue', 'Zahlung überfällig',
            "Praxis \"{$sub['practice_name']}\" (#{$sub['tenant_id']}): {$sub['amount']} € überfällig seit {$sub['next_billing']}");
        echo "  [OVERDUE] Tenant #{$sub['tenant_id']} '{$sub['practice_name']}' - past_due\n";
    }
}

// ── 3. Revenue Snapshot erstellen (täglich) ───────────────────────────────
function createRevenueSnapshot(PDO $pdo): void
{
    $year  = (int)date('Y');
    $month = (int)date('n');

    $monthly = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM subscriptions WHERE status = 'active' AND billing_cycle = 'monthly'"
    )->fetchColumn();
    $yearly = (float)$pdo->query(
        "SELECT COALESCE(SUM(amount/12),0) FROM subscriptions WHERE status = 'active' AND billing_cycle = 'yearly'"
    )->fetchColumn();
    $total = $monthly + $yearly;

    $count = (int)$pdo->query(
        "SELECT COUNT(*) FROM subscriptions WHERE status = 'active'"
    )->fetchColumn();

    $newTenants = (int)$pdo->query(
        "SELECT COUNT(*) FROM tenants WHERE YEAR(created_at) = {$year} AND MONTH(created_at) = {$month}"
    )->fetchColumn();

    $churn = (int)$pdo->query(
        "SELECT COUNT(*) FROM tenants WHERE status = 'cancelled' AND YEAR(updated_at) = {$year} AND MONTH(updated_at) = {$month}"
    )->fetchColumn();

    $stmt = $pdo->prepare(
        "INSERT INTO revenue_snapshots (year, month, amount, count, new_tenants, churn)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE amount = ?, count = ?, new_tenants = ?, churn = ?"
    );
    $stmt->execute([$year, $month, $total, $count, $newTenants, $churn,
                    $total, $count, $newTenants, $churn]);

    echo "  [REVENUE] Snapshot {$year}-{$month}: {$total} € ({$count} aktive Abos, {$newTenants} neu, {$churn} churn)\n";
}

// ── 4. Alte Benachrichtigungen aufräumen ─────────────────────────────────
function cleanOldNotifications(PDO $pdo): void
{
    $deleted = $pdo->exec(
        "DELETE FROM saas_notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    echo "  [CLEAN] {$deleted} alte Benachrichtigungen gelöscht\n";
}

// ── 5. Feedback-Benachrichtigung zusammenfassen ────────────────────────────
function summarizeFeedback(PDO $pdo): void
{
    $count = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE is_read = 0")->fetchColumn();
    if ($count === 0) return;

    // Nur wenn noch keine heutige Zusammenfassung
    $exists = $pdo->query(
        "SELECT id FROM saas_notifications WHERE type = 'feedback' AND title LIKE '%Zusammenfassung%'
         AND DATE(created_at) = CURDATE() LIMIT 1"
    )->fetch();
    if ($exists) return;

    notify($pdo, 'feedback', 'Feedback Tages-Zusammenfassung', "{$count} ungelesene Feedback-Einträge warten auf deine Antwort.");
    echo "  [FEEDBACK] Tages-Zusammenfassung: {$count} ungelesen\n";
}

// ── 6. Stripe Subscription Sync ──────────────────────────────────────────
function syncStripeSubscriptions(PDO $pdo): void
{
    $key = '';
    $row = $pdo->query("SELECT value FROM saas_settings WHERE `key` = 'stripe_secret_key'")->fetch();
    if ($row) $key = $row['value'];
    if (!$key) return;

    $enabled = $pdo->query("SELECT value FROM saas_settings WHERE `key` = 'stripe_enabled'")->fetchColumn();
    if ($enabled !== '1') return;

    // Get all active Stripe subscriptions
    $tenants = $pdo->query(
        "SELECT t.id, t.stripe_customer_id, s.stripe_sub_id, s.id AS sub_id
         FROM tenants t
         JOIN subscriptions s ON s.tenant_id = t.id
         WHERE t.stripe_customer_id IS NOT NULL
           AND s.stripe_sub_id IS NOT NULL
           AND s.status = 'active'
         LIMIT 50"
    )->fetchAll();

    foreach ($tenants as $t) {
        $ch = curl_init("https://api.stripe.com/v1/subscriptions/{$t['stripe_sub_id']}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$key}"],
        ]);
        $res    = json_decode(curl_exec($ch) ?: '', true) ?? [];
        curl_close($ch);

        $status = $res['status'] ?? null;
        if (!$status) continue;

        $nextBilling = isset($res['current_period_end'])
            ? date('Y-m-d H:i:s', $res['current_period_end']) : null;

        $pdo->prepare(
            "UPDATE subscriptions SET last_payment_status = ?, next_billing = ? WHERE id = ?"
        )->execute([$status, $nextBilling, $t['sub_id']]);

        if ($status === 'canceled') {
            $pdo->prepare("UPDATE tenants SET status = 'cancelled' WHERE id = ?")->execute([$t['id']]);
            echo "  [STRIPE] Tenant #{$t['id']} - subscription canceled\n";
        } elseif ($status === 'past_due') {
            $pdo->prepare("UPDATE subscriptions SET status = 'past_due' WHERE id = ?")->execute([$t['sub_id']]);
            notify($pdo, 'overdue', 'Stripe: Zahlung überfällig', "Tenant #{$t['id']}: Stripe subscription past_due");
        }
    }
    echo "  [STRIPE] " . count($tenants) . " Subscriptions synchronisiert\n";
}

// ── Helper: Notification erstellen ────────────────────────────────────────
// ── Helper: Notification erstellen ────────────────────────────────────────
function notify(PDO $pdo, string $type, string $title, string $message): void
{
    try {
        $pdo->prepare(
            "INSERT INTO saas_notifications (type, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())"
        )->execute([$type, $title, $message]);
    } catch (\Throwable) {}
}

/**
 * ── 7. Tenant Dispatcher & Self-Healing ──────────────────────────────────
 * Triggers the local /cron/dispatcher for all active tenants.
 * Also performs "Self-healing" for known missing columns or tables.
 */
function dispatchTenants(PDO $pdo): void
{
    echo "  [TENANTS] Suche aktive Tenants...\n";
    $tenants = $pdo->query("SELECT id, tid, db_name, practice_name FROM tenants WHERE status = 'active'")->fetchAll();

    foreach ($tenants as $t) {
        $tid   = $t['tid'];
        $prefix = $t['db_name'];
        echo "  [TENANT] Verarbeite \"{$t['practice_name']}\" ({$tid})...\n";

        // ── Self Healing ──
        try {
            // 1. Check for missing columns in appointments
            $pdo->exec("ALTER TABLE `{$prefix}appointments` ADD COLUMN IF NOT EXISTS `reminder_minutes` SMALLINT UNSIGNED NULL DEFAULT 60 AFTER `notes`") ;
            $pdo->exec("ALTER TABLE `{$prefix}appointments` ADD COLUMN IF NOT EXISTS `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_minutes`") ;
            $pdo->exec("ALTER TABLE `{$prefix}appointments` ADD COLUMN IF NOT EXISTS `patient_reminder_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reminder_sent`") ;

            // 2. Ensure TherapyCare Pro tables exist (Basic set)
            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}tcp_reminder_queue` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `template_id` INT UNSIGNED NULL,
                `type` ENUM('appointment','homework','followup','custom') NOT NULL,
                `patient_id` INT UNSIGNED NULL,
                `owner_id` INT UNSIGNED NOT NULL,
                `appointment_id` INT UNSIGNED NULL,
                `subject` VARCHAR(255) NOT NULL,
                `body` TEXT NOT NULL,
                `send_at` DATETIME NOT NULL,
                `sent_at` DATETIME NULL,
                `status` ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
                `error_message` TEXT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Add other critical TCP tables if missing
            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}tcp_reminder_logs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `queue_id` INT UNSIGNED NULL,
                `type` VARCHAR(50) NOT NULL,
                `recipient` VARCHAR(255) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `status` ENUM('sent','failed') NOT NULL,
                `error` TEXT NULL,
                `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        } catch (\Throwable $e) {
            echo "    [WARN] Self-healing failed for {$tid}: " . $e->getMessage() . "\n";
        }

        // ── Dispatch via HTTP ──
        // Since all tenants are hosted on app.therapano.de (or similar)
        $baseUrl = getenv('APP_URL') ?: 'https://app.therapano.de';
        $url     = $baseUrl . "/cron/dispatcher?tid=" . urlencode($tid);

        // Optional: Token aus Settings holen falls erforderlich (obwohl wir den Header nutzen)
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'X-Internal-Cron: true',
                'User-Agent: TheraPano-SaaS-Dispatcher/1.0'
            ],
        ]);

        $res      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            echo "    [OK] Dispatcher erfolgreich (HTTP {$httpCode})\n";
        } else {
            echo "    [FAIL] Dispatcher Fehler (HTTP {$httpCode})\n";
            echo "    Response: " . substr((string)$res, 0, 200) . "...\n";
        }
    }
}

// ── Ausführen ─────────────────────────────────────────────────────────────
try {
    checkTrialExpiry($pdo);
    checkOverduePayments($pdo);
    createRevenueSnapshot($pdo);
    dispatchTenants($pdo);

    if ($mode === 'daily') {
        cleanOldNotifications($pdo);
        summarizeFeedback($pdo);
        syncStripeSubscriptions($pdo);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Cron abgeschlossen ✓\n";
} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
