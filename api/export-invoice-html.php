<?php
include '../includes/session-check.php';

$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
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

// Render the same compact layout as the PDF export (single source of truth).
$html = include '../includes/invoice-pdf-template.php';
echo $html;
