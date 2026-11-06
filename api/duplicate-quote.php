<?php
require_once '../config.php';
// session_check assumes user is logged in
include '../includes/session-check.php';

// WP4: state-changing endpoint accepts POST only (was GET-linkable).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/view-quotes.php?error=Invalid request method');
    exit;
}

$quote_id = $_POST['id'] ?? null;

if (!$quote_id) {
    header('Location: ../pages/view-quotes.php?error=No quote specified');
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch original quote
    $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$quote_id]);
    $original = $stmt->fetch();

    if (!$original) {
        throw new Exception('Quote not found');
    }

    // WP3: duplicating needs create rights + visibility of the source quote.
    requirePermission('create_quote');
    if (function_exists('canViewDocument') && !canViewDocument($original)) {
        throw new Exception('You do not have permission to use this quote');
    }

    // Fetch line items
    $stmt = $pdo->prepare("SELECT * FROM quote_line_items WHERE quote_id = ? ORDER BY item_number");
    $stmt->execute([$quote_id]);
    $line_items = $stmt->fetchAll();

    // Allocate the next quote number atomically (same format as new quotes).
    require_once '../includes/helpers.php';
    $new_quote_number = generateQuoteNumber($pdo);


    // Insert new quote
    // quotes: quote_title, customer_name, salesperson, quote_date, subtotal, total_vat, grand_total, payment_terms, status, created_by, customer_id
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            quote_number, quote_title, customer_id, customer_name, salesperson,
            quote_date, subtotal, total_vat, grand_total,
            payment_terms, status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
    ");

    $stmt->execute([
        $new_quote_number,
        $original['quote_title'] . ' (Copy)',
        $original['customer_id'],
        $original['customer_name'],
        $original['salesperson'],
        date('Y-m-d'), // Today's date
        $original['subtotal'],
        $original['total_vat'],
        $original['grand_total'],
        $original['payment_terms'],
        $current_user['id']
    ]);

    $new_quote_id = $pdo->lastInsertId();

    // Copy line items
    $stmt = $pdo->prepare("
        INSERT INTO quote_line_items (
            quote_id, item_number, quantity, description,
            unit_price, vat_applicable, vat_amount, line_total
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($line_items as $item) {
        $stmt->execute([
            $new_quote_id,
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

    header("Location: ../pages/view-quote.php?id=" . $new_quote_id . "&duplicated=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Duplicate error: " . $e->getMessage());
    header("Location: ../pages/view-quotes.php?error=Failed to duplicate quote");
    exit;
}
?>