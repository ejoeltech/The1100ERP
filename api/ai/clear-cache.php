<?php
define('IS_API', true);
require_once '../../includes/session-check.php';
require_once '../../includes/ai-rate-limiter.php';

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

try {
    AiRateLimiter::clearAllCache();
    echo json_encode(['success' => true, 'message' => 'Cache cleared successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>