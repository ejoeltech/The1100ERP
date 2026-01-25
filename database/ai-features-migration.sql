-- ============================================
-- 1100erp AI Features Database Migration
-- Run this AFTER all previous migrations
-- ============================================

USE 1100erp;
SET FOREIGN_KEY_CHECKS=0;

-- ============================================
-- PRODUCTS CATALOG
-- ============================================

DROP TABLE IF EXISTS products;
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('inverter', 'battery', 'solar_panel', 'accessory', 'installation') NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    specifications JSON,
    unit_price DECIMAL(15,2) NOT NULL,
    cost_price DECIMAL(15,2) NOT NULL,
    stock_quantity INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    warranty_months INT DEFAULT 12,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_is_active (is_active),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SAMPLE PRODUCTS (Nigerian Market Prices - Jan 2026)
-- ============================================

-- INVERTERS
INSERT INTO products (category, name, description, specifications, unit_price, cost_price, stock_quantity, warranty_months) VALUES

('inverter', '3.5KVA Pure Sine Wave Inverter', 
 'Suitable for small homes and offices with moderate power needs',
 JSON_OBJECT(
    'capacity_kva', 3.5,
    'capacity_watts', 3500,
    'voltage', '24V',
    'efficiency', '90%',
    'waveform', 'Pure Sine Wave',
    'brand', 'Generic'
 ),
 380000, 320000, 15, 24),

('inverter', '5KVA Pure Sine Wave Inverter',
 'Ideal for medium homes with fridges, fans, TVs, and moderate AC usage',
 JSON_OBJECT(
    'capacity_kva', 5,
    'capacity_watts', 5000,
    'voltage', '48V',
    'efficiency', '92%',
    'waveform', 'Pure Sine Wave',
    'brand', 'Generic'
 ),
 520000, 440000, 12, 24),

('inverter', '7.5KVA Pure Sine Wave Inverter',
 'For larger homes with multiple air conditioners and heavy appliances',
 JSON_OBJECT(
    'capacity_kva', 7.5,
    'capacity_watts', 7500,
    'voltage', '48V',
    'efficiency', '93%',
    'waveform', 'Pure Sine Wave',
    'brand', 'Generic'
 ),
 720000, 610000, 8, 24),

('inverter', '10KVA Pure Sine Wave Inverter',
 'Commercial grade for offices, shops, and large residential properties',
 JSON_OBJECT(
    'capacity_kva', 10,
    'capacity_watts', 10000,
    'voltage', '48V',
    'efficiency', '94%',
    'waveform', 'Pure Sine Wave',
    'brand', 'Generic'
 ),
 950000, 800000, 5, 24),

('inverter', '15KVA Three Phase Inverter',
 'Heavy-duty commercial inverter for factories and large businesses',
 JSON_OBJECT(
    'capacity_kva', 15,
    'capacity_watts', 15000,
    'voltage', '3-Phase 48V',
    'efficiency', '95%',
    'waveform', 'Pure Sine Wave',
    'brand', 'Generic'
 ),
 1450000, 1220000, 3, 36);

-- BATTERIES
INSERT INTO products (category, name, description, specifications, unit_price, cost_price, stock_quantity, warranty_months) VALUES

('battery', '200Ah Deep Cycle Battery (12V)',
 'Tubular battery with 5-year lifespan, suitable for medium backup requirements',
 JSON_OBJECT(
    'capacity_ah', 200,
    'voltage', 12,
    'type', 'Tubular Lead-Acid',
    'warranty_years', 5,
    'cycle_life', 1500,
    'weight_kg', 65
 ),
 185000, 155000, 30, 60),

('battery', '220Ah Deep Cycle Battery (12V)',
 'Premium tubular battery with extended capacity and reliability',
 JSON_OBJECT(
    'capacity_ah', 220,
    'voltage', 12,
    'type', 'Tubular Lead-Acid',
    'warranty_years', 5,
    'cycle_life', 1800,
    'weight_kg', 70
 ),
 205000, 172000, 25, 60),

('battery', '250Ah Lithium Battery (12V)',
 'Long-lasting lithium-ion battery with 10-year lifespan and faster charging',
 JSON_OBJECT(
    'capacity_ah', 250,
    'voltage', 12,
    'type', 'Lithium-Ion LiFePO4',
    'warranty_years', 10,
    'cycle_life', 5000,
    'weight_kg', 28
 ),
 450000, 380000, 10, 120),

