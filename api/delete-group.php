<?php
// Delete a custom group (system groups are protected). Members fall back to role-matched group.
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

requirePermission('manage_access');
require_once '../includes/security.php';
requireCsrf();

// WP4: POST only (was GET-linkable).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users/manage-groups.php');
    exit;
}

$group_id = $_POST['id'] ?? null;
if (!$group_id) {
    header('Location: ../pages/users/manage-groups.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM user_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();
    if (!$group) {
        throw new Exception('Group not found');
    }
    if ($group['is_system']) {
        throw new Exception('System groups cannot be deleted');
    }

    // Reassign members to the group matching their role (or viewer fallback)
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE group_id = ?");
    $stmt->execute([$group_id]);
    $members = $stmt->fetchAll();
    foreach ($members as $m) {
        $stmt2 = $pdo->prepare("SELECT id FROM user_groups WHERE name = ?");
        $stmt2->execute([$m['role']]);
        $fallback = $stmt2->fetch();
        $fallbackId = $fallback ? $fallback['id'] : null;
        if (!$fallbackId) {
            $stmt2 = $pdo->prepare("SELECT id FROM user_groups WHERE name = 'viewer'");
            $stmt2->execute();
            $fallback = $stmt2->fetch();
            $fallbackId = $fallback ? $fallback['id'] : null;
        }
        $upd = $pdo->prepare("UPDATE users SET group_id = ? WHERE id = ?");
        $upd->execute([$fallbackId, $m['id']]);
    }

    $stmt = $pdo->prepare("DELETE FROM user_groups WHERE id = ?");
    $stmt->execute([$group_id]);

    header('Location: ../pages/users/manage-groups.php?deleted=1');
    exit;
} catch (Exception $e) {
    $error = urlencode($e->getMessage());
    header("Location: ../pages/users/manage-groups.php?error=$error");
    exit;
}
