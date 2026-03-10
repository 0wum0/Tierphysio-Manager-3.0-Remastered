-- Hausaufgaben-Templates Tabelle
CREATE TABLE IF NOT EXISTS homework_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('bewegung', 'dehnung', 'massage', 'kalt_warm', 'medikamente', 'fuetterung', 'beobachtung', 'sonstiges') NOT NULL,
    category_emoji VARCHAR(10) NOT NULL,
    frequency ENUM('daily', 'twice_daily', 'three_times_daily', 'weekly', 'as_needed') NOT NULL,
    duration_value INT NOT NULL,
    duration_unit ENUM('minutes', 'hours', 'days', 'weeks') NOT NULL,
    therapist_notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patienten-Hausaufgaben Tabelle
CREATE TABLE IF NOT EXISTS patient_homework (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    homework_template_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('bewegung', 'dehnung', 'massage', 'kalt_warm', 'medikamente', 'fuetterung', 'beobachtung', 'sonstiges') NOT NULL,
    category_emoji VARCHAR(10) NOT NULL,
    frequency ENUM('daily', 'twice_daily', 'three_times_daily', 'weekly', 'as_needed') NOT NULL,
    duration_value INT NOT NULL,
    duration_unit ENUM('minutes', 'hours', 'days', 'weeks') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    therapist_notes TEXT,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (homework_template_id) REFERENCES homework_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hausaufgaben-Completion Tabelle (für Besitzer-Portal)
CREATE TABLE IF NOT EXISTS homework_completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    homework_id INT NOT NULL,
    completed_by INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    
    FOREIGN KEY (homework_id) REFERENCES patient_homework(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id),
    
    UNIQUE KEY unique_completion (homework_id, DATE(completed_at))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indizes
CREATE INDEX IF NOT EXISTS idx_patient_homework_patient ON patient_homework(patient_id);
CREATE INDEX IF NOT EXISTS idx_patient_homework_status ON patient_homework(status);
CREATE INDEX IF NOT EXISTS idx_patient_homework_dates ON patient_homework(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_homework_completions_homework ON homework_completions(homework_id);

-- Vordefinierte Tierphysio-Hausaufgaben Templates
INSERT IGNORE INTO homework_templates (title, description, category, category_emoji, frequency, duration_value, duration_unit, therapist_notes) VALUES
-- Bewegung
('Tägliche Spaziergänge', 'Kurze, kontrollierte Spaziergänge auf weichem Untergrund zur Förderung der Bewegung und Durchblutung.', 'bewegung', '🏃', 'daily', 15, 'minutes', 'Wichtig bei Gelenkerkrankungen'),
('Sanfte Bewegungsübungen', 'Leichte Dehn- und Bewegungsübungen zur Erhaltung der Muskelfunktion.', 'bewegung', '🏃', 'twice_daily', 10, 'minutes', 'Nur nach Absprache durchführen'),
('Schwimmen', 'Wassergymnastik oder Schwimmen zur gelenkschonenden Bewegung.', 'bewegung', '🏃', 'weekly', 30, 'minutes', 'Ideal für Arthrose-Patienten'),

-- Dehnung
('Leichte Dehnübungen', 'Sanfte Dehnung der betroffenen Muskelpartien zur Verbesserung der Flexibilität.', 'dehnung', '🤸', 'daily', 5, 'minutes', 'Nicht überdehnen!'),
('Passive Dehnung', 'Hilfestellung bei der Dehnung durch den Besitzer.', 'dehnung', '🤸', 'twice_daily', 10, 'minutes', 'Vorsicht bei Schmerzreaktionen'),

-- Massage
('Lymphdrainage-Massage', 'Leichte Massage zur Förderung der Lymphzirkulation.', 'massage', '💆', 'daily', 10, 'minutes', 'In Strömungsrichtung massieren'),
('Muskelpflege-Massage', 'Sanfte Massage verspannter Muskelpartien.', 'massage', '💆', 'as_needed', 15, 'minutes', 'Bei Muskelverspannungen anwenden'),

-- Kalt/Warm-Anwendungen
('Kälteanwendung', 'Kühlung bei Entzündungen oder Schwellungen.', 'kalt_warm', '🌡️', 'as_needed', 15, 'minutes', 'Kühlpackung in Tuch wickeln'),
('Wärmeanwendung', 'Wärme zur Muskelentspannung und Durchblutungsförderung.', 'kalt_warm', '🌡️', 'as_needed', 20, 'minutes', 'Nicht bei akuten Entzündungen'),

-- Medikamente
('Medikamentengabe', 'Regelmäßige Gabe verschriebener Medikamente.', 'medikamente', '💊', 'as_needed', 1, 'hours', 'Genau nach Anweisung durchführen'),
('Salbeneinreibung', 'Auftragen verschriebener Salben.', 'medikamente', '💊', 'twice_daily', 2, 'minutes', 'Einmassieren bis zur vollständigen Aufnahme'),

-- Fütterung
('Spezialfutter', 'Gabe von verschriebenem Spezialfutter.', 'fuetterung', '🍽️', 'daily', 15, 'minutes', 'Fütterungsmengen genau beachten'),
('Gewichtskontrolle', 'Regelmäßige Kontrolle des Körpergewichts.', 'fuetterung', '🍽️', 'weekly', 2, 'minutes', 'Gewicht protokollieren'),

-- Beobachtung
('Verhaltensbeobachtung', 'Beobachtung von Verhaltensänderungen und Schmerzsignalen.', 'beobachtung', '👁️', 'daily', 5, 'minutes', 'Auffälligkeiten dokumentieren'),
('Bewegungsbeobachtung', 'Kontrolle der Bewegungsabläufe und Haltung.', 'beobachtung', '👁️', 'daily', 3, 'minutes', 'Besonders auf Lahmheiten achten');
