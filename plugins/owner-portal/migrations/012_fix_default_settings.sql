-- Migration 012: Restore portal_show_homework and pdf_show_qr defaults
-- Both settings default to '1' (enabled). If they were accidentally written
-- as '0' (e.g. by a settings-save that did not include the checkbox),
-- this migration resets them back to '1'.

INSERT INTO `{PREFIX}settings` (`key`, `value`)
VALUES ('portal_show_homework', '1')
ON DUPLICATE KEY UPDATE `value` = IF(`value` = '0', '1', `value`);

INSERT INTO `{PREFIX}settings` (`key`, `value`)
VALUES ('pdf_show_qr', '1')
ON DUPLICATE KEY UPDATE `value` = IF(`value` = '0', '1', `value`);
