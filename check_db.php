<?php
// Check if homework_templates table exists and has data
try {
    // Database connection
    $pdo = new PDO('mysql:host=localhost;dbname=tierphysio', 'root', '');
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
        $stmt = $pdo->query("SELECT id, title, category FROM homework_templates LIMIT 5");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "First few templates:\n";
        foreach ($templates as $template) {
            echo "ID: {$template['id']}, Title: {$template['title']}, Category: {$template['category']}\n";
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
        } else {
            echo "\n✗ Template ID 3 NOT FOUND\n";
        }
        
    } else {
        echo "✗ Table does not exist\n";
        
        // Create table
        echo "\n=== Creating homework_templates table ===\n";
        $sql = "
        CREATE TABLE homework_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            category VARCHAR(50) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($sql);
        echo "✓ Table created\n";
        
        // Insert sample templates
        echo "\n=== Inserting sample templates ===\n";
        $templates = [
            [
                'title' => 'Tägliche Spaziergänge',
                'description' => 'Führen Sie 2-3 mal täglich kurze Spaziergänge von 10-15 Minuten durch. Achten Sie auf eine gleichmäßige Bewegung und vermeiden Sie abruptes Stoppen oder Springen.',
                'category' => 'bewegung'
            ],
            [
                'title' => 'Sanfte Dehnübungen',
                'description' => 'Führen Sie sanfte Dehnübungen für die Gliedmaßen durch. Halten Sie jede Dehnung für 15-20 Sekunden und vermeiden Sie Schmerz.',
                'category' => 'dehnung'
            ],
            [
                'title' => 'Wärmeanwendungen',
                'description' => 'Wenden Sie 2-3 mal täglich warme Kompressen für 10-15 Minuten an. Stellen Sie sicher, dass die Temperatur angenehm warm ist, nicht heiß.',
                'category' => 'kalt_warm'
            ],
            [
                'title' => 'Massageübungen',
                'description' => 'Führen Sie sanfte Massagen in den betroffenen Bereichen durch. Verwenden Sie kreisende Bewegungen und leichten Druck.',
                'category' => 'massage'
            ],
            [
                'title' => 'Medikamentengabe',
                'description' => 'Verabreichen Sie die verschriebenen Medikamente genau nach Anweisung des Tierarztes. Notieren Sie die Zeit der Gabe und eventuelle Reaktionen.',
                'category' => 'medikamente'
            ]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO homework_templates (title, description, category) VALUES (?, ?, ?)");
        foreach ($templates as $template) {
            $stmt->execute([$template['title'], $template['description'], $template['category']]);
            echo "✓ Inserted: " . $template['title'] . "\n";
        }
    }
    
    // Check patient_homework table
    echo "\n=== Checking patient_homework table ===\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'patient_homework'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ patient_homework table exists\n";
    } else {
        echo "✗ patient_homework table does not exist\n";
        
        // Create table
        $sql = "
        CREATE TABLE patient_homework (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            homework_template_id INT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            category VARCHAR(50) NOT NULL,
            frequency VARCHAR(50) NOT NULL DEFAULT 'daily',
            duration_value INT NOT NULL DEFAULT 10,
            duration_unit VARCHAR(20) NOT NULL DEFAULT 'minutes',
            start_date DATE NOT NULL,
            end_date DATE NULL,
            therapist_notes TEXT NULL,
            assigned_by INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id),
            FOREIGN KEY (homework_template_id) REFERENCES homework_templates(id),
            FOREIGN KEY (assigned_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($sql);
        echo "✓ patient_homework table created\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
