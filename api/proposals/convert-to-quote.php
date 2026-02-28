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

    $pdo->beginTransaction();

    // 2a. Get Salesperson Name
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $salesperson = $user['full_name'] ?? 'System Admin';

    // 2b. Generate Quote Number
    // Logic: Get max numeric suffix, ignore year? Or simplified?
    // Let's use simplified max(id) for now to ensure uniqueness combined with year
    // Better: Check last quote number format
    $stmt = $pdo->query("SELECT quote_number FROM quotes ORDER BY id DESC LIMIT 1");
    $lastQuoteRaw = $stmt->fetchColumn();

    $nextId = 1;
    if ($lastQuoteRaw && preg_match('/QT-\d{4}-(\d+)/', $lastQuoteRaw, $matches)) {
        $nextId = intval($matches[1]) + 1;
    }
    $quoteNumber = 'QT-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

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

    $stmtLine = $pdo->prepare("INSERT INTO quote_line_items (quote_id, quantity, unit_price, description, vat_applicable, line_total) VALUES (?, ?, 0, ?, 0, 0)");

    foreach ($items as $item) {
        $stmtLine->execute([$quoteId, $item['qty'], $item['desc']]);
    }

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
