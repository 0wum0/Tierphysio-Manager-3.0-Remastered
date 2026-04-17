ALTER TABLE `{{prefix}}patients` MODIFY COLUMN `gender` ENUM('männlich','weiblich','kastriert','sterilisiert','unbekannt') NULL DEFAULT 'unbekannt';
