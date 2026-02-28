-- HR Module Schema Update v2
-- Adds support for extended Profile Details and ID Cards

SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `hr_employees`
ADD COLUMN `passport_path` varchar(255) DEFAULT NULL AFTER `gender`,
ADD COLUMN `signature_path` varchar(255) DEFAULT NULL AFTER `passport_path`,
ADD COLUMN `secondary_phone` varchar(50) DEFAULT NULL AFTER `address`,
ADD COLUMN `nin_number` varchar(50) DEFAULT NULL AFTER `account_name`,
ADD COLUMN `bvn_number` varchar(50) DEFAULT NULL AFTER `nin_number`,
-- Tin Number already exists in v1
ADD COLUMN `next_of_kin_name` varchar(255) DEFAULT NULL AFTER `emergency_contact_phone`,
ADD COLUMN `next_of_kin_phone` varchar(50) DEFAULT NULL AFTER `next_of_kin_name`,
ADD COLUMN `next_of_kin_relationship` varchar(100) DEFAULT NULL AFTER `next_of_kin_phone`,
ADD COLUMN `reference_1_name` varchar(255) DEFAULT NULL AFTER `next_of_kin_relationship`,
ADD COLUMN `reference_1_phone` varchar(50) DEFAULT NULL AFTER `reference_1_name`,
ADD COLUMN `reference_1_org` varchar(255) DEFAULT NULL AFTER `reference_1_phone`,
ADD COLUMN `reference_2_name` varchar(255) DEFAULT NULL AFTER `reference_1_org`,
ADD COLUMN `reference_2_phone` varchar(50) DEFAULT NULL AFTER `reference_2_name`,
ADD COLUMN `reference_2_org` varchar(255) DEFAULT NULL AFTER `reference_2_phone`;

SET FOREIGN_KEY_CHECKS=1;
