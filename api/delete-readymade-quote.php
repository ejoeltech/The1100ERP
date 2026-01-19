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

try {
    $stmt = $pdo->prepare("UPDATE readymade_quote_templates SET is_active = 0 WHERE id = ?");
    $stmt->execute([$template_id]);

    header('Location: ../pages/readymade-quotes.php?deleted=1');
    exit;

} catch (Exception $e) {
    error_log("Delete readymade quote error: " . $e->getMessage());
    header('Location: ../pages/readymade-quotes.php?error=' . urlencode($e->getMessage()));
    exit;
}