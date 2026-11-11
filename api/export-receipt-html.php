<?php
include '../includes/session-check.php';
require_once '../includes/document-export.php';

$receipt_id = (int) ($_GET['id'] ?? 0);

if ($receipt_id <= 0) {
    http_response_code(400);
    die('Receipt ID required');
}

// Fetch receipt
$stmt = $pdo->prepare("
    SELECT r.*, r.receipt_number as document_number, i.invoice_title as quote_title,
           i.grand_total as invoice_grand_total, i.subtotal, i.total_vat, i.payment_terms
    FROM receipts r
    LEFT JOIN invoices i ON r.invoice_id = i.id
    WHERE r.id = ? AND r.deleted_at IS NULL
");
$stmt->execute([$receipt_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    http_response_code(404);
    die('Receipt not found');
}

// WP3 IDOR: ownership check (same rule as the PDF export + detail page).
if (function_exists('canViewDocument') && !canViewDocument($receipt)) {
    http_response_code(403);
    die('Access denied: you do not have permission to view this document');
}

// Map invoice fields to receipt fields if needed for template compatibility
$receipt['grand_total'] = $receipt['invoice_grand_total'];

// Show the parent invoice's line items (receipts carry no items of their own).
$stmt = $pdo->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY item_number");
$stmt->execute([$receipt['invoice_id']]);
$line_items = $stmt->fetchAll();

// Parent invoice details for the template (same as the PDF export).
$parent_invoice = null;
if (!empty($receipt['invoice_id'])) {
    $stmt = $pdo->prepare("SELECT *, invoice_number as document_number FROM invoices WHERE id = ?");
    $stmt->execute([$receipt['invoice_id']]);
    $parent_invoice = $stmt->fetch();
}

$html = documentHtmlForBrowser('receipt', $receipt, $line_items, $parent_invoice);

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="' . $receipt['document_number'] . '.html"');
echo $html;
