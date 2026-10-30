-- HR Module Schema Update v11
-- ID Card Maker: HR signature image rendered behind every card design.
-- The signature is stored per saved card, per saved template, and per base
-- default so the same HR signature flows through all seven templates and
-- bulk production. Idempotent: re-running ignores duplicate-column errors.

ALTER TABLE `hr_id_cards`
  ADD COLUMN `signature_path` varchar(255) NOT NULL DEFAULT '';

ALTER TABLE `hr_id_templates`
  ADD COLUMN `signature_path` varchar(255) NOT NULL DEFAULT '';

ALTER TABLE `hr_id_template_defaults`
  ADD COLUMN `signature_path` varchar(255) NOT NULL DEFAULT '';
