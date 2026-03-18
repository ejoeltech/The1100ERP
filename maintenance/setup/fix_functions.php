<?php
/**
 * Fix Database Script (Nuke & Pave Strategy)
 * completely recreates market_data and function to ensure consistency.
 */

header('Content-Type: text/html');
require_once '../config.php';

echo "<h1>Database Integrity Fix</h1>";
echo "<style>body{font-family:sans-serif;line-height:1.5;padding:20px;background:#f5f5f5} .box{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);margin-bottom:20px;} .ok{color:green} .err{color:red}</style>";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<div class='box'>";
    echo "<h3>1. Diagnostics</h3>";

    $stmt = $pdo->query("SELECT @@collation_connection as conn, @@collation_database as db");
    $res = $stmt->fetch();
    echo "Connection Collation: <b>" . $res['conn'] . "</b> ";
    if ($res['conn'] === 'utf8mb4_unicode_ci')
        echo "<span class='ok'>(Correct)</span><br>";
    else
        echo "<span class='err'>(Warning: Should be utf8mb4_unicode_ci)</span><br>";

    echo "Database Collation: <b>" . $res['db'] . "</b><br>";
    echo "</div>";

    echo "<div class='box'>";
    echo "<h3>2. Rebuilding 'market_data' Table</h3>";

    // DROP TABLE
    $pdo->exec("DROP TABLE IF EXISTS market_data");
    echo "Dropped 'market_data' table.<br>";

    // CREATE TABLE (Explicit)
    $sqlMarket = "
    CREATE TABLE market_data (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sqlMarket);
    echo "Created 'market_data' table with <b class='ok'>utf8mb4_unicode_ci</b>.<br>";

    // INSERT DATA
    $sqlData = "
    INSERT INTO market_data (data_type, data_key, data_value, effective_date, notes) VALUES
    ('fuel_price', 'petrol_per_litre', 650, '2026-01-01', 'Average petrol price in Nigeria'),
    ('fuel_price', 'diesel_per_litre', 850, '2026-01-01', 'Average diesel price in Nigeria'),
    ('electricity', 'nepa_per_kwh_residential', 68, '2026-01-01', 'NEPA residential tariff (average)'),
    ('electricity', 'nepa_per_kwh_commercial', 85, '2026-01-01', 'NEPA commercial tariff (average)'),
    ('generator', 'running_cost_per_hour_2.5kva', 300, '2026-01-01', 'Small generator fuel cost'),
    ('generator', 'running_cost_per_hour_5kva', 500, '2026-01-01', 'Medium generator fuel cost'),
    ('generator', 'running_cost_per_hour_10kva', 900, '2026-01-01', 'Large generator fuel cost'),
    ('generator', 'maintenance_per_month_2.5kva', 8000, '2026-01-01', 'Oil, servicing, repairs'),
    ('generator', 'maintenance_per_month_5kva', 15000, '2026-01-01', 'Oil, servicing, repairs'),
    ('generator', 'maintenance_per_month_10kva', 25000, '2026-01-01', 'Oil, servicing, repairs'),
    ('solar', 'avg_sun_hours_per_day', 5.5, '2026-01-01', 'Average sun hours in Nigeria'),
    ('solar', 'performance_degradation_annual', 0.5, '2026-01-01', 'Annual panel efficiency loss %'),
    ('inflation', 'annual_rate', 24, '2026-01-01', 'Nigeria inflation rate'),
    ('currency', 'usd_to_ngn', 1600, '2026-01-01', 'Exchange rate USD to Naira');";
    $pdo->exec($sqlData);
    echo "Inserted default market data.<br>";
    echo "</div>";

    echo "<div class='box'>";
    echo "<h3>3. Recreating Function</h3>";
    $pdo->exec("DROP FUNCTION IF EXISTS get_market_data");

    $sqlFunc = "
    CREATE FUNCTION get_market_data(
        p_data_type VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci,
        p_data_key VARCHAR(100) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
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
    END";

    $pdo->exec($sqlFunc);
    echo "Function 'get_market_data' recreated with explicit collation matching.<br>";
    echo "</div>";

    echo "<div class='box'>";
    echo "<h3>4. Test</h3>";
    $test = $pdo->query("SELECT get_market_data('fuel_price', 'petrol_per_litre') as test");
    $result = $test->fetch();
    echo "Test Result (Petrol Price): <b>₦" . $result['test'] . "</b><br>";
    if ($result['test'] > 0)
        echo "<b class='ok'>Verfication Passed!</b>";
    else
        echo "<b class='err'>Verification Failed</b>";
    echo "</div>";

    echo "<br><a href='../pages/roi-calculator.php' style='display:inline-block; padding:15px 30px; background:#007bff; color:white; text-decoration:none; border-radius:5px; font-weight:bold'>Return to ROI Calculator</a>";

} catch (PDOException $e) {
    echo "<div class='box'><strong style='color:red'>❌ Critical Error: " . $e->getMessage() . "</strong><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre></div>";
}
?>