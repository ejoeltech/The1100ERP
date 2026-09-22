-- Update Quote Templates for Solar Inverter Business
USE 1100erp;

DELETE FROM quote_templates;
ALTER TABLE quote_templates AUTO_INCREMENT = 1;

-- Basic Home Solar System (1.5KVA) - ₦483,750
INSERT INTO quote_templates (name, items_json, total) VALUES (
'Basic Home Solar System (1.5KVA)',
'[{"description":"1.5KVA/12V Pure Sine Wave Solar Inverter","quantity":1,"unit_price":85000,"amount":85000},{"description":"100Ah/12V Deep Cycle Battery","quantity":2,"unit_price":75000,"amount":150000},{"description":"200W Monocrystalline Solar Panel","quantity":2,"unit_price":62000,"amount":124000},{"description":"30A MPPT Solar Charge Controller","quantity":1,"unit_price":35000,"amount":35000},{"description":"Solar Panel Mounting Kit","quantity":1,"unit_price":25000,"amount":25000},{"description":"Solar Cables & Accessories","quantity":1,"unit_price":16000,"amount":16000},{"description":"Professional Installation","quantity":1,"unit_price":15000,"amount":15000}]',
483750.00);

-- Standard Home Solar System (3.5KVA) - ₦1,343,750
INSERT INTO quote_templates (name, items_json, total) VALUES (
'Standard Home Solar System (3.5KVA)',
'[{"description":"3.5KVA/24V Pure Sine Wave Solar Inverter","quantity":1,"unit_price":165000,"amount":165000},{"description":"200Ah/12V Deep Cycle Battery","quantity":4,"unit_price":125000,"amount":500000},{"description":"300W Monocrystalline Solar Panel","quantity":4,"unit_price":88000,"amount":352000},{"description":"60A MPPT Solar Charge Controller","quantity":1,"unit_price":65000,"amount":65000},{"description":"Solar Panel Mounting Kit","quantity":1,"unit_price":25000,"amount":25000},{"description":"63A Automatic Change Over Switch","quantity":1,"unit_price":18000,"amount":18000},{"description":"Solar Cables & Accessories","quantity":1,"unit_price":50000,"amount":50000},{"description":"Professional Installation","quantity":1,"unit_price":75000,"amount":75000}]',
1343750.00);

-- Premium Home Solar System (5KVA) - ₦2,311,250
INSERT INTO quote_templates (name, items_json, total) VALUES (
'Premium Home Solar System (5KVA)',
'[{"description":"5KVA/48V Pure Sine Wave Solar Inverter","quantity":1,"unit_price":285000,"amount":285000},{"description":"200Ah/12V Deep Cycle Battery","quantity":8,"unit_price":125000,"amount":1000000},{"description":"450W Monocrystalline Solar Panel","quantity":6,"unit_price":125000,"amount":750000},{"description":"60A MPPT Charge Controller","quantity":1,"unit_price":65000,"amount":65000},{"description":"Solar Panel Mounting Kit (x2)","quantity":2,"unit_price":25000,"amount":50000},{"description":"Solar Cables & Accessories","quantity":1,"unit_price":36250,"amount":36250},{"description":"Professional Installation","quantity":1,"unit_price":125000,"amount":125000}]',
2311250.00);

-- Small Office/Shop Solar System (3.5KVA) - ₦1,268,500
INSERT INTO quote_templates (name, items_json, total) VALUES (
'Small Office/Shop Solar System (3.5KVA)',
'[{"description":"3.5KVA/24V Solar Inverter","quantity":1,"unit_price":165000,"amount":165000},{"description":"200Ah/12V Deep Cycle Battery","quantity":4,"unit_price":125000,"amount":500000},{"description":"300W Solar Panel","quantity":5,"unit_price":88000,"amount":440000},{"description":"60A MPPT Charge Controller","quantity":1,"unit_price":65000,"amount":65000},{"description":"63A Change Over Switch","quantity":1,"unit_price":18000,"amount":18000},{"description":"Installation & Accessories","quantity":1,"unit_price":80500,"amount":80500}]',
1268500.00);

-- Budget Backup System (1.5KVA) - ₦306,375
INSERT INTO quote_templates (name, items_json, total) VALUES (
'Budget Backup System (1.5KVA - No Solar)',
'[{"description":"1.5KVA/12V Pure Sine Wave Inverter","quantity":1,"unit_price":85000,"amount":85000},{"description":"200Ah/12V Deep Cycle Battery","quantity":2,"unit_price":125000,"amount":250000},{"description":"Battery Terminals & Cables","quantity":1,"unit_price":8000,"amount":8000},{"description":"63A Change Over Switch","quantity":1,"unit_price":18000,"amount":18000},{"description":"Installation Service","quantity":1,"unit_price":24000,"amount":24000}]',
385000.00);

-- Commercial Solar System (10KVA) - ₦4,568,750
INSERT INTO quote_templates (name, items_json, total) VALUES (
'Commercial Solar System (10KVA)',
'[{"description":"10KVA/48V Solar Inverter","quantity":1,"unit_price":575000,"amount":575000},{"description":"200Ah/12V Deep Cycle Battery","quantity":16,"unit_price":125000,"amount":2000000},{"description":"450W Monocrystalline Solar Panel","quantity":12,"unit_price":125000,"amount":1500000},{"description":"60A MPPT Charge Controller (x2)","quantity":2,"unit_price":65000,"amount":130000},{"description":"Heavy Duty Change Over Switch","quantity":1,"unit_price":45000,"amount":45000},{"description":"Installation & Accessories","quantity":1,"unit_price":143750,"amount":143750}]',
4393750.00);

SELECT 'Solar Quote Templates Updated!' as status;
SELECT name, total FROM quote_templates ORDER BY total;
