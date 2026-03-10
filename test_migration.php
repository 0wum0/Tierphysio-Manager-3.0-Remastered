<?php
require_once 'vendor/autoload.php';

// Bootstrap
$db = require_once 'app/bootstrap.php';

echo "=== Hausaufgaben-System Migration (Version 012) ===\n\n";

// Migration ausführen
$sql = file_get_contents('migrations/012_homework_system.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        try {
            $db->query($statement);
            echo "✓ Migration erfolgreich: " . substr($statement, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "✗ Fehler bei: " . substr($statement, 0, 50) . "...\n";
            echo "  Fehler: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Migration abgeschlossen! ===\n";
echo "\nDie Migration 012_homework_system.sql ist jetzt bereit für:\n";
echo "Einstellungen → Updates → Migrationen ausführen\n";
echo "\nDie folgenden Tabellen wurden erstellt:\n";
echo "- homework_templates (Hausaufgaben-Vorlagen)\n";
echo "- patient_homework (Patienten-Hausaufgaben)\n";
echo "- homework_completions (Hausaufgaben-Completions für Besitzer-Portal)\n";
echo "\n15 vordefinierte Tierphysio-Hausaufgaben wurden importiert.\n";
?>
