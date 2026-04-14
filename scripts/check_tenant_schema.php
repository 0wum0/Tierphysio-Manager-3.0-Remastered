<?php

declare(strict_types=1);

/**
 * Non-destructive tenant schema check.
 *
 * Usage:
 *   php scripts/check_tenant_schema.php t_example_123_
 */

function usage(): void
{
    fwrite(STDERR, "Usage: php scripts/check_tenant_schema.php <tenant_prefix>\n");
}

function loadEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eqPos));
        $val = trim(substr($line, $eqPos + 1));
        $val = trim($val, "\"'");
        $vars[$key] = $val;
    }

    return $vars;
}

function getConfig(): array
{
    $root = dirname(__DIR__);
    $env = array_merge(
        loadEnvFile($root . '/.env'),
        loadEnvFile($root . '/saas-platform/.env')
    );

    return [
        'host' => $env['DB_HOST'] ?? '127.0.0.1',
        'port' => (int)($env['DB_PORT'] ?? 3306),
        'name' => $env['DB_NAME'] ?? '',
        'user' => $env['DB_USER'] ?? '',
        'pass' => $env['DB_PASS'] ?? '',
    ];
}

function parseTenantSchema(string $schemaPath): array
{
    $sql = (string)file_get_contents($schemaPath);
    if ($sql === '') {
        throw new RuntimeException('tenant_schema.sql is empty');
    }

    preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`\s*\((.*?)\)\s*ENGINE=/is', $sql, $matches, PREG_SET_ORDER);

    $tables = [];
    foreach ($matches as $m) {
        $tableName = $m[1];
        $body = $m[2];

        preg_match_all('/^\s*`([^`]+)`\s+/m', $body, $colMatches);
        $columns = $colMatches[1] ?? [];

        $tables[$tableName] = array_values(array_unique($columns));
    }

    return $tables;
}

if ($argc < 2) {
    usage();
    exit(1);
}

$prefix = trim((string)$argv[1]);
if ($prefix === '') {
    usage();
    exit(1);
}

$schemaPath = dirname(__DIR__) . '/saas-platform/provisioning/tenant_schema.sql';
if (!is_file($schemaPath)) {
    fwrite(STDERR, "tenant_schema.sql not found: {$schemaPath}\n");
    exit(1);
}

$config = getConfig();
if ($config['name'] === '' || $config['user'] === '') {
    fwrite(STDERR, "DB config missing in .env (DB_NAME / DB_USER).\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['name']);
$pdo = new PDO($dsn, $config['user'], $config['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$expected = parseTenantSchema($schemaPath);

$missingTables = [];
$missingColumns = [];

foreach ($expected as $baseTable => $columns) {
    $table = $prefix . $baseTable;

    $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $exists->execute([$config['name'], $table]);
    if ((int)$exists->fetchColumn() === 0) {
        $missingTables[] = $table;
        continue;
    }

    $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $stmt->execute([$config['name'], $table]);
    $actualColumns = array_map(static fn(array $r): string => (string)$r['COLUMN_NAME'], $stmt->fetchAll());
    $actualLookup = array_fill_keys($actualColumns, true);

    foreach ($columns as $col) {
        if (!isset($actualLookup[$col])) {
            $missingColumns[$table][] = $col;
        }
    }
}

$totalTables = count($expected);
$missingTableCount = count($missingTables);
$missingColumnCount = array_sum(array_map('count', $missingColumns));

fwrite(STDOUT, "Tenant Prefix: {$prefix}\n");
fwrite(STDOUT, "Expected Base Tables: {$totalTables}\n");
fwrite(STDOUT, "Missing Tables: {$missingTableCount}\n");
fwrite(STDOUT, "Missing Columns: {$missingColumnCount}\n\n");

if ($missingTables !== []) {
    fwrite(STDOUT, "--- Missing tables ---\n");
    foreach ($missingTables as $t) {
        fwrite(STDOUT, "- {$t}\n");
    }
    fwrite(STDOUT, "\n");
}

if ($missingColumns !== []) {
    fwrite(STDOUT, "--- Missing columns ---\n");
    foreach ($missingColumns as $table => $cols) {
        fwrite(STDOUT, "- {$table}: " . implode(', ', $cols) . "\n");
    }
    fwrite(STDOUT, "\n");
}

if ($missingTableCount === 0 && $missingColumnCount === 0) {
    fwrite(STDOUT, "OK: Base schema is complete for this tenant prefix.\n");
    exit(0);
}

fwrite(STDOUT, "WARN: Base schema drift detected. Safe to run tenant repair afterwards.\n");
exit(2);
