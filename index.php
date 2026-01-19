<?php
// Check if installation is required
if (!file_exists('config.php') && !file_exists('setup/lock')) {
    header('Location: setup/index.php');
    exit;
}

// Check if system is installed but config is missing (corrupted)
if (!file_exists('config.php') && file_exists('setup/lock')) {
    die('
        <h1>Configuration Error</h1>
        <p>The config.php file is missing. Please restore it from backup or reinstall.</p>
        <p>To reinstall, delete: <code>setup/lock</code> and refresh this page.</p>
    ');
}

session_start();
require_once 'config.php';
require_once 'includes/auth.php';

// Redirect to dashboard if logged in, otherwise to login
if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>