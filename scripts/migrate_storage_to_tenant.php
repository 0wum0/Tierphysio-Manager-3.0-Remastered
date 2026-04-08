<?php
/**
 * Storage Migration: Move existing files into tenant-isolated subfolder.
 *
 * Run ONCE on the server after deploying the tenant_storage_path() changes:
 *   php scripts/migrate_storage_to_tenant.php
 *
 * What it does:
 *   1. Detects the tenant prefix from INFORMATION_SCHEMA (same logic as Application.php)
 *   2. Creates storage/tenants/{slug}/ and mirrors the folder structure
 *   3. Moves: patients/, intake/, uploads/, befunde/, vet-reports/
 *   4. Themes stay in storage/themes/ (they are global, not tenant-specific)
 *
 * Safe to re-run — already-moved files are skipped.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('STORAGE_PATH', ROOT_PATH . '/storage');

require_once ROOT_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env
if (file_exists(ROOT_PATH . '/.env')) {
    Dotenv::createImmutable(ROOT_PATH)->load();
}

// ── Connect to DB ────────────────────────────────────────────────────────────
$host   = $_ENV['DB_HOST']     ?? 'localhost';
$port   = $_ENV['DB_PORT']     ?? '3306';
$dbname = $_ENV['DB_DATABASE'] ?? '';
$user   = $_ENV['DB_USERNAME'] ?? '';
$pass   = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\Throwable $e) {
    die("DB connection failed: " . $e->getMessage() . "\n");
}

// ── Detect tenant prefix ─────────────────────────────────────────────────────
$stmt = $pdo->query(
    "SELECT table_name FROM information_schema.tables
      WHERE table_schema = DATABASE()
        AND table_name LIKE 't\\_%\\_users'
      ORDER BY table_name ASC LIMIT 1"
);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    die("No tenant prefix detected — is the database set up?\n");
}
$tableName = $row['table_name'] ?? $row['TABLE_NAME'];
$prefix    = substr($tableName, 0, -strlen('users')); // e.g. "t_abc123_"
$slug      = rtrim($prefix, '_');                     // e.g. "t_abc123"

echo "Detected tenant prefix: '{$prefix}' → slug: '{$slug}'\n";

// ── Define dirs to migrate ───────────────────────────────────────────────────
$tenantBase = STORAGE_PATH . '/tenants/' . $slug;
$dirs = ['patients', 'intake', 'uploads', 'befunde', 'vet-reports'];

echo "Target: {$tenantBase}\n\n";

foreach ($dirs as $dir) {
    $src = STORAGE_PATH . '/' . $dir;
    $dst = $tenantBase . '/' . $dir;

    if (!is_dir($src)) {
        echo "  [SKIP] {$dir}/ — source does not exist\n";
        continue;
    }

    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
        echo "  [MKDIR] {$dst}\n";
    }

    $moved = moveDir($src, $dst);
    echo "  [OK] {$dir}/ — {$moved} file(s) moved\n";
}

echo "\nDone. Please verify the files are in place, then you can remove the old empty dirs.\n";

// ── Helper: recursively move files ──────────────────────────────────────────
function moveDir(string $src, string $dst): int
{
    $count = 0;
    $iter  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iter as $item) {
        $relative = substr($item->getPathname(), strlen($src) + 1);
        $target   = $dst . '/' . $relative;

        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            if (file_exists($target)) {
                // Already migrated — skip
                continue;
            }
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            rename($item->getPathname(), $target);
            $count++;
        }
    }

    return $count;
}
