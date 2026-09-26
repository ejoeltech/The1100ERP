-- WP2 authentication hardening migration (APPLY MANUALLY, e.g. phpMyAdmin).
-- Additive only. Rollback statements are commented at the bottom.
-- Verified additive-safe on dev 2026-09-26 (tables created, codes backfilled).

-- 1. Force-password-change flag for admin-reset accounts.
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

-- 2. One-time invite tokens (random 256-bit, stored hashed, 48h expiry, single-use).
CREATE TABLE IF NOT EXISTS `user_invites` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token_hash` (`token_hash`),
  KEY `idx_invite_user` (`user_id`),
  KEY `idx_invite_expiry` (`expires_at`),
  CONSTRAINT `user_invites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Hardened onboarding codes: hash at rest, expiry, per-code attempt counter.
ALTER TABLE `hr_onboarding_codes` ADD COLUMN IF NOT EXISTS `code_hash` CHAR(64) DEFAULT NULL AFTER `code`;
ALTER TABLE `hr_onboarding_codes` ADD COLUMN IF NOT EXISTS `expires_at` DATETIME DEFAULT NULL AFTER `is_used`;
ALTER TABLE `hr_onboarding_codes` ADD COLUMN IF NOT EXISTS `failed_attempts` INT(11) NOT NULL DEFAULT 0 AFTER `expires_at`;

-- Backfill hashes for existing plaintext codes (one-way) and give them 30 days.
UPDATE `hr_onboarding_codes` SET `code_hash` = SHA2(`code`, 256) WHERE `code_hash` IS NULL;
UPDATE `hr_onboarding_codes` SET `expires_at` = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE `expires_at` IS NULL;

-- 4. Generic DB-backed throttle buckets (signup codes, invites).
CREATE TABLE IF NOT EXISTS `auth_throttle` (
  `bucket` varchar(128) NOT NULL,
  `window_start` datetime NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================= ROLLBACK (run manually, in order) =================
-- DELETE FROM `user_invites`;
-- DROP TABLE IF EXISTS `user_invites`;
-- DROP TABLE IF EXISTS `auth_throttle`;
-- ALTER TABLE `users` DROP COLUMN `must_change_password`;
-- ALTER TABLE `hr_onboarding_codes` DROP COLUMN `code_hash`;
-- ALTER TABLE `hr_onboarding_codes` DROP COLUMN `expires_at`;
-- ALTER TABLE `hr_onboarding_codes` DROP COLUMN `failed_attempts`;
-- NOTE: rollback does not restore deleted invite/code rows or prior hashes.
