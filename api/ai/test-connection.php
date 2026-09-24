<?php
/**
 * Test AI Connection API - Multi-Provider (hardened, WP0-E).
 * Live feature used by the AI settings UI.
 *
 * Guard: login + manage_settings + POST + CSRF + per-user rate limit.
 * Never returns, logs, or echoes API keys. Custom provider URLs are
 * SSRF-validated (https only, public IP, no metadata ranges).
 */
define('IS_API', true);
require_once '../../includes/session-check.php';
require_once '../../includes/security.php';
require_once '../../includes/ai-config.php';
require_once '../../includes/groq-config.php';

header('Content-Type: application/json');

function aiTestFail($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

requirePermission('manage_settings');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    aiTestFail('Invalid request method', 405);
}

if (!validateCSRFToken($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    aiTestFail('Invalid CSRF token', 403);
}

// Per-user rate limit: 10 tests / 10 minutes (test endpoint, not data path).
$rlFile = sys_get_temp_dir() . '/erp-ai-test-' . (int)($_SESSION['user_id'] ?? 0) . '.json';
$rl = [];
if (is_readable($rlFile)) {
    $decoded = json_decode((string)@file_get_contents($rlFile), true);
    if (is_array($decoded)) {
        $rl = array_values(array_filter($decoded, fn($t) => (time() - (int)$t) < 600));
    }
}
if (count($rl) >= 10) {
    aiTestFail('Too many connection tests. Try again in a few minutes.', 429);
}
$rl[] = time();
@file_put_contents($rlFile, json_encode($rl), LOCK_EX);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$apiKey = trim($input['api_key'] ?? '');
$provider = $input['provider'] ?? getSetting('ai_provider', 'groq');
$model = trim($input['model'] ?? '');
$baseUrl = trim($input['base_url'] ?? '');

// Provider allow-list (registry keys only).
$registry = getAiProviders();
if (!isset($registry[$provider])) {
    aiTestFail('Unknown provider.');
}
if (strlen($model) > 100 || (string)$model !== '' && !preg_match('#^[A-Za-z0-9._\-/:]+$#', $model)) {
    aiTestFail('Invalid model name.');
}

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
    aiTestFail('No API key provided for ' . $provider);
}

// SSRF validation for the effective base URL.
$registryUrl = $registry[$provider]['base_url'] ?? '';
if ($provider !== 'custom' && $baseUrl !== '' && $registryUrl !== '' && $baseUrl !== $registryUrl) {
    // Built-in providers have fixed endpoints; a different URL is rejected.
    aiTestFail('Custom endpoint URLs are only allowed for the Custom provider.');
}
if ($baseUrl !== '' && !aiTestUrlAllowed($baseUrl)) {
    aiTestFail('Endpoint URL is not allowed (https + public host required).');
}

try {
    $startTime = microtime(true);
    $override = ['provider' => $provider, 'api_key' => $apiKey, 'model' => $model];
    if (!empty($baseUrl)) {
        $override['base_url'] = $baseUrl;
    }

    // Minimal test: ask for OK
    $reply = callAiAPI("Reply with exactly 'OK'", '', ['temperature' => 0.1, 'max_tokens' => 10, 'timeout' => 20], $override);

    $duration = round((microtime(true) - $startTime) * 1000) . 'ms';

    echo json_encode([
        'success' => true,
        'message' => 'Connection Successful!',
        'latency' => $duration,
        'reply' => is_string($reply) ? substr($reply, 0, 200) : 'OK',
        'provider' => $provider,
        'model' => $model,
    ]);
} catch (Exception $e) {
    // Scrub the key from any error text (some transports echo URLs).
    $msg = str_replace($apiKey, '[redacted]', $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => substr($msg, 0, 300),
        'provider' => $provider,
        'model' => $model,
    ]);
}

/**
 * SSRF gate for outbound AI endpoint URLs.
 * https only, default port, resolvable hostname, and the resolved IP must be
 * a public unicast address (no private/loopback/link-local/multicast,
 * no cloud metadata endpoints). Note: DNS is resolved at check time;
 * callAiAPI does not follow redirects, which closes the redirect leg.
 */
function aiTestUrlAllowed($url)
{
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https') {
        return false;
    }
    $host = $parts['host'] ?? '';
    if ($host === '') {
        return false;
    }
    if (isset($parts['port']) && (int)$parts['port'] !== 443) {
        return false;
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }
    $ip = $host;
    if (!filter_var($host, FILTER_VALIDATE_IP)) {
        $resolved = gethostbyname($host);
        if ($resolved === $host) {
            return false; // unresolvable
        }
        $ip = $resolved;
    }
    // Must be public IPv4/IPv6: FILTER_FLAG_NO_PRIV_RANGE | NO_RES_RANGE
    // covers private, loopback (v4), link-local, multicast, and 0.0.0.0.
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
    }
    $lower = strtolower($host);
    // Cloud metadata endpoints by name,belt and braces.
    foreach (['169.254.169.254', '100.100.100.200', 'metadata.google.internal', 'metadata.google', 'instance-data'] as $blocked) {
        if ($lower === $blocked || str_ends_with($lower, '.' . $blocked)) {
            return false;
        }
    }
    if (str_starts_with($lower, 'metadata.')) {
        return false;
    }
    return true;
}
