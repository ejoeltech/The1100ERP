<?php
// Save per-user permission overrides. Deny (0) wins over group; grant (1) adds beyond group.
require_once '../includes/security.php';
configureSessionCookies();
session_start();
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/audit.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

requirePermission('manage_access');
require_once '../includes/security.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users/manage-users.php');
    exit;
}

// Method first so direct navigation lands back on the page;
// CSRF is enforced on actual POSTs below.
requireCsrf();

try {
    $user_id = $_POST['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('User is required');
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $target = $stmt->fetch();
    if (!$target) {
        throw new Exception('User not found');
    }
    if ($target['role'] === 'super_admin' && !isSuperAdmin()) {
        throw new Exception('Only a super admin can change a super admin\'s permissions');
    }

    $validKeys = getAllPermissionKeys();
    // Expect overrides[permission_key] = 'grant'|'deny'
    $overrides = $_POST['overrides'] ?? [];

    $stmt = $pdo->prepare("DELETE FROM user_permission_overrides WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $ins = $pdo->prepare("INSERT INTO user_permission_overrides (user_id, permission_key, granted, created_by) VALUES (?, ?, ?, ?)");
    $saved = 0;
    foreach ($overrides as $key => $mode) {
        if (!in_array($key, $validKeys, true)) {
            continue; // skip unknown keys silently
        }
        if ($mode !== 'grant' && $mode !== 'deny') {
            continue; // 'inherit' or empty = no row
        }
        $ins->execute([$user_id, $key, $mode === 'grant' ? 1 : 0, $_SESSION['user_id']]);
        $saved++;
    }

    if (function_exists('logUserUpdate')) {
        logUserUpdate($user_id, $target['username'], ['permission_overrides' => $saved . ' rows']);
    }
    if (function_exists('clearUserPermissionCache')) {
        clearUserPermissionCache($user_id);
    }

    header("Location: ../pages/users/user-permissions.php?id=$user_id&saved=1");
    exit;
} catch (Exception $e) {
    $uid = urlencode($_POST['user_id'] ?? '');
    $error = urlencode($e->getMessage());
    header("Location: ../pages/users/user-permissions.php?id=$uid&error=$error");
    exit;
}
