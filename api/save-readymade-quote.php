<?php
require_once '../includes/session-check.php';

requirePermission('create_document');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/readymade-quotes.php');
    exit;
}

// WP4: manual session bootstrap, so enforce CSRF explicitly. The form already
// renders csrfField(); this closes the last write endpoint without the check.
requireCsrf();

try {
    $pdo->beginTransaction();

    $template_name = $_POST['template_name'] ?? '';
    $description = $_POST['template_description'] ?? '';
    $quote_title = $_POST['quote_title'] ?? '';
    $payment_terms = $_POST['payment_terms'] ?? DEFAULT_PAYMENT_TERMS;

    // Recompute every figure from quantity × unit price + VAT. Posted
    // subtotals, VAT amounts and line totals are never trusted.
    $calc = recalcDocumentTotals($_POST['line_items'] ?? []);
    $line_items = $calc['items'];
    $subtotal = $calc['subtotal'];
    $total_vat = $calc['vat'];
    $grand_total = $calc['grand'];

    // Insert template record
    $stmt = $pdo->prepare("
        INSERT INTO readymade_quote_templates 
        (template_name, description, payment_terms, grand_total, is_active, default_project_title, subtotal, total_vat, category_id) 
        VALUES (?, ?, ?, ?, 1, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $template_name,
        $description,
        $payment_terms,
        $grand_total,
        $quote_title,
        $subtotal,
        $total_vat
    ]);
    $template_id = $pdo->lastInsertId();

    // Insert line items (already normalized and recalculated).
    if (!empty($line_items)) {
        $stmt = $pdo->prepare("
            INSERT INTO readymade_quote_template_items 
            (template_id, item_number, quantity, description, unit_price, vat_applicable, vat_amount, line_total) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($line_items as $item) {
            $stmt->execute([
                $template_id,
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

    $pdo->commit();
    header('Location: ../pages/readymade-quotes.php?created=1');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Save readymade quote error: " . $e->getMessage());
    header('Location: ../pages/create-readymade-quote.php?error=' . urlencode($e->getMessage()));
    exit;
}