<?php
require_once 'includes/security.php';
configureSessionCookies();
session_start();
require_once 'includes/auth.php';

logout();
?>