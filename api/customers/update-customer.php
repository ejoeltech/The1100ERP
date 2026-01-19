<?php
include '../../includes/session-check.php';
requirePermission('manage_customers');

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    die('Invalid request');

try {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['customer_name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$id)
        throw new Exception('Invalid customer ID');
    if (empty($name) || empty($email))
        throw new Exception('Customer name and email required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        throw new Exception('Invalid email');

    // Check duplicate email (exclude current)
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch())
        throw new Exception('Email already exists');

    $stmt = $pdo->prepare("UPDATE customers SET customer_name=?, company=?, email=?, phone=?, address=?, city=?, notes=? WHERE id=?");
    $stmt->execute([$name, $company, $email, $phone, $address, $city, $notes, $id]);

    if (function_exists('logAudit')) {
        logAudit('update', 'customer', $id, ['customer_name' => $name]);
    }

    header('Location: ../../pages/customers/manage-customers.php?updated=1');
    exit;

} catch (Exception $e) {
    $id = $_POST['id'] ?? '';
    header('Location: ../../pages/customers/edit-customer.php?id=' . $id . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>