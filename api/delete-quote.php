<?php
include '../includes/session-check.php';

// Only privileged users can delete quotes (WP4: POST only, was GET-linkable)
requirePermission('delete_quote');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/view-quotes.php?error=Invalid request method');
    exit;
}

$quote_id = $_POST['id'] ?? null;

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