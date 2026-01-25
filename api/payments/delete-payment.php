<?php
// API to Delete a Payment
// POST /api/payments/delete-payment.php
// Payload: { payment_id: 123, reason: "Entered in error" }

define('IS_API', true);
require_once '../../config.php';
require_once '../../includes/session-check.php';

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
    $payment_id = filter_var($data['payment_id'] ?? null, FILTER_VALIDATE_INT);
    $reason = $data['reason'] ?? 'Deleted by Admin';

    if (!$payment_id) {
        throw new Exception('Payment ID required');
    }

    // 2. Fetch Payment Details
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? FOR UPDATE");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        throw new Exception('Payment not found');
    }

    $customer_id = $payment['customer_id'];
    $total_payment_amount = floatval($payment['amount']);

    // 3. Fetch Linked Receipts (Allocations)
    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE payment_id = ? AND deleted_at IS NULL");
    $stmt->execute([$payment_id]);
    $receipts = $stmt->fetchAll();

    $total_allocated_reversed = 0;

    // 4. Reverse Each Receipt
    foreach ($receipts as $receipt) {
        // Only reverse financial impact if the receipt was VALID (not void)
        if ($receipt['status'] !== 'void') {
            $amount = floatval($receipt['amount_paid']);
            $invoice_id = $receipt['invoice_id'];

            // Update Invoice: Increase Balance Due
            $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? FOR UPDATE");
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch();

            if ($invoice) {
                $new_amount_paid = max(0, floatval($invoice['amount_paid']) - $amount);
                $new_balance_due = floatval($invoice['grand_total']) - $new_amount_paid;

                $status = ($new_balance_due <= 0) ? 'paid' :
                    (($new_amount_paid > 0) ? 'partial' : 'pending');

                $updateInv = $pdo->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE id = ?");
                $updateInv->execute([$new_amount_paid, $new_balance_due, $status, $invoice_id]);
            }

            $total_allocated_reversed += $amount;
        }

        // Soft Delete the Receipt
        $deleteNote = " | PARENT PAYMENT DELETED on " . date('Y-m-d H:i') . " by " . $_SESSION['user_id'];
        $updateReceipt = $pdo->prepare("UPDATE receipts SET deleted_at = NOW(), notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?");
        $updateReceipt->execute([$deleteNote, $receipt['id']]);
    }

    // 5. Handle Unallocated Amount (Credit Reversal)
    // Any amount from this payment that WASN'T allocated to receipts is sitting in the customer's account balance.
    // We must deduct it.
    // Note: We use the original payment amount vs what we actually found allocated.
    // However, if some receipts were already voided, they are not counted in $total_allocated_reversed loop above.
    // Let's rely on the math: 
    // The payment brought in X amount.
    // We are deleting the payment entirely.
    // So we must effectively remove X amount from the ecosystem.
    // Part of X was in invoices (we just reversed that).
    // The rest of X is in Customer Credit (calculated as Total - Allocated).
    // But wait, if a receipt was voided previously, the money was ALREADY returned to Customer Credit or just reversed.
    // If a receipt was voided, it returned money to... where?
    // In our `void-receipt.php`, voiding REVERSES invoice but DOES NOT automatically add to customer credit unless it was "Credit Applied". 
    // Wait, if I paid $1000 cash, and voided the receipt, the money is "gone" (refunded). It doesn't go to credit automatically unless logic says so.
    // Actually, `void-receipt.php` simply reverses the transaction. It assumes money is returned.

    // So for THIS payment deletion:
    // We assume the ENTIRE payment amount is being "refunded" or removed.
    // We need to verify if any part of it is currently sitting in customer_balance.
    // Logic: 
    // 1. We just reversed all VALID receipts. Those amounts are now "free".
    // 2. Previously VOIDED receipts: The money was already removed/reversed from invoice. 
    //    Does voiding a receipt linked to a payment put money back on account? 
    //    Let's check `void-receipt.php`. It says:
    //    `if ($receipt['payment_method'] === 'Credit Applied' && $customer_id) { refund to credit }`
    //    But this payment is likely "Bank Transfer" etc.
    //    So voiding a receipt from a direct payment just kills the receipt. It doesn't credit the user.
    //    So that money is effectively "unaccounted for" or "returned".

    // BUT, the `payments` table row represents the actual incoming money.
    // If we have a $10,000 payment.
    // Receipt A: $3,000 (Valid).
    // Receipt B: $2,000 (Voided - effectively removed).
    // Unallocated: $5,000.
    // This $5,000 was added to Customer Balance when `save-payment.php` ran (Logic step 5).

    // So we need to remove the Unallocated portion from Customer Balance.
    // How do we know what was unallocated?
    // Initial Unallocated = Payment Amount - Sum(All Original Receipts).
    // But we don't track "Original Receipts" easily if some were deleted manually.

    // Correction: We can calculate the CURRENT Unallocated amount attributable to this payment?
    // No, `payments` table doesn't track unallocated balance.
    // But typically: Unallocated = Payment - Sum(Receipts).
    // But since we just Soft Deleted the receipts, we sort of know.

    // SIMPLIFIED APPROACH:
    // When the payment was made, it increased Customer Balance by (Total - Allocations).
    // If we delete the payment, we should reverse that specific increase.
    // AND reverse the allocations.

    // We need to know exactly how much credit this payment generated.
    // `save-payment.php` Step 5: `UPDATE customers SET account_balance = account_balance + ?` where ? is remainder.
    // Remainder = Total - Sum(Allocated at creation).

    // Since we can't travel back in time, we have to estimate or calculate based on current DB state.
    // Current Sum(Receipts for this payment) = X (including voids? No, voids don't count towards utilizing the money usually).
    // Actually, if I void a receipt, I usually expect the money to be available for something else?
    // If `void-receipt.php` DOES NOT return to credit for "Bank Transfer", then that money is just "lost/returned to customer".
    // So we don't need to deduct it from credit because it's not there.

    // So, we only need to deduct the amount that IS currently acting as Credit.
    // That is: Payment Amount - Sum(All Receipts ever created for this payment?).
    // Wait, if I paid $10k, allocated $5k. $5k went to credit.
    // If I then delete the payment, I should remove $5k from credit.
    // What if I subsequently used that $5k credit for another invoice?
    // Then Customer Balance went down. If I deduct $5k now, balance might go negative. That is CORRECT. The user spent money they didn't have (since we are deleting the source).

    // So:
    // 1. Calculate Sum of ALL receipts (Valid + Void + Deleted) linked to this payment?
    //    No, only Valid receipts consumed the money from the "Credit" perspective?
    //    Actually, `save-payment.php` creates receipts immediately. 
    //    The credit is `Total - Sum(Receipts created at that moment)`.
    //    If I later create a NEW receipt using "Credit Applied", it is NOT linked to this payment ID. It has `payment_id = NULL` usually (or we didn't implement linking credit usage to original payment).
    //    Let's check `save-payment.php`... `payment_id` is null if using credit.
    //    CORRECT.

    // Therefore:
    // The `receipts` linked to THIS `payment_id` are the ones created *at the moment of payment*.
    // Any amount NOT in these receipts was put into Credit.
    // So we should find the sum of all receipts with this `payment_id` (regardless of status, effectively, because they represented the initial split).
    // Wait, if I voided one, did it go back to credit? No.
    // So `Total Payment` = `Sum(Receipts linked)` + `Initial Credit`.
    // `Initial Credit` = `Total Payment` - `Sum(Receipts linked)`.
    // We must deduct `Initial Credit` from Customer Balance.

    $stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM receipts WHERE payment_id = ?"); // Include all history
    $stmt->execute([$payment_id]);
    $sum_linked_receipts = floatval($stmt->fetchColumn() ?: 0);

    $credit_to_reverse = $total_payment_amount - $sum_linked_receipts;

    // Safety clamp (though negative credit is technically possible/correct here)
    // If $credit_to_reverse is extremely small (float error), ignore
    if ($credit_to_reverse > 0.01) {
        $stmt = $pdo->prepare("UPDATE customers SET account_balance = account_balance - ? WHERE id = ?");
        $stmt->execute([$credit_to_reverse, $customer_id]);
    }

    // 6. Delete Payment Record
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
    $stmt->execute([$payment_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Payment deleted and all affects reversed', 'debug_credit_reversed' => $credit_to_reverse]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>