('battery', '100Ah Lithium Battery (12V)',
 'Compact lithium battery for small to medium systems',
 JSON_OBJECT(
    'capacity_ah', 100,
    'voltage', 12,
    'type', 'Lithium-Ion LiFePO4',
    'warranty_years', 10,
    'cycle_life', 5000,
    'weight_kg', 12
 ),
 220000, 185000, 15, 120);

-- SOLAR PANELS
INSERT INTO products (category, name, description, specifications, unit_price, cost_price, stock_quantity, warranty_months) VALUES

('solar_panel', '400W Monocrystalline Solar Panel',
 'High efficiency solar panel with 25-year performance warranty',
 JSON_OBJECT(
    'capacity_watts', 400,
    'type', 'Monocrystalline',
    'efficiency', '21%',
    'warranty_years', 25,
    'dimensions', '2000x1000x40mm',
    'weight_kg', 22
 ),
 95000, 80000, 40, 300),

('solar_panel', '450W Monocrystalline Solar Panel',
 'Premium efficiency panel for maximum power generation',
 JSON_OBJECT(
    'capacity_watts', 450,
    'type', 'Monocrystalline',
    'efficiency', '22%',
    'warranty_years', 25,
    'dimensions', '2100x1050x40mm',
    'weight_kg', 24
 ),
 105000, 88000, 35, 300),

('solar_panel', '550W Monocrystalline Solar Panel',
 'Maximum power output for commercial and large residential systems',
 JSON_OBJECT(
    'capacity_watts', 550,
    'type', 'Monocrystalline',
    'efficiency', '23%',
    'warranty_years', 25,
    'dimensions', '2300x1100x45mm',
    'weight_kg', 28
 ),
 130000, 110000, 20, 300);

-- ACCESSORIES
INSERT INTO products (category, name, description, specifications, unit_price, cost_price, stock_quantity, warranty_months) VALUES

('accessory', 'Solar Charge Controller (60A MPPT)',
 'Maximum Power Point Tracking controller for optimal solar charging',
 JSON_OBJECT(
    'capacity_amps', 60,
    'type', 'MPPT',
    'voltage', '12V/24V/48V Auto',
    'efficiency', '98%'
 ),
 85000, 72000, 15, 24),

('accessory', 'Solar Charge Controller (80A MPPT)',
 'High capacity MPPT controller for large solar arrays',
 JSON_OBJECT(
    'capacity_amps', 80,
    'type', 'MPPT',
    'voltage', '12V/24V/48V Auto',
    'efficiency', '98%'
 ),
 115000, 97000, 12, 24),

('accessory', 'Installation Kit (Complete)',
 'Cables, breakers, mounting hardware, and connectors for professional installation',
 JSON_OBJECT(
    'contents', 'MC4 Connectors, DC Cables, AC Cables, Circuit Breakers, Mounting Rails, Fuses'
 ),
 95000, 75000, 20, 12),

('accessory', 'Surge Protector (Industrial 20KA)',
 'Protect your investment from power surges and lightning',
 JSON_OBJECT(
    'rating', '20KA',
    'warranty_years', 3,
    'type', 'Type 1+2 Combined'
 ),
 45000, 38000, 25, 36),

('accessory', 'Change-Over Switch (Automatic 63A)',
 'Automatic switching between grid, inverter, and generator',
 JSON_OBJECT(
    'capacity_amps', 63,
    'type', 'Automatic Transfer Switch',
    'poles', 4
 ),
 65000, 55000, 18, 24),

('accessory', 'Battery Terminal Cables (Set of 4)',
 'Heavy-duty cables for connecting batteries in series/parallel',
 JSON_OBJECT(
    'gauge', '35mm²',
    'length', '500mm',
    'quantity', 4
 ),
 12000, 9000, 40, 12);

-- INSTALLATION SERVICES
INSERT INTO products (category, name, description, specifications, unit_price, cost_price, stock_quantity, warranty_months) VALUES

('installation', 'Standard Installation Service',
 'Professional installation for inverter and battery systems',
 JSON_OBJECT(
    'includes', 'Labor, Wiring, Testing, Configuration',
    'duration', '1 day',
    'warranty', '12 months'
 ),
 75000, 50000, 999, 12),

