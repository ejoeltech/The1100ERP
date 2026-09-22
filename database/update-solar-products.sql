-- Solar Inverter Products Update
-- Replaces existing products with solar inverter catalog

USE 1100erp;

-- Clear existing products
DELETE FROM products;

-- Reset auto-increment
ALTER TABLE products AUTO_INCREMENT = 1;

-- ============================================
-- SOLAR INVERTERS & BATTERIES
-- ============================================

INSERT INTO products (product_code, product_name, description, unit_price, category, is_active) VALUES
-- Inverters
('INV-001', '1.5KVA/12V Solar Inverter', 'Pure sine wave inverter, 1500W capacity, 12V battery compatible', 85000.00, 'Inverters', 1),
('INV-002', '2.5KVA/24V Solar Inverter', 'Pure sine wave inverter, 2500W capacity, 24V battery compatible', 125000.00, 'Inverters', 1),
('INV-003', '3.5KVA/24V Solar Inverter', 'Pure sine wave inverter, 3500W capacity, 24V battery compatible', 165000.00, 'Inverters', 1),
('INV-004', '5KVA/48V Solar Inverter', 'Pure sine wave inverter, 5000W capacity, 48V battery compatible', 285000.00, 'Inverters', 1),
('INV-005', '7.5KVA/48V Solar Inverter', 'Pure sine wave inverter, 7500W capacity, 48V battery compatible', 425000.00, 'Inverters', 1),
('INV-006', '10KVA/48V Solar Inverter', 'Pure sine wave inverter, 10000W capacity, 48V battery compatible', 575000.00, 'Inverters', 1),

-- Solar Panels
('PAN-001', '100W Monocrystalline Solar Panel', 'High efficiency monocrystalline panel, 25-year warranty', 35000.00, 'Solar Panels', 1),
('PAN-002', '150W Monocrystalline Solar Panel', 'High efficiency monocrystalline panel, 25-year warranty', 48000.00, 'Solar Panels', 1),
('PAN-003', '200W Monocrystalline Solar Panel', 'High efficiency monocrystalline panel, 25-year warranty', 62000.00, 'Solar Panels', 1),
('PAN-004', '250W Monocrystalline Solar Panel', 'High efficiency monocrystalline panel, 25-year warranty', 75000.00, 'Solar Panels', 1),
('PAN-005', '300W Monocrystalline Solar Panel', 'High efficiency monocrystalline panel, 25-year warranty', 88000.00, 'Solar Panels', 1),
('PAN-006', '450W Monocrystalline Solar Panel', 'High efficiency monocrystalline panel, 25-year warranty', 125000.00, 'Solar Panels', 1),

-- Batteries
('BAT-001', '100Ah/12V Deep Cycle Battery', 'Tubular deep cycle battery, 5-year warranty', 75000.00, 'Batteries', 1),
('BAT-002', '150Ah/12V Deep Cycle Battery', 'Tubular deep cycle battery, 5-year warranty', 95000.00, 'Batteries', 1),
('BAT-003', '200Ah/12V Deep Cycle Battery', 'Tubular deep cycle battery, 5-year warranty', 125000.00, 'Batteries', 1),
('BAT-004', '220Ah/12V Deep Cycle Battery', 'Tubular deep cycle battery, 5-year warranty', 145000.00, 'Batteries', 1),
('BAT-005', '200Ah/12V Lithium Battery', 'Lithium-ion battery, 10-year warranty, lightweight', 285000.00, 'Batteries', 1),

-- Complete Systems
('SYS-001', 'Basic Home Solar System (1.5KVA)', 'Complete system: 1.5KVA inverter, 2x100W panels, 2x100Ah batteries, accessories', 450000.00, 'Complete Systems', 1),
('SYS-002', 'Standard Home Solar System (3.5KVA)', 'Complete system: 3.5KVA inverter, 4x200W panels, 4x200Ah batteries, accessories', 1250000.00, 'Complete Systems', 1),
('SYS-003', 'Premium Home Solar System (5KVA)', 'Complete system: 5KVA inverter, 6x300W panels, 8x200Ah batteries, accessories', 2150000.00, 'Complete Systems', 1),
('SYS-004', 'Commercial Solar System (10KVA)', 'Complete system: 10KVA inverter, 12x450W panels, 16x200Ah batteries, accessories', 4250000.00, 'Complete Systems', 1),

-- Accessories
('ACC-001', 'Solar Charge Controller (30A)', 'MPPT charge controller, 12/24V auto-detect', 35000.00, 'Accessories', 1),
('ACC-002', 'Solar Charge Controller (60A)', 'MPPT charge controller, 12/24/48V auto-detect', 65000.00, 'Accessories', 1),
('ACC-003', 'Solar Panel Mounting Kit', 'Complete mounting kit for roof installation, 4 panels', 25000.00, 'Accessories', 1),
('ACC-004', 'Solar Cable (Per Meter)', 'UV-resistant solar cable, 4mm² copper', 450.00, 'Accessories', 1),
('ACC-005', 'Battery Terminal Set', 'Heavy duty battery terminals and connectors', 3500.00, 'Accessories', 1),
('ACC-006', 'Change Over Switch (63A)', 'Automatic/manual change over switch', 18000.00, 'Accessories', 1),

-- Installation & Services
('SRV-001', 'Installation Service (Basic System)', 'Professional installation for 1-2KVA systems', 45000.00, 'Services', 1),
('SRV-002', 'Installation Service (Standard System)', 'Professional installation for 3-5KVA systems', 75000.00, 'Services', 1),
('SRV-003', 'Installation Service (Large System)', 'Professional installation for 7-10KVA systems', 125000.00, 'Services', 1),
('SRV-004', 'Maintenance Service (Quarterly)', 'Quarterly system check and maintenance', 25000.00, 'Services', 1),
('SRV-005', 'Inverter Repair Service', 'Inverter diagnostic and repair service', 15000.00, 'Services', 1),
('SRV-006', 'Battery Replacement Service', 'Old battery removal and new battery installation', 8000.00, 'Services', 1),
('SRV-007', 'Site Assessment & Survey', 'Professional site visit and solar system sizing', 10000.00, 'Services', 1);

-- ============================================
-- VERIFICATION
-- ============================================

SELECT 'Solar Products Updated!' AS status;

SELECT 'Products by Category' AS info, category, COUNT(*) AS count 
FROM products 
GROUP BY category 
ORDER BY category;

SELECT 'Total Active Products' AS info, COUNT(*) AS count
FROM products 
WHERE is_active = 1;
