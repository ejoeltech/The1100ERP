<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
// Authentication helper functions
require_once __DIR__ . '/passwords.php';

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        // Redirect to login page with absolute path to avoid deep nesting issues
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        // Find the root by removing known subdirectories or using a config constant if available
        // Simple heuristic: Go up until we find login.php or just use root if possible.
        // Better: Use a relative path that works from anywhere if we know depth, but absolute is safer.
        // From api/payments/delete-payment.php (depth 2), we need ../../login.php.
        // Let's use the helper we already have logic for but make it robust.

        $redirect = 'login.php';
        if (file_exists('login.php')) {
            $redirect = 'login.php';
        } elseif (file_exists('../login.php')) {
            $redirect = '../login.php';
        } elseif (file_exists('../../login.php')) {
            $redirect = '../../login.php';
        } elseif (file_exists('../../../login.php')) {
            $redirect = '../../../login.php';
        }

        // If this is an API call (AJAX/Fetch), return 401 instead of redirecting
        // This PREVENTS the "Too Many Redirects" loop when Fetch tries to follow login redirects
        if (
            (defined('IS_API') && IS_API)
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            || (strpos($_SERVER['REQUEST_URI'], '/api/') !== false)
        ) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.', 'redirect' => $redirect]);
            exit;
        }

        header('Location: ' . $redirect);
        exit;
    }
}

function getCurrentUser($pdo)
{
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function login($pdo, $username, $password)
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && verifyPassword($password, $user['password'])) {
        // Transparently re-hash if needed (consolidated Argon2id via helper)
        if (passwordNeedsRehash($user['password'])) {
            $newHash = hashPassword($password);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$newHash, $user['id']]);
        }

        completeLoginSession($pdo, $user);

        return true;
    }

    return false;
}

/**
 * Establish an authenticated session for an already-verified user
 * (used by password login and by the MFA second step alike).
 */
function completeLoginSession($pdo, $user)
{
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];

    // Set role if column exists (Phase 3A)
    if (isset($user['role'])) {
        $_SESSION['role'] = $user['role'];
    }
    unset($_SESSION['mfa_pending']);

    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Log audit trail (Phase 3A) - only if audit system is loaded
    if (function_exists('logUserLogin')) {
        logUserLogin($user['id'], $user['username']);
    }

    // Register this session for revocation support (WP4)
    if (function_exists('registerUserSession')) {
        registerUserSession($user['id']);
    }
}

function logout()
{
    // Revoke this session server-side first (WP4 session registry).
    if (function_exists('revokeCurrentSession')) {
        revokeCurrentSession();
    }
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    header('Location: ' . $protocol . '://' . $host . $base . '/login.php');
    exit;
}

/**
 * Server-side session registry (WP4 revocation).
 * Sessions are keyed by SHA-256 of the PHP session id (never stored raw).
 */
function currentSessionHash()
{
    return hash('sha256', session_id() . '|erp-session');
}

function registerUserSession($userId)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO user_sessions (session_hash, user_id, ip_address) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_seen = NOW(), ip_address = VALUES(ip_address)");
        $stmt->execute([currentSessionHash(), $userId, $_SERVER['REMOTE_ADDR'] ?? null]);
        $_SESSION['sess_registered_at'] = time();
    } catch (Exception $e) {
        // Table missing (migration pending): sessions still work, revocation waits.
    }
}

function touchUserSession()
{
    global $pdo;
    // Throttle last_seen writes to ~5 minutes.
    if (!empty($_SESSION['sess_registered_at']) && (time() - $_SESSION['sess_registered_at']) < 300) {
        return;
    }
    registerUserSession($_SESSION['user_id'] ?? 0);
}

function sessionStillValid($userId)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return true;
    }
    try {
        // Grace: users with no registered sessions yet (pre-WP4 logins) pass
        // until their next full login registers one.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
        if ((int)$stmt->fetchColumn() === 0) {
            return true;
        }
        $stmt = $pdo->prepare("SELECT 1 FROM user_sessions WHERE session_hash = ? AND user_id = ?");
        $stmt->execute([currentSessionHash(), $userId]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return true;
    }
}

function revokeOtherSessions($userId)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_hash <> ?");
        $stmt->execute([$userId, currentSessionHash()]);
    } catch (Exception $e) {
        // best effort
    }
}

function revokeAllSessions($userId)
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        // best effort
    }
}

function revokeCurrentSession()
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return;
    }
    try {
        if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_hash = ?");
            $stmt->execute([currentSessionHash()]);
        }
    } catch (Exception $e) {
        // best effort
    }
}

function getBaseUrl()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return $protocol . '://' . $host . $script . '/';
}
?>