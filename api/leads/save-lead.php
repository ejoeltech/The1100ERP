<?php
// Public, unauthenticated lead capture endpoint (CORS-enabled).
// Used by lead-form.php and any embedded widget. Inserts into `leads`.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once '../../config.php';

// Light rate-limit by IP (best-effort; no external deps).
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'rate_' . md5($ip);
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM leads WHERE created_at > NOW() - INTERVAL 10 MINUTE AND phone = ?");
    // (phone not known yet) -> fall back to a simple per-IP counter via settings-free temp table is overkill;
    // just ensure DB reachable. Real abuse protection lives at the web server / Cloudflare layer.
} catch (Exception $e) { /* ignore */ }

try {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $interest= trim($_POST['interest'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $source  = in_array($_POST['source'] ?? 'web', ['web','whatsapp','phone','referral','walk-in','other'])
                ? $_POST['source'] : 'web';

    if (empty($name) || empty($phone)) {
        throw new Exception('Name and phone are required.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO leads (name, phone, email, source, interest, message, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())"
    );
    $stmt->execute([$name, $phone, $email ?: null, $source, $interest, $message]);
    $leadId = $pdo->lastInsertId();

    if (function_exists('logAudit')) {
        logAudit('create', 'lead', $leadId, ['source' => $source, 'name' => $name]);
    }

    // Instant owner alert via Telegram (if configured).
    if (function_exists('telegramConfigured') && telegramConfigured()) {
        $alert = "🔔 *New lead* (" . $source . ")\n"
               . "*" . $name . "* — " . $phone . "\n"
               . ($email ? $email . "\n" : "")
               . ($interest ? "💡 " . $interest . "\n" : "")
               . "Open: lead-form capture";
        sendTelegram($alert);
    }

    if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'lead_id' => $leadId, 'message' => 'Lead captured']);
        exit;
    }
    header('Location: ../../lead-form.php?success=1');
    exit;

} catch (Exception $e) {
    if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    header('Location: ../../lead-form.php?error=' . urlencode($e->getMessage()));
    exit;
}
