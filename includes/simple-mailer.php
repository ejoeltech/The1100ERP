<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Document Email Mailer
 * Sends quotes, invoices and receipts with PDF attachments.
 * Transport: SMTP (Settings > Email) when configured, else PHP mail().
 */
require_once __DIR__ . '/smtp-mailer.php';

/**
 * Send document email with PDF attachment
 * @param string $documentType quote|invoice|receipt
 * @param int $documentId
 * @param string $recipientEmail
 * @param string $recipientName
 * @param string $customMessage
 * @param string|null $subject   Override auto-generated subject
 * @param bool $attachPdf        Attach the PDF (else body only)
 * @param string|array|null $bcc BCC address(es)
 * @return array ['success' => bool, 'message' => string, 'method' => string]
 */
function sendDocumentEmail($documentType, $documentId, $recipientEmail, $recipientName = '', $customMessage = '', $subject = null, $attachPdf = true, $bcc = null)
{
    global $pdo;

    try {
        // Validate email
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }

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
        } else {
            throw new Exception('Invalid document type');
        }

        if (!$document) {
            throw new Exception('Document not found');
        }

        $companyName = defined('COMPANY_NAME') ? COMPANY_NAME : getSetting('company_name', 'Eleven100 ERP');
        $fromEmail = getSetting('email_from_address', '') ?: getSetting('company_email', '');
        $fromName = getSetting('email_from_name', '') ?: $companyName;
        if (!$fromEmail) {
            throw new Exception('Sender email is not configured (Settings > Email > From Email)');
        }

        $emailSubject = $subject ?: getEmailSubject($documentType, $document, $companyName);
        $body = getEmailBody($documentType, $document, $recipientName, $customMessage, $companyName);

        $attachments = [];
        $filename = $document['document_number'] . '.pdf';
        if ($attachPdf) {
            $pdfBinary = buildDocumentPdfBinary($documentType, $documentId);
            if (!$pdfBinary) {
                throw new Exception('Failed to generate PDF');
            }
            $attachments[] = ['content' => $pdfBinary, 'name' => $filename, 'type' => 'application/pdf'];
        }

        // Transport: SMTP when fully configured, else PHP mail()
        $method = getSetting('email_method', 'php_mail');
        if ($method === 'smtp' && getSetting('smtp_host', '')) {
            $result = smtpSendEmail(
                $recipientEmail,
                $emailSubject,
                $body,
                $attachments,
                [
                    'from' => $fromEmail,
                    'fromName' => $fromName,
                    'replyTo' => $fromEmail,
                    'bcc' => $bcc ? (array)$bcc : [],
                    'host' => getSetting('smtp_host', ''),
                    'port' => (int)getSetting('smtp_port', 587),
                    'encryption' => getSetting('smtp_encryption', 'tls'),
                    'username' => getSetting('smtp_username', ''),
                    'password' => getSetting('smtp_password', ''),
                ]
            );
            $result['method'] = 'smtp';
        } else {
            $result = sendEmailWithAttachment(
                $recipientEmail,
                $recipientName,
                $emailSubject,
                $body,
                $attachments ? $attachments[0] : null,
                $fromEmail,
                $fromName,
                $bcc ? (array)$bcc : []
            );
            $result['method'] = 'php_mail';
        }

        // Log to audit trail
        if (function_exists('logEmailSend')) {
            logEmailSend(
                $documentType,
                $documentId,
                $document['document_number'],
                $recipientEmail,
                $result['success'] ? 'sent' : 'failed'
            );
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
 * Build the PDF binary for a document (same templates as the export endpoints,
 * but returned as a string so no HTTP headers are polluted).
 */
function buildDocumentPdfBinary($documentType, $documentId)
{
    global $pdo;

    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/validate-pdf-env.php';
    validatePdfEnvironment(__DIR__ . '/../tmp/mpdf');

    if ($documentType === 'quote') {
        $stmt = $pdo->prepare("
            SELECT q.*, q.quote_number as document_number, u.signature_file
            FROM quotes q
            LEFT JOIN users u ON q.created_by = u.id
            WHERE q.id = ? AND q.deleted_at IS NULL
        ");
        $stmt->execute([$documentId]);
        $quote = $stmt->fetch();
        if (!$quote) {
            throw new Exception('Quote not found');
        }
        $stmt = $pdo->prepare("SELECT * FROM quote_line_items WHERE quote_id = ? ORDER BY item_number");
        $stmt->execute([$documentId]);
        $line_items = $stmt->fetchAll();
        $template = __DIR__ . '/pdf-template.php';
        $filename = 'Quote_' . $quote['document_number'] . '.pdf';
    } elseif ($documentType === 'invoice') {
        // Same row shape as api/export-invoice-pdf.php (template needs quote_date alias)
        $stmt = $pdo->prepare("
            SELECT *, invoice_number as document_number, invoice_title as quote_title, invoice_date as quote_date
            FROM invoices
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$documentId]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            throw new Exception('Invoice not found');
        }
        $stmt = $pdo->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY item_number");
        $stmt->execute([$documentId]);
        $line_items = $stmt->fetchAll();
        $template = __DIR__ . '/invoice-pdf-template.php';
        $filename = 'Invoice_' . $invoice['document_number'] . '.pdf';
    } elseif ($documentType === 'receipt') {
        $stmt = $pdo->prepare("
            SELECT r.*, r.receipt_number as document_number, i.invoice_title as quote_title
            FROM receipts r
            LEFT JOIN invoices i ON r.invoice_id = i.id
            WHERE r.id = ? AND r.deleted_at IS NULL
        ");
        $stmt->execute([$documentId]);
        $receipt = $stmt->fetch();
        if (!$receipt) {
            throw new Exception('Receipt not found');
        }
        $parent_invoice = null;
        if ($receipt['invoice_id']) {
            $stmt = $pdo->prepare("SELECT *, invoice_number as document_number FROM invoices WHERE id = ?");
            $stmt->execute([$receipt['invoice_id']]);
            $parent_invoice = $stmt->fetch();
        }
        $template = __DIR__ . '/receipt-pdf-template.php';
        $filename = $receipt['document_number'] . '.pdf';
    } else {
        throw new Exception('Invalid document type');
    }

    require_once __DIR__ . '/helpers.php';
    ini_set('memory_limit', '256M');
    set_time_limit(120);

    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'tempDir' => __DIR__ . '/../tmp/mpdf'
        ]);

        $html = include $template;
        if (!$html || is_int($html)) {
            throw new Exception("PDF template did not return valid content.");
        }
        $mpdf->WriteHTML($html);
        return $mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN);
    } catch (\Mpdf\MpdfException $e) {
        error_log("Email PDF Generation Failure: " . $e->getMessage());
        throw new Exception('Error generating PDF: ' . $e->getMessage());
    }
}

