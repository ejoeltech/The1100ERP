<?php
/**
 * Groq AI Configuration for 1100erp
 * Uses Groq's FREE tier with Llama 3.1 70B
 */

// Groq API Configuration
// Try env var first, then database setting
$groq_key = getenv('GROQ_API_KEY');
if (!$groq_key && function_exists('getSetting')) {
    $groq_key = getSetting('groq_api_key', '');
}
define('GROQ_API_KEY', $groq_key);
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');
define('GROQ_TIMEOUT', 30); // seconds

// Nigerian Solar Market Context
define('NIGERIAN_SOLAR_CONTEXT', [
    'currency' => 'NGN',
    'currency_symbol' => '₦',
    'avg_sun_hours' => 5.5,
    'typical_voltage' => 230,
    'power_reliability' => 'poor',
    'climate' => 'tropical',
    'backup_hours_needed' => '8-12',
    'vat_rate' => 0.075
]);

// Common Nigerian Appliances Database (Watts & Usage)
define('NIGERIAN_APPLIANCES', [
    // Kitchen Appliances
    'fridge' => ['watts' => 150, 'hours_per_day' => 24, 'surge' => 600, 'category' => 'kitchen'],
    'freezer' => ['watts' => 200, 'hours_per_day' => 24, 'surge' => 800, 'category' => 'kitchen'],
    'chest_freezer' => ['watts' => 250, 'hours_per_day' => 24, 'surge' => 1000, 'category' => 'kitchen'],
    'microwave' => ['watts' => 1000, 'hours_per_day' => 0.5, 'surge' => 1500, 'category' => 'kitchen'],
    'blender' => ['watts' => 300, 'hours_per_day' => 0.3, 'surge' => 500, 'category' => 'kitchen'],
    'kettle' => ['watts' => 1500, 'hours_per_day' => 0.5, 'surge' => 1500, 'category' => 'kitchen'],
    'toaster' => ['watts' => 800, 'hours_per_day' => 0.3, 'surge' => 800, 'category' => 'kitchen'],

    // Cooling & Comfort
    'fan' => ['watts' => 75, 'hours_per_day' => 12, 'surge' => 100, 'category' => 'cooling'],
    'ceiling_fan' => ['watts' => 75, 'hours_per_day' => 12, 'surge' => 100, 'category' => 'cooling'],
    'standing_fan' => ['watts' => 70, 'hours_per_day' => 10, 'surge' => 90, 'category' => 'cooling'],
    'ac' => ['watts' => 1500, 'hours_per_day' => 8, 'surge' => 3500, 'category' => 'cooling'],
    'ac_1hp' => ['watts' => 1000, 'hours_per_day' => 8, 'surge' => 2500, 'category' => 'cooling'],
    'ac_1.5hp' => ['watts' => 1500, 'hours_per_day' => 8, 'surge' => 3500, 'category' => 'cooling'],
    'ac_2hp' => ['watts' => 2000, 'hours_per_day' => 8, 'surge' => 4500, 'category' => 'cooling'],

    // Entertainment
    'tv' => ['watts' => 100, 'hours_per_day' => 8, 'surge' => 150, 'category' => 'entertainment'],
    'tv_32inch' => ['watts' => 80, 'hours_per_day' => 8, 'surge' => 120, 'category' => 'entertainment'],
    'tv_43inch' => ['watts' => 100, 'hours_per_day' => 8, 'surge' => 150, 'category' => 'entertainment'],
    'tv_55inch' => ['watts' => 150, 'hours_per_day' => 8, 'surge' => 200, 'category' => 'entertainment'],
    'decoder' => ['watts' => 25, 'hours_per_day' => 8, 'surge' => 30, 'category' => 'entertainment'],
    'sound_system' => ['watts' => 150, 'hours_per_day' => 4, 'surge' => 200, 'category' => 'entertainment'],

    // Computing
    'laptop' => ['watts' => 65, 'hours_per_day' => 6, 'surge' => 90, 'category' => 'computing'],
    'desktop' => ['watts' => 200, 'hours_per_day' => 8, 'surge' => 300, 'category' => 'computing'],
    'printer' => ['watts' => 50, 'hours_per_day' => 2, 'surge' => 200, 'category' => 'computing'],
    'router' => ['watts' => 10, 'hours_per_day' => 24, 'surge' => 15, 'category' => 'computing'],
    'phone_charger' => ['watts' => 10, 'hours_per_day' => 3, 'surge' => 10, 'category' => 'computing'],

    // Lighting
    'bulb' => ['watts' => 15, 'hours_per_day' => 6, 'surge' => 15, 'category' => 'lighting'],
    'led_bulb' => ['watts' => 10, 'hours_per_day' => 6, 'surge' => 10, 'category' => 'lighting'],
    'tube_light' => ['watts' => 40, 'hours_per_day' => 8, 'surge' => 40, 'category' => 'lighting'],
    'security_lights' => ['watts' => 50, 'hours_per_day' => 12, 'surge' => 50, 'category' => 'lighting'],

    // Home Appliances
    'washing_machine' => ['watts' => 500, 'hours_per_day' => 2, 'surge' => 1500, 'category' => 'home'],
    'iron' => ['watts' => 1200, 'hours_per_day' => 1, 'surge' => 1200, 'category' => 'home'],
    'pressing_iron' => ['watts' => 1200, 'hours_per_day' => 1, 'surge' => 1200, 'category' => 'home'],
    'water_pump' => ['watts' => 750, 'hours_per_day' => 1, 'surge' => 2200, 'category' => 'home'],
    'water_heater' => ['watts' => 2000, 'hours_per_day' => 2, 'surge' => 2000, 'category' => 'home'],

    // Business Equipment
    'pos_machine' => ['watts' => 30, 'hours_per_day' => 12, 'surge' => 50, 'category' => 'business'],
    'cash_register' => ['watts' => 40, 'hours_per_day' => 10, 'surge' => 60, 'category' => 'business'],
    'cctv_camera' => ['watts' => 15, 'hours_per_day' => 24, 'surge' => 20, 'category' => 'business']
]);

