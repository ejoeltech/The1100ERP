-- AI Rate Limiting & Usage Tracking Schema
-- Run this migration to add rate limiting capabilities

-- Usage logging table
CREATE TABLE IF NOT EXISTS ai_usage_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    tool_name VARCHAR(50) NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    request_hash VARCHAR(64),
    tokens_used INT DEFAULT 0,
    cost_usd DECIMAL(10,4) DEFAULT 0,
    processing_time FLOAT,
    success BOOLEAN DEFAULT TRUE,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_ip_date (ip_address, created_at),
    INDEX idx_tool_date (tool_name, created_at),
    INDEX idx_date (created_at),
    INDEX idx_hash (request_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Request caching table
CREATE TABLE IF NOT EXISTS ai_request_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_hash VARCHAR(64) UNIQUE NOT NULL,
    tool_name VARCHAR(50) NOT NULL,
    request_params TEXT NOT NULL,
    response_data MEDIUMTEXT NOT NULL,
    hit_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    INDEX idx_hash (request_hash),
    INDEX idx_tool (tool_name),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Settings
INSERT INTO settings (setting_key, setting_value, category, description) VALUES
('ai_ip_hourly_limit', '5', 'ai_limits', 'Hourly request limit for public/IP-based users'),
('ai_ip_daily_limit', '20', 'ai_limits', 'Daily request limit for public/IP-based users'),
('ai_user_hourly_limit', '10', 'ai_limits', 'Hourly request limit for authenticated users'),
('ai_user_daily_limit', '50', 'ai_limits', 'Daily request limit for authenticated users'),
('ai_monthly_budget_usd', '100', 'ai_limits', 'Monthly API budget in USD'),
('ai_enable_caching', '1', 'ai_features', 'Enable response caching'),
('ai_cache_ttl_hours', '24', 'ai_features', 'Cache time-to-live in hours'),
('ai_enable_public_access', '1', 'ai_features', 'Allow public access to AI tools'),
('ai_public_tools', 'system_designer', 'ai_features', 'Comma-separated list of public tools'),
('ai_log_retention_days', '90', 'ai_features', 'Days to retain usage logs'),
('ai_emergency_disable', '0', 'ai_features', 'Emergency disable all AI features')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Create cleanup event for old logs (runs daily at 2 AM)
CREATE EVENT IF NOT EXISTS cleanup_old_ai_logs
ON SCHEDULE EVERY 1 DAY
STARTS CONCAT(CURDATE() + INTERVAL 1 DAY, ' 02:00:00')
DO
    DELETE FROM ai_usage_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL (SELECT setting_value FROM settings WHERE setting_key = 'ai_log_retention_days') DAY);

-- Create cleanup event for expired cache (runs every hour)
CREATE EVENT IF NOT EXISTS cleanup_expired_cache
ON SCHEDULE EVERY 1 HOUR
DO
    DELETE FROM ai_request_cache WHERE expires_at < NOW();
