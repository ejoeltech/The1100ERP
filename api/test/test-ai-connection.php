<?php
/**
 * Test AI Connection API
 * Tests the connection to Groq API
 */

header('Content-Type: application/json');
require_once '../../includes/public-init.php';
require_once '../../includes/groq-config.php';

// strict admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$apiKey = $input['api_key'] ?? '';

// If key provided in request, override constant for this request only
if (!empty($apiKey)) {
    // We can't redefine the constant, so we'll need to handle this manually 
    // or modify callGroqAPI to accept an optional key
    // For now, let's assume callGroqAPI might need a small tweak or we pass it
} else {
    // If no key provided, check if configured
    if (!defined('GROQ_API_KEY') || empty(GROQ_API_KEY)) {
        echo json_encode(['success' => false, 'error' => 'No API Key configured or provided']);
        exit;
    }
    $apiKey = GROQ_API_KEY;
}

try {
    $startTime = microtime(true);

    // Simple test prompt
    $testPrompt = "Reply with exactly 'OK'";

    // We need to support passing key to callGroqAPI. 
    // Since current helpers use the constant, we might need to modify public-init/groq-config 
    // OR we just assume the constant is set if we are testing stored settings.
    // BUT the user might want to test BEFORE saving.

    // Let's modify callGroqAPI in groq-config.php to accept a key override first?
    // Or we just temporarily define it if not defined?
    // Constants are permanent.

    // BEST APPROACH: Modify callGroqAPI to accept key as 4th arg or in options

    // Temporary hack: validation logic inside this script similar to callGroqAPI
    // to avoid refactoring everything right now.

    $messages = [
        ['role' => 'user', 'content' => $testPrompt]
    ];

    $data = [
        'model' => GROQ_MODEL,
        'messages' => $messages,
        'temperature' => 0.1,
        'max_tokens' => 10
    ];

    $ch = curl_init(GROQ_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000) . 'ms';

    if ($curlError) {
        throw new Exception("Connection error: {$curlError}");
    }

    if ($httpCode !== 200) {
        $errFn = json_decode($response, true);
        $msg = $errFn['error']['message'] ?? "HTTP $httpCode";
        throw new Exception("API Error: $msg");
    }

    $result = json_decode($response, true);
    $reply = $result['choices'][0]['message']['content'] ?? '';

    echo json_encode([
        'success' => true,
        'message' => 'Connection Successful!',
        'latency' => $duration,
        'reply' => $reply
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>