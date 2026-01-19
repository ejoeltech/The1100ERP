<?php
require_once __DIR__ . '/../config.php';

try {
    echo "Starting template population...\n";

    // 1. Ensure Category Exists
    $categoryName = 'Solar Installation';
    $stmt = $pdo->prepare("SELECT id FROM readymade_quote_categories WHERE category_name = ?");
    $stmt->execute([$categoryName]);
    $category = $stmt->fetch();

    if (!$category) {
        echo "Creating category: $categoryName\n";
        $stmt = $pdo->prepare("INSERT INTO readymade_quote_categories (category_name, description, is_active) VALUES (?, ?, 1)");
        $stmt->execute([$categoryName, 'Solar power system installations']);
        $categoryId = $pdo->lastInsertId();
    } else {
        echo "Category exists: $categoryName (ID: {$category['id']})\n";
        $categoryId = $category['id'];
    }

    // 2. Define Template Data
    $templateName = '8kVA Hybrid Solar System';
    $items = [
        [
            'description' => '8 kva Hybrid Inverter',
            'quantity' => 1,
            'unit_price' => 700000.00,
            'vat_applicable' => 0
        ],
        [
            'description' => '10KWH Lithium Battery',
            'quantity' => 1,
            'unit_price' => 2100000.00,
            'vat_applicable' => 0
        ],
        [
            'description' => '620W solar panels',
            'quantity' => 12,
            'unit_price' => 135000.00,
            'vat_applicable' => 0
        ],
        [
            'description' => 'Instalation acccessories',
            'quantity' => 1,
            'unit_price' => 420000.00,
            'vat_applicable' => 0
        ],
        [
            'description' => 'Installation',
            'quantity' => 1,
            'unit_price' => 350000.00,
            'vat_applicable' => 0
        ]
    ];

    // Calculate Totals
    $subtotal = 0;
    $totalVat = 0;

    foreach ($items as $item) {
        $lineTotal = $item['quantity'] * $item['unit_price'];
        $subtotal += $lineTotal;
        // VAT is 0 based on user example
    }
    $grandTotal = $subtotal + $totalVat;

    // 3. Insert Template (Check if exists first to avoid duplicates)
    $stmt = $pdo->prepare("SELECT id FROM readymade_quote_templates WHERE template_name = ?");
    $stmt->execute([$templateName]);
    $existingTemplate = $stmt->fetch();

    if ($existingTemplate) {
        echo "Template already exists: $templateName. Skipping insertion.\n";
        $templateId = $existingTemplate['id'];
    } else {
        echo "Creating template: $templateName\n";
        $stmt = $pdo->prepare("
            INSERT INTO readymade_quote_templates 
            (category_id, template_name, description, subtotal, total_vat, grand_total, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $categoryId,
            $templateName,
            'Complete 8kVA Hybrid Solar System installation package',
            $subtotal,
            $totalVat,
            $grandTotal
        ]);
        $templateId = $pdo->lastInsertId();

        // 4. Insert Items
        echo "Inserting items...\n";
        $itemStmt = $pdo->prepare("
            INSERT INTO readymade_quote_template_items 
            (template_id, item_number, quantity, description, unit_price, vat_applicable, vat_amount, line_total) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $itemNumber = 1;
        foreach ($items as $item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];
            $vatAmount = 0; // Assuming 0 VAT for now as per example

            $itemStmt->execute([
                $templateId,
                $itemNumber++,
                $item['quantity'],
                $item['description'],
                $item['unit_price'],
                $item['vat_applicable'],
                $vatAmount,
                $lineTotal
            ]);
        }
        echo "Items inserted successfully.\n";
    }

    echo "Done!\n";

} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>