-- HR Module Schema Update v13
-- Widen NIN/BVN columns so versioned encrypted envelopes fit.
-- Run after v2 (adds the columns) and before encrypting any PII.

ALTER TABLE `hr_employees`
  MODIFY COLUMN `nin_number` VARCHAR(255) DEFAULT NULL,
  MODIFY COLUMN `bvn_number` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `hr_onboarding_entries`
  MODIFY COLUMN `nin_number` VARCHAR(255) DEFAULT NULL,
  MODIFY COLUMN `bvn_number` VARCHAR(255) DEFAULT NULL;
