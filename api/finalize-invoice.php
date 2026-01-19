<?php
include '../includes/session-check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

$invoice_id = $_POST['invoice_id'] ?? null;

if (!$invoice_id) {
    header('Location: ../pages/view-invoices.php?error=No invoice specified');
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch invoice and verify current status
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        throw new Exception('Invoice not found');
    }

    if ($invoice['status'] !== 'draft') {
        throw new Exception('Only draft invoices can be finalized');
    }

    // Update status to finalized
    $stmt = $pdo->prepare("UPDATE invoices SET status = 'finalized', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$invoice_id]);

    // Optional: Log action
    if (function_exists('logAudit')) {
        logAudit('finalize', 'invoice', $invoice_id, ['invoice_number' => $invoice['invoice_number']]);
    }

    $pdo->commit();

    header('Location: ../pages/view-invoice.php?id=' . $invoice_id . '&finalized=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../pages/view-invoice.php?id=' . $invoice_id . '&error=' . urlencode($e->getMessage()));
    exit;
}
