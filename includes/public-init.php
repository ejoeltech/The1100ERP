<?php
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
        return isset($_SESSION['role']) && strcasecmp($_SESSION['role'], 'admin') === 0;
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