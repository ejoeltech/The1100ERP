<?php
// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');
require_once '../../includes/session-check.php';

// Custom Error Handler to catch warnings/notices => throw exception
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $proposalId = $input['id'] ?? null;

    if (!$proposalId) {
        throw new Exception('Proposal ID is required');
    }

    // Check if Proposal exists
    $stmt = $pdo->prepare("SELECT * FROM proposals WHERE id = ?");
    $stmt->execute([$proposalId]);
    $proposal = $stmt->fetch();

    if (!$proposal) {
        throw new Exception("Proposal not found");
    }

    require_once '../../includes/helpers.php';

    $pdo->beginTransaction();

    // 2a. Get Salesperson Name
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $salesperson = $user['full_name'] ?? 'System Admin';

    // 2b. Generate Quote Number using standard helper
    $quoteNumber = generateQuoteNumber($pdo);

    // 3. Create Quote
    // quote_title, customer_name, salesperson are REQUIRED
    $quoteTitle = $proposal['title'] ?: 'Solar System Proposal';

    // FIX: Check if customer_name column exists or use placeholders if missing
    // Assuming schema is correct from previous fixes.
    $customerName = isset($proposal['customer_name']) ? $proposal['customer_name'] : 'Guest Customer'; // Handle missing key

    $stmt = $pdo->prepare("INSERT INTO quotes 
        (quote_number, quote_title, customer_name, salesperson, quote_date, status, payment_terms, delivery_period, total_vat, grand_total, subtotal) 
        VALUES (?, ?, ?, ?, CURDATE(), 'draft', ?, ?, 0, 0, 0)");

    $stmt->execute([
        $quoteNumber,
        $quoteTitle,
        $customerName,
        $salesperson,
        '100% upfront', // Payment Terms
        'Immediate'     // Delivery Period
    ]);
    $quoteId = $pdo->lastInsertId();

    // 4. Add Line Items (Detailed Breakdown)
    $specs = json_decode($proposal['system_specs'], true) ?? [];

    // Helper to extract quantity from string (e.g. "8x 600W Panels" -> Qty: 8, Desc: "600W Panels")
    if (!function_exists('parseQty')) {
        function parseQty($str)
        {
            $qty = 1;
            $desc = trim((string) $str);

            // Regex for "Nx", "N x", "N units" at start
            if (preg_match('/^(\d+)\s*(x|units?|pcs?)\s+(.*)$/i', $desc, $matches)) {
                $qty = intval($matches[1]);
                $desc = trim($matches[3]);
            }
            return ['qty' => $qty, 'desc' => $desc];
        }
    }

    // Parse items
    $inv = parseQty($specs['inverter'] ?? 'TBD');
    $bat = parseQty($specs['batteries'] ?? 'TBD');
    $pan = parseQty($specs['panels'] ?? 'TBD');

    // Define the items
    $items = [
        [
            'desc' => "Inverter System: " . $inv['desc'],
            'qty' => $inv['qty']
        ],
        [
            'desc' => "Battery Storage: " . $bat['desc'],
            'qty' => $bat['qty']
        ],
        [
            'desc' => "Solar Panels: " . $pan['desc'],
            'qty' => $pan['qty']
        ],
        [
            'desc' => "Installation Accessories & Logistics",
            'qty' => 1
        ],
        [
            'desc' => "Professional Installation & Commissioning",
            'qty' => 1
        ]
    ];

    // Define the items with price lookup
    $finalItems = [];
    foreach ($items as $item) {
        $desc = $item['desc'];
        $unitPrice = 0;
        $itemId = null;
        $itemName = null;

        // Simple keyword-based price lookup for common solar components
        $searchTerms = [];
        if (stripos($desc, 'Inverter') !== false) $searchTerms[] = 'Inverter';
        if (stripos($desc, 'Battery') !== false) $searchTerms[] = 'Battery';
        if (stripos($desc, 'Panel') !== false) $searchTerms[] = 'Panel';
        
        if (!empty($searchTerms)) {
            // Try to find an item in the store that matches these keywords
            $likeQuery = '%' . implode('%', $searchTerms) . '%';
            $stmtItem = $pdo->prepare("SELECT id, name, price FROM items WHERE name LIKE ? OR description LIKE ? ORDER BY price DESC LIMIT 1");
            $stmtItem->execute([$likeQuery, $likeQuery]);
            $foundItem = $stmtItem->fetch();
            
            if ($foundItem) {
                $unitPrice = $foundItem['price'];
                $itemId = $foundItem['id'];
                $itemName = $foundItem['name'];
            }
        }

        // Defaults for installation if not found
        if ($unitPrice == 0) {
            if (stripos($desc, 'Installation') !== false) $unitPrice = 250000;
            if (stripos($desc, 'Logistics') !== false) $unitPrice = 150000;
        }

        $lineTotal = $unitPrice * $item['qty'];

        $finalItems[] = [
            'desc' => $desc,
            'qty' => $item['qty'],
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'item_id' => $itemId,
            'item_name' => $itemName
        ];
    }

    $stmtLine = $pdo->prepare("
        INSERT INTO quote_line_items (
            quote_id, item_number, quantity, description, 
            unit_price, vat_applicable, line_total, item_id, item_name
        ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
    ");

    $itemNum = 1;
    $subtotal = 0;
    foreach ($finalItems as $fItem) {
        $stmtLine->execute([
            $quoteId,
            $itemNum,
            $fItem['qty'],
            $fItem['desc'],
            $fItem['unit_price'],
            $fItem['line_total'],
            $fItem['item_id'],
            $fItem['item_name']
        ]);
        $subtotal += $fItem['line_total'];
        $itemNum++;
    }

    // 4.5 Update Quote Totals
    $totalVat = $subtotal * 0.075;
    $grandTotal = $subtotal + $totalVat;
    $stmt = $pdo->prepare("UPDATE quotes SET subtotal = ?, total_vat = ?, grand_total = ? WHERE id = ?");
    $stmt->execute([$subtotal, $totalVat, $grandTotal, $quoteId]);

    // 5. Update Proposal status
    $stmt = $pdo->prepare("UPDATE proposals SET status = 'converted', converted_quote_id = ? WHERE id = ?");
    $stmt->execute([$quoteId, $proposalId]);

    $pdo->commit();


    // Clear buffer and send output
    ob_end_clean();
    echo json_encode(['success' => true, 'quote_id' => $quoteId, 'message' => 'Quote created successfully']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error for debugging
    error_log("Convert Quote Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    // Clear buffer to remove any partial HTML output
    ob_end_clean();

    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
