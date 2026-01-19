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

requirePermission('create_user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/users/manage-users.php');
    exit;
}

try {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($username) || empty($password) || empty($full_name) || empty($role)) {
        throw new Exception('Required fields are missing');
    }

    if ($password !== $confirm_password) {
        throw new Exception('Passwords do not match');
    }

    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }

    if (!in_array($role, ['admin', 'manager', 'sales_rep'])) {
        throw new Exception('Invalid role selected');
    }

    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new Exception('Username already exists');
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, full_name, email, phone, role, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $username,
        $password_hash,
        $full_name,
        $email,
        $phone,
        $role,
        $is_active
    ]);

    $new_user_id = $pdo->lastInsertId();

    // Log audit trail
    logUserCreate($new_user_id, $username, $role);

    header('Location: ../pages/users/manage-users.php?created=1');
    exit;

} catch (Exception $e) {
    $error = urlencode($e->getMessage());
    header("Location: ../pages/users/create-user.php?error=$error");
    exit;
}
?>