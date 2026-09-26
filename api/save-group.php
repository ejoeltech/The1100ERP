<?php
// Create or update a user group (custom groups allowed; system groups keep locked names)
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
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users/manage-groups.php');
    exit;
}

try {
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $primary_role = $_POST['primary_role'] ?? 'viewer';
    $permissions = $_POST['permissions'] ?? [];

    $ALLOWED_ROLES = ['super_admin', 'admin', 'manager', 'sales_rep', 'accountant', 'viewer'];
    if (empty($name)) {
        throw new Exception('Group name is required');
    }
    if (!preg_match('/^[a-zA-Z0-9_ ]+$/', $name)) {
        throw new Exception('Group name may only contain letters, numbers, spaces and underscores');
    }
    if (!in_array($primary_role, $ALLOWED_ROLES, true)) {
        throw new Exception('Invalid primary role');
    }
    if ($primary_role === 'super_admin' && !isSuperAdmin()) {
        throw new Exception('Only a super admin can create a super-admin-level group');
    }

    // Validate permission keys against catalog
    $validKeys = getAllPermissionKeys();
    foreach ($permissions as $p) {
        if (!in_array($p, $validKeys, true)) {
            throw new Exception('Invalid permission key: ' . $p);
        }
    }

    if ($group_id) {
        // Editing: system groups cannot be renamed, and only super_admin edits super_admin group
        $stmt = $pdo->prepare("SELECT * FROM user_groups WHERE id = ?");
        $stmt->execute([$group_id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            throw new Exception('Group not found');
        }
        if ($existing['is_system'] && $existing['name'] !== $name) {
            throw new Exception('System groups cannot be renamed');
        }
        if ($existing['name'] === 'super_admin' && !isSuperAdmin()) {
            throw new Exception('Only a super admin can edit the super admin group');
        }
        $stmt = $pdo->prepare("UPDATE user_groups SET name = ?, description = ?, primary_role = ? WHERE id = ?");
        $stmt->execute([$name, $description, $primary_role, $group_id]);
    } else {
        // Duplicate name check
        $stmt = $pdo->prepare("SELECT id FROM user_groups WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            throw new Exception('A group with this name already exists');
        }
        $stmt = $pdo->prepare("INSERT INTO user_groups (name, description, primary_role, is_system) VALUES (?, ?, ?, 0)");
        $stmt->execute([$name, $description, $primary_role]);
        $group_id = (int)$pdo->lastInsertId();
    }

    // Replace permission set
    $stmt = $pdo->prepare("DELETE FROM group_permissions WHERE group_id = ?");
    $stmt->execute([$group_id]);
    if (!empty($permissions)) {
        $stmt = $pdo->prepare("INSERT INTO group_permissions (group_id, permission_key) VALUES (?, ?)");
        foreach ($permissions as $p) {
            $stmt->execute([$group_id, $p]);
        }
    }

    if (function_exists('logAudit')) {
        logAudit('manage_access', 'user_group', $group_id, ['name' => $name, 'permissions' => count($permissions)]);
    }
    if (function_exists('clearUserPermissionCache')) {
        clearUserPermissionCache();
    }

    header('Location: ../pages/users/manage-groups.php?saved=1');
    exit;
} catch (Exception $e) {
    $error = urlencode($e->getMessage());
    header("Location: ../pages/users/manage-groups.php?error=$error");
    exit;
}
