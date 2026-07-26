<?php
/**
 * Bluedots Technologies — WhatsApp Cloud API inbound webhook (capture-only).
 * Meta GET verification + signature-verified POST capture -> leads(source=whatsapp).
 * Capture-only by design (no outbound auto-reply) to respect Meta's 24h window;
 * the follow-up engine handles outreach.
 *
 * Configure in Meta App > WhatsApp > Configuration:
 *   Callback URL: https://your-erp/api/leads/whatsapp-webhook.php
 *   Verify token: matches getSetting('whatsapp_verify_token')
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/notifications.php';

// --- GET verification (Meta handshake) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode'] ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    $expected  = getSetting('whatsapp_verify_token', '');
    if ($mode === 'subscribe' && $token !== '' && hash_equals($expected, $token)) {
        http_response_code(200);
        echo $challenge;
    } else {
        http_response_code(403);
        echo 'Forbidden';
    }
    exit;
}

// --- POST (incoming message) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$secret = getSetting('whatsapp_app_secret', '');
$raw    = file_get_contents('php://input');

// Signature check (X-Hub-Signature-256 = sha256 HMAC of body with app secret).
$sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if ($secret !== '' && $sigHeader !== '') {
    $expectedSig = 'sha256=' . hash_hmac('sha256', $raw, $secret);
    if (!hash_equals($expectedSig, $sigHeader)) {
        http_response_code(401);
        error_log('WhatsApp webhook: signature mismatch');
        exit;
    }
} elseif ($secret !== '') {
    // Secret configured but no signature present -> reject.
    http_response_code(401);
    exit;
}

$data = json_decode($raw, true);
if (!isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
    // Not a message event (e.g. status/delivery) — acknowledge.
    http_response_code(200);
    exit;
}

$msg = $data['entry'][0]['changes'][0]['value']['messages'][0];
if (($msg['type'] ?? '') !== 'text') {
    http_response_code(200);
    exit;
}
$from    = $msg['from'] ?? '';
$text    = $msg['text']['body'] ?? '';
$name    = $data['entry'][0]['changes'][0]['value']['contacts'][0]['profile']['name'] ?? $from;
$wtsId   = $msg['id'] ?? '';

if ($from === '' || $text === '') {
    http_response_code(200);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO leads (name, phone, email, source, interest, message, status, created_at)
         VALUES (?, ?, NULL, 'whatsapp', 'WhatsApp enquiry', ?, 'new', NOW())"
    );
    $stmt->execute([$name ?: $from, $from, $text]);
    $leadId = $pdo->lastInsertId();

    if (function_exists('logAudit')) {
        logAudit('create', 'lead', $leadId, ['source' => 'whatsapp', 'wa_id' => $wtsId]);
    }

    if (telegramConfigured()) {
        $alert = "📱 *New WhatsApp lead*\n*" . $name . "* — " . $from . "\n“" . mb_substr($text, 0, 300) . "”";
        sendTelegram($alert);
    }
} catch (PDOException $e) {
    error_log('WhatsApp webhook DB error: ' . $e->getMessage());
}

http_response_code(200);
exit;
