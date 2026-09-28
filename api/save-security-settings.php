<?php
// Save security feature toggles (super_admin only).
require_once '../includes/security.php';
configureSessionCookies();
session_start();
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

if (!function_exists('isSuperAdmin') || !isSuperAdmin()) {
    header('Location: ../pages/users/manage-users.php?error=' . urlencode('Only a super admin can change security settings'));
    exit;
}

require_once '../includes/security.php';
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users/security-settings.php');
    exit;
}

try {
    // Allow-listed keys with strict value validation (fail-closed).
    $mfa = $_POST['security_mfa_enabled'] ?? null;
    $inv = $_POST['security_invites_enabled'] ?? null;
    $min = $_POST['security_password_min'] ?? null;

    if (!in_array($mfa, ['0', '1'], true)) {
        throw new Exception('Invalid MFA setting');
    }
    if (!in_array($inv, ['0', '1'], true)) {
        throw new Exception('Invalid invite setting');
    }
    if (!in_array($min, ['8', '10', '12'], true)) {
        throw new Exception('Invalid password length setting');
    }

    setSetting('security_mfa_enabled', $mfa);
    setSetting('security_invites_enabled', $inv);
    setSetting('security_password_min', $min);

    if (function_exists('logAudit')) {
        logAudit('system_update', 'system', null, [
            'source' => 'security-settings',
            'mfa' => $mfa, 'invites' => $inv, 'password_min' => $min,
        ]);
    }

    header('Location: ../pages/users/security-settings.php?saved=1');
    exit;
} catch (Exception $e) {
    header('Location: ../pages/users/security-settings.php?error=' . urlencode($e->getMessage()));
    exit;
}
