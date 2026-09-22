<?php
// Check if installation is required
if (!file_exists('config.php') && !file_exists('maintenance/setup/lock')) {
    if (is_dir('maintenance/setup')) {
        header('Location: maintenance/setup/index.php');
        exit;
    }
    die('
        <h1>Installer Not Present</h1>
        <p>No configuration found and the installer was removed after setup (expected).</p>
        <p>To reinstall, re-upload the <code>maintenance/setup/</code> folder from your deployment package, then refresh this page.</p>
    ');
}

// Check if system is installed but config is missing (corrupted)
if (!file_exists('config.php') && file_exists('maintenance/setup/lock')) {
    die('
        <h1>Configuration Error</h1>
        <p>The config.php file is missing. Please restore it from backup or reinstall.</p>
        <p>To reinstall, delete: <code>maintenance/setup/lock</code> and refresh this page.</p>
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