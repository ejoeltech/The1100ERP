<?php
header('Content-Type: application/json');
require_once '../../includes/session-check.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$proposalId = $input['id'] ?? null;

if (!$proposalId) {
    echo json_encode(['success' => false, 'error' => 'Proposal ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get proposal
    $stmt = $pdo->prepare("SELECT * FROM proposals WHERE id = ?");
    $stmt->execute([$proposalId]);
    $proposal = $stmt->fetch();

    if (!$proposal) {
        throw new Exception("Proposal not found");
    }

    // 2a. Get Salesperson Name
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $salesperson = $user['full_name'] ?? 'System Admin';

    // 2b. Generate Quote Number
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM quotes");
    $row = $stmt->fetch();
    $nextId = ($row['max_id'] ?? 0) + 1;
    $quoteNumber = 'QT-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

    // 3. Create Quote
    // quote_title, customer_name, salesperson are REQUIRED
    $quoteTitle = $proposal['title'] ?: 'Solar System Proposal';
    $customerName = $proposal['customer_name'] ?: 'Guest Customer';

    $stmt = $pdo->prepare("INSERT INTO quotes 
        (quote_number, quote_title, customer_name, salesperson, quote_date, status, payment_terms, delivery_period, total_vat, grand_total, subtotal) 
        VALUES (?, ?, ?, ?, CURDATE(), 'draft', ?, ?, 0, 0, 0)");

    $stmt->execute([
        $quoteNumber,
        $quoteTitle,
        $customerName,
        $salesperson,
        '100% upfront',
        'Immediate'
    ]);
    $quoteId = $pdo->lastInsertId();

    // 4. Add Line Items (Detailed Breakdown)
    $specs = json_decode($proposal['system_specs'], true);

    // Helper to extract quantity from string (e.g. "8x 600W Panels" -> Qty: 8, Desc: "600W Panels")
    function parseQty($str)
    {
        $qty = 1;
        $desc = trim($str);

        // Regex for "Nx", "N x", "N units" at start
        if (preg_match('/^(\d+)\s*(x|units?|pcs?)\s+(.*)$/i', $desc, $matches)) {
            $qty = intval($matches[1]);
            $desc = trim($matches[3]);
        }

        return ['qty' => $qty, 'desc' => $desc];
    }

    // Parse items
    $inv = parseQty($specs['inverter'] ?? 'TBD');
    $bat = parseQty($specs['batteries'] ?? 'TBD');
    $pan = parseQty($specs['panels'] ?? 'TBD');

    // Define the 5 items
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
            'desc' => "Installation Accessories (Cables, Racks, Breakers, Disconnects)",
            'qty' => 1
        ],
        [
            'desc' => "Professional Installation & Commissioning",
            'qty' => 1
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO quote_line_items (quote_id, quantity, unit_price, description, vat_applicable, line_total) VALUES (?, ?, 0, ?, 0, 0)");

    foreach ($items as $item) {
        $stmt->execute([$quoteId, $item['qty'], $item['desc']]);
    }

    // 5. Update Proposal status
    $stmt = $pdo->prepare("UPDATE proposals SET status = 'converted', converted_quote_id = ? WHERE id = ?");
    $stmt->execute([$quoteId, $proposalId]);

    // Also, update the quote's terms/notes with the full proposal text if possible?
    // Let's assume there is a 'notes' or 'customer_notes' field.
    // Checking schema earlier... 'customer_notes' exists in typical schemas.
    // Let's try updating customer_notes
    // $stmt = $pdo->prepare("UPDATE quotes SET customer_notes = ? WHERE id = ?");
    // $stmt->execute([$proposal['content'], $quoteId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'quote_id' => $quoteId, 'message' => 'Quote created successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
