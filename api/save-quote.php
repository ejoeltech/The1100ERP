<?php
require_once '../config.php';
require_once '../includes/helpers.php';
include '../includes/session-check.php'; // Ensure user is logged in

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Extract form data
    $quote_number = $_POST['quote_number'];
    $quote_title = trim($_POST['quote_title']);
    $customer_name = trim($_POST['customer_name']);
    $salesperson = trim($_POST['salesperson']);
    $quote_date = $_POST['quote_date'];
    $delivery_period = trim($_POST['delivery_period'] ?? '10 Days');
    $payment_terms = trim($_POST['payment_terms']);
    // WP3-D: money figures are recomputed server-side; the posted totals are ignored.
    // Status is allow-listed (draft/finalized only).
    $status = sanitizeDocumentStatus($_POST['status'] ?? 'draft');
    $line_items = $_POST['line_items'];

    // Validate required fields
    if (empty($quote_title) || empty($customer_name) || empty($salesperson)) {
        throw new Exception('Required fields are missing');
    }

    // Server-side recalculation (throws on empty/invalid items)
    $calc = recalcDocumentTotals($line_items);
    $line_items = $calc['items'];
    $subtotal = $calc['subtotal'];
    $total_vat = $calc['vat'];
    $grand_total = $calc['grand'];

    // Save customer if new (INSERT IGNORE will skip if already exists)
    $customer_id = null;
    if (!empty($customer_name)) {
        // Check if customer exists to get ID
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE customer_name = ?");
        $stmt->execute([$customer_name]);
        $customer = $stmt->fetch();

        if ($customer) {
            $customer_id = $customer['id'];
        } else {
            // Create new customer
            $stmt = $pdo->prepare("INSERT INTO customers (customer_name, created_at) VALUES (?, NOW())");
            $stmt->execute([$customer_name]);
            $customer_id = $pdo->lastInsertId();
        }
    }

    // Insert document
    // Quotes table: quote_number, quote_title, customer_id, customer_name, salesperson, quote_date, subtotal, total_vat, grand_total, payment_terms, status, created_by
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            quote_number, quote_title, customer_id, customer_name, salesperson,
            quote_date, delivery_period, subtotal, total_vat, grand_total, 
            payment_terms, status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $quote_number,
        $quote_title,
        $customer_id,
        $customer_name,
        $salesperson,
        $quote_date,
        $delivery_period,
        $subtotal,
        $total_vat,
        $grand_total,
        $payment_terms,
        $status,
        $current_user['id']
    ]);

    $quote_id = $pdo->lastInsertId();

    // Insert line items
    $stmt = $pdo->prepare("
        INSERT INTO quote_line_items (
            quote_id, item_number, quantity, description,
            unit_price, vat_applicable, vat_amount, line_total,
            item_id, item_name
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($line_items as $item) {
        // All money figures come from recalcDocumentTotals() above; the
        // client-sent vat_amount/line_total are never used.
        $stmt->execute([
            $quote_id,
            $item['item_number'],
            $item['quantity'],
            $item['description'],
            $item['unit_price'],
            $item['vat_applicable'],
            $item['vat_amount'],
            $item['line_total'],
            $item['item_id'],
            $item['item_name']
        ]);
    }

    // Commit transaction
    $pdo->commit();

    // Redirect to view quote
    header("Location: ../pages/view-quote.php?id=" . $quote_id . "&success=1");
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error (in production, use proper logging)
    error_log("Quote save error: " . $e->getMessage());

    // Redirect back with error
    header("Location: ../pages/create-quote.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>