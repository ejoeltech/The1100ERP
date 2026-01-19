<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/readymade-quotes.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $template_id = $_POST['template_id'] ?? null;
    $template_name = $_POST['template_name'] ?? '';
    $description = $_POST['template_description'] ?? '';
    $payment_terms = $_POST['payment_terms'] ?? DEFAULT_PAYMENT_TERMS;
    $subtotal = $_POST['subtotal'] ?? 0;
    $total_vat = $_POST['total_vat'] ?? 0;
    $grand_total = $_POST['grand_total'] ?? 0;

    if (!$template_id) {
        throw new Exception('Template ID required');
    }

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

    // Insert new line items
    if (isset($_POST['line_items']) && is_array($_POST['line_items'])) {
        $stmt = $pdo->prepare("
            INSERT INTO readymade_quote_template_items 
            (template_id, item_number, quantity, description, unit_price, vat_applicable, vat_amount, line_total) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $item_number = 1;
        foreach ($_POST['line_items'] as $item) {
            $stmt->execute([
                $template_id,
                $item_number++,
                $item['quantity'],
                $item['description'],
                $item['unit_price'],
                isset($item['vat_applicable']) ? 1 : 0,
                $item['vat_amount'] ?? 0,
                $item['line_total'] ?? 0
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