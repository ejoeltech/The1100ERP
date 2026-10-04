<?php
include '../includes/session-check.php';

$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    die('Invoice ID required');
}

try {
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

    // Try Imagick first: renders the actual PDF (exact screenshot).
    if (class_exists('Imagick')) {
        require_once '../vendor/autoload.php';

        ob_start();
        $html = include '../includes/invoice-pdf-template.php';
        ob_end_clean();

        $mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 15, 'margin_bottom' => 15]);
        $mpdf->WriteHTML($html);

        $tempPdfFile = sys_get_temp_dir() . '/invoice_' . $invoice_id . '_' . time() . '.pdf';
        $mpdf->Output($tempPdfFile, 'F');

        $imagick = new Imagick();
        $imagick->setResolution(300, 300);
        $imagick->readImage($tempPdfFile . '[0]');
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(95);
        $imagick->setImageBackgroundColor('white');
        $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename="' . $invoice['document_number'] . '.jpg"');
        echo $imagick->getImageBlob();

        $imagick->clear();
        $imagick->destroy();
        @unlink($tempPdfFile);
        exit;
    }

    // GD fallback: shared renderer mirroring the PDF template sections
    // (all items, dynamic height) — same visual language as the PDF.
    require_once '../includes/jpeg-renderer.php';

    $items = [];
    foreach ($line_items as $item) {
        $items[] = [
            'qty' => $item['quantity'],
            'desc' => $item['description'],
            'unit' => formatNaira($item['unit_price']),
            'total' => formatNaira($item['line_total']),
        ];
    }
    $banks = [];
    if (function_exists('getBankAccountsForDisplay')) {
        foreach (getBankAccountsForDisplay() as $b) {
            $banks[] = [$b['bank_name'] ?? '', $b['account_number'] ?? ''];
        }
    }
    renderDocumentJpeg([
        'title' => 'INVOICE',
        'subtitle' => $invoice['quote_title'] ?? '',
        'contact' => 'Contact Address: ' . COMPANY_ADDRESS . ' Phone: ' . COMPANY_PHONE . ' | Email: ' . COMPANY_EMAIL . ' | Website: ' . COMPANY_WEBSITE,
        'info_left_label' => 'Bill To:',
        'customer' => $invoice['customer_name'],
        'info_rows' => [
            ['Invoice Number:', $invoice['document_number']],
            ['Date:', date('d/m/Y', strtotime($invoice['quote_date']))],
            ['Salesperson:', $invoice['salesperson']],
        ],
        'meta_heads' => ['Invoice Date', 'Status', 'Payment Terms', 'Balance Due'],
        'meta_vals' => [
            date('d/m/Y', strtotime($invoice['quote_date'])),
            ucfirst($invoice['status'] ?? 'draft'),
            $invoice['payment_terms'] ?? 'Due on Receipt',
            formatNaira($invoice['balance_due'] ?? $invoice['grand_total']),
        ],
        'items' => $items,
        'totals' => [
            ['Subtotal:', formatNaira($invoice['subtotal'])],
            ['VAT:', formatNaira($invoice['total_vat'])],
        ],
        'grand_label' => 'Balance Due:',
        'grand' => formatNaira($invoice['balance_due'] ?? $invoice['grand_total']),
        'thanks' => function_exists('getSetting') ? getSetting('footer_text', 'Thank you for your business!') : 'Thank you',
        'pay_label' => 'MAKE ALL PAYMENTS IN FAVOUR OF',
        'banks' => $banks,
        'prepared' => 'Invoice prepared by: ' . $invoice['salesperson'],
    ], $invoice['document_number'] . '.jpg');

} catch (Exception $e) {
    error_log("Invoice JPEG export error: " . $e->getMessage());
    die('Error generating JPEG. Check server logs.');
}
?>
