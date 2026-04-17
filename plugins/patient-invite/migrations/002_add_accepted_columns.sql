ALTER TABLE `{{prefix}}patient_invite_tokens` ADD COLUMN `accepted_patient_id` INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `{{prefix}}patient_invite_tokens` ADD COLUMN `accepted_owner_id`   INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `{{prefix}}patient_invite_tokens` ADD COLUMN `accepted_at`         DATETIME     NULL DEFAULT NULL;
