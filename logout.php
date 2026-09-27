<?php
require_once 'includes/security.php';
configureSessionCookies();
session_start();
// DB handle so logout revokes the server-side session and audits the event
// (auth.php guards when absent, but normally config.php is present).
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
require_once 'includes/auth.php';
require_once 'includes/audit.php';

logout();
?>