-- HR Module Schema Update v6
-- Adds support for Extended ID Card Settings (Text & Layout)

INSERT IGNORE INTO `hr_settings` (`setting_key`, `setting_value`) VALUES
('id_card_subtitle_text', 'TECHNOLOGIES'),
('id_card_emergency_label', 'IN CASE OF EMERGENCY CONTACT:'),
('id_card_disclaimer_text', 'If found, please return to the address above.'),
('id_card_logo_align', 'center'),    -- center, flex-start (left), flex-end (right)
('id_card_header_align', 'center'),  -- center, left, right
('id_card_photo_shape', 'circle');   -- circle, rounded, square
