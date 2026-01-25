<?php
define('IS_API', true);
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$description = $input['description'] ?? '';

if (!$type || !$description) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type and description required']);
    exit;
}

try {
    $specs = extractSpecsWithAI($type, $description, $input);
    echo json_encode(['success' => true, 'specs' => $specs]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function extractSpecsWithAI($type, $description, $input)
{
    switch ($type) {
        case 'inverter':
            return extractInverterSpecs($description);
        case 'panel':
            return extractPanelSpecs($description, $input['count'] ?? 1);
        case 'battery':
            return extractBatterySpecs($description, $input['count'] ?? 1);
        default:
            throw new Exception("Unknown component type: $type");
    }
}

function extractInverterSpecs($description)
{
    $prompt = "Extract the technical specifications from this inverter description: \"$description\"
    
Return ONLY valid JSON with these fields (use null if not found):
{
    \"brand\": \"manufacturer name\",
    \"capacity_kva\": number,
    \"mppt_max_voltage\": number,
    \"mppt_max_current\": number,
    \"mppt_max_wattage\": number,
    \"input_voltage\": number
}";

    $response = callGroqAPI($prompt, 'json');
    return extractJSON($response) ?: [];
}

function extractPanelSpecs($description, $count)
{
    $prompt = "Extract the technical specifications from this solar panel description: \"$description\"
    
Return ONLY valid JSON with these fields (use null if not found):
{
    \"brand\": \"manufacturer name\",
    \"wattage\": number,
    \"voc\": number,
    \"vmp\": number,
    \"isc\": number,
    \"imp\": number
}";

    $response = callGroqAPI($prompt, 'json');
    return extractJSON($response) ?: [];
}

function extractBatterySpecs($description, $count)
{
    $prompt = "Extract the technical specifications from this battery description: \"$description\"
    
Determine if it's lithium or lead_acid based on the description.

Return ONLY valid JSON with these fields (use null if not found):
{
    \"brand\": \"manufacturer name\",
    \"capacity_ah\": number,
    \"voltage\": number,
    \"type\": \"lithium\" or \"lead_acid\"
}";

    $response = callGroqAPI($prompt, 'json');
    return extractJSON($response) ?: [];
}

function extractJSON($response)
{
    // First, try direct parsing
    $data = json_decode($response, true);
    if ($data !== null) {
        return $data;
    }

    // If that fails, try to extract from markdown code blocks
    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $matches)) {
        $data = json_decode($matches[1], true);
        if ($data !== null) {
            return $data;
        }
    }

    // Try to find any JSON object in the response
    if (preg_match('/(\{.*\})/s', $response, $matches)) {
        $data = json_decode($matches[1], true);
        if ($data !== null) {
            return $data;
        }
    }

    return null;
}
?>