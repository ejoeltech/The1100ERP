<?php
include '../includes/session-check.php';
require_once '../includes/document-export.php';

$invoice_id = (int) ($_GET['id'] ?? 0);

if ($invoice_id <= 0) {
    http_response_code(400);
    die('Invoice ID required');
}

try {
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

    $stmt = $pdo->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY item_number");
    $stmt->execute([$invoice_id]);
    $line_items = $stmt->fetchAll();

    documentServeJpeg('invoice', $invoice, $line_items, null, $invoice['document_number'] . '.jpg');

} catch (Throwable $e) {
    error_log('Invoice JPEG export error: ' . $e->getMessage());
    http_response_code(500);
    die('Error generating JPEG. Check server logs.');
}
