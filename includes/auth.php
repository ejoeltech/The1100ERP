<?php
// Authentication helper functions

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

    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];

        // Set role if column exists (Phase 3A)
        if (isset($user['role'])) {
            $_SESSION['role'] = $user['role'];
        }

        // Update last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        // Log audit trail (Phase 3A) - only if audit system is loaded
        if (function_exists('logUserLogin')) {
            logUserLogin($user['id'], $user['username']);
        }

        return true;
    }

    return false;
}

function logout()
{
    session_destroy();
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    header('Location: ' . $protocol . '://' . $host . $base . '/login.php');
    exit;
}

function getBaseUrl()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return $protocol . '://' . $host . $script . '/';
}
?>