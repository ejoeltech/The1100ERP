<?php
session_start();
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/audit.php';
require_once '../includes/invites.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

requirePermission('edit_user');
require_once '../includes/security.php';
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users/manage-users.php');
    exit;
}

$ALLOWED_ROLES = ['super_admin', 'admin', 'manager', 'sales_rep', 'accountant', 'viewer'];

try {
    $user_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $reset_password = !empty($_POST['reset_password']);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username) || empty($full_name) || empty($role)) {
        throw new Exception('Required fields are missing');
    }

    if (!in_array($role, $ALLOWED_ROLES, true)) {
        throw new Exception('Invalid role selected');
    }

    // Get current user data for audit trail
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $old_user = $stmt->fetch();
    if (!$old_user) {
        throw new Exception('User not found');
    }

    // Privilege escalation guards
    $promotingToAdmin = in_array($role, ['super_admin', 'admin'], true) && !in_array($old_user['role'], ['super_admin', 'admin'], true);
    if ($promotingToAdmin && !isSuperAdmin()) {
        throw new Exception('Only a super admin can promote users to admin level');
    }
    // Only super_admin may edit a super_admin (or change their role/group)
    if ($old_user['role'] === 'super_admin' && !isSuperAdmin()) {
        throw new Exception('Only a super admin can edit a super admin');
    }
    // Never demote/deactivate the last active super_admin
    if ($old_user['role'] === 'super_admin') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1");
        $isLastActiveSuper = $stmt->fetchColumn() <= 1 && $old_user['is_active'] == 1;
        if ($isLastActiveSuper && ($role !== 'super_admin' || $is_active == 0)) {
            throw new Exception('Cannot demote or deactivate the last active super admin');
        }
    }

    // Validate group if supplied
    if ($group_id) {
        $stmt = $pdo->prepare("SELECT id, name FROM user_groups WHERE id = ?");
        $stmt->execute([$group_id]);
        $group = $stmt->fetch();
        if (!$group) {
            throw new Exception('Invalid group selected');
        }
        if ($group['name'] === 'super_admin' && !isSuperAdmin()) {
            throw new Exception('Only a super admin can assign the super admin group');
        }
    } else {
        $group_id = $old_user['group_id'];
    }

    // Check if username exists for other users
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $user_id]);
    if ($stmt->fetch()) {
        throw new Exception('Username already exists');
    }

    // Build update query
    $update_fields = "username = ?, full_name = ?, email = ?, phone = ?, role = ?, group_id = ?, is_active = ?";
    $params = [$username, $full_name, $email, $phone, $role, $group_id, $is_active];

    // Handle password change if provided (self-service style: policy-checked).
    // Admin resets use the reset_password flag instead (invite flow below);
    // any literal password fields posted alongside are ignored for resets.
    if (!empty($password)) {
        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match');
        }
        $problems = validatePasswordPolicy($password, $old_user['password'], $username);
        if ($problems) {
            throw new Exception(implode(' ', $problems));
        }
        $update_fields .= ", password = ?, must_change_password = 0";
        $params[] = hashPassword($password);
    }

    $params[] = $user_id;

    // Update user
    $stmt = $pdo->prepare("UPDATE users SET $update_fields WHERE id = ?");
    $stmt->execute($params);

    // WP2 admin reset: unknowable password + forced-change flag + one-time invite.
    $resetInvite = null;
    if ($reset_password) {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?");
        $stmt->execute([hashPassword(bin2hex(random_bytes(32))), $user_id]);
        $resetInvite = createUserInvite($user_id, $_SESSION['user_id']);
    }

    // Log audit trail
    $changes = [];
    if ($old_user['username'] != $username)
        $changes['username'] = $username;
    if ($old_user['full_name'] != $full_name)
        $changes['full_name'] = $full_name;
    if ($old_user['role'] != $role)
        $changes['role'] = $role;
    if (($old_user['group_id'] ?? null) != $group_id)
        $changes['group_id'] = $group_id;
    if ($old_user['is_active'] != $is_active)
        $changes['status'] = $is_active ? 'activated' : 'deactivated';
    if (!empty($password))
        $changes['password'] = 'changed';
    if ($reset_password)
        $changes['password'] = 'reset via invite';

    if (!empty($changes)) {
        logUserUpdate($user_id, $username, $changes);
    }
    if (function_exists('clearUserPermissionCache')) {
        clearUserPermissionCache($user_id);
    }
    // WP4: role/group/status/password changes revoke the target's other sessions.
    if (function_exists('revokeOtherSessions')) {
        revokeOtherSessions($user_id);
    }

    $dest = '../pages/users/manage-users.php?updated=1';
    if ($resetInvite) {
        $dest .= '&reset_user_id=' . $user_id . '&invite=' . urlencode($resetInvite);
    }
    header('Location: ' . $dest);
    exit;

} catch (Exception $e) {
    $error = urlencode($e->getMessage());
    header("Location: ../pages/users/edit-user.php?id=$user_id&error=$error");
    exit;
}
?>
