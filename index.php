<?php
// Fresh copies send the first visitor straight to the installer (the wizard
// itself is gated by the ALLOW_INSTALL claim file). Installed copies fall
// through to the normal bootstrap below.
if (!file_exists('config.php')) {
    if (is_dir('maintenance/setup')) {
        header('Location: maintenance/setup/');
        exit;
    }
    http_response_code(503);
    die('
        <h1>Application Not Installed</h1>
        <p>No configuration was found on this server. Installation is performed
        by the server administrator following the deployment runbook.</p>
    ');
}

require_once 'config.php';
require_once 'includes/security.php';
configureSessionCookies();
session_start();
require_once 'includes/auth.php';

// Redirect to dashboard if logged in, otherwise to login
if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>