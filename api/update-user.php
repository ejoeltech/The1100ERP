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

requirePermission('edit_user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users/manage-users.php');
    exit;
}

try {
    $user_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username) || empty($full_name) || empty($role)) {
        throw new Exception('Required fields are missing');
    }

    if (!in_array($role, ['admin', 'manager', 'sales_rep'])) {
        throw new Exception('Invalid role selected');
    }

    // Check if username exists for other users
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $user_id]);
    if ($stmt->fetch()) {
        throw new Exception('Username already exists');
    }

    // Get current user data for audit trail
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $old_user = $stmt->fetch();

    // Build update query
    $update_fields = "username = ?, full_name = ?, email = ?, phone = ?, role = ?, is_active = ?";
    $params = [$username, $full_name, $email, $phone, $role, $is_active];

    // Handle password change if provided
    if (!empty($password)) {
        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match');
        }
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters');
        }
        $update_fields .= ", password = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    $params[] = $user_id;

    // Update user
    $stmt = $pdo->prepare("UPDATE users SET $update_fields WHERE id = ?");
    $stmt->execute($params);

    // Log audit trail
    $changes = [];
    if ($old_user['username'] != $username)
        $changes['username'] = $username;
    if ($old_user['full_name'] != $full_name)
        $changes['full_name'] = $full_name;
    if ($old_user['role'] != $role)
        $changes['role'] = $role;
    if ($old_user['is_active'] != $is_active)
        $changes['status'] = $is_active ? 'activated' : 'deactivated';
    if (!empty($password))
        $changes['password'] = 'changed';

    if (!empty($changes)) {
        logUserUpdate($user_id, $username, $changes);
    }

    header('Location: ../pages/users/manage-users.php?updated=1');
    exit;

} catch (Exception $e) {
    $error = urlencode($e->getMessage());
    header("Location: ../pages/users/edit-user.php?id=$user_id&error=$error");
    exit;
}
?>