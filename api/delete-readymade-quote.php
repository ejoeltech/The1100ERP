<?php
require_once '../includes/security.php';
configureSessionCookies();
session_start();
require_once '../config.php';
require_once '../includes/permissions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

requirePermission('delete_document');
require_once '../includes/security.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/readymade-quotes.php');
    exit;
}

// Method first so direct navigation lands back on the page;
// CSRF is enforced on actual POSTs below.
requireCsrf();

$template_id = $_POST['id'] ?? null;

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
    header('Location: ../pages/readymade-quotes.php?error=Failed to delete template');
    exit;
}
