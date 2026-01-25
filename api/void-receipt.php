<?php
// API to Void a Receipt and Reverse Transaction
// POST /api/void-receipt.php
// Payload: { receipt_id: 123, reason: "Entered in error" }

define('IS_API', true);
require_once '../config.php';
require_once '../includes/session-check.php';

header('Content-Type: application/json');

// 1. Check Admin Permissions
// Assuming role is in session or user table. For now, strict check on 'role' column if exists, or just use login.
// User requested "only available to admin accounts".
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
    $reason = $data['reason'] ?? 'Voided by Admin';

    if (!$receipt_id) {
        throw new Exception('Receipt ID required');
    }

    // 2. Fetch Receipt Details
    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE id = ? FOR UPDATE");
    $stmt->execute([$receipt_id]);
    $receipt = $stmt->fetch();

    if (!$receipt)
        throw new Exception('Receipt not found');
    if ($receipt['status'] === 'void')
        throw new Exception('Receipt is already void');

    $amount = floatval($receipt['amount_paid']);
    $invoice_id = $receipt['invoice_id'];
    $customer_id = null; // Need to fetch from invoice

    // 3. Update Invoice
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

    // 4. Handle Customer Credit Reversal?
    // If the payment method was "Credit Applied", we should REFUND that credit to the customer.
    if ($receipt['payment_method'] === 'Credit Applied' && $customer_id) {
        $stmt = $pdo->prepare("UPDATE customers SET account_balance = account_balance + ? WHERE id = ?");
        $stmt->execute([$amount, $customer_id]);
    }

    // 5. Build Void Note
    $voidNote = " | VOIDED on " . date('Y-m-d H:i') . " by " . $_SESSION['user_id'] . ". Reason: $reason";

    // 6. Update Receipt
    $updateReceipt = $pdo->prepare("UPDATE receipts SET status = 'void', notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?");
    $updateReceipt->execute([$voidNote, $receipt_id]);

    // 7. Cleanup Parent Payment (Prevent Zombie Records)
    // If this receipt was linked to a payment, and that payment has no other VALID receipts, delete the payment.
    if (!empty($receipt['payment_id'])) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM receipts WHERE payment_id = ? AND status != 'void'");
        $checkStmt->execute([$receipt['payment_id']]);
        $remaining_receipts = $checkStmt->fetchColumn();

        if ($remaining_receipts == 0) {
            // No other valid receipts exist for this payment. It's safe to remove the master payment record
            // so it doesn't show up in Manage Payments as a "Ghost" payment.
            $deletePayment = $pdo->prepare("DELETE FROM payments WHERE id = ?");
            $deletePayment->execute([$receipt['payment_id']]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Receipt voided and transaction reversed']);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>