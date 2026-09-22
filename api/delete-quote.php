<?php
include '../includes/session-check.php';

// Only admins can delete quotes
requirePermission('delete_quote');

$quote_id = $_GET['id'] ?? null;

if (!$quote_id) {
    header('Location: ../pages/view-quotes.php?error=No quote specified');
    exit;
}

try {
    // Soft Delete from quotes table
    $stmt = $pdo->prepare("UPDATE quotes SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$quote_id]);

    // Log audit
    if (function_exists('logDocumentDelete')) {
        logDocumentDelete('quote', $quote_id, '');
    }

    header('Location: ../pages/view-quotes.php?deleted=1');
    exit;

} catch (PDOException $e) {
    error_log("Delete error: " . $e->getMessage());
    header('Location: ../pages/view-quotes.php?error=Failed to delete quote');
    exit;
}
?>