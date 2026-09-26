<?php
session_start();
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/audit.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

requirePermission('delete_user');
require_once '../includes/security.php';
requireCsrf();

// WP4: POST only (was GET-linkable).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users/manage-users.php');
    exit;
}

$user_id = $_POST['id'] ?? null;

if (!$user_id) {
    header('Location: ../pages/users/manage-users.php');
    exit;
}

// Cannot delete yourself
if ($user_id == $_SESSION['user_id']) {
    header('Location: ../pages/users/manage-users.php?error=Cannot delete your own account');
    exit;
}

try {
    // Get user details for audit log
    $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Never delete the last super_admin (would lock out all administration)
    if ($user['role'] === 'super_admin') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'");
        if ($stmt->fetchColumn() <= 1) {
            throw new Exception('Cannot delete the last super admin');
        }
        // Only a super_admin may delete another super_admin
        if (!isSuperAdmin()) {
            throw new Exception('Only a super admin can delete a super admin');
        }
    }

    // Delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    // Log audit trail
    logUserDelete($user_id, $user['username']);

    header('Location: ../pages/users/manage-users.php?deleted=1');
    exit;

} catch (Exception $e) {
    $error = urlencode($e->getMessage());
    header("Location: ../pages/users/manage-users.php?error=$error");
    exit;
}
?>