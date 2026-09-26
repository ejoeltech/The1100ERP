<?php
require_once '../config.php';
require_once '../includes/helpers.php';
// WP3: was session_start() only — anonymous users could POST edits.
// session-check enforces login (redirects anonymous to login.php).
include '../includes/session-check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

try {
    $pdo->beginTransaction();

    // Extract form data
    $quote_id = intval($_POST['quote_id']);
    $quote_title = trim($_POST['quote_title']);
    $customer_name = trim($_POST['customer_name']);
    $salesperson = trim($_POST['salesperson']);
    $quote_date = $_POST['quote_date'];
    $payment_terms = trim($_POST['payment_terms']);
    // WP3-D: money figures are recomputed server-side; posted totals ignored.
    // Status is allow-listed (draft/finalized only).
    $status = sanitizeDocumentStatus($_POST['status'] ?? 'draft');
    $line_items = $_POST['line_items'];

    // Fetch existing quote
    $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) {
        throw new Exception('Quote not found');
    }

    // WP3 IDOR: canonical edit gate (finalized = admin only, drafts = own
    // for sales reps). Mirrors pages/edit-quote.php.
    require_once '../includes/permissions.php';
    if (!canEditDocument($quote)) {
        throw new Exception('You do not have permission to edit this quote');
    }

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

    // Ensure customer exists or update (not tracking customer IDs strictly on update if name changes, but good practice to update)
    // For now, we update the name in the quote.

    // Build UPDATE query with conditional edit tracking
    $update_sql = "
        UPDATE quotes SET
            quote_title = ?,
            customer_name = ?,
            salesperson = ?,
            quote_date = ?,
            subtotal = ?,
            total_vat = ?,
            grand_total = ?,
            payment_terms = ?,
            status = ?,
            updated_at = NOW()";

    $params = [
        $quote_title,
        $customer_name,
        $salesperson,
        $quote_date,
        $subtotal,
        $total_vat,
        $grand_total,
        $payment_terms,
        $status
    ];

    $update_sql .= " WHERE id = ?";
    $params[] = $quote_id;

    $stmt = $pdo->prepare($update_sql);
    $stmt->execute($params);

    // Delete existing line items
    $stmt = $pdo->prepare("DELETE FROM quote_line_items WHERE quote_id = ?");
    $stmt->execute([$quote_id]);

    // Insert new line items
    $stmt = $pdo->prepare("
        INSERT INTO quote_line_items (
            quote_id, item_number, quantity, description,
            unit_price, vat_applicable, vat_amount, line_total,
            item_id, item_name
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($line_items as $item) {
        // Recalculated rows only; client money figures are never used.
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

    // Phase 4: Log audit trail if finalized was edited
    if ($quote['status'] === 'finalized' && function_exists('logDocumentEdit')) {
        logDocumentEdit('quote', $quote_id, $quote['quote_number'], [
            'edited_by' => $_SESSION['full_name'] ?? 'Unknown',
            'status' => 'finalized',
            'action' => 'edited_finalized_quote'
        ]);
    }

    $pdo->commit();

    header("Location: ../pages/view-quote.php?id=" . $quote_id . "&updated=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Quote update error: " . $e->getMessage());
    $redirect_id = isset($quote_id) ? $quote_id : '';
    header("Location: ../pages/edit-quote.php?id=" . $redirect_id . "&error=" . urlencode($e->getMessage()));
    exit;
}
?>