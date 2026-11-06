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

    // WP3: recording payments needs create_payment; sales reps may only
    // touch their own invoices (checked per allocation below + auto-alloc).
    requirePermission('create_payment');
    $isSalesRep = getUserRole() === 'sales_rep';

    // 1. Get Input Data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new Exception('Invalid request data');
    }

    $customer_id = isset($data['customer_id']) ? (int)$data['customer_id'] : 0;
    $amount = filter_var($data['amount'] ?? null, FILTER_VALIDATE_FLOAT);
    $payment_date = $data['payment_date'] ?? date('Y-m-d');
    $payment_method = $data['payment_method'] ?? 'Bank Transfer';
    $reference = $data['reference'] ?? '';
    $notes = $data['notes'] ?? '';
    $allocations = is_array($data['allocations'] ?? null) ? $data['allocations'] : [];
    $use_credit = !empty($data['use_credit']);

    if ($customer_id <= 0 || $amount === false || !is_finite((float)$amount) || $amount <= 0) {
        throw new Exception('Customer and a positive Amount are required');
    }
    $amount = (float)$amount;

    // Reject payments for customers that do not exist (or are deleted).
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$customer_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Customer not found');
    }

    // 2. Handle Credit Usage vs New Payment
    $payment_id = null;
    $total_allocated = 0;

    // If NOT using existing credit, record a new payment
    if (!$use_credit) {
        // Allocate the payment number atomically.
        $payment_number = generatePaymentNumber($pdo);

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
        // Lock the customer row and verify credit, but do not deduct the full
        // requested amount yet: only the amount actually allocated to invoices
        // should leave the account.
        $stmt = $pdo->prepare("SELECT account_balance FROM customers WHERE id = ? FOR UPDATE");
        $stmt->execute([$customer_id]);
        $current_balance = (float)$stmt->fetchColumn();
    }

    // 3. Process Allocations
    foreach ($allocations as $allocation) {
        if (!is_array($allocation)) {
            continue;
        }
        $invoice_id = (int)($allocation['invoice_id'] ?? 0);
        $alloc_amount = filter_var($allocation['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($invoice_id <= 0 || $alloc_amount === false || !is_finite((float)$alloc_amount) || $alloc_amount <= 0) {
            continue;
        }

        // Fetch and lock the invoice to get its current state.
        $stmt = $pdo->prepare("SELECT invoice_number, customer_id, customer_name, grand_total, amount_paid, created_by
            FROM invoices WHERE id = ? AND deleted_at IS NULL FOR UPDATE");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch();

        if (!$invoice)
            continue;

        // A payment for one customer must never be allocated to another
        // customer's invoice.
        if ((int)$invoice['customer_id'] !== $customer_id) {
            throw new Exception('Invoice #' . $invoice_id . ' does not belong to this customer');
        }

        // WP3 IDOR: sales reps allocate only to own invoices.
        if ($isSalesRep && (int)$invoice['created_by'] !== (int)$_SESSION['user_id']) {
            throw new Exception('You do not have permission to allocate to invoice #' . $invoice_id);
        }

        // Never apply more than the invoice still owes, and never allocate
        // more in total than the payment/credit being applied.
        $due = max(0, round((float)$invoice['grand_total'] - (float)$invoice['amount_paid'], 2));
        $alloc_amount = min((float)$alloc_amount, $due, max(0, $amount - $total_allocated));
        if ($alloc_amount <= 0) {
            continue;
        }
        $total_allocated += $alloc_amount;

        // Create Receipt. customer_name is NOT NULL on the receipts table;
        // payment_method must be one of the schema ENUM keys.
        $method = normalizePaymentMethod($use_credit ? 'Credit Applied' : $payment_method);

        $receipt_number = generateReceiptNumber($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO receipts (receipt_number, invoice_id, customer_id, customer_name, payment_id, amount_paid, payment_date, payment_method, reference_number, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $receipt_number,
            $invoice_id,
            $invoice['customer_id'],
            $invoice['customer_name'],
            $payment_id,
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
            // WP3: sales reps auto-allocate across own invoices only.
            $ownerScope = $isSalesRep ? 'AND created_by = ' . (int)$_SESSION['user_id'] : '';
            $stmt = $pdo->prepare("SELECT id, invoice_number, customer_id, customer_name, grand_total, amount_paid, balance_due
                FROM invoices
                WHERE customer_id = ? AND status != 'paid' AND deleted_at IS NULL $ownerScope
                ORDER BY invoice_date ASC, id ASC
                FOR UPDATE");
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
                    INSERT INTO receipts (receipt_number, invoice_id, customer_id, customer_name, payment_id, amount_paid, payment_date, payment_method, reference_number, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $receipt_number,
                    $inv['id'],
                    $inv['customer_id'],
                    $inv['customer_name'],
                    $payment_id,
                    $allocate_here,
                    $payment_date,
                    normalizePaymentMethod($payment_method),
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

    if ($use_credit) {
        if ($total_allocated <= 0) {
            throw new Exception('Select at least one invoice to apply credit to.');
        }
        if ($total_allocated > $current_balance) {
            throw new Exception('Insufficient credit balance. Available: ' . formatNaira($current_balance));
        }
        $stmt = $pdo->prepare("UPDATE customers SET account_balance = account_balance - ? WHERE id = ? AND account_balance >= ?");
        $stmt->execute([$total_allocated, $customer_id, $total_allocated]);
        if ($stmt->rowCount() !== 1) {
            throw new Exception('Insufficient credit balance.');
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
