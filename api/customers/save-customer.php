<?php
include '../../includes/session-check.php';
requirePermission('manage_customers');

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    die('Invalid request');

try {
    $name = trim($_POST['customer_name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($name) || empty($email))
        throw new Exception('Customer name and email required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        throw new Exception('Invalid email');

    // Check duplicate email
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch())
        throw new Exception('Email already exists');

    $stmt = $pdo->prepare("INSERT INTO customers (customer_name, company, email, phone, address, city, notes, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$name, $company, $email, $phone, $address, $city, $notes]);

    if (function_exists('logAudit')) {
        logAudit('create', 'customer', $pdo->lastInsertId(), ['customer_name' => $name]);
    }

    header('Location: ../../pages/customers/manage-customers.php?created=1');
    exit;

} catch (Exception $e) {
    header('Location: ../../pages/customers/create-customer.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>