-- HR Module Schema Update v14
-- Onboarding codes are stored only as a SHA-256 hash. The legacy plaintext
-- column becomes nullable and existing values are cleared.

ALTER TABLE `hr_onboarding_codes`
  MODIFY COLUMN `code` VARCHAR(20) NULL DEFAULT NULL;

-- Backfill hashes first so outstanding legacy codes keep working.
UPDATE `hr_onboarding_codes`
SET `code_hash` = SHA2(`code`, 256)
WHERE `code_hash` IS NULL AND `code` IS NOT NULL;

UPDATE `hr_onboarding_codes`
SET `code` = NULL
WHERE `code_hash` IS NOT NULL;

-- Older entries cached the plaintext code; clear it too.
UPDATE `hr_onboarding_entries`
SET `signup_code` = ''
WHERE `signup_code` IS NOT NULL AND `signup_code` <> '';
