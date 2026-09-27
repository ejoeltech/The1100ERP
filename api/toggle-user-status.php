<?php
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

requirePermission('toggle_user_status');
require_once '../includes/security.php';

// Method first so direct navigation lands back on the page;
// CSRF is enforced on actual POSTs below.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users/manage-users.php');
    exit;
}
requireCsrf();

$user_id = $_POST['id'] ?? null;

if (!$user_id) {
    header('Location: ../pages/users/manage-users.php');
    exit;
}

// Cannot toggle your own status
if ($user_id == $_SESSION['user_id']) {
    header('Location: ../pages/users/manage-users.php?error=Cannot change your own status');
    exit;
}

try {
    // Get current status
    $stmt = $pdo->prepare("SELECT username, is_active, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Toggle status
    $new_status = $user['is_active'] ? 0 : 1;

    // Never deactivate the last active super_admin
    if ($new_status == 0 && $user['role'] === 'super_admin') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1");
        if ($stmt->fetchColumn() <= 1) {
            throw new Exception('Cannot deactivate the last active super admin');
        }
    }

    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->execute([$new_status, $user_id]);

    // WP4: deactivation revokes all of the target's sessions immediately.
    if ($new_status == 0 && function_exists('revokeAllSessions')) {
        revokeAllSessions($user_id);
    }

    // Log audit trail
    logUserStatusToggle($user_id, $user['username'], $new_status);

    header('Location: ../pages/users/manage-users.php?status_changed=1');
    exit;

} catch (Exception $e) {
    $error = urlencode($e->getMessage());
    header("Location: ../pages/users/manage-users.php?error=$error");
    exit;
}
?>