<?php
include '../includes/session-check.php';
require_once '../includes/document-export.php';

$receipt_id = (int) ($_GET['id'] ?? 0);

if ($receipt_id <= 0) {
    http_response_code(400);
    die('Receipt ID required');
}

try {
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

    $receipt['grand_total'] = $receipt['invoice_grand_total'];

    $parent_invoice = null;
    if (!empty($receipt['invoice_id'])) {
        $stmt = $pdo->prepare("SELECT *, invoice_number as document_number FROM invoices WHERE id = ?");
        $stmt->execute([$receipt['invoice_id']]);
        $parent_invoice = $stmt->fetch();
    }

    // The receipt PDF template does not render invoice line items, so the
    // JPEG must not invent them either.
    documentServeJpeg('receipt', $receipt, [], $parent_invoice, $receipt['document_number'] . '.jpg');

} catch (Throwable $e) {
    error_log('Receipt JPEG export error: ' . $e->getMessage());
    http_response_code(500);
    die('Error generating JPEG. Check server logs.');
}
