<?php
include '../includes/session-check.php';
require_once '../includes/passwords.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

try {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        throw new Exception('All fields are required');
    }

    if ($new_password !== $confirm_password) {
        throw new Exception('New passwords do not match');
    }

    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$current_user['id']]);
    $user = $stmt->fetch();

    if (!verifyPassword($current_password, $user['password'])) {
        throw new Exception('Current password is incorrect');
    }

    // Policy (12+, blocklist, differs from current)
    $problems = validatePasswordPolicy($new_password, $user['password'], $current_user['username']);
    if ($problems) {
        throw new Exception(implode(' ', $problems));
    }

    // Update password and clear any forced-change flag.
    // The flag column needs the WP2 migration; retry without it on stale DBs.
    $new_password_hash = hashPassword($new_password);

    try {
        $stmt = $pdo->prepare("
            UPDATE users
            SET password = ?, must_change_password = 0, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_password_hash, $current_user['id']]);
    } catch (Exception $e) {
        error_log('Change password without must_change_password flag (WP2 migration pending): ' . $e->getMessage());
        $stmt = $pdo->prepare("
            UPDATE users
            SET password = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_password_hash, $current_user['id']]);
    }

    // Log audit
    if (function_exists('logUserUpdate')) {
        logUserUpdate($current_user['id'], $current_user['username'], ['password' => 'changed']);
    }

    header('Location: ../pages/users/change-password.php?success=1');
    exit;

} catch (Exception $e) {
    error_log("Change password error: " . $e->getMessage());
    header('Location: ../pages/users/change-password.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>