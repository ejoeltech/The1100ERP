-- Bluedots Technologies — Leads & Follow-up module
-- Run in phpMyAdmin, or via the bundled runner: database/run-leads-migration.php
-- Plain MySQL/MariaDB. Tables are unprefixed to match the 1100erp-ai schema.

CREATE TABLE IF NOT EXISTS leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255),
    source ENUM('web','whatsapp','phone','referral','walk-in','other') NOT NULL DEFAULT 'web',
    interest TEXT,
    message TEXT,
    status ENUM('new','contacted','qualified','converted','lost') NOT NULL DEFAULT 'new',
    assigned_to VARCHAR(255),
    converted_to INT UNSIGNED DEFAULT NULL,
    followup_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_followup_at DATETIME DEFAULT NULL,
    next_followup_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_next_followup (next_followup_at),
    INDEX idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings keys seed (idempotent; safe to run repeatedly)
INSERT INTO settings (setting_key, setting_value) VALUES
    ('lead_followup_enabled', '1'),
    ('lead_followup_interval_days', '3'),
    ('lead_followup_max_attempts', '3'),
    ('lead_digest_enabled', '1'),
    ('telegram_bot_token', ''),
    ('telegram_chat_id', ''),
    ('whatsapp_verify_token', ''),
    ('whatsapp_app_secret', '')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
