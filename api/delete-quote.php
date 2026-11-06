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
    // WP3 IDOR: a delete permission alone must not allow deleting another
    // user's document. Admins can delete any quote; other roles only their own.
    require_once '../includes/permissions.php';
    $stmt = $pdo->prepare("SELECT id, quote_number, created_by FROM quotes WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();
    if (!$quote) {
        header('Location: ../pages/view-quotes.php?error=Quote not found');
        exit;
    }
    if (!canDeleteDocument($quote)) {
        header('Location: ../pages/view-quotes.php?error=' . urlencode('You do not have permission to delete this quote'));
        exit;
    }

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