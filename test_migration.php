<?php
require_once 'vendor/autoload.php';

// Bootstrap
$app = require_once 'app/bootstrap.php';

// Migration ausführen
$sql = file_get_contents('migrations/005_homework_tables.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        try {
            $app->get('App\Core\Database')->query($statement);
            echo "✓ Migration erfolgreich: " . substr($statement, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "✗ Fehler bei: " . substr($statement, 0, 50) . "...\n";
            echo "  Fehler: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nMigration abgeschlossen!\n";
?>
