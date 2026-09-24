USE 1100erp;

-- Disable FK checks to avoid issues during conversion
SET FOREIGN_KEY_CHECKS=0;

-- Convert tables to utf8mb4_unicode_ci
ALTER TABLE market_data CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE ai_recommendations CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE proposals CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Recreate the function to ensure it picks up the right collation context or defines it explicitly
DROP FUNCTION IF EXISTS get_market_data;

DELIMITER $$
CREATE FUNCTION get_market_data(
    p_data_type VARCHAR(50),
    p_data_key VARCHAR(100)
) RETURNS DECIMAL(15,2)
DETERMINISTIC
BEGIN
    DECLARE v_value DECIMAL(15,2);
    
    SELECT data_value INTO v_value
    FROM market_data
    WHERE data_type = p_data_type COLLATE utf8mb4_unicode_ci
    AND data_key = p_data_key COLLATE utf8mb4_unicode_ci
    AND effective_date <= CURDATE()
    ORDER BY effective_date DESC
    LIMIT 1;
    
    RETURN COALESCE(v_value, 0);
END$$
DELIMITER ;

SET FOREIGN_KEY_CHECKS=1;
