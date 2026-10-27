<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';
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
    $prompt = "Extract product details from this text into JSON.
    Context: Nigerian Solar Equipment Market.
    Fields: name, description (short), quantity (int), price_ngn (float, numeric only), category (Panel, Inverter, Battery, Charge Controller, Cable, Other).
    
    TEXT: \"$text\"
    
    Output JSON ONLY.";

    // Small object, but reasoning models can burn the budget before emitting.
    $data = aiCallJson($prompt, "You are a data extraction assistant. Output strict JSON.", ['max_tokens' => 1200, 'temperature' => 0.1]);

    if (!is_array($data) || $data === []) {
        throw new Exception("Failed to parse AI response");
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>