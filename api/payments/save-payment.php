<?php
// API to save a payment and allocate it to invoices
// POST /api/payments/save-payment.php

include '../../config.php';
include '../../includes/session-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get Input Data
    $data = json_decode(file_get_contents('php://input'), true);

    $customer_id = $data['customer_id'] ?? null;
    $amount = filter_var($data['amount'], FILTER_VALIDATE_FLOAT);
    $payment_date = $data['payment_date'] ?? date('Y-m-d');
    $payment_method = $data['payment_method'] ?? 'Bank Transfer';
    $reference = $data['reference'] ?? '';
    $notes = $data['notes'] ?? '';
    $allocations = $data['allocations'] ?? []; // Array of {invoice_id, amount}
    $use_credit = !empty($data['use_credit']);

    if (!$customer_id || !$amount) {
        throw new Exception('Customer and Amount are required');
    }

    // 2. Handle Credit Usage vs New Payment
    $payment_id = null;
    $total_allocated = 0;

    // If NOT using existing credit, record a new payment
    if (!$use_credit) {
        // Generate Payment Number
        $stmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE YEAR(created_at) = YEAR(CURRENT_DATE)");
        $count = $stmt->fetchColumn() + 1;
        $payment_number = 'PAY-' . date('ym') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO payments (payment_number, customer_id, amount, payment_date, payment_method, reference, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $payment_number,
            $customer_id,
            $amount,
            $payment_date,
            $payment_method,
            $reference,
            $notes,
            $_SESSION['user_id']
        ]);
        $payment_id = $pdo->lastInsertId();
    } else {
        // If using credit, verify balance
        $stmt = $pdo->prepare("SELECT account_balance FROM customers WHERE id = ? FOR UPDATE");
        $stmt->execute([$customer_id]);
        $current_balance = $stmt->fetchColumn();

        if ($current_balance < $amount) {
            throw new Exception("Insufficient credit balance. Available: " . formatNaira($current_balance));
        }

        // Deduct from credit
        $stmt = $pdo->prepare("UPDATE customers SET account_balance = account_balance - ? WHERE id = ?");
        $stmt->execute([$amount, $customer_id]);
    }

    // 3. Process Allocations
    foreach ($allocations as $allocation) {
        $invoice_id = $allocation['invoice_id'];
        $alloc_amount = filter_var($allocation['amount'], FILTER_VALIDATE_FLOAT);

        if ($alloc_amount <= 0)
            continue;

        $total_allocated += $alloc_amount;

        // Fetch invoice to get current state
        $stmt = $pdo->prepare("SELECT invoice_number, grand_total, amount_paid FROM invoices WHERE id = ? FOR UPDATE");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch();

        if (!$invoice)
            continue;

        // Create Receipt
        // If it's a credit usage, we mark it as 'Credit Applied'
        $method = $use_credit ? 'Credit Applied' : $payment_method;

        $receipt_number = generateReceiptNumber($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO receipts (receipt_number, invoice_id, payment_id, amount_paid, payment_date, payment_method, reference_number, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $receipt_number,
            $invoice_id,
            $payment_id, // Can be null if using credit only (conceptually, or we could link to a 'credit usage' record if we had one)
            $alloc_amount,
            $payment_date,
            $method,
            $reference,
            $notes,
            $_SESSION['user_id']
        ]);

        // Update Invoice
        $new_amount_paid = $invoice['amount_paid'] + $alloc_amount;
        $new_balance_due = max(0, $invoice['grand_total'] - $new_amount_paid);
        $status = ($new_balance_due <= 0) ? 'paid' : 'partial';

        $stmt = $pdo->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE id = ?");
        $stmt->execute([$new_amount_paid, $new_balance_due, $status, $invoice_id]);
    }

    // 4. Handle Unallocated Amount (Auto-Allocation)
    // Only if this is a NEW payment (not using existing credit)
    if (!$use_credit) {
        $remaining_balance = $amount - $total_allocated;

        if ($remaining_balance > 0) {
            // Fetch any remaining unpaid invoices for this customer, ordered by oldest first
            // We exclude invoices that might have just been fully paid in step 3 (though logic handles it)
            $stmt = $pdo->prepare("SELECT id, invoice_number, grand_total, amount_paid, balance_due FROM invoices WHERE customer_id = ? AND status != 'paid' ORDER BY invoice_date ASC, id ASC");
            $stmt->execute([$customer_id]);
            $unpaid_invoices = $stmt->fetchAll();

            foreach ($unpaid_invoices as $inv) {
                if ($remaining_balance <= 0)
                    break;

                // Re-check balance due in case it was partially paid in step 3 (unlikely if loop excluded allocated ids, but safe)
                // Actually, step 3 updates the DB, so we should rely on what we just fetched or re-fetch if needed.
                // The query above fetches current state.

                $due = $inv['balance_due'];
                if ($due <= 0)
                    continue;

                $allocate_here = min($remaining_balance, $due);

                // Create Receipt for Auto-Allocation
                $receipt_number = generateReceiptNumber($pdo);

                $stmt = $pdo->prepare("
                    INSERT INTO receipts (receipt_number, invoice_id, payment_id, amount_paid, payment_date, payment_method, reference_number, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $receipt_number,
                    $inv['id'],
                    $payment_id,
                    $allocate_here,
                    $payment_date,
                    $payment_method,
                    $reference,
                    $notes . " (Auto-Allocated)",
                    $_SESSION['user_id']
                ]);

                // Update Invoice
                $new_amount_paid = $inv['amount_paid'] + $allocate_here;
                $new_balance_due = max(0, $inv['grand_total'] - $new_amount_paid);
                $status = ($new_balance_due <= 0) ? 'paid' : 'partial';

                $stmt = $pdo->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE id = ?");
                $stmt->execute([$new_amount_paid, $new_balance_due, $status, $inv['id']]);

                $total_allocated += $allocate_here;
                $remaining_balance -= $allocate_here;
            }
        }

        // 5. Handle Final Overpayment (True Credit)
        if ($remaining_balance > 0) {
            // Credit the customer account
            $stmt = $pdo->prepare("UPDATE customers SET account_balance = account_balance + ? WHERE id = ?");
            $stmt->execute([$remaining_balance, $customer_id]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment recorded successfully',
        'payment_id' => $payment_id,
        'allocated' => $total_allocated,
        'credited' => (!$use_credit && isset($remaining_balance)) ? $remaining_balance : 0
    ]);

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