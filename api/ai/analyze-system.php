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

try {
    $analysis = analyzeSystem($input);
    echo json_encode(['success' => true, 'analysis' => $analysis]);
} catch (Exception $e) {
    error_log("System Analysis Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function analyzeSystem($config)
{
    // Build comprehensive system analysis prompt
    $systemSpecs = [
        'project' => [
            'name' => $config['project_name'] ?? 'Unnamed Project',
            'location' => $config['location'] ?? 'Unknown',
            'setup_type' => $config['setup_type'] ?? 'solar',
        ],
        'inverter' => $config['inverter'] ?? [],
        'panels' => $config['panels'] ?? [],
        'batteries' => $config['batteries'] ?? [],
        'system_voltage' => $config['system_voltage'] ?? '48V',
    ];

    $prompt = "You are a Professional Solar System Engineer. Analyze this complete solar power system and provide comprehensive technical recommendations.

System Specifications:
" . json_encode($systemSpecs, JSON_PRETTY_PRINT) . "

Provide a complete analysis including:
1. Calculate optimal solar array arrangement (strings x panels per string)
2. Determine battery bank configuration (series/parallel)
3. Calculate total system capacity
4. Estimate daily energy production (assume 5.5 peak sun hours for Nigeria)
5. Calculate recommended wire gauges (AWG) for PV and battery connections
6. Determine appropriate breaker/fuse ratings
7. Estimate costs (use typical Nigerian market prices in NGN)
8. Identify any safety warnings or compatibility issues

IMPORTANT: Return ONLY valid JSON with NO markdown formatting.

JSON Structure:
{
    \"safe\": boolean,
    \"pv_capacity\": \"total kWp\",
    \"battery_capacity\": \"totalAh @ voltage\",
    \"daily_production\": \"kWh/day\",
    \"backup_hours\": \"hours or N/A\",
    \"solar_arrangement\": {
        \"strings\": number,
        \"panels_per_string\": number,
        \"total_voc\": \"voltage\",
        \"total_current\": \"amperage\",
        \"explanation\": \"detailed explanation\"
    },
    \"battery_arrangement\": {
        \"series\": number,
        \"parallel\": number,
        \"total_voltage\": \"voltage\",
        \"total_capacity\": \"capacity\",
        \"explanation\": \"detailed explanation\"
    },
    \"wire_gauge\": {
        \"pv_to_controller\": \"AWG size\",
        \"battery_to_inverter\": \"AWG size\",
        \"explanation\": \"detailed explanation\"
    },
    \"breaker_ratings\": {
        \"pv_input\": \"rating in A\",
        \"battery_breaker\": \"rating in A\",
        \"explanation\": \"detailed explanation\"
    },
    \"cost_estimate\": {
        \"equipment\": \"₦X,XXX,XXX\",
        \"installation\": \"₦XXX,XXX\",
        \"total\": \"₦X,XXX,XXX\"
    },
    \"warnings\": [\"warning 1\", \"warning 2\"],
    \"installation_notes\": [\"note 1\", \"note 2\"]
}";

    $response = callGroqAPI($prompt, 'json');
    $analysis = extractJSON($response);

    if (!$analysis) {
        error_log("Failed to parse AI response: " . substr($response, 0, 500));
        throw new Exception("AI returned invalid response");
    }

    return $analysis;
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