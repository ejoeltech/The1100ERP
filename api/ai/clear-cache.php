<?php
define('IS_API', true);
require_once '../../includes/session-check.php';
require_once '../../includes/ai-rate-limiter.php';

header('Content-Type: application/json');

// WP3: manage_settings instead of a role-string check (the old 'Admin'
// comparison never matched lowercase roles, leaving this open to any login).
requirePermission('manage_settings');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    AiRateLimiter::clearAllCache();
    echo json_encode(['success' => true, 'message' => 'Cache cleared successfully']);
} catch (Exception $e) {
    error_log('AI clear-cache error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Cache clear failed. Check server logs.']);
}
?>