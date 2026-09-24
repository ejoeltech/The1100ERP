<?php
// Normal bootstrap. This file must NEVER send visitors to the installer:
// on a missing config it shows a static message with no link (WP0-C).
if (!file_exists('config.php')) {
    http_response_code(503);
    die('
        <h1>Application Not Installed</h1>
        <p>No configuration was found on this server. Installation is performed
        by the server administrator following the deployment runbook.</p>
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