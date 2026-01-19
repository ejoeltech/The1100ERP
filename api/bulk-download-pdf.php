<?php
include '../includes/session-check.php';
require_once '../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

try {
    $document_type = $_POST['document_type'] ?? '';
    // Handle both JSON string (if sent via JS) or array (if sent via HTML form, though JSON is likely from earlier script logic)
    // Original code: $ids = json_decode($_POST['ids'] ?? '[]', true);
    $ids = json_decode($_POST['ids'] ?? '[]', true);

    $table = '';
    $lineItemTable = '';
    $foreignKey = '';
    $docNumberAlias = '';
    $titleAlias = '';
    $dateAlias = '';

    switch ($document_type) {
        case 'quote':
            $table = 'quotes';
            $lineItemTable = 'quote_line_items';
            $foreignKey = 'quote_id';
            $docNumberAlias = 'quote_number as document_number';
            $titleAlias = 'quote_title'; // No alias needed if same, but useful for generic access
            $dateAlias = 'quote_date';
            break;
        case 'invoice':
            $table = 'invoices';
            $lineItemTable = 'invoice_line_items';
            $foreignKey = 'invoice_id';
            $docNumberAlias = 'invoice_number as document_number';
            $titleAlias = 'invoice_title as quote_title';
            $dateAlias = 'invoice_date as quote_date';
            break;
        case 'receipt':
            $table = 'receipts';
            $lineItemTable = null; // Special handling for receipts (fetch from parent invoice)
            $docNumberAlias = 'receipt_number as document_number';
            $titleAlias = ''; // joined from invoice
            $dateAlias = 'payment_date as quote_date';
            break;
        default:
            throw new Exception('Invalid document type');
    }

    if (empty($ids) || !is_array($ids)) {
        throw new Exception('No items selected');
    }

    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function ($id) {
        return $id > 0;
    });

    if (empty($ids)) {
        throw new Exception('Invalid item IDs');
    }

    // Temporary directory for PDFs
    $tempDir = sys_get_temp_dir() . '/bluedots_bulk_' . uniqid();
    mkdir($tempDir);

    $pdfFiles = [];

    foreach ($ids as $id) {
        try {
            $document = null;
            $line_items = [];

            if ($document_type === 'receipt') {
                // Special Receipt Logic
                $stmt = $pdo->prepare("
                    SELECT r.*, r.receipt_number as document_number, i.invoice_title as quote_title, 
                           r.payment_date as quote_date, i.grand_total as invoice_grand_total
                    FROM receipts r
                    LEFT JOIN invoices i ON r.invoice_id = i.id
                    WHERE r.id = ? AND r.deleted_at IS NULL
                ");
                $stmt->execute([$id]);
                $document = $stmt->fetch();

                if (!$document)
                    continue;

                // Map invoice grand_total
                $document['grand_total'] = $document['invoice_grand_total'];

                // Parent invoice for template
                $parent_invoice = null;
                if ($document['invoice_id']) {
                    $stmt = $pdo->prepare("SELECT *, invoice_number as document_number FROM invoices WHERE id = ?");
                    $stmt->execute([$document['invoice_id']]);
                    $parent_invoice = $stmt->fetch();
                }

                $receipt = $document; // Template expects $receipt
                // No line items usually for receipts in this system logic so far

            } else {
                // Quote or Invoice
                $query = "SELECT *, $docNumberAlias";
                if ($titleAlias && strpos($titleAlias, 'as') !== false)
                    $query .= ", $titleAlias";
                if ($dateAlias && strpos($dateAlias, 'as') !== false)
                    $query .= ", $dateAlias";

                $query .= " FROM $table WHERE id = ? AND deleted_at IS NULL";

                $stmt = $pdo->prepare($query);
                $stmt->execute([$id]);
                $document = $stmt->fetch();

                if (!$document)
                    continue;

                // Fetch line items
                $stmt = $pdo->prepare("SELECT * FROM $lineItemTable WHERE $foreignKey = ? ORDER BY item_number");
                $stmt->execute([$id]);
                $line_items = $stmt->fetchAll();
            }

            // Generate HTML
            ob_start();
            if ($document_type === 'quote') {
                $quote = $document;
                // Template expects $quote and $line_items
                include '../includes/quote-pdf-template.php';
            } elseif ($document_type === 'invoice') {
                $invoice = $document;
                // Template expects $invoice and $line_items
                include '../includes/invoice-pdf-template.php';
            } else { // receipt
                // $receipt and $parent_invoice already set
                include '../includes/receipt-pdf-template.php';
            }
            $html = ob_get_clean();

            // Create PDF
            $mpdf = new \Mpdf\Mpdf([
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15
            ]);

            $mpdf->WriteHTML($html);

            // Save PDF to temp directory
            $filename = $document['document_number'] . '.pdf';
            $filepath = $tempDir . '/' . $filename;
            $mpdf->Output($filepath, 'F');

            $pdfFiles[] = $filepath;

        } catch (Exception $e) {
            error_log("Error generating PDF for $document_type ID $id: " . $e->getMessage());
            continue;
        }
    }

    if (empty($pdfFiles)) {
        throw new Exception('No PDFs could be generated');
    }

    // Create ZIP file
    $zipFilename = $document_type . 's_' . date('Y-m-d_H-i-s') . '.zip';
    $zipPath = $tempDir . '/' . $zipFilename;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('Could not create ZIP file');
    }

    foreach ($pdfFiles as $file) {
        $zip->addFile($file, basename($file));
    }

    $zip->close();

    // Send ZIP file to browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);

    // Clean up temp files
    foreach ($pdfFiles as $file) {
        @unlink($file);
    }
    @unlink($zipPath);
    @rmdir($tempDir);

    exit;

} catch (Exception $e) {
    error_log("Bulk download error: " . $e->getMessage());
    die('Error: ' . $e->getMessage());
}
?>