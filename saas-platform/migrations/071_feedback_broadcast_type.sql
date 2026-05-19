-- ============================================================
-- Migration 071: Broadcast-Typ für Feedback + Massen-Nachrichten
-- ============================================================

-- Erweitert feedback.type ENUM um 'broadcast' für Massen-Nachrichten
ALTER TABLE `feedback`
    MODIFY COLUMN `type` ENUM('bug','feature','support','question','praise','other','broadcast') NOT NULL DEFAULT 'other';

-- Erweitert feedback.category ENUM um 'broadcast'
ALTER TABLE `feedback`
    MODIFY COLUMN `category` ENUM('bug','feature','praise','other','broadcast') NOT NULL DEFAULT 'other';
