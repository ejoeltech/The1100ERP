<?php
include '../includes/session-check.php';

$quote_id = $_GET['id'] ?? null;

if (!$quote_id) {
    die('Quote ID required');
}

try {
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

    // Try Imagick first: renders the actual PDF (exact screenshot).
    if (class_exists('Imagick')) {
        require_once '../vendor/autoload.php';

        ob_start();
        $html = include '../includes/pdf-template.php';
        ob_end_clean();

        $mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 15, 'margin_bottom' => 15]);
        $mpdf->WriteHTML($html);

        $tempPdfFile = sys_get_temp_dir() . '/quote_' . $quote_id . '_' . time() . '.pdf';
        $mpdf->Output($tempPdfFile, 'F');

        $imagick = new Imagick();
        $imagick->setResolution(300, 300);
        $imagick->readImage($tempPdfFile . '[0]');
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(95);
        $imagick->setImageBackgroundColor('white');
        $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename="' . $quote['document_number'] . '.jpg"');
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
        'title' => 'QUOTATION',
        'subtitle' => $quote['quote_title'] ?? '',
        'contact' => 'Contact Address: ' . COMPANY_ADDRESS . ' Phone: ' . COMPANY_PHONE . ' | Email: ' . COMPANY_EMAIL . ' | Website: ' . COMPANY_WEBSITE,
        'info_left_label' => 'Quote For:',
        'customer' => $quote['customer_name'],
        'info_rows' => [
            ['Quote Number:', $quote['document_number']],
            ['Date:', date('d/m/Y', strtotime($quote['quote_date']))],
            ['Salesperson:', $quote['salesperson']],
        ],
        'meta_heads' => ['Delivery Period', 'Validity', 'Ship Date', 'Payment Terms'],
        'meta_vals' => [
            $quote['delivery_period'] ?? 'Immediate',
            '30 Days',
            (isset($quote['ship_date']) && $quote['ship_date']) ? date('d/m/Y', strtotime($quote['ship_date'])) : '-',
            $quote['payment_terms'] ?? (defined('DEFAULT_PAYMENT_TERMS') ? DEFAULT_PAYMENT_TERMS : 'Due on Receipt'),
        ],
        'items' => $items,
        'totals' => [
            ['Subtotal:', formatNaira($quote['subtotal'])],
            ['VAT:', formatNaira($quote['total_vat'])],
        ],
        'grand_label' => 'Total Quote:',
        'grand' => formatNaira($quote['grand_total']),
        'thanks' => function_exists('getSetting') ? getSetting('footer_text', 'We look forward to working with you! Thank you') : 'Thank you',
        'pay_label' => 'MAKE ALL PAYMENTS IN FAVOUR OF',
        'banks' => $banks,
        'prepared' => 'Quote prepared by: ' . $quote['salesperson'],
    ], $quote['document_number'] . '.jpg');

} catch (Exception $e) {
    error_log("Quote JPEG export error: " . $e->getMessage());
    die('Error generating JPEG. Check server logs.');
}
?>
