<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';
require_once '../../includes/ai-history.php';
require_once '../../includes/ai-json.php';

header('Content-Type: application/json');

// Only logged-in users
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$text = $input['text'] ?? '';

if (empty($text)) {
    echo json_encode(['error' => 'Empty text']);
    exit;
}

try {
    $history = getAiBusinessContext($pdo);
    $prompt = "Generate a detailed quote for a solar installation project described as: \"$text\".
    Context: Nigerian Solar Market.
    " . ($history !== '' ? "Business history: $history\n" : "") . "
    Output a JSON ARRAY of items. Each item must have:
    - name (e.g., '5kW Hybrid Inverter')
    - description (short technical specs)
    - quantity (int)
    - price_per_unit_ngn (float, realistic market price in Naira)
    
    Include: Panels, Inverter, Batteries, Installation Material (Cables/Rack), and Installation Labor.
    
    JSON ONLY.";

    // Long itemised arrays need headroom; aiCallJson retries if truncated.
    $items = aiCallJson($prompt, "You are a Solar Project Estimator. Output strict JSON array.", ['max_tokens' => 4000]);

    // Optional: Match against existing DB products to get real IDs?
    // For now, we return free text which the user can edit/save as new product if needed.

    echo json_encode(['success' => true, 'data' => $items]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>