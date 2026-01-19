<?php
// API to get unpaid invoices for a customer
// GET /api/customers/get-unpaid-invoices.php?customer_id=123

include '../../config.php';
include '../../includes/session-check.php';

$customer_id = $_GET['customer_id'] ?? null;

if (!$customer_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID required']);
    exit;
}

try {
    // Get customer's current credit balance
    $stmt = $pdo->prepare("SELECT account_balance FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $credit_balance = $stmt->fetchColumn() ?: 0;

    // Get unpaid or partial invoices
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            invoice_number, 
            invoice_date, 
            grand_total, 
            amount_paid, 
            balance_due 
        FROM invoices 
        WHERE customer_id = ? 
        AND status != 'paid' 
        AND deleted_at IS NULL
        ORDER BY invoice_date ASC
    ");
    $stmt->execute([$customer_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'credit_balance' => (float) $credit_balance,
        'invoices' => $invoices
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>