<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';

header('Content-Type: application/json');

// Only logged-in users
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$businessType = $input['business_type'] ?? '';

if (empty($businessType)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a business type.']);
    exit;
}

try {
    $prompt = "Generate a list of 10-15 common inventory items for a business of type: \"$businessType\".
    Context: Nigerian Market prices in Naira (NGN).
    
    Output a JSON ARRAY of items. Each item must have:
    - name (clear item name)
    - description (short info)
    - category_name (logical grouping like 'Electronics', 'Installation', etc.)
    - price (realistic current average selling price in Naira)
    - unit (pcs, meters, set, etc.)
    - cost_price (estimated cost price, usually 15-25% lower than selling price)
    
    JSON ONLY.";

    $aiResponse = callGroqAPI($prompt, "You are a Business Inventory Expert. Output strict JSON array.");

    // Clean up Markdown Code Blocks
    $jsonStr = $aiResponse;
    if (preg_match('/```(?:json)?\s*(\[.*\])\s*```/s', $aiResponse, $matches)) {
        $jsonStr = $matches[1];
    } elseif (preg_match('/\[.*\]/s', $aiResponse, $matches)) {
        $jsonStr = $matches[0];
    }

    $items = json_decode($jsonStr, true);

    if (!$items) {
        throw new Exception("Failed to parse AI response. Raw: " . $aiResponse);
    }

    echo json_encode(['success' => true, 'data' => $items]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
