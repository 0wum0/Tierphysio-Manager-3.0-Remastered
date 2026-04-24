-- Migration 002: Extend patients.gender ENUM to include kastriert and sterilisiert
ALTER TABLE `{{prefix}}patients` MODIFY COLUMN `gender` ENUM('männlich','weiblich','kastriert','sterilisiert','unbekannt') NULL DEFAULT 'unbekannt';
