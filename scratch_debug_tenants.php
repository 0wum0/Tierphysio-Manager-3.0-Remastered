<?php
require_once 'vendor/autoload.php';
$config = require_once 'saas-platform/app/bootstrap.php'; // Using SaaS bootstrap if it exists

// Manually get config from .env
require_once 'app/Core/Config.php';
$appConfig = new \App\Core\Config(__DIR__);

$host = $appConfig->get('db.host');
$port = $appConfig->get('db.port');
$database = $appConfig->get('db.database');
$username = $appConfig->get('db.username');
$password = $appConfig->get('db.password');

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Tenents Status ===\n";
    $stmt = $pdo->query("SELECT id, name, email, db_prefix, status FROM tenants");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tenants as $t) {
        echo "ID: {$t['id']}, Name: {$t['name']}, Email: {$t['email']}, Prefix: {$t['db_prefix']}, Status: {$t['status']}\n";
        
        // Check if users table exists for this prefix
        $usersTable = $t['db_prefix'] . 'users';
        $check = $pdo->query("SHOW TABLES LIKE '{$usersTable}'")->fetchColumn();
        if ($check) {
            echo "  ✓ Users table exists ({$usersTable})\n";
            $count = $pdo->query("SELECT COUNT(*) FROM `{$usersTable}`")->fetchColumn();
            echo "  ✓ Users count: {$count}\n";
        } else {
            echo "  ✗ Users table MISSING ({$usersTable})\n";
        }
    }
    
    echo "\n=== All Prefixed Users Tables in DB ===\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 't\_%\_users'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "Found: {$row[0]}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
