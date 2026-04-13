-- Migration 008: Add 'payment' to patient_timeline.type ENUM
ALTER TABLE `patient_timeline`
    MODIFY COLUMN `type` ENUM('note','treatment','photo','document','other','payment')
        NOT NULL DEFAULT 'note';
