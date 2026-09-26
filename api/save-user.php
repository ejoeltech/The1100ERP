<?php
require_once '../includes/security.php';
configureSessionCookies();
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

requirePermission('create_user');
require_once '../includes/security.php';
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users/manage-users.php');
    exit;
}

$ALLOWED_ROLES = ['super_admin', 'admin', 'manager', 'sales_rep', 'accountant', 'viewer'];

try {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation (WP2: no admin-chosen password — the user gets a one-time invite)
    if (empty($username) || empty($full_name) || empty($role)) {
        throw new Exception('Required fields are missing');
    }

    if (!in_array($role, $ALLOWED_ROLES, true)) {
        throw new Exception('Invalid role selected');
    }

    // Bootstrap: a system with no active super_admin has no one who can
    // create one, so the next user created IS a super_admin (self-closing:
    // normal guards apply again once one exists).
    $bootstrapSuper = !systemHasSuperAdmin();
    if ($bootstrapSuper) {
        $role = 'super_admin';
    }

    // Privilege escalation guard: only a super_admin may create super_admin/admin users
    if (in_array($role, ['super_admin', 'admin'], true) && !isSuperAdmin() && !$bootstrapSuper) {
        throw new Exception('Only a super admin can create admin-level users');
    }

    // Validate group if supplied
    if ($group_id) {
        $stmt = $pdo->prepare("SELECT id, name, primary_role FROM user_groups WHERE id = ?");
        $stmt->execute([$group_id]);
        $group = $stmt->fetch();
        if (!$group) {
            throw new Exception('Invalid group selected');
        }
        // Only super_admin may put users in the super_admin group (bootstrap excepted)
        if ($group['name'] === 'super_admin' && !isSuperAdmin() && !$bootstrapSuper) {
            throw new Exception('Only a super admin can assign the super admin group');
        }
        // The developer group is super-admin managed, always
        if ($group['name'] === 'developer' && !isSuperAdmin()) {
            throw new Exception('Only a super admin can assign the developer group');
        }
        if ($bootstrapSuper && $group['name'] !== 'super_admin') {
            // Bootstrap users belong in the super_admin group, not a picked one
            $group = null;
            $group_id = null;
        }
    }
    if (!$group_id) {
        // Default group = matching role name
        $stmt = $pdo->prepare("SELECT id FROM user_groups WHERE name = ?");
        $stmt->execute([$role]);
        $row = $stmt->fetch();
        $group_id = $row ? (int)$row['id'] : null;
    }

    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new Exception('Username already exists');
    }

    // WP2: unknowable initial password + one-time invite (shown once below).
    $created = createUserWithInvite($username, $full_name, $email, $phone, $role, $group_id, $is_active, $_SESSION['user_id']);
    $new_user_id = $created['user_id'];

    // Log audit trail (invite token itself is never logged)
    logUserCreate($new_user_id, $username, $role);

    header('Location: ../pages/users/manage-users.php?created=1&new_user_id=' . $new_user_id . '&invite=' . urlencode($created['invite_token']));
    exit;

} catch (Exception $e) {
    $error = urlencode($e->getMessage());
    header("Location: ../pages/users/create-user.php?error=$error");
    exit;
}
?>
