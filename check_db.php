<?php
// Check if homework_templates table exists and has data
try {
    // Database connection - use same config as app
    $pdo = new PDO('mysql:host=localhost;dbname=tierphysio', 'tierphysio', 'tierphysio123');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Checking homework_templates table ===\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'homework_templates'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ Table exists\n";
        
        // Count records
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM homework_templates");
        $count = $stmt->fetch();
        echo "Records: " . $count['count'] . "\n";
        
        // Show first few records
        $stmt = $pdo->query("SELECT id, title, category, category_emoji FROM homework_templates LIMIT 5");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "First few templates:\n";
        foreach ($templates as $template) {
            echo "ID: {$template['id']}, Title: {$template['title']}, Category: {$template['category']}, Emoji: {$template['category_emoji']}\n";
        }
        
        // Test specific template ID 3
        $stmt = $pdo->prepare("SELECT * FROM homework_templates WHERE id = ?");
        $stmt->execute([3]);
        $template3 = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template3) {
            echo "\n✓ Template ID 3 found:\n";
            echo "Title: " . $template3['title'] . "\n";
            echo "Description: " . $template3['description'] . "\n";
            echo "Category: " . $template3['category'] . "\n";
            echo "Emoji: " . $template3['category_emoji'] . "\n";
        } else {
            echo "\n✗ Template ID 3 NOT FOUND\n";
        }
        
    } else {
        echo "✗ Table does not exist\n";
    }
    
    // Check patient_homework table
    echo "\n=== Checking patient_homework table ===\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'patient_homework'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ patient_homework table exists\n";
        
        // Count records
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM patient_homework");
        $count = $stmt->fetch();
        echo "Records: " . $count['count'] . "\n";
        
        // Test API calls like the app does
        echo "\n=== Testing API calls ===\n";
        
        // Test getPatientHomework for patient 14
        $stmt = $pdo->prepare("
            SELECT ph.*, u.first_name, u.last_name,
                    CASE 
                        WHEN ph.frequency = 'daily' THEN 'Täglich'
                        WHEN ph.frequency = 'twice_daily' THEN '2x täglich'
                        WHEN ph.frequency = 'three_times_daily' THEN '3x täglich'
                        WHEN ph.frequency = 'weekly' THEN 'Wöchentlich'
                        WHEN ph.frequency = 'as_needed' THEN 'Bei Bedarf'
                    END as frequency_display,
                    CONCAT(ph.duration_value, ' ', 
                        CASE ph.duration_unit
                            WHEN 'minutes' THEN 'Minuten'
                            WHEN 'hours' THEN 'Stunden'
                            WHEN 'days' THEN 'Tage'
                            WHEN 'weeks' THEN 'Wochen'
                        END
                    ) as duration_display
            FROM patient_homework ph
            JOIN users u ON ph.assigned_by = u.id
            WHERE ph.patient_id = ? AND ph.status != 'cancelled'
            ORDER BY ph.created_at DESC
        ");
        $stmt->execute([14]);
        $homework = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Patient 14 homework items: " . count($homework) . "\n";
        
        // Test template lookup
        $stmt = $pdo->prepare("SELECT * FROM homework_templates WHERE id = ? AND is_active = 1 ORDER BY category, title");
        $stmt->execute([3]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template) {
            echo "✓ Template lookup successful for ID 3\n";
            echo "Template data: " . json_encode($template, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "✗ Template lookup failed for ID 3\n";
        }
        
    } else {
        echo "✗ patient_homework table does not exist\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
