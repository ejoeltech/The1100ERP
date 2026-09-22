<?php
include '../includes/session-check.php';

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

    if (strlen($new_password) < 6) {
        throw new Exception('New password must be at least 6 characters');
    }

    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$current_user['id']]);
    $user = $stmt->fetch();

    if (!password_verify($current_password, $user['password'])) {
        throw new Exception('Current password is incorrect');
    }

    // Update password
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users 
        SET password = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$new_password_hash, $current_user['id']]);

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