<?php
include '../includes/session-check.php';
require_once '../includes/document-export.php';

$invoice_id = (int) ($_GET['id'] ?? 0);

if ($invoice_id <= 0) {
    http_response_code(400);
    die('Invoice ID required');
}

// Fetch invoice
$stmt = $pdo->prepare("
    SELECT *, invoice_number as document_number, invoice_title as quote_title, invoice_date as quote_date
    FROM invoices
    WHERE id = ? AND deleted_at IS NULL
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    die('Invoice not found');
}

// WP3 IDOR: ownership check (same rule as the PDF export + detail page).
if (function_exists('canViewDocument') && !canViewDocument($invoice)) {
    http_response_code(403);
    die('Access denied: you do not have permission to view this document');
}

// Fetch line items
$stmt = $pdo->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY item_number");
$stmt->execute([$invoice_id]);
$line_items = $stmt->fetchAll();

$html = documentHtmlForBrowser('invoice', $invoice, $line_items);

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="' . $invoice['document_number'] . '.html"');
echo $html;
