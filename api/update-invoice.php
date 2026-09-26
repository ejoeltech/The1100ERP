<?php
require_once '../includes/security.php';
configureSessionCookies();
session_start();
require_once '../config.php';
require_once '../includes/helpers.php';
require_once '../includes/security.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// WP4: explicit CSRF check (manual session bootstrap, no central gate).
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/view-invoices.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Get form data
    $invoice_id = $_POST['invoice_id'] ?? null;
    $quote_title = $_POST['quote_title'] ?? ''; // This is invoice_title
    $customer_name = $_POST['customer_name'] ?? '';
    $salesperson = $_POST['salesperson'] ?? '';
    $quote_date = $_POST['quote_date'] ?? date('Y-m-d'); // This is invoice_date
    $subtotal = $_POST['subtotal'] ?? 0;
    $total_vat = $_POST['total_vat'] ?? 0;
    $grand_total = $_POST['grand_total'] ?? 0;
    $amount_paid = $_POST['amount_paid'] ?? 0;
    $balance_due = $_POST['balance_due'] ?? 0;
    $payment_terms = $_POST['payment_terms'] ?? DEFAULT_PAYMENT_TERMS;
    $status = $_POST['status'] ?? 'draft';

    if (!$invoice_id) {
        throw new Exception('Invoice ID required');
    }

    // Verify invoice exists
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        throw new Exception('Invoice not found');
    }

    // Phase 4: Check if invoice has receipts (using receipts table)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM receipts WHERE invoice_id = ? AND deleted_at IS NULL");
    $stmt->execute([$invoice_id]);
    $has_receipts = $stmt->fetchColumn() > 0;

    if ($has_receipts) {
        // In most ERPs, you can edit non-financial fields, but let's stick to original logic: block edit.
        // Or strictly block financial fields? 
        // Original logic threw exception.
        throw new Exception('Cannot edit invoice - receipts have been generated for this invoice');
    }

    // WP3 IDOR: canonical edit gate (finalized = admin only, drafts = own
    // for sales reps). Mirrors pages/edit-invoice.php.
    require_once '../includes/permissions.php';
    if (!canEditDocument($invoice)) {
        throw new Exception('You do not have permission to edit this invoice');
    }

    // Validate required fields
    if (empty($quote_title) || empty($customer_name)) {
        throw new Exception('Required fields missing');
    }

    // WP3-D: money figures are recomputed server-side. amount_paid is
    // payment-derived and NEVER client-settable: keep the stored value and
    // recompute balance + paid state from the recalculated grand total.
    $calc = recalcDocumentTotals($_POST['line_items'] ?? []);
    $line_items = $calc['items'];
    $subtotal = $calc['subtotal'];
    $total_vat = $calc['vat'];
    $grand_total = $calc['grand'];
    $amount_paid = (float)($invoice['amount_paid'] ?? 0);
    $balance_due = max(0, round($grand_total - $amount_paid, 2));
    if ($amount_paid > 0) {
        $status = $balance_due <= 0 ? 'paid' : 'partial';
    } else {
        $status = sanitizeDocumentStatus($_POST['status'] ?? 'draft');
    }

    // Build UPDATE with edit tracking
    // invoices table columns: invoice_title, customer_name, salesperson, invoice_date, subtotal, total_vat, grand_total, amount_paid, balance_due, payment_terms, status
    $update_sql = "
        UPDATE invoices SET
            invoice_title = ?,
            customer_name = ?,
            salesperson = ?,
            invoice_date = ?,
            subtotal = ?,
            total_vat = ?,
            grand_total = ?,
            amount_paid = ?,
            balance_due = ?,
            payment_terms = ?,
            status = ?,
            updated_at = NOW()";

    $params = [
        $quote_title, // Mapped to invoice_title
        $customer_name,
        $salesperson,
        $quote_date, // Mapped to invoice_date
        $subtotal,
        $total_vat,
        $grand_total,
        $amount_paid,
        $balance_due,
        $payment_terms,
        $status
    ];

    // Phase 4 block removed (dead code referencing undefined $is_finalized).

    $update_sql .= " WHERE id = ?";
    $params[] = $invoice_id;

    $stmt = $pdo->prepare($update_sql);
    $stmt->execute($params);

    // Handle line items (recalculated rows only)
    $line_items = $line_items ?? [];

    if (!empty($line_items)) {
        // Delete existing line items
        $stmt = $pdo->prepare("DELETE FROM invoice_line_items WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);

        // Insert new line items
        $stmt = $pdo->prepare("
            INSERT INTO invoice_line_items (invoice_id, item_number, quantity, description, unit_price, vat_applicable, vat_amount, line_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($line_items as $item) {
            $stmt->execute([
                $invoice_id,
                $item['item_number'],
                $item['quantity'],
                $item['description'],
                $item['unit_price'],
                $item['vat_applicable'],
                $item['vat_amount'],
                $item['line_total']
            ]);
        }
    }

    // Add customer if not exists
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE customer_name = ?");
    $stmt->execute([$customer_name]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO customers (customer_name) VALUES (?)");
        $stmt->execute([$customer_name]);
    }

    // Phase 4: Log audit trail
    if ($is_finalized && function_exists('logDocumentEdit')) {
        logDocumentEdit('invoice', $invoice_id, $invoice['invoice_number'], [
            'edited_by' => $_SESSION['full_name'] ?? 'Unknown',
            'status' => 'finalized',
            'action' => 'edited_finalized_invoice'
        ]);
    }

    $pdo->commit();

    // Redirect to invoice view
    header('Location: ../pages/view-invoice.php?id=' . $invoice_id . '&updated=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Update Invoice Error: " . $e->getMessage());
    header('Location: ../pages/edit-invoice.php?id=' . ($invoice_id ?? '') . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>