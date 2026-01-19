<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';

echo "<h2>Debug Mode</h2>";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Use POST method");
}

try {
    $pdo->beginTransaction();

    echo "Transaction started.<br>";
    echo "POST Data:<pre>" . print_r($_POST, true) . "</pre>";

    $template_name = $_POST['template_name'] ?? '';
    $description = $_POST['template_description'] ?? '';
    $quote_title = $_POST['quote_title'] ?? '';
    $payment_terms = $_POST['payment_terms'] ?? 'Terms';
    $subtotal = $_POST['subtotal'] ?? 0;
    $total_vat = $_POST['total_vat'] ?? 0;
    $grand_total = $_POST['grand_total'] ?? 0;

    echo "Inserting template...<br>";
    // Insert template record
    $stmt = $pdo->prepare("
        INSERT INTO readymade_quote_templates 
        (template_name, description, payment_terms, grand_total, is_active, default_project_title, subtotal, total_vat, category_id) 
        VALUES (?, ?, ?, ?, 1, ?, ?, ?, 1)
    ");

    $params = [
        $template_name,
        $description,
        $payment_terms,
        $grand_total,
        $quote_title,
        $subtotal,
        $total_vat
    ];
    echo "Params: <pre>" . print_r($params, true) . "</pre>";

    $stmt->execute($params);
    $template_id = $pdo->lastInsertId();
    echo "Template inserted ID: $template_id<br>";

    // Insert line items
    if (isset($_POST['line_items']) && is_array($_POST['line_items'])) {
        echo "Processing line items...<br>";
        $stmt = $pdo->prepare("
            INSERT INTO readymade_quote_template_items 
            (template_id, item_number, quantity, description, unit_price, vat_applicable, vat_amount, line_total) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $item_number = 1;
        foreach ($_POST['line_items'] as $item) {
            echo "Inserting item $item_number...<br>";
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
    } else {
        echo "No line items found.<br>";
    }

    $pdo->commit();
    echo "<h3>SUCCESS! Transaction committed.</h3>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>ERROR: " . $e->getMessage() . "</h2>";
    echo "Trace: <pre>" . $e->getTraceAsString() . "</pre>";
    $pdo->rollBack();
}
