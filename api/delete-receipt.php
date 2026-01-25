<?php
// API to Delete a Receipt (Soft Delete)
// POST /api/delete-receipt.php
// POST /api/delete-receipt.php
// Payload: { receipt_id: 123, reason: "Entered in error" }

define('IS_API', true);
require_once '../config.php';
require_once '../includes/session-check.php';

header('Content-Type: application/json');

// 1. Check Admin Permissions
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo->beginTransaction();

    $data = json_decode(file_get_contents('php://input'), true);
    $receipt_id = filter_var($data['receipt_id'] ?? null, FILTER_VALIDATE_INT);
    $reason = $data['reason'] ?? 'Deleted by Admin';

    if (!$receipt_id) {
        throw new Exception('Receipt ID required');
    }

    // 2. Fetch Receipt Details
    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE id = ? FOR UPDATE");
    $stmt->execute([$receipt_id]);
    $receipt = $stmt->fetch();

    if (!$receipt)
        throw new Exception('Receipt not found');
    if ($receipt['deleted_at'] !== null)
        throw new Exception('Receipt is already deleted');

    // 3. Reverse Transaction (ONLY If not already void)
    // Deleting a valid receipt must behave like voiding it first to correct balances
    if ($receipt['status'] !== 'void') {
        $amount = floatval($receipt['amount_paid']);
        $invoice_id = $receipt['invoice_id'];
        $customer_id = null;

        // Update Invoice
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? FOR UPDATE");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch();

        if ($invoice) {
            $customer_id = $invoice['customer_id'];

            $new_amount_paid = max(0, floatval($invoice['amount_paid']) - $amount);
            $new_balance_due = floatval($invoice['grand_total']) - $new_amount_paid;

            $status = ($new_balance_due <= 0) ? 'paid' :
                (($new_amount_paid > 0) ? 'partial' : 'pending');

            $updateInv = $pdo->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE id = ?");
            $updateInv->execute([$new_amount_paid, $new_balance_due, $status, $invoice_id]);
        }

        // Handle Customer Credit Reversal
        if ($receipt['payment_method'] === 'Credit Applied' && $customer_id) {
            $stmt = $pdo->prepare("UPDATE customers SET account_balance = account_balance + ? WHERE id = ?");
            $stmt->execute([$amount, $customer_id]);
        }
    }

    // 4. Cleanup Parent Payment (Same logic as void)
    if (!empty($receipt['payment_id'])) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM receipts WHERE payment_id = ? AND status != 'void' AND deleted_at IS NULL AND id != ?");
        $checkStmt->execute([$receipt['payment_id'], $receipt_id]);
        $remaining_receipts = $checkStmt->fetchColumn();

        if ($remaining_receipts == 0) {
            $deletePayment = $pdo->prepare("DELETE FROM payments WHERE id = ?");
            $deletePayment->execute([$receipt['payment_id']]);
        }
    }

    // 5. Soft Delete Receipt
    $deleteNote = " | DELETED on " . date('Y-m-d H:i') . " by " . $_SESSION['user_id'] . ". Reason: $reason";
    $updateReceipt = $pdo->prepare("UPDATE receipts SET deleted_at = NOW(), notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?");
    $updateReceipt->execute([$deleteNote, $receipt_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Receipt deleted successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>