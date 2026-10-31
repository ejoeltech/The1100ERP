-- HR Module Schema Update v12
-- Adds the staff type to self-service onboarding entries and normalises
-- legacy code roles (viewer/employee) to the new staff type.

SET FOREIGN_KEY_CHECKS=0;

-- Codes now carry one of: staff, intern, contract.
ALTER TABLE `hr_onboarding_codes`
  MODIFY COLUMN `role` VARCHAR(50) DEFAULT 'staff';

UPDATE `hr_onboarding_codes`
SET `role` = 'staff'
WHERE `role` NOT IN ('staff', 'intern', 'contract');

-- The chosen type follows the entry so HR can see it before importing.
ALTER TABLE `hr_onboarding_entries`
  ADD COLUMN `employment_type` VARCHAR(20) NOT NULL DEFAULT 'staff' AFTER `signup_code`;

SET FOREIGN_KEY_CHECKS=1;
