<?php
/**
 * Simple Email Mailer
 * Uses PHP mail() function with PDF attachments
 */

/**
 * Send document email with PDF attachment
 * @param string $documentType quote|invoice|receipt
 * @param int $documentId
 * @param string $recipientEmail
 * @param string $recipientName
 * @param string $customMessage
 * @return array ['success' => bool, 'message' => string]
 */
function sendDocumentEmail($documentType, $documentId, $recipientEmail, $recipientName = '', $customMessage = '')
{
    global $pdo;

    try {
        // Validate email
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }

        // Fetch document
        // Fetch document
        $document = null;
        if ($documentType === 'quote') {
            $stmt = $pdo->prepare("SELECT *, quote_number as document_number FROM quotes WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch();
        } elseif ($documentType === 'invoice') {
            $stmt = $pdo->prepare("SELECT *, invoice_number as document_number, invoice_title as quote_title FROM invoices WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch();
        } elseif ($documentType === 'receipt') {
            $stmt = $pdo->prepare("
                SELECT r.*, r.receipt_number as document_number, i.invoice_title as quote_title 
                FROM receipts r
                LEFT JOIN invoices i ON r.invoice_id = i.id
                WHERE r.id = ? AND r.deleted_at IS NULL
            ");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch();
        }

        if (!$document) {
            throw new Exception('Document not found');
        }

        // Generate PDF (use existing PDF export)
        $pdfPath = generateDocumentPDF($documentType, $documentId);

        if (!file_exists($pdfPath)) {
            throw new Exception('Failed to generate PDF');
        }

        // Build email
        $subject = getEmailSubject($documentType, $document);
        $body = getEmailBody($documentType, $document, $recipientName, $customMessage);
        $fromEmail = 'noreply@bluedots.com.ng';
        $fromName = 'Your Company Name';

        // Send email with attachment
        $result = sendEmailWithAttachment(
            $recipientEmail,
            $recipientName,
            $subject,
            $body,
            $pdfPath,
            $fromEmail,
            $fromName
        );

        // Log to email_log
        logEmailSend(
            $documentType,
            $documentId,
            $document['document_number'],
            $recipientEmail,
            $result['success'] ? 'sent' : 'failed'
        );

        // Clean up PDF
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }

        return $result;

    } catch (Exception $e) {
        error_log("Email send error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Generate PDF for document
 */
function generateDocumentPDF($documentType, $documentId)
{
    // Use existing PDF export APIs
    $tempDir = sys_get_temp_dir();
    $filename = $documentType . '_' . $documentId . '_' . time() . '.pdf';
    $pdfPath = $tempDir . '/' . $filename;

    // Call appropriate PDF export
    $exportUrl = __DIR__ . '/../api/export-' . $documentType . '-pdf.php';

    // Create PDF using existing export
    $_GET['id'] = $documentId;
    ob_start();
    include $exportUrl;
    $pdfContent = ob_get_clean();

    file_put_contents($pdfPath, $pdfContent);

    return $pdfPath;
}

/**
 * Get email subject based on document type
 */
function getEmailSubject($documentType, $document)
{
    $subjects = [
        'quote' => "Quote #{number} from Your Company",
        'invoice' => "Invoice #{number} from Your Company",
        'receipt' => "Payment Receipt #{number} from Your Company"
    ];

    $subject = $subjects[$documentType] ?? "Document from Your Company";
    return str_replace('{number}', $document['document_number'], $subject);
}

/**
 * Get email body based on document type
 */
function getEmailBody($documentType, $document, $recipientName, $customMessage)
{
    $greeting = $recipientName ? "Dear $recipientName," : "Dear Customer,";

    $bodies = [
        'quote' => "
$greeting

Please find attached Quote #{$document['document_number']} for {$document['quote_title']}.

Total Amount: ₦" . number_format($document['grand_total'], 2) . "

" . ($customMessage ? "$customMessage\n\n" : "") . "
If you have any questions, please don't hesitate to contact us.

Best regards,
Bluedots Technologies
",
        'invoice' => "
$greeting

Please find attached Invoice #{$document['document_number']} for {$document['quote_title']}.

Total Amount: ₦" . number_format($document['grand_total'], 2) . "
Amount Paid: ₦" . number_format($document['amount_paid'], 2) . "
Balance Due: ₦" . number_format($document['balance_due'], 2) . "

" . ($customMessage ? "$customMessage\n\n" : "") . "
Payment Terms: {$document['payment_terms']}

Thank you for your business!

Best regards,
Bluedots Technologies
",
        'receipt' => "
$greeting

Thank you for your payment! Please find attached Receipt #{$document['document_number']}.

Amount Paid: ₦" . number_format($document['amount_paid'], 2) . "
Payment Method: {$document['payment_method']}

" . ($customMessage ? "$customMessage\n\n" : "") . "
We appreciate your business.

Best regards,
Bluedots Technologies
"
    ];

    return $bodies[$documentType] ?? "Please find attached document from Bluedots Technologies.";
}

/**
 * Send email with PDF attachment using PHP mail()
 */
function sendEmailWithAttachment($to, $toName, $subject, $body, $attachmentPath, $fromEmail, $fromName)
{
    try {
        // Read attachment
        $fileContent = file_get_contents($attachmentPath);
        $fileContent = chunk_split(base64_encode($fileContent));
        $filename = basename($attachmentPath);

        // Generate boundary
        $boundary = md5(time());

        // Headers
        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

        // Message body
        $message = "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $body . "\r\n\r\n";

        // Attachment
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: application/pdf; name=\"$filename\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
        $message .= $fileContent . "\r\n";
        $message .= "--$boundary--";

        // Send email
        $result = mail($to, $subject, $message, $headers);

        if ($result) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        } else {
            throw new Exception('Failed to send email');
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>