<?php
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$mode = $input['mode'] ?? 'basic';

if (!isset($input['inverter_id']) || !isset($input['panel_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Inverter and Panel are required']);
    exit;
}

try {
    if ($mode === 'basic') {
        // BASIC MODE - Original functionality
        handleBasicMode($pdo, $input);
    } else {
        // ADVANCED MODE - Detailed technical analysis
        handleAdvancedMode($pdo, $input);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleBasicMode($pdo, $input)
{
    // 1. Fetch Product Details
    $stmt = $pdo->prepare("SELECT name as product_name, description FROM products WHERE id = ?");

    // Inverter
    $stmt->execute([$input['inverter_id']]);
    $inverter = $stmt->fetch();
    if (!$inverter)
        throw new Exception("Inverter not found");

    // Panel
    $stmt->execute([$input['panel_id']]);
    $panel = $stmt->fetch();
    if (!$panel)
        throw new Exception("Panel not found");

    // Batteries (Optional)
    $battery = null;
    if (!empty($input['battery_id'])) {
        $stmt->execute([$input['battery_id']]);
        $battery = $stmt->fetch();
    }

    // 2. Prepare AI Prompt
    $systemConfig = [
        'inverter' => $inverter['product_name'] . ' ' . $inverter['description'],
        'panels' => [
            'model' => $panel['product_name'] . ' ' . $panel['description'],
            'per_string' => $input['panels_per_string'] ?? 1,
            'string_count' => $input['string_count'] ?? 1
        ],
        'batteries' => $battery ? [
            'model' => $battery['product_name'] . ' ' . $battery['description'],
            'count' => $input['battery_count'] ?? 1,
            'system_voltage' => $input['system_voltage'] ?? 'Unknown'
        ] : null
    ];

    $prompt = "You are a Solar Engineering Assistant. Analyze the compatibility of the following solar power system components based on their product names/descriptions.
    
    System Configuration:
    " . json_encode($systemConfig, JSON_PRETTY_PRINT) . "
    
    Task:
    1. Estimate the likely technical specifications (VOC, VMP, Amps, Max Voltage, etc.) for these specific models.
    2. Check for incompatibility issues:
       - Panel Total VOC vs Inverter Max Input Voltage.
       - Panel string currents vs Inverter Max MPPT Current.
       - Battery Bank Voltage vs Inverter Nominal Voltage.
    
    Return purely JSON output with this structure (no markdown):
    {
        \"safe\": boolean,
        \"warnings\": [\"string warning 1\", \"string warning 2\"],
        \"specs_estimated\": {
            \"inverter_max_voltage\": \"string\",
            \"panel_voc\": \"string\",
            \"total_string_voc\": \"string\"
        },
        \"recommendation\": \"string\"
    }";

    // Call Groq
    $response = callGroqAPI($prompt, 'json');

    // Extract JSON from response (handles markdown wrapping)
    $analysis = extractJSON($response);

    if (!$analysis) {
        error_log("System Designer - Failed to parse AI response: " . substr($response, 0, 500));
        throw new Exception("AI returned invalid response. Please check Groq API configuration.");
    }

    echo json_encode(['success' => true, 'analysis' => $analysis]);
}

function handleAdvancedMode($pdo, $input)
{
    // Validate required advanced fields
    $required = ['setup_type', 'solar_type', 'system_voltage'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Build detailed prompt
    $systemSpecs = [
        'setup_type' => $input['setup_type'], // 'grid' or 'solar'
        'solar_type' => $input['solar_type'], // 'hybrid' or 'charge_controller'
    ];

    // Inverter/Controller Details
    if ($input['solar_type'] === 'hybrid') {
        $systemSpecs['hybrid_inverter'] = [
            'brand' => $input['inverter_brand'] ?? 'Unknown',
            'capacity_kva' => $input['inverter_capacity'] ?? 0,
            'battery_voltage' => $input['system_voltage'],
            'mppt_max_wattage' => $input['mppt_max_wattage'] ?? 0,
            'mppt_max_voltage' => $input['mppt_max_voltage'] ?? 0,
            'mppt_nominal_voltage' => $input['mppt_nominal_voltage'] ?? 0,
            'mppt_max_current' => $input['mppt_max_current'] ?? 0,
        ];
    } else {
        $systemSpecs['charge_controller'] = [
            'brand' => $input['controller_brand'] ?? 'Unknown',
            'battery_voltage' => $input['system_voltage'],
            'max_wattage' => $input['controller_max_wattage'] ?? 0,
            'max_voltage' => $input['controller_max_voltage'] ?? 0,
            'nominal_voltage' => $input['controller_nominal_voltage'] ?? 0,
            'max_current' => $input['controller_max_current'] ?? 0,
        ];
    }

    // Battery Details
    $systemSpecs['battery'] = [
        'type' => $input['battery_type'] ?? 'lithium', // 'lithium' or 'lead_acid'
        'capacity' => $input['battery_capacity'] ?? 0,
        'brand' => $input['battery_brand'] ?? 'Unknown',
        'count' => $input['battery_count'] ?? 0,
        'terminal_voltage' => $input['battery_terminal_voltage'] ?? 0,
    ];

    // Solar Panel Details
    $systemSpecs['solar_panels'] = [
        'count' => $input['panel_count'] ?? 0,
        'brand' => $input['panel_brand'] ?? 'Unknown',
        'capacity_w' => $input['panel_capacity'] ?? 0,
        'voc' => $input['panel_voc'] ?? 0,
        'isc' => $input['panel_isc'] ?? 0,
        'max_fuse_rating' => $input['panel_max_fuse'] ?? 0,
    ];

    $prompt = "You are a Professional Solar Installation Engineer. Analyze this solar power system and provide comprehensive installation guidance.

System Specifications:
" . json_encode($systemSpecs, JSON_PRETTY_PRINT) . "

Tasks:
1. Calculate optimal solar array arrangement (series/parallel configuration)
2. Determine battery bank configuration (series/parallel for desired voltage)
3. Calculate wire gauge for:
   - PV array to controller/inverter
   - Battery to inverter
4. Calculate breaker/fuse ratings per NEC standards
5. Identify safety issues and warnings

IMPORTANT: Return ONLY valid JSON with NO markdown formatting. Do not wrap in code blocks.

JSON Structure:
{
    \"safe\": boolean,
    \"solar_arrangement\": {
        \"strings\": number,
        \"panels_per_string\": number,
        \"total_voc\": \"string with V\",
        \"total_current\": \"string with A\",
        \"explanation\": \"string\"
    },
    \"battery_arrangement\": {
        \"series\": number,
        \"parallel\": number,
        \"total_voltage\": \"string\",
        \"total_capacity\": \"string\",
        \"explanation\": \"string\"
    },
    \"wire_gauge\": {
        \"pv_to_controller\": \"AWG size\",
        \"battery_to_inverter\": \"AWG size\",
        \"explanation\": \"string\"
    },
    \"breaker_ratings\": {
        \"pv_input\": \"rating in A\",
        \"battery_breaker\": \"rating in A\",
        \"explanation\": \"string\"
    },
    \"warnings\": [\"array of warning strings\"],
    \"installation_notes\": [\"array of critical safety notes\"]
}";

    // Call Groq
    $response = callGroqAPI($prompt, 'json');

    // Extract JSON from response (handles markdown wrapping)
    $analysis = extractJSON($response);

    if (!$analysis) {
        error_log("System Designer Advanced - Failed to parse AI response: " . substr($response, 0, 500));
        throw new Exception("AI returned invalid response. Please check Groq API configuration.");
    }

    echo json_encode(['success' => true, 'mode' => 'advanced', 'analysis' => $analysis]);
}

/**
 * Extract JSON from AI response, handling markdown code blocks
 */
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