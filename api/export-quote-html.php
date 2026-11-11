<?php
include '../includes/session-check.php';
require_once '../includes/document-export.php';

$quote_id = (int) ($_GET['id'] ?? 0);

if ($quote_id <= 0) {
    http_response_code(400);
    die('Quote ID required');
}

// Fetch quote with the creator's signature, matching the PDF export.
$stmt = $pdo->prepare("
    SELECT q.*, q.quote_number as document_number, u.signature_file
    FROM quotes q
    LEFT JOIN users u ON q.created_by = u.id
    WHERE q.id = ? AND q.deleted_at IS NULL
");
$stmt->execute([$quote_id]);
$quote = $stmt->fetch();

if (!$quote) {
    http_response_code(404);
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

// Same template, logo/signature, A4 geometry and T&C pages as the PDF.
$html = documentHtmlForBrowser('quote', $quote, $line_items);

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="' . $quote['document_number'] . '.html"');
echo $html;
