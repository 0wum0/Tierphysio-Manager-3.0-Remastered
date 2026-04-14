-- Migration 003: Extend patients.gender ENUM to include kastriert and sterilisiert
ALTER TABLE `patients` MODIFY COLUMN `gender` ENUM('männlich','weiblich','kastriert','sterilisiert','unbekannt') NULL DEFAULT 'unbekannt';
