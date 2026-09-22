<?php
require_once '../config.php';

$quote_id = $_GET['id'] ?? null;

if (!$quote_id) {
    die('No quote specified');
}

require_once '../vendor/autoload.php';
require_once '../includes/validate-pdf-env.php';

// Validate environment (extensions, permissions, etc.)
validatePdfEnvironment(__DIR__ . '/../tmp/mpdf');

try {
    // Fetch quote
    $stmt = $pdo->prepare("
        SELECT q.*, q.quote_number as document_number, u.signature_file 
        FROM quotes q 
        LEFT JOIN users u ON q.created_by = u.id 
        WHERE q.id = ? AND q.deleted_at IS NULL
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) {
        die('Quote not found');
    }

    // Fetch line items
    $stmt = $pdo->prepare("SELECT * FROM quote_line_items WHERE quote_id = ? ORDER BY item_number");
    $stmt->execute([$quote_id]);
    $line_items = $stmt->fetchAll();

    // Generate HTML for PDF
    require_once '../includes/helpers.php';
    
    // Increase memory and time for PDF generation
    ini_set('memory_limit', '256M');
    set_time_limit(120);

    // Create PDF
    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'tempDir' => __DIR__ . '/../tmp/mpdf' // Explicitly set temp directory
        ]);

        $html = include '../includes/pdf-template.php';
        
        // Safety check for HTML content
        if (!$html || is_int($html)) {
            throw new Exception("PDF template did not return valid content.");
        }

        $mpdf->WriteHTML($html);

        // Append Terms & Conditions if set
        $terms = getSetting('quote_terms', '');
        if (!empty(trim($terms))) {
            $mpdf->AddPage();
            $mpdf->WriteHTML('<h2 style="font-family: sans-serif; color: #0076BE; font-size: 24px; margin-bottom: 20px;">Terms & Conditions</h2>');
            $mpdf->WriteHTML($terms);
        }

        // Append Warranty Information if set
        $warranty = getSetting('quote_warranty', '');
        if (!empty(trim($warranty))) {
            $mpdf->AddPage();
            $mpdf->WriteHTML('<h2 style="font-family: sans-serif; color: #0076BE; font-size: 24px; margin-bottom: 20px;">Warranty Information</h2>');
            $mpdf->WriteHTML($warranty);
        }

        // Output PDF
        $filename = $quote['document_number'] . '_' . date('Ymd') . '.pdf';
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

    } catch (Exception $e) {
        error_log("PDF Export Fail: " . $e->getMessage());
        die("System error: " . $e->getMessage());
    }
} catch (Exception $e) {
    error_log("General Export error: " . $e->getMessage());
    die("General Error: " . $e->getMessage());
}
?>