/**
 * Call Groq API
 * 
 * @param string $prompt User prompt
 * @param string $systemPrompt System context
 * @param array $options Additional options (temperature, max_tokens)
 * @return string AI response
 */
function callGroqAPI($prompt, $systemPrompt = '', $options = [])
{
    if (empty(GROQ_API_KEY)) {
        throw new Exception('Groq API key not configured. Please set GROQ_API_KEY environment variable.');
    }

    $messages = [];

    if (!empty($systemPrompt)) {
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];
    }

    $messages[] = [
        'role' => 'user',
        'content' => $prompt
    ];

    $defaultOptions = [
        'temperature' => 0.7,
        'max_tokens' => 2048,
        'top_p' => 1,
        'stream' => false
    ];

    $options = array_merge($defaultOptions, $options);

    $data = [
        'model' => GROQ_MODEL,
        'messages' => $messages,
        'temperature' => $options['temperature'],
        'max_tokens' => $options['max_tokens'],
        'top_p' => $options['top_p']
    ];

    $ch = curl_init(GROQ_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, GROQ_TIMEOUT);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Groq API connection error: {$curlError}");
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? "HTTP {$httpCode}";
        throw new Exception("Groq API error: {$errorMsg}");
    }

    $result = json_decode($response, true);

    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Invalid Groq API response format');
    }

    return trim($result['choices'][0]['message']['content']);
}

/**
 * Get appliance data by name
 */
function getApplianceData($applianceName)
{
    $appliances = NIGERIAN_APPLIANCES;
    $key = strtolower(str_replace([' ', '-'], '_', $applianceName));
    return $appliances[$key] ?? null;
}

/**
 * Calculate total wattage from appliances list
 */
function calculateTotalWattage($appliances)
{
    $totalWatts = 0;
    $totalSurge = 0;
    $dailyWattHours = 0;

    foreach ($appliances as $appliance) {
        $name = $appliance['name'];
        $quantity = $appliance['quantity'] ?? 1;

        $data = getApplianceData($name);
        if (!$data)
            continue;

        $watts = $data['watts'] * $quantity;
        $surge = $data['surge'] * $quantity;
        $hours = $data['hours_per_day'];

        $totalWatts += $watts;
        $totalSurge += $surge;
        $dailyWattHours += ($watts * $hours);
    }

    return [
        'continuous_watts' => $totalWatts,
        'surge_watts' => $totalSurge,
        'daily_watt_hours' => $dailyWattHours,
        'daily_kwh' => round($dailyWattHours / 1000, 2)
    ];
}
?>