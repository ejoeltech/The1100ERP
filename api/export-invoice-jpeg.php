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

    // Fetch line items
    $stmt = $pdo->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY item_number");
    $stmt->execute([$invoice_id]);
    $line_items = $stmt->fetchAll();

    // Try Imagick first (best quality)
    if (class_exists('Imagick')) {
        require_once '../vendor/autoload.php';

        ob_start();
        include '../includes/invoice-pdf-template.php';
        $html = ob_get_clean();

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

    // Fallback to GD library - IMPROVED FORMATTING
    $width = 2100;  // Increased width for better spacing
    $height = 2970; // A4 ratio

    $image = imagecreatetruecolor($width, $height);

    // Enhanced color palette
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $blue = imagecolorallocate($image, 37, 99, 235);
    $darkBlue = imagecolorallocate($image, 29, 78, 216);
    $gray = imagecolorallocate($image, 75, 85, 99);
    $lightGray = imagecolorallocate($image, 229, 231, 235);
    $bgGray = imagecolorallocate($image, 249, 250, 251);

    // Fill background
    imagefill($image, 0, 0, $white);

    // Fonts
    $fontPath = 'C:/Windows/Fonts/arial.ttf';
    $fontBoldPath = 'C:/Windows/Fonts/arialbd.ttf';

    $margin = 120;
    $y = 100;

    // Top blue bar
    imagefilledrectangle($image, 0, 0, $width, 25, $blue);
    $y += 60;

    // Company Header with blue circles logo
    imagettftext($image, 56, 0, $margin, $y, $darkBlue, $fontBoldPath, 'Bluedots');
    $y += 40;
    imagettftext($image, 20, 0, $margin, $y, $gray, $fontPath, 'TECHNOLOGIES');
    $y += 80;

    // Horizontal line
    imagefilledrectangle($image, $margin, $y, $width - $margin, $y + 2, $lightGray);
    $y += 40;

    // Document type in large letters
    imagettftext($image, 72, 0, $margin, $y, $blue, $fontBoldPath, 'INVOICE');
    $y += 100;

    // Invoice details box
    $boxY = $y;
    imagefilledrectangle($image, $margin, $boxY, $width - $margin, $boxY + 250, $bgGray);
    imagerectangle($image, $margin, $boxY, $width - $margin, $boxY + 250, $lightGray);

    $y += 50;

    // Left column
    imagettftext($image, 28, 0, $margin + 40, $y, $gray, $fontBoldPath, 'Invoice #:');
    imagettftext($image, 28, 0, $margin + 300, $y, $black, $fontPath, $invoice['document_number']);
    $y += 60;

    imagettftext($image, 28, 0, $margin + 40, $y, $gray, $fontBoldPath, 'Date:');
    imagettftext($image, 28, 0, $margin + 300, $y, $black, $fontPath, date('d/m/Y', strtotime($invoice['quote_date'])));
    $y += 60;

    imagettftext($image, 28, 0, $margin + 40, $y, $gray, $fontBoldPath, 'Customer:');
    imagettftext($image, 28, 0, $margin + 300, $y, $black, $fontPath, substr($invoice['customer_name'], 0, 40));
    $y += 60;

    imagettftext($image, 28, 0, $margin + 40, $y, $gray, $fontBoldPath, 'Salesperson:');
    imagettftext($image, 28, 0, $margin + 300, $y, $black, $fontPath, $invoice['salesperson']);

    $y = $boxY + 280;

    // Document title
    imagettftext($image, 36, 0, $margin, $y, $black, $fontBoldPath, 'Project: ' . substr($invoice['quote_title'], 0, 50));
    $y += 100;

    // Line items section header
    imagefilledrectangle($image, $margin, $y, $width - $margin, $y + 60, $darkBlue);
    imagettftext($image, 26, 0, $margin + 30, $y + 43, $white, $fontBoldPath, 'ITEMS');
    imagettftext($image, 26, 0, $width - $margin - 300, $y + 43, $white, $fontBoldPath, 'AMOUNT');
    $y += 80;

    // Line items
    $itemCount = 0;
    $maxItems = 10; // Limit items for better formatting

    foreach ($line_items as $item) {
        if ($itemCount >= $maxItems) {
            $remaining = count($line_items) - $maxItems;
            imagettftext($image, 24, 0, $margin + 40, $y, $gray, $fontPath, "... and $remaining more items");
            $y += 50;
            break;
        }

        // Alternate row background
        if ($itemCount % 2 == 0) {
            imagefilledrectangle($image, $margin, $y - 35, $width - $margin, $y + 25, $bgGray);
        }

        // Item description (truncate if too long)
        $desc = substr($item['description'], 0, 55) . (strlen($item['description']) > 55 ? '...' : '');
        imagettftext($image, 24, 0, $margin + 40, $y, $black, $fontPath, $item['quantity'] . ' x ' . $desc);

        // Amount (right aligned)
        $amount = formatNaira($item['line_total']);
        imagettftext($image, 24, 0, $width - $margin - 320, $y, $black, $fontBoldPath, $amount);

        $y += 60;
        $itemCount++;
    }

    $y += 40;

    // Summary box
    imagefilledrectangle($image, $width - $margin - 800, $y, $width - $margin, $y + 320, $bgGray);
    imagerectangle($image, $width - $margin - 800, $y, $width - $margin, $y + 320, $lightGray);

    $y += 60;

    // Subtotal
    imagettftext($image, 28, 0, $width - $margin - 750, $y, $gray, $fontPath, 'Subtotal:');
    imagettftext($image, 28, 0, $width - $margin - 380, $y, $black, $fontBoldPath, formatNaira($invoice['subtotal']));
    $y += 70;

    // VAT
    imagettftext($image, 28, 0, $width - $margin - 750, $y, $gray, $fontPath, 'VAT (7.5%):');
    imagettftext($image, 28, 0, $width - $margin - 380, $y, $black, $fontBoldPath, formatNaira($invoice['total_vat']));
    $y += 90;

    // Line
    imagefilledrectangle($image, $width - $margin - 750, $y - 40, $width - $margin - 50, $y - 38, $lightGray);

    // Grand Total
    imagettftext($image, 38, 0, $width - $margin - 750, $y, $darkBlue, $fontBoldPath, 'Grand Total:');
    imagettftext($image, 38, 0, $width - $margin - 380, $y, $blue, $fontBoldPath, formatNaira($invoice['grand_total']));

    // Footer section
    $y = $height - 180;
    imagefilledrectangle($image, 0, $y - 20, $width, $y - 18, $lightGray);

    imagettftext($image, 20, 0, $margin, $y, $gray, $fontPath, COMPANY_ADDRESS);
    $y += 45;
    imagettftext($image, 20, 0, $margin, $y, $gray, $fontPath, 'Phone: ' . COMPANY_PHONE . ' | Email: ' . COMPANY_EMAIL);
    $y += 45;
    imagettftext($image, 18, 0, $margin, $y, $gray, $fontPath, 'Visit: ' . COMPANY_WEBSITE);

    // Generated with note
    $y += 55;
    imagettftext($image, 16, 0, $margin, $y, $lightGray, $fontPath, 'Generated with GD Library | For complete document use PDF export');

    // Output
    header('Content-Type: image/jpeg');
    header('Content-Disposition: attachment; filename="' . $invoice['document_number'] . '.jpg"');
    imagejpeg($image, null, 95);
    imagedestroy($image);

} catch (Exception $e) {
    error_log("Invoice JPEG export error: " . $e->getMessage());
    die('Error generating JPEG: ' . $e->getMessage());
}
?>