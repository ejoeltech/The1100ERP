<?php
/**
 * AI Spec Recommender Endpoint
 * Analyzes house details and recommends solar system specifications
 */

header('Content-Type: application/json');
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';

// Check permissions
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get input
$data = json_decode(file_get_contents('php://input'), true);

// Validate inputs
if (empty($data['appliances'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Please list the appliances to be powered.']);
    exit;
}

try {
    // Construct System Prompt
    $systemPrompt = "You are an expert Solar Energy Engineer in Nigeria.
    Your task is to analyze a client's power needs based on their appliances and house type, and recommend the MOST APPROPRIATE Inverter, Battery, and Solar Panel specifications.
    
    Inventory Constraints (ONLY recommend from these ranges):
    - Inverters: 1kVA to 300kVA (Single Phase or Three Phase as needed).
    - Batteries (Tubular): 220Ah 12V Tall Tubular Batteries (Banks of 1 to 30).
    - Batteries (Lithium): 3kWh to 200kWh Lithium Iron Phosphate (LiFePO4) stacks (48V or High Voltage).
    - Panels: 450W, 550W, or 600W Monocrystalline.

    Logic for Battery Selection:
    - If user prefers 'Tubular': Recommend 220Ah 12V batteries (e.g., '4x 220Ah 12V Tubular Batteries').
    - If user prefers 'Lithium': Recommend kWh capacity (e.g., '10kWh Lithium Battery Bank').
    - If no preference: Recommend Lithium for longevity/heavy use, or Tubular for budget/light use.
    
    Return ONLY a JSON object with the following keys:
    - inverter: (string) e.g., '5kVA 48V Hybrid Inverter'
    - batteries: (string) e.g., '4x 220Ah 12V Tubular Batteries'
    - panels: (string) e.g., '8x 450W Monocrystalline Panels'
    - context_summary: (string) A brief (1-2 sentence) technical summary of why this sizing was chosen.
    
    Do NOT include any markdown formatting or extra text. JUST the JSON.";

    // Construct User Prompt
    $userPrompt = "House Type: {$data['house_type']}
    Appliances: {$data['appliances']}
    Grid Availability: {$data['power_hours']} hours/day
    Battery Preference: " . ($data['battery_preference'] ?? 'Any') . "
    Priority: " . ($data['priority'] ?? 'Balanced') . "
    
    Recommend the system specs.";

    // Call Groq API
    $response = callGroqAPI($userPrompt, $systemPrompt, ['max_tokens' => 500, 'temperature' => 0.3]);

    // Extract JSON from response (in case of markdown blocks)
    if (preg_match('/\{.*\}/s', $response, $matches)) {
        $jsonStr = $matches[0];
    } else {
        $jsonStr = $response;
    }

    $recommendation = json_decode($jsonStr, true);

    if (!$recommendation) {
        throw new Exception("Failed to parse AI recommendation: $response");
    }

    echo json_encode(['success' => true, 'recommendation' => $recommendation]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>