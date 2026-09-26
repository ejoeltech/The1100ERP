<?php
// Public bootstrap for a FIXED allow-list of intentionally-public scripts.
// Any other script including this file dies: this makes public-init.php
// impossible to include accidentally (WP3 default-deny).
$PUBLIC_ALLOW_LIST = [
    'roi-calculator.php',
    'system-designer.php',
    'calculate-roi.php',
];
$__caller = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!in_array($__caller, $PUBLIC_ALLOW_LIST, true)) {
    http_response_code(403);
    die('Forbidden: public bootstrap is not available to this script.');
}
unset($__caller);

// Initialize session without enforcing login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

// Define authentication functions for public pages (without redirects)
if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('getCurrentUser')) {
    function getCurrentUser($pdo)
    {
        if (!isLoggedIn()) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT id, username, email, full_name, role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error fetching current user: " . $e->getMessage());
            return null;
        }
    }
}

// Get current user if logged in
$current_user = getCurrentUser($pdo);

// Initialize role if user exists
if ($current_user && isset($current_user['role']) && !isset($_SESSION['role'])) {
    $_SESSION['role'] = $current_user['role'];
}

// Public-safe authentication functions (no redirects)
if (!function_exists('isAdmin')) {
    function isAdmin()
    {
        return isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'admin'], true);
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin()
    {
        // No-op for public pages - allows access without login
        return;
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission($action, $resource = null, $ownerId = null)
    {
        return false; // Public users have no permissions
    }
}
?>