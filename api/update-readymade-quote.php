<?php
require_once '../includes/session-check.php';

requirePermission('edit_document');
require_once '../includes/security.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/readymade-quotes.php');
    exit;
}

// Method first so direct navigation lands back on the page;
// CSRF is enforced on actual POSTs below.
requireCsrf();

try {
    $pdo->beginTransaction();

    $template_id = $_POST['template_id'] ?? null;
    $template_name = $_POST['template_name'] ?? '';
    $description = $_POST['template_description'] ?? '';
    $payment_terms = $_POST['payment_terms'] ?? DEFAULT_PAYMENT_TERMS;

    if (!$template_id) {
        throw new Exception('Template ID required');
    }

    // Recompute totals server-side; posted figures are ignored.
    $calc = recalcDocumentTotals($_POST['line_items'] ?? []);
    $line_items = $calc['items'];
    $subtotal = $calc['subtotal'];
    $total_vat = $calc['vat'];
    $grand_total = $calc['grand'];

    // Update template
    $stmt = $pdo->prepare("
        UPDATE readymade_quote_templates 
        SET 
            template_name = ?, 
            description = ?, 
            payment_terms = ?,
            grand_total = ?,
            default_project_title = ?,
            subtotal = ?,
            total_vat = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $template_name,
        $description,
        $payment_terms,
        $grand_total,
        $_POST['quote_title'] ?? '',
        $subtotal,
        $total_vat,
        $template_id
    ]);

    // Clear existing items
    $stmt = $pdo->prepare("DELETE FROM readymade_quote_template_items WHERE template_id = ?");
    $stmt->execute([$template_id]);

    // Insert new line items (already normalized and recalculated).
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
    header('Location: ../pages/readymade-quotes.php?updated=1');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Update readymade quote error: " . $e->getMessage());
    header('Location: ../pages/edit-readymade-quote.php?id=' . $template_id . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>