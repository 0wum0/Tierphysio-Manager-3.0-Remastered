<?php

declare(strict_types=1);

/**
 * REPAIR ALL TENANTS
 * ──────────────────────────────────────────────────────────
 * Dieses Skript iteriert durch alle aktiven Mandanten und 
 * erzwingt eine Synchronisation auf das neueste Schema (V48).
 * ──────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/vendor/autoload.php';

use Saas\Core\Config;
use Saas\Core\Database;
use Saas\Services\MigrationService;

define('ROOT_PATH', __DIR__);

// 1. App-Konfiguration laden
require_once __DIR__ . '/app/Core/Config.php';
$appConfig = new \App\Core\Config(ROOT_PATH);

// 2. Datenbank-Verbindung (SaaS Kontext)
$dbHost = $appConfig->get('db.host');
$dbPort = (int)$appConfig->get('db.port', 3306);
$dbUser = $appConfig->get('db.username');
$dbPass = $appConfig->get('db.password');
$dbName = $appConfig->get('db.database');

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Wir nutzen eine temporäre SaaS Config Instanz für den Service
    $saasConfig = new class(ROOT_PATH) extends Config {
        public function getRootPath(): string { return ROOT_PATH; }
        public function get(string $key, mixed $default = null): mixed { return null; }
    };
    
    // SaaS Database Wrapper
    $saasDb = new Database($saasConfig);
    // Wir setzen das PDO manuell, falls die Database Klasse es nicht per Config lädt
    $reflection = new ReflectionClass($saasDb);
    $prop = $reflection->getProperty('pdo');
    $prop->setAccessible(true);
    $prop->setValue($saasDb, $pdo);

    $migrationService = new MigrationService($saasConfig, $saasDb);

    echo "=== MANDANTEN-REPARATUR START ===\n";
    echo "Suche aktive Mandanten...\n\n";

    $stmt = $pdo->query("SELECT id, name, email, db_prefix FROM tenants WHERE status IN ('active','trial')");
    $tenants = $stmt->fetchAll();

    echo "Anzahl Mandanten: " . count($tenants) . "\n";
    echo str_repeat("-", 40) . "\n";

    $successCount = 0;
    $errorCount = 0;

    foreach ($tenants as $t) {
        $prefix = $t['db_prefix'];
        echo "Repariere Mandant: {$t['name']} ({$prefix})...\n";

        try {
            // Führt ensureTenantBaseSchema und alle Migrations aus
            $res = $migrationService->migrateTenant($prefix);
            
            if ($res['success']) {
                echo "  ✓ Erfolgreich (Version {$res['from']} -> {$res['to']}, {$res['ran_count']} Migrations)\n";
                $successCount++;
            } else {
                echo "  ✗ Fehler bei Migration:\n";
                print_r($res['report']);
                $errorCount++;
            }
        } catch (\Throwable $e) {
            echo "  ✗ FATALER FEHLER: " . $e->getMessage() . "\n";
            $errorCount++;
        }
        echo str_repeat("-", 40) . "\n";
    }

    echo "\n=== REPARATUR ABGESCHLOSSEN ===\n";
    echo "Erfolgreich: {$successCount}\n";
    echo "Fehler:      {$errorCount}\n";

} catch (\Throwable $e) {
    echo "Kritischer Fehler beim Start: " . $e->getMessage() . "\n";
}
