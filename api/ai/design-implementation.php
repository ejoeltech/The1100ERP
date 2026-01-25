<?php
// Allow public access - no session required
session_start();
define('IS_API', true);
define('ALLOW_PUBLIC', true);

require_once '../../includes/db.php';
require_once '../../includes/groq-config.php';
require_once '../../includes/ai-rate-limiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$startTime = microtime(true);

try {
    // Initialize rate limiter
    $userId = $_SESSION['user_id'] ?? null;
    $rateLimiter = new AiRateLimiter('system_designer', $userId);

    // Check rate limit and cache
    $limitCheck = $rateLimiter->checkLimit($input);

    // If cached, return immediately
    if ($limitCheck['cached']) {
        echo json_encode([
            'success' => true,
            'recommendation' => $limitCheck['data'],
            'cached' => true,
            'remaining' => $limitCheck['remaining']
        ]);
        exit;
    }

    $requestHash = AiRateLimiter::generateRequestHash($input);

    // Generate recommendation
    $recommendation = generateImplementationRecommendation($input);

    // Calculate processing time
    $processingTime = microtime(true) - $startTime;

    // Estimate tokens
    $tokensUsed = (strlen(json_encode($input)) + strlen(json_encode($recommendation))) / 4;

    // Log usage
    $rateLimiter->logUsage($requestHash, $tokensUsed, $processingTime, true);

    // Cache response
    $rateLimiter->cacheResponse($requestHash, $input, $recommendation);

    echo json_encode([
        'success' => true,
        'recommendation' => $recommendation,
        'cached' => false,
        'remaining' => $rateLimiter->getRemainingRequests()
    ]);

} catch (Exception $e) {
    // Log failed attempt
    if (isset($rateLimiter)) {
        $processingTime = microtime(true) - $startTime;
        $rateLimiter->logUsage($requestHash ?? '', 0, $processingTime, false, $e->getMessage());
    }

    error_log("Design Implementation Error: " . $e->getMessage());
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function generateImplementationRecommendation($data)
{
    // Build comprehensive prompt for Mode 2
    $systemType = $data['system_type'] ?? 'hybrid';
    $panelCount = $data['panel_count'] ?? 0;

    $systemSpecs = [
        'mode' => 'implementation',
        'system_type' => $systemType,
        'panel_count' => $panelCount,
    ];

    // Add parameters based on system type
    if ($systemType === 'hybrid') {
        $systemSpecs['inverter'] = [
            'capacity' => $data['inverter_capacity'] ?? 0,
            'controller_capacity' => $data['controller_capacity'] ?? 0,
            'nominal_voltage' => $data['nominal_voltage'] ?? 0,
            'max_voltage' => $data['max_voltage'] ?? 0,
            'voltage_range' => $data['voltage_range'] ?? '',
            'max_current' => $data['max_current'] ?? 0,
            'battery_voltage' => $data['battery_voltage'] ?? 0,
        ];
    } else {
        $systemSpecs['controller'] = [
            'inverter_capacity' => $data['inverter_capacity'] ?? 0,
            'controller_capacity' => $data['controller_capacity'] ?? 0,
            'battery_voltage' => $data['battery_voltage'] ?? 0,
            'max_voltage' => $data['max_voltage'] ?? 0,
        ];
    }

    $systemSpecs['panel'] = [
        'power_w' => $data['panel_power'] ?? 0,
        'voc_v' => $data['panel_voc'] ?? 0,
        'isc_a' => $data['panel_isc'] ?? 0,
    ];

    $prompt = "You are a Professional Solar Installation Engineer. The installer has already purchased {$panelCount} solar panels and needs installation guidance.

System Specifications:
" . json_encode($systemSpecs, JSON_PRETTY_PRINT) . "

Your Tasks:
1. Recommend the OPTIMAL arrangement for the {$panelCount} panels available
2. VALIDATE that this configuration is safe and complies with controller limits
3. Calculate DC circuit breaker ratings (considering 125% safety factor per NEC)
4. Calculate wire sizing for both PV-to-controller and battery-to-inverter connections
5. Provide installation guidance and any necessary warnings

CRITICAL SAFETY RULES:
- VOC×panels_per_string MUST NOT exceed controller max voltage
- Total panel current MUST NOT exceed controller max current
- If the configuration is unsafe, provide clear warnings and suggest alternatives
- Always round down for safety

Return ONLY valid JSON with NO markdown formatting:
{
    \"safe\": boolean,
    \"arrangement\": {
        \"configuration\": \"e.g., 2 strings of 5 panels in series\",
        \"strings\": number,
        \"panels_per_string\": number,
        \"total_voc\": \"XXX V\",
        \"total_current\": \"XX A\",
        \"explanation\": \"detailed explanation and validation notes\"
    },
    \"breaker\": {
        \"rating\": \"XX A\",
        \"explanation\": \"calculation details\"
    },
    \"wiring\": {
        \"pv_wire\": \"X mm² (XX AWG)\",
        \"battery_wire\": \"X mm² (XX AWG)\",
        \"explanation\": \"why these wire sizes based on current and distance\"
    },
    \"summary\": \"HTML formatted installation summary\",
    \"warnings\": [\"warning 1\", \"warning 2\"]
}";

    $response = callGroqAPI($prompt, 'json');
    $recommendation = extractJSON($response);

    if (!$recommendation) {
        error_log("Failed to parse recommendation: " . substr($response, 0, 500));
        throw new Exception("Failed to generate recommendation");
    }

    return $recommendation;
}

function extractJSON($response)
{
    $data = json_decode($response, true);
    if ($data !== null) {
        return $data;
    }

    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $matches)) {
        $data = json_decode($matches[1], true);
        if ($data !== null) {
            return $data;
        }
    }

    if (preg_match('/(\{.*\})/s', $response, $matches)) {
        $data = json_decode($matches[1], true);
        if ($data !== null) {
            return $data;
        }
    }

    return null;
}
?>