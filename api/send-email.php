<?php
define('IS_API', true);
require_once '../includes/session-check.php';
require_once '../includes/simple-mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    requirePermission('email_document');

    $documentType = $_POST['document_type'] ?? '';
    $documentId = intval($_POST['document_id'] ?? 0);
    $recipientEmail = trim($_POST['recipient_email'] ?? '');
    $recipientName = trim($_POST['recipient_name'] ?? '');
    // Form field names (email-document.php) + legacy API names
    $customMessage = trim($_POST['additional_message'] ?? $_POST['custom_message'] ?? '');
    $subject = trim($_POST['email_subject'] ?? '') ?: null;
    $attachPdf = !isset($_POST['attach_pdf']) || $_POST['attach_pdf'] === '1' || $_POST['attach_pdf'] === 'on';

    // Validate inputs
    if (!in_array($documentType, ['quote', 'invoice', 'receipt'])) {
        throw new Exception('Invalid document type');
    }

    if (!$documentId) {
        throw new Exception('Document ID required');
    }

    if (!$recipientEmail) {
        throw new Exception('Recipient email required');
    }

    // Verify document exists and user has permission to view it
    $document = null;
    if ($documentType === 'quote') {
        $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
    } elseif ($documentType === 'invoice') {
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
    } elseif ($documentType === 'receipt') {
        $stmt = $pdo->prepare("SELECT * FROM receipts WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
    }

    if (!$document) {
        throw new Exception('Document not found');
    }

    // Check permission (user must be able to view document to email it)
    if (function_exists('canViewDocument') && !canViewDocument($document)) {
        throw new Exception('You do not have permission to email this document');
    }

    // Optional BCC to salesperson (resolved from users by name)
    $bcc = null;
    if (!empty($_POST['bcc_salesperson']) && !empty($document['salesperson'])) {
        $bcc = resolveSalespersonEmail($document['salesperson']);
    }

    // Send email
    $result = sendDocumentEmail(
        $documentType,
        $documentId,
        $recipientEmail,
        $recipientName,
        $customMessage,
        $subject,
        $attachPdf,
        $bcc
    );

    header('Content-Type: application/json');
    echo json_encode($result);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
