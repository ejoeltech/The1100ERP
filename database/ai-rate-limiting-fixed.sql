-- AI Rate Limiting & Usage Tracking Schema (Fixed)
-- Run this migration to add rate limiting capabilities

-- Usage logging table (removed foreign key constraint causing issues)
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
    INDEX idx_hash (request_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Request caching table (fixed expires_at to allow NULL)
CREATE TABLE IF NOT EXISTS ai_request_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_hash VARCHAR(64) UNIQUE NOT NULL,
    tool_name VARCHAR(50) NOT NULL,
    request_params TEXT NOT NULL,
    response_data MEDIUMTEXT NOT NULL,
    hit_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_hash (request_hash),
    INDEX idx_tool (tool_name),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Settings (removed category and description columns if they don't exist)
INSERT INTO settings (setting_key, setting_value) VALUES
('ai_ip_hourly_limit', '5'),
('ai_ip_daily_limit', '20'),
('ai_user_hourly_limit', '10'),
('ai_user_daily_limit', '50'),
('ai_monthly_budget_usd', '100'),
('ai_enable_caching', '1'),
('ai_cache_ttl_hours', '24'),
('ai_enable_public_access', '1'),
('ai_public_tools', 'system_designer'),
('ai_log_retention_days', '90'),
('ai_emergency_disable', '0')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