('installation', 'Solar Panel Installation Service',
 'Roof mounting and electrical connection of solar panels',
 JSON_OBJECT(
    'includes', 'Mounting, Wiring, Testing, Waterproofing',
    'duration', '2-3 days',
    'warranty', '12 months'
 ),
 150000, 100000, 999, 12),

('installation', 'Complete System Installation',
 'Full installation of inverter, batteries, solar panels, and all accessories',
 JSON_OBJECT(
    'includes', 'Complete System Setup, Testing, Training',
    'duration', '3-5 days',
    'warranty', '24 months'
 ),
 250000, 175000, 999, 24);

-- ============================================
-- AI RECOMMENDATIONS LOG
-- ============================================

CREATE TABLE IF NOT EXISTS ai_recommendations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    customer_name VARCHAR(255),
    customer_description TEXT NOT NULL,
    appliances_json JSON,
    power_analysis JSON,
    recommended_system JSON NOT NULL,
    roi_analysis JSON NOT NULL,
    quote_id INT UNSIGNED,
    created_quote TINYINT(1) DEFAULT 0,
    model_used VARCHAR(50) DEFAULT 'groq-llama-3.1-70b',
    processing_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_customer_name (customer_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- NIGERIAN MARKET DATA
-- ============================================

CREATE TABLE IF NOT EXISTS market_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_type VARCHAR(50) NOT NULL,
    data_key VARCHAR(100) NOT NULL,
    data_value DECIMAL(15,2) NOT NULL,
    effective_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_data (data_type, data_key, effective_date),
    INDEX idx_data_type (data_type),
    INDEX idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert current Nigerian market data
INSERT INTO market_data (data_type, data_key, data_value, effective_date, notes) VALUES

-- Fuel Prices
('fuel_price', 'petrol_per_litre', 650, '2026-01-01', 'Average petrol price in Nigeria'),
('fuel_price', 'diesel_per_litre', 850, '2026-01-01', 'Average diesel price in Nigeria'),

-- Electricity Rates
('electricity', 'nepa_per_kwh_residential', 68, '2026-01-01', 'NEPA residential tariff (average)'),
('electricity', 'nepa_per_kwh_commercial', 85, '2026-01-01', 'NEPA commercial tariff (average)'),

-- Generator Running Costs
('generator', 'running_cost_per_hour_2.5kva', 300, '2026-01-01', 'Small generator fuel cost'),
('generator', 'running_cost_per_hour_5kva', 500, '2026-01-01', 'Medium generator fuel cost'),
('generator', 'running_cost_per_hour_10kva', 900, '2026-01-01', 'Large generator fuel cost'),

-- Generator Maintenance
('generator', 'maintenance_per_month_2.5kva', 8000, '2026-01-01', 'Oil, servicing, repairs'),
('generator', 'maintenance_per_month_5kva', 15000, '2026-01-01', 'Oil, servicing, repairs'),
('generator', 'maintenance_per_month_10kva', 25000, '2026-01-01', 'Oil, servicing, repairs'),

-- Solar Performance
('solar', 'avg_sun_hours_per_day', 5.5, '2026-01-01', 'Average sun hours in Nigeria'),
('solar', 'performance_degradation_annual', 0.5, '2026-01-01', 'Annual panel efficiency loss %'),

-- Economic Indicators
('inflation', 'annual_rate', 24, '2026-01-01', 'Nigeria inflation rate'),
('currency', 'usd_to_ngn', 1600, '2026-01-01', 'Exchange rate USD to Naira');

-- ============================================
-- HELPER FUNCTIONS
-- ============================================

-- Get current market data
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS get_market_data(
    p_data_type VARCHAR(50),
    p_data_key VARCHAR(100)
) RETURNS DECIMAL(15,2)
DETERMINISTIC
BEGIN
    DECLARE v_value DECIMAL(15,2);
    
    SELECT data_value INTO v_value
    FROM market_data
    WHERE data_type = p_data_type
    AND data_key = p_data_key
    AND effective_date <= CURDATE()
    ORDER BY effective_date DESC
    LIMIT 1;
    
    RETURN COALESCE(v_value, 0);
END$$
DELIMITER ;
SET FOREIGN_KEY_CHECKS=1;
