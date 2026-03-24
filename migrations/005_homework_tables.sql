-- Superseded by 012_homework_system.sql (correct INT UNSIGNED types)
-- This file is intentionally empty

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
