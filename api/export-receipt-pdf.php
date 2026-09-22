<?php
include '../includes/session-check.php';
require_once '../vendor/autoload.php';
require_once '../includes/validate-pdf-env.php';

// Validate environment (extensions, permissions, etc.)
validatePdfEnvironment(__DIR__ . '/../tmp/mpdf');

$receipt_id = $_GET['id'] ?? null;

if (!$receipt_id) {
    die('No receipt specified');
}

try {
    // Fetch receipt
    $stmt = $pdo->prepare("
        SELECT r.*, r.receipt_number as document_number, i.invoice_title as quote_title
        FROM receipts r
        LEFT JOIN invoices i ON r.invoice_id = i.id
        WHERE r.id = ? AND r.deleted_at IS NULL
    ");
    $stmt->execute([$receipt_id]);
    $receipt = $stmt->fetch();

    if (!$receipt) {
        die('Receipt not found');
    }

    // Fetch parent invoice
    $parent_invoice = null;
    if ($receipt['invoice_id']) {
        $stmt = $pdo->prepare("SELECT *, invoice_number as document_number FROM invoices WHERE id = ?");
        $stmt->execute([$receipt['invoice_id']]);
        $parent_invoice = $stmt->fetch();
    }

    // Increase memory and time for PDF generation
    ini_set('memory_limit', '256M');
    set_time_limit(120);

    // Create PDF
    try {
        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'tempDir' => __DIR__ . '/../tmp/mpdf' // Explicitly set temp directory
        ]);

        $html = include '../includes/receipt-pdf-template.php';
        
        // Safety check for HTML content
        if (!$html || is_int($html)) {
            throw new Exception("Receipt PDF template did not return valid content.");
        }

        $mpdf->WriteHTML($html);

        // Output
        $filename = $receipt['document_number'] . '_' . date('Ymd') . '.pdf';
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

    } catch (Exception $e) {
        error_log("Receipt PDF Export Failure: " . $e->getMessage());
        
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }
        die('Error generating Receipt PDF. Please check server logs for details. Error: ' . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("General Receipt Export error: " . $e->getMessage());
    die("General Error: " . $e->getMessage());
}
?>