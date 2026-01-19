<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$template_id = $_GET['id'] ?? null;

if (!$template_id) {
    header('Location: ../pages/readymade-quotes.php');
    exit;
}

// Fetch template and items
$stmt = $pdo->prepare("SELECT * FROM readymade_quote_templates WHERE id = ? AND is_active = 1");
$stmt->execute([$template_id]);
$template = $stmt->fetch();

if (!$template) {
    header('Location: ../pages/readymade-quotes.php?error=Template not found');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM readymade_quote_template_items WHERE template_id = ? ORDER BY item_number");
$stmt->execute([$template_id]);
$items = $stmt->fetchAll();

// Store in session for create-quote.php to use
$_SESSION['template_data'] = [
    'template_name' => $template['template_name'],
    'payment_terms' => $template['payment_terms'],
    'items' => $items
];

header('Location: ../pages/create-quote.php?from_template=1');
exit;
?>