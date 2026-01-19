<?php
// Include at top of every protected page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

requireLogin();

// Get current user
$current_user = getCurrentUser($pdo);

// Phase 3A features - Only load if migration is complete
// Check if role column exists before enabling
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $roleColumnExists = $stmt->fetch() !== false;
} catch (Exception $e) {
    $roleColumnExists = false;
}

if ($roleColumnExists && file_exists(__DIR__ . '/permissions.php')) {
    // Phase 3A is active
    require_once __DIR__ . '/permissions.php';

    // Set role in session
    if ($current_user && isset($current_user['role']) && !isset($_SESSION['role'])) {
        $_SESSION['role'] = $current_user['role'];
    }

    // DISABLED: audit.php causes error 500 - keeping it disabled for now
    // The fallback stub functions below will handle audit calls
    /*
    if (file_exists(__DIR__ . '/audit.php')) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'audit_log'");
            if ($stmt->fetch()) {
                require_once __DIR__ . '/audit.php';
            }
        } catch (Exception $e) {
            // Audit table doesn't exist yet
        }
    }
    */
}

// Fallback functions (Always available if not defined above)
if (!function_exists('getRoleFilter')) {
    function getRoleFilter($tableName = 'd')
    {
        return '';
    }
}
if (!function_exists('hasPermission')) {
    function hasPermission($action, $resource = null, $ownerId = null)
    {
        return true;
    }
}
if (!function_exists('requirePermission')) {
    function requirePermission($action, $resource = null, $ownerId = null)
    {
        return true;
    }
}
if (!function_exists('isAdmin')) {
    function isAdmin()
    {
        return true;
    }
}
if (!function_exists('getRoleBadge')) {
    function getRoleBadge($role)
    {
        return '';
    }
}
if (!function_exists('logAudit')) {
    function logAudit($action, $resourceType, $resourceId = null, $details = [])
    {
        return;
    }
}
if (!function_exists('logUserLogin')) {
    function logUserLogin($userId, $username)
    {
        return;
    }
}
?>