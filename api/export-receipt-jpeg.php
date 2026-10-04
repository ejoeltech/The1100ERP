<?php
include '../includes/session-check.php';

$receipt_id = $_GET['id'] ?? null;

if (!$receipt_id) {
    die('Receipt ID required');
}

try {
    // Fetch receipt (alias columns for template compatibility)
    $stmt = $pdo->prepare("
        SELECT r.*, r.receipt_number as document_number, i.invoice_title as quote_title, 
               r.payment_date as quote_date, i.grand_total as invoice_grand_total
        FROM receipts r
        LEFT JOIN invoices i ON r.invoice_id = i.id
        WHERE r.id = ? AND r.deleted_at IS NULL
    ");
    $stmt->execute([$receipt_id]);
    $receipt = $stmt->fetch();

    // Map invoice grand_total to receipt for template
    if ($receipt) {
        $receipt['grand_total'] = $receipt['invoice_grand_total'];
    }

    if (!$receipt) {
        die('Receipt not found');
    }

    // WP3 IDOR: ownership check (same rule as the PDF export + detail page).
    if (function_exists('canViewDocument') && !canViewDocument($receipt)) {
        http_response_code(403);
        die('Access denied: you do not have permission to view this document');
    }

    // Parent invoice for template
    $parent_invoice = null;
    if ($receipt['invoice_id']) {
        $stmt = $pdo->prepare("SELECT *, invoice_number as document_number FROM invoices WHERE id = ?");
        $stmt->execute([$receipt['invoice_id']]);
        $parent_invoice = $stmt->fetch();
    }

    // Try Imagick first: renders the actual PDF (exact screenshot).
    if (class_exists('Imagick')) {
        require_once '../vendor/autoload.php';

        ob_start();
        $html = include '../includes/receipt-pdf-template.php';
        ob_end_clean();

        $mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 15, 'margin_bottom' => 15]);
        $mpdf->WriteHTML($html);

        $tempPdfFile = sys_get_temp_dir() . '/receipt_' . $receipt_id . '_' . time() . '.pdf';
        $mpdf->Output($tempPdfFile, 'F');

        $imagick = new Imagick();
        $imagick->setResolution(300, 300);
        $imagick->readImage($tempPdfFile . '[0]');
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(95);
        $imagick->setImageBackgroundColor('white');
        $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename="' . $receipt['document_number'] . '.jpg"');
        echo $imagick->getImageBlob();

        $imagick->clear();
        $imagick->destroy();
        @unlink($tempPdfFile);
        exit;
    }

    // GD fallback: shared renderer mirroring the PDF template sections
    // (all items, dynamic height) — same visual language as the PDF.
    require_once '../includes/jpeg-renderer.php';

    // Receipts carry no items of their own: show the parent invoice's items.
    $stmt = $pdo->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY item_number");
    $stmt->execute([$receipt['invoice_id']]);
    $invoice_items = $stmt->fetchAll();
    $items = [];
    foreach ($invoice_items as $item) {
        $items[] = [
            'qty' => $item['quantity'],
            'desc' => $item['description'],
            'unit' => formatNaira($item['unit_price']),
            'total' => formatNaira($item['line_total']),
        ];
    }
    $balance = $parent_invoice ? $parent_invoice['balance_due'] : null;
    renderDocumentJpeg([
        'title' => 'RECEIPT',
        'subtitle' => ($receipt['quote_title'] ?? '') . ' — Payment Received',
        'contact' => 'Contact Address: ' . COMPANY_ADDRESS . ' Phone: ' . COMPANY_PHONE . ' | Email: ' . COMPANY_EMAIL . ' | Website: ' . COMPANY_WEBSITE,
        'info_left_label' => 'Received From:',
        'customer' => $receipt['customer_name'],
        'info_rows' => [
            ['Receipt Number:', $receipt['document_number']],
            ['Date:', date('d/m/Y', strtotime($receipt['quote_date'] ?? $receipt['created_at'] ?? 'now'))],
            ['Invoice:', $parent_invoice ? $parent_invoice['document_number'] : '-'],
        ],
        'meta_heads' => ['Payment Method', 'Amount Paid', 'Reference', 'Balance'],
        'meta_vals' => [
            $receipt['payment_method'] ?? '-',
            formatNaira($receipt['amount_paid']),
            $receipt['reference_number'] ?? '-',
            $balance !== null ? formatNaira($balance) : '-',
        ],
        'items' => $items,
        'totals' => [
            ['Invoice Amount:', formatNaira($parent_invoice ? $parent_invoice['grand_total'] : $receipt['amount_paid'])],
            ['Paid (this receipt):', formatNaira($receipt['amount_paid'])],
        ],
        'grand_label' => 'Remaining Balance:',
        'grand' => formatNaira($balance !== null ? $balance : $receipt['amount_paid']),
        'thanks' => 'Thank you for your business!',
        'pay_label' => '',
        'banks' => [],
        'prepared' => '',
    ], $receipt['document_number'] . '.jpg');

} catch (Exception $e) {
    error_log("Receipt JPEG export error: " . $e->getMessage());
    die('Error generating JPEG. Check server logs.');
}
?>
