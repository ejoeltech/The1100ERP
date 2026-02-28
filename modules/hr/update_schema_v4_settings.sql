-- HR Module Schema Update v4
-- Adds support for HR Settings (ID Card Design)

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `hr_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default ID Card settings
INSERT INTO `hr_settings` (`setting_key`, `setting_value`) VALUES
('id_card_primary_color', '#0072bc'),
('id_card_secondary_color', '#39b54a'),
('id_card_tertiary_color', '#005a9c'),
('id_card_logo_type', 'system'), -- 'system' or 'custom_upload'
('id_card_show_qr', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

SET FOREIGN_KEY_CHECKS=1;
