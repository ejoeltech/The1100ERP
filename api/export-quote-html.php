<?php
include '../includes/session-check.php';

$quote_id = $_GET['id'] ?? null;

if (!$quote_id) {
    die('Quote ID required');
}

// Fetch quote
$stmt = $pdo->prepare("SELECT *, quote_number as document_number FROM quotes WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$quote_id]);
$quote = $stmt->fetch();

if (!$quote) {
    die('Quote not found');
}

// WP3 IDOR: ownership check (same rule as the PDF export + detail page).
if (function_exists('canViewDocument') && !canViewDocument($quote)) {
    http_response_code(403);
    die('Access denied: you do not have permission to view this document');
}

// Fetch line items
$stmt = $pdo->prepare("SELECT * FROM quote_line_items WHERE quote_id = ? ORDER BY item_number");
$stmt->execute([$quote_id]);
$line_items = $stmt->fetchAll();

// Render the same compact layout as the PDF export (single source of truth).
$html = include '../includes/pdf-template.php';
echo $html;
