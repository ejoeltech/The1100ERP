<?php
define('IS_API', true);
require_once '../../includes/session-check.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');

// Admin only
if ($_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    // Update settings
    $settings = [
        'ai_ip_hourly_limit' => (int) $input['ip_hourly'],
        'ai_ip_daily_limit' => (int) $input['ip_daily'],
        'ai_user_hourly_limit' => (int) $input['user_hourly'],
        'ai_user_daily_limit' => (int) $input['user_daily'],
        'ai_monthly_budget_usd' => (float) $input['monthly_budget'],
        'ai_cache_ttl_hours' => (int) $input['cache_ttl'],
        'ai_log_retention_days' => (int) $input['log_retention'],
        'ai_enable_caching' => isset($input['enable_caching']) ? '1' : '0',
        'ai_enable_public_access' => isset($input['enable_public']) ? '1' : '0',
    ];

    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }

    echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>