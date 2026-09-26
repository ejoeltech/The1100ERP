-- WP4 authentication hardening migration (APPLY MANUALLY, e.g. phpMyAdmin).
-- Additive only. Rollback statements are commented at the bottom.

-- 1. TOTP MFA columns (secret is AES-256-GCM encrypted, never plaintext).
-- NOTE: MariaDB has no ADD COLUMN IF NOT EXISTS; ignore "Duplicate column"
-- errors if re-running, or prefer the SchemaPatcher / fresh install-schema.
ALTER TABLE `users` ADD COLUMN `mfa_secret` TEXT DEFAULT NULL AFTER `must_change_password`;
ALTER TABLE `users` ADD COLUMN `mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `mfa_secret`;
ALTER TABLE `users` ADD COLUMN `mfa_enrolled_at` DATETIME DEFAULT NULL AFTER `mfa_enabled`;

-- 2. Single-use recovery codes (Argon2 hashes, never plaintext).
CREATE TABLE IF NOT EXISTS `mfa_recovery_codes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mfa_rc_user` (`user_id`),
  CONSTRAINT `mfa_recovery_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Server-side session registry (revocation on password/role change).
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `session_hash` char(64) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_seen` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_hash`),
  KEY `idx_us_user` (`user_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================= ROLLBACK (run manually, in order) =================
-- DELETE FROM `mfa_recovery_codes`;
-- DROP TABLE IF EXISTS `mfa_recovery_codes`;
-- DELETE FROM `user_sessions`;
-- DROP TABLE IF EXISTS `user_sessions`;
-- ALTER TABLE `users` DROP COLUMN `mfa_secret`;
-- ALTER TABLE `users` DROP COLUMN `mfa_enabled`;
-- ALTER TABLE `users` DROP COLUMN `mfa_enrolled_at`;
-- NOTE: rollback disables MFA for everyone (users keep passwords).
