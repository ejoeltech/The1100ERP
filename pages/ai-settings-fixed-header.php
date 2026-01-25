<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Admin only - show helpful error if not admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;text-align:center;">';
    echo '<h1>Access Denied</h1>';
    echo '<p>AI Settings requires <strong>Admin</strong> role.</p>';
    echo '<p>Your current role: <strong>' . ($_SESSION['role'] ?? 'Not set') . '</strong></p>';
    echo '<a href="../dashboard.php" style="color:blue;text-decoration:underline;">Return to Dashboard</a>';
    echo '</body></html>';
    exit;
}

require_once '../includes/ai-rate-limiter.php';

$pageTitle = 'AI Settings & Analytics - ERP System';
include '../includes/header.php';

// Get current settings
$settings = [
    'ip_hourly' => getSetting('ai_ip_hourly_limit', 5),
    'ip_daily' => getSetting('ai_ip_daily_limit', 20),
    'user_hourly' => getSetting('ai_user_hourly_limit', 10),
    'user_daily' => getSetting('ai_user_daily_limit', 50),
    'monthly_budget' => getSetting('ai_monthly_budget_usd', 100),
    'enable_caching' => getSetting('ai_enable_caching', '1') === '1',
    'cache_ttl' => getSetting('ai_cache_ttl_hours', 24),
    'enable_public' => getSetting('ai_enable_public_access', '1') === '1',
    'log_retention' => getSetting('ai_log_retention_days', 90),
    'emergency_disable' => getSetting('ai_emergency_disable', '0') === '1',
];

// Get statistics
$stats = AiRateLimiter::getStatistics(30);

// Calculate quick stats
$todayCost = 0;
$monthCost = 0;
$todayRequests = 0;

foreach ($stats['daily_costs'] as $day) {
    $monthCost += $day['cost'];
    if ($day['date'] === date('Y-m-d')) {
        $todayCost = $day['cost'];
        $todayRequests = $day['requests'];
    }
}

$cacheHitRate = $stats['cache_stats']['total_cached'] > 0
    ? round(($stats['cache_stats']['total_hits'] / $stats['cache_stats']['total_cached']) * 100, 1)
    : 0;
?>