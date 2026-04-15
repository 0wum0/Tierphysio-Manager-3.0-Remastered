<?php
require 'vendor/autoload.php';
use App\Core\Config;
use App\Core\Database;

$config = new Config(__DIR__);
$db = new Database($config);

// Check all mobile_api_tokens tables
$tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '%mobile_api_tokens'");

echo "Found " . count($tables) . " token tables:\n";
foreach ($tables as $t) {
    $count = $db->fetchColumn("SELECT COUNT(*) FROM `{$t['table_name']}`");
    echo "  - {$t['table_name']} ($count rows)\n";
}

// Check some specific tenants
$tenants = $db->fetchAll("SELECT * FROM tenants LIMIT 5");
echo "\nExample tenants:\n";
foreach ($tenants as $t) {
    echo "  - {$t['email']} (DB: {$t['db_name']})\n";
}
