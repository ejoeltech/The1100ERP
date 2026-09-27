<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
// Include at top of every protected page
require_once __DIR__ . '/security.php';
configureSessionCookies();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/totp.php';

requireLogin();

// WP4 default-deny CSRF: every POST through the authed bootstrap must carry
// a valid token (form field or X-CSRF-TOKEN header injected by helpers.js).
// Pre-auth flows (login, signup, invites, webhooks) don't use this bootstrap.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireCsrf();
}

// Get current user
$current_user = getCurrentUser($pdo);

// If session is valid but user not found in DB (e.g. after restore), force logout
if (!$current_user) {
    logout();
}

// WP4 session lifetime: idle 30 min, absolute 8 h. Timestamps are refreshed
// on each request; expiry destroys the session (API callers get 401/403).
// Exempt: the installer wizard manages its own lifetime (claim file +
// admin password gates); a slow manual install must not die mid-wizard.
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
$isInstaller = strpos($reqUri, '/maintenance/setup/') !== false;
$now = time();
if (!isset($_SESSION['sess_created_at'])) {
    $_SESSION['sess_created_at'] = $now;
}
if (!isset($_SESSION['sess_last_activity'])) {
    $_SESSION['sess_last_activity'] = $now;
}
if (!$isInstaller && (($now - $_SESSION['sess_last_activity']) > 1800 || ($now - $_SESSION['sess_created_at']) > 28800)) {
    logout();
}
$_SESSION['sess_last_activity'] = $now;

// WP4 session registry: revoke sessions killed by password/role change.
// Exempt: wizard sessions are never registered (no login event), so a strict
// check could kill a live install after the operator logs in elsewhere.
if (!$isInstaller && function_exists('sessionStillValid') && !sessionStillValid($current_user['id'])) {
    logout();
}
if (function_exists('touchUserSession')) {
    touchUserSession();
}

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

    // Always refresh role from DB so demotions/promotions take effect immediately
    // (a cached session role must never outlive the database record)
    if ($current_user && isset($current_user['role'])) {
        $_SESSION['role'] = $current_user['role'];
    }

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
}

// Fallback functions (fail-CLOSED when the permission system is unavailable —
// WP3/WP4: these previously returned true, making every check pass on old DBs).
if (!function_exists('getRoleFilter')) {
    function getRoleFilter($tableName = 'd')
    {
        return ['sql' => ' AND 1=0', 'params' => []];
    }
}
if (!function_exists('hasPermission')) {
    function hasPermission($action, $resource = null, $ownerId = null)
    {
        return false;
    }
}
if (!function_exists('requirePermission')) {
    function requirePermission($action, $resource = null, $ownerId = null)
    {
        http_response_code(403);
        die('Access Denied');
    }
}
if (!function_exists('isAdmin')) {
    function isAdmin()
    {
        return false;
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

// WP4 mandatory MFA: privileged roles without an enrolled authenticator are
// confined to the enrollment page until they enroll (API/CLI/installer paths
// are never redirected).
if (function_exists('mfaRequiredForUser') && function_exists('mfaIsEnabled')) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $isApiLike = (defined('IS_API') && IS_API)
        || strpos($uri, '/api/') !== false
        || strpos($uri, '/maintenance/') !== false
        || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    $mfaFreePages = ['security-mfa.php', 'logout.php', 'change-password.php'];
    if (!$isApiLike && !in_array($script, $mfaFreePages, true) && isset($current_user['id'])) {
        try {
            if (mfaRequiredForUser($current_user['id']) && !mfaIsEnabled($current_user['id'])) {
                // Depth-aware path to the enrollment page (same discovery order
                // as requirePermission()).
                $mfaBase = '.';
                if (file_exists('../config.php')) {
                    $mfaBase = '..';
                } elseif (file_exists('../../config.php')) {
                    $mfaBase = '../..';
                } elseif (file_exists('../../../config.php')) {
                    $mfaBase = '../../..';
                }
                header('Location: ' . $mfaBase . '/pages/users/security-mfa.php?enforce=1');
                exit;
            }
        } catch (Exception $e) {
            // MFA tables missing (migration pending): do not lock anyone out.
        }
    }
}
?>