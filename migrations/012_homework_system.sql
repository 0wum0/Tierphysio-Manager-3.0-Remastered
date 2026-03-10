-- Hausaufgaben-Templates Tabelle
CREATE TABLE IF NOT EXISTS `homework_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `category` ENUM('bewegung', 'dehnung', 'massage', 'kalt_warm', 'medikamente', 'fuetterung', 'beobachtung', 'sonstiges') NOT NULL,
    `category_emoji` VARCHAR(10) NOT NULL,
    `frequency` ENUM('daily', 'twice_daily', 'three_times_daily', 'weekly', 'as_needed') NOT NULL,
    `duration_value` INT NOT NULL,
    `duration_unit` ENUM('minutes', 'hours', 'days', 'weeks') NOT NULL,
    `therapist_notes` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patienten-Hausaufgaben Tabelle
CREATE TABLE IF NOT EXISTS `patient_homework` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id` INT UNSIGNED NOT NULL,
    `homework_template_id` INT UNSIGNED NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `category` ENUM('bewegung', 'dehnung', 'massage', 'kalt_warm', 'medikamente', 'fuetterung', 'beobachtung', 'sonstiges') NOT NULL,
    `category_emoji` VARCHAR(10) NOT NULL,
    `frequency` ENUM('daily', 'twice_daily', 'three_times_daily', 'weekly', 'as_needed') NOT NULL,
    `duration_value` INT NOT NULL,
    `duration_unit` ENUM('minutes', 'hours', 'days', 'weeks') NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NULL,
    `therapist_notes` TEXT NULL,
    `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `assigned_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_patient_homework_patient` (`patient_id`),
    INDEX `idx_patient_homework_status` (`status`),
    INDEX `idx_patient_homework_dates` (`start_date`, `end_date`),
    CONSTRAINT `fk_patient_homework_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_patient_homework_template` FOREIGN KEY (`homework_template_id`) REFERENCES `homework_templates` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_patient_homework_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hausaufgaben-Completion Tabelle
CREATE TABLE IF NOT EXISTS `homework_completions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `homework_id` INT UNSIGNED NOT NULL,
    `completed_by` INT UNSIGNED NOT NULL,
    `completed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_homework_completions_homework` (`homework_id`),
    CONSTRAINT `fk_homework_completions_homework` FOREIGN KEY (`homework_id`) REFERENCES `patient_homework` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_homework_completions_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vordefinierte Tierphysio-Hausaufgaben Templates
INSERT IGNORE INTO `homework_templates` (`title`, `description`, `category`, `category_emoji`, `frequency`, `duration_value`, `duration_unit`, `therapist_notes`) VALUES
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
