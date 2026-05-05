ALTER TABLE `{PREFIX}portal_messages`
    ADD COLUMN IF NOT EXISTS `attachment_path` VARCHAR(500)  NULL DEFAULT NULL AFTER `body`,
    ADD COLUMN IF NOT EXISTS `attachment_name` VARCHAR(255)  NULL DEFAULT NULL AFTER `attachment_path`,
    ADD COLUMN IF NOT EXISTS `attachment_size` INT UNSIGNED  NULL DEFAULT NULL AFTER `attachment_name`;
