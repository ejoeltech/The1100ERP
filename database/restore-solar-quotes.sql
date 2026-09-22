-- Restore Solar Quote Templates - CORRECT VERSION
USE 1100erp;

-- Basic Home Solar System (1.5KVA) - ₦483,750
INSERT INTO quote_templates (template_name, estimated_total, template_description)
VALUES ('Basic Home Solar System (1.5KVA)', 483750.00, 'Complete solar solution for small homes');

SET @tid = LAST_INSERT_ID();

INSERT INTO quote_template_items (template_id, item_number, description, quantity, unit_price, line_total) VALUES
(@tid, 1, '1.5KVA/12V Pure Sine Wave Solar Inverter', 1, 85000, 85000),
(@tid, 2, '100Ah/12V Deep Cycle Battery', 2, 75000, 150000),
(@tid, 3, '200W Monocrystalline Solar Panel', 2, 62000, 124000),
(@tid, 4, '30A MPPT Solar Charge Controller', 1, 35000, 35000),
(@tid, 5, 'Solar Panel Mounting Kit', 1, 25000, 25000),
(@tid, 6, 'Solar Cables & Accessories', 1, 16000, 16000),
(@tid, 7, 'Professional Installation', 1, 15000, 15000);

-- Standard Home Solar System (3.5KVA) - ₦1,343,750
INSERT INTO quote_templates (template_name, estimated_total, template_description)
VALUES ('Standard Home Solar System (3.5KVA)', 1343750.00, 'Mid-range solar for average homes');

SET @tid = LAST_INSERT_ID();

INSERT INTO quote_template_items (template_id, item_number, description, quantity, unit_price, line_total) VALUES
(@tid, 1, '3.5KVA/24V Pure Sine Wave Solar Inverter', 1, 165000, 165000),
(@tid, 2, '200Ah/12V Deep Cycle Battery', 4, 125000, 500000),
(@tid, 3, '300W Monocrystalline Solar Panel', 4, 88000, 352000),
(@tid, 4, '60A MPPT Solar Charge Controller', 1, 65000, 65000),
(@tid, 5, 'Solar Panel Mounting Kit', 1, 25000, 25000),
(@tid, 6, '63A Automatic Change Over Switch', 1, 18000, 18000),
(@tid, 7, 'Solar Cables & Accessories', 1, 50000, 50000),
(@tid, 8, 'Professional Installation', 1, 75000, 75000);

-- Premium Home Solar System (5KVA) - ₦2,311,250
INSERT INTO quote_templates (template_name, estimated_total, template_description)
VALUES ('Premium Home Solar System (5KVA)', 2311250.00, 'Premium solar for larger homes with AC');

SET @tid = LAST_INSERT_ID();

INSERT INTO quote_template_items (template_id, item_number, description, quantity, unit_price, line_total) VALUES
(@tid, 1, '5KVA/48V Pure Sine Wave Solar Inverter', 1, 285000, 285000),
(@tid, 2, '200Ah/12V Deep Cycle Battery', 8, 125000, 1000000),
(@tid, 3, '450W Monocrystalline Solar Panel', 6, 125000, 750000),
(@tid, 4, '60A MPPT Charge Controller', 1, 65000, 65000),
(@tid, 5, 'Solar Panel Mounting Kits (x2)', 2, 25000, 50000),
(@tid, 6, 'Solar Cables & Accessories', 1, 36250, 36250),
(@tid, 7, 'Professional Installation', 1, 125000, 125000);

-- Small Office/Shop Solar System - ₦1,268,500
INSERT INTO quote_templates (template_name, estimated_total, template_description)
VALUES ('Small Office/Shop Solar System (3.5KVA)', 1268500.00, 'Ideal for small offices and shops');

SET @tid = LAST_INSERT_ID();

INSERT INTO quote_template_items (template_id, item_number, description, quantity, unit_price, line_total) VALUES
(@tid, 1, '3.5KVA/24V Solar Inverter', 1, 165000, 165000),
(@tid, 2, '200Ah/12V Deep Cycle Battery', 4, 125000, 500000),
(@tid, 3, '300W Solar Panel', 5, 88000, 440000),
(@tid, 4, '60A MPPT Charge Controller', 1, 65000, 65000),
(@tid, 5, '63A Change Over Switch', 1, 18000, 18000),
(@tid, 6, 'Installation & Accessories', 1, 80500, 80500);

-- Budget Backup System - ₦385,000
INSERT INTO quote_templates (template_name, estimated_total, template_description)
VALUES ('Budget Backup System (1.5KVA)', 385000.00, 'Affordable backup - no solar panels');

SET @tid = LAST_INSERT_ID();

INSERT INTO quote_template_items (template_id, item_number, description, quantity, unit_price, line_total) VALUES
(@tid, 1, '1.5KVA/12V Pure Sine Wave Inverter', 1, 85000, 85000),
(@tid, 2, '200Ah/12V Deep Cycle Battery', 2, 125000, 250000),
(@tid, 3, 'Battery Terminals & Cables', 1, 8000, 8000),
(@tid, 4, '63A Change Over Switch', 1, 18000, 18000),
(@tid, 5, 'Installation Service', 1, 24000, 24000);

-- Commercial Solar System - ₦4,393,750
INSERT INTO quote_templates (template_name, estimated_total, template_description)
VALUES ('Commercial Solar System (10KVA)', 4393750.00, 'Heavy-duty commercial solution');

SET @tid = LAST_INSERT_ID();

INSERT INTO quote_template_items (template_id, item_number, description, quantity, unit_price, line_total) VALUES
(@tid, 1, '10KVA/48V Solar Inverter', 1, 575000, 575000),
(@tid, 2, '200Ah/12V Deep Cycle Battery', 16, 125000, 2000000),
(@tid, 3, '450W Monocrystalline Solar Panel', 12, 125000, 1500000),
(@tid, 4, '60A MPPT Charge Controller', 2, 65000, 130000),
(@tid, 5, 'Heavy Duty Change Over Switch', 1, 45000, 45000),
(@tid, 6, 'Installation & Accessories', 1, 143750, 143750);

SELECT '✓ Solar Quote Templates Restored!' as status;
SELECT template_name, estimated_total FROM quote_templates ORDER BY estimated_total;