/**
 * Get email subject based on document type
 */
function getEmailSubject($documentType, $document, $companyName = 'Eleven100 ERP')
{
    $subjects = [
        'quote' => "Quote #{number} from {company}",
        'invoice' => "Invoice #{number} from {company}",
        'receipt' => "Payment Receipt #{number} from {company}"
    ];

    $subject = $subjects[$documentType] ?? "Document from {company}";
    return str_replace(['{number}', '{company}'], [$document['document_number'], $companyName], $subject);
}

/**
 * Get email body based on document type.
 * Only the requested type's body is built (avoids touching keys other types lack).
 */
function getEmailBody($documentType, $document, $recipientName, $customMessage, $companyName = 'Eleven100 ERP')
{
    $greeting = $recipientName ? "Dear $recipientName," : "Dear Customer,";
    $extra = $customMessage ? "$customMessage\n\n" : "";
    $total = number_format($document['grand_total'] ?? 0, 2);

    if ($documentType === 'quote') {
        return "
$greeting

Please find attached Quote #{$document['document_number']} for {$document['quote_title']}.

Total Amount: Î“Ã©Âª$total

" . $extra . "If you have any questions, please don't hesitate to contact us.

Best regards,
$companyName
";
    }

    if ($documentType === 'invoice') {
        $paid = number_format($document['amount_paid'] ?? 0, 2);
        $balance = number_format($document['balance_due'] ?? 0, 2);
        $terms = $document['payment_terms'] ?? '';
        return "
$greeting

Please find attached Invoice #{$document['document_number']} for {$document['quote_title']}.

Total Amount: Î“Ã©Âª$total
Amount Paid: Î“Ã©Âª$paid
Balance Due: Î“Ã©Âª$balance

" . $extra . "Payment Terms: $terms

Thank you for your business!

Best regards,
$companyName
";
    }

    if ($documentType === 'receipt') {
        $paid = number_format($document['amount_paid'] ?? 0, 2);
        $method = $document['payment_method'] ?? '';
        return "
$greeting

Thank you for your payment! Please find attached Receipt #{$document['document_number']}.

Amount Paid: Î“Ã©Âª$paid
Payment Method: $method

" . $extra . "We appreciate your business.

Best regards,
$companyName
";
    }

    return "Please find attached document from $companyName.";
}

/**
 * Resolve a salesperson name to a user email for BCC (matches username or full name).
 */
function resolveSalespersonEmail($salesperson)
{
    global $pdo;
    $salesperson = trim($salesperson ?? '');
    if ($salesperson === '') {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE (username = ? OR full_name = ?) AND is_active = 1 LIMIT 1");
        $stmt->execute([$salesperson, $salesperson]);
        $row = $stmt->fetch();
        if ($row && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            return $row['email'];
        }
    } catch (Exception $e) {
        // ignore lookup failures
    }
    return null;
}

/**
 * Send email with PDF attachment using PHP mail()
 */
function sendEmailWithAttachment($to, $toName, $subject, $body, $attachment, $fromEmail, $fromName, $bcc = [])
{
    try {
        $boundary = md5((string)time());

        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";
        if ($bcc) {
            $headers .= 'Bcc: ' . implode(', ', $bcc) . "\r\n";
        }
        $headers .= "MIME-Version: 1.0\r\n";

        if ($attachment && isset($attachment['content'])) {
            $fileContent = chunk_split(base64_encode($attachment['content']));
            $filename = $attachment['name'] ?? 'document.pdf';

            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

            $message = "--$boundary\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $body . "\r\n\r\n";

            $message .= "--$boundary\r\n";
            $message .= "Content-Type: application/pdf; name=\"$filename\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
            $message .= $fileContent . "\r\n";
            $message .= "--$boundary--";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message = $body;
        }

        $result = mail($to, $subject, $message, $headers);

        if ($result) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        }
        throw new Exception('PHP mail() failed (no local mail server Î“Ã‡Ã¶ configure SMTP in Settings > Email)');
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
