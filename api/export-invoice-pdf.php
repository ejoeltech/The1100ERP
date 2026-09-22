<?php
require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../vendor/autoload.php';
require_once '../includes/validate-pdf-env.php';

// Validate environment (extensions, permissions, etc.)
validatePdfEnvironment(__DIR__ . '/../tmp/mpdf');

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

// Fetch line items
$stmt = $pdo->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY item_number");
$stmt->execute([$invoice_id]);
$line_items = $stmt->fetchAll();

// Increase memory and time for PDF generation
ini_set('memory_limit', '256M');
set_time_limit(120);

// Generate PDF using mPDF
try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_header' => 10,
        'margin_footer' => 10,
        'tempDir' => __DIR__ . '/../tmp/mpdf' // Explicitly set temp directory
    ]);

    // Include the PDF template
    $html = include '../includes/invoice-pdf-template.php';
    
    // Safety check for HTML content
    if (!$html || is_int($html)) {
        throw new Exception("Invoice PDF template did not return valid content.");
    }

    $mpdf->WriteHTML($html);

    // Output PDF
    $filename = 'Invoice_' . $invoice['document_number'] . '.pdf';
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

} catch (\Mpdf\MpdfException $e) {
    error_log("Invoice PDF Generation Failure: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
    }
    die('Error generating Invoice PDF. Please check server logs for details. Error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Invoice PDF General Failure: " . $e->getMessage());
    die('Error generating PDF: ' . $e->getMessage());
}
?>