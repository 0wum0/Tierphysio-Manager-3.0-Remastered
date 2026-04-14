-- Migration 024: Practice type setting (therapeut / trainer)
INSERT INTO `settings` (`key`, `value`)
VALUES ('practice_type', 'therapeut')
ON DUPLICATE KEY UPDATE `value` = `value`;
