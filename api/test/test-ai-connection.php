<?php
/**
 * Test AI Connection API - Multi-Provider
 */
header('Content-Type: application/json');
require_once '../../includes/public-init.php';
require_once '../../includes/ai-config.php';
require_once '../../includes/groq-config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$apiKey = trim($input['api_key'] ?? '');
$provider = $input['provider'] ?? getSetting('ai_provider', 'groq');
$model = trim($input['model'] ?? '');
$baseUrl = trim($input['base_url'] ?? '');

// If testing unsaved form values, use provided key directly
if (empty($apiKey)) {
    $cfg = getAiProviderConfig($provider);
    $apiKey = $cfg['api_key'];
}
if (empty($model)) {
    $cfg = getAiProviderConfig($provider);
    $model = $cfg['model'];
}
if (empty($baseUrl)) {
    $cfg = getAiProviderConfig($provider);
    $baseUrl = $cfg['base_url'];
}

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'No API Key provided for ' . $provider]);
    exit;
}

try {
    $startTime = microtime(true);
    $override = ['provider' => $provider, 'api_key' => $apiKey, 'model' => $model];
    if (!empty($baseUrl)) $override['base_url'] = $baseUrl;

    // Minimal test: ask for OK
    $reply = callAiAPI("Reply with exactly 'OK'", '', ['temperature' => 0.1, 'max_tokens' => 10], $override);

    $duration = round((microtime(true) - $startTime) * 1000) . 'ms';

    // Mask key for logging
    $masked = substr($apiKey, 0, 7) . str_repeat('*', max(0, strlen($apiKey)-10)) . substr($apiKey, -3);

    echo json_encode([
        'success' => true,
        'message' => 'Connection Successful!',
        'latency' => $duration,
        'reply' => $reply,
        'provider' => $provider,
        'model' => $model,
        'key_preview' => $masked,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'provider' => $provider,
        'model' => $model,
    ]);
}
