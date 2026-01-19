<?php
include '../includes/session-check.php';

$quote_id = $_GET['id'] ?? null;

if (!$quote_id) {
    header('Location: ../pages/view-quotes.php?error=No quote specified');
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch quote
    $stmt = $pdo->prepare("
        SELECT * FROM quotes 
        WHERE id = ? 
        AND status = 'finalized'
        AND deleted_at IS NULL
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) {
        throw new Exception('Quote not found or not finalized');
    }

    // Check if already converted
    $stmt = $pdo->prepare("
        SELECT id FROM invoices 
        WHERE quote_id = ? 
    ");
    $stmt->execute([$quote_id]);
    if ($stmt->fetch()) {
        throw new Exception('Quote already converted to invoice');
    }

    // Generate invoice number
    $stmt = $pdo->query("
        SELECT invoice_number 
        FROM invoices 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $lastInvoice = $stmt->fetch();
    if ($lastInvoice) {
        $lastNumber = intval(substr($lastInvoice['invoice_number'], 4));
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }
    $invoice_number = 'INV-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

    // Fetch quote line items
    $stmt = $pdo->prepare("SELECT * FROM quote_line_items WHERE quote_id = ? ORDER BY item_number");
    $stmt->execute([$quote_id]);
    $line_items = $stmt->fetchAll();

    // Create invoice
    // Invoices table: invoice_number, quote_id, invoice_title, customer_id, customer_name, salesperson, invoice_date, subtotal, total_vat, grand_total, amount_paid, balance_due, payment_terms, status, created_by
    $stmt = $pdo->prepare("
        INSERT INTO invoices (
            invoice_number, quote_id, invoice_title, customer_id, customer_name, salesperson,
            invoice_date, subtotal, total_vat, grand_total,
            amount_paid, balance_due, payment_terms, status,
            created_by
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            0.00, ?, ?, 'draft',
            ?
        )
    ");

    $stmt->execute([
        $invoice_number,
        $quote['id'],
        $quote['quote_title'], // Using quote title as invoice title initially
        $quote['customer_id'],
        $quote['customer_name'],
        $quote['salesperson'],
        date('Y-m-d'), // Invoice date = today
        $quote['subtotal'],
        $quote['total_vat'],
        $quote['grand_total'],
        $quote['grand_total'], // balance_due = grand_total
        $quote['payment_terms'],
        $current_user['id']
    ]);

    $invoice_id = $pdo->lastInsertId();

    // Copy line items to invoice_line_items
    $stmt = $pdo->prepare("
        INSERT INTO invoice_line_items (
            invoice_id, product_id, item_number, quantity, description,
            unit_price, vat_applicable, vat_amount, line_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($line_items as $item) {
        $stmt->execute([
            $invoice_id,
            $item['product_id'],
            $item['item_number'],
            $item['quantity'],
            $item['description'],
            $item['unit_price'],
            $item['vat_applicable'],
            $item['vat_amount'],
            $item['line_total']
        ]);
    }

    $pdo->commit();

    header("Location: ../pages/view-invoice.php?id=" . $invoice_id . "&converted=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Convert to invoice error: " . $e->getMessage());
    header("Location: ../pages/view-quote.php?id=" . $quote_id . "&error=" . urlencode($e->getMessage()));
    exit;
}
?>