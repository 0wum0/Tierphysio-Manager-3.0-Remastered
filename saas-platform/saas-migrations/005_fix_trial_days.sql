-- Migration 005: Testzeitraum auf 14 Tage korrigieren
-- Alle bezahlten Pläne sollen exakt 14 Tage Testphase haben.
UPDATE `plans`
SET `trial_days` = 14
WHERE `slug` != 'free';
