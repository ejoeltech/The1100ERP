<?php
// Authentication helper functions

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        // Redirect to login page with absolute path
        // Redirect to login page with dynamic path
        $redirect = 'login.php';
        if (!file_exists($redirect) && file_exists('../' . $redirect)) {
            $redirect = '../' . $redirect;
        } elseif (!file_exists($redirect) && file_exists('../../' . $redirect)) {
            $redirect = '../../' . $redirect;
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