<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';
require_once '../../includes/ai-json.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$businessType = trim($input['business_type'] ?? '');

if (empty($businessType)) {
    echo json_encode(['success' => false, 'message' => 'Please describe what you service/install.']);
    exit;
}

try {
    $prompt = "Suggest 10-15 internal tools, instruments and consumables a \"$businessType\" business in Nigeria would keep in its own accessories store for servicing and installations (NOT items sold to customers).
    Context: prices in Naira (NGN). Include hand tools, testing instruments, cables/consumables, safety gear and commonly replaced service parts.

    Output a JSON ARRAY. Each item must have:
    - name (clear item name)
    - description (short info, mention service/install use)
    - category (logical grouping like 'Hand Tools', 'Testing Instruments', 'Cables & Consumables', 'Safety Gear', 'Spare Parts')
    - unit_cost (realistic current average cost price in Naira)
    - unit (pcs, meters, set, roll, box, etc.)
    - stock_quantity (sensible starting stock number)
    - minimum_stock (reorder level)
    - location (suggested storage label, e.g. 'Shelf A1')

    JSON ONLY.";

    // 10-15 items x 8 fields overruns the 2048 default; allow room + retry.
    $items = aiCallJson($prompt, "You are a field-service inventory expert for Nigerian solar/electrical businesses. Output strict JSON array.", ['max_tokens' => 4000]);

    echo json_encode(['success' => true, 'data' => $items]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
