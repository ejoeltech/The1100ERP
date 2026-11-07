<?php
// Public, unauthenticated lead capture endpoint.
// CORS is only enabled for explicitly configured origins; same-origin form
// posts need no CORS entry.

require_once '../../config.php';
require_once '../../includes/invites.php';
require_once '../../includes/audit.php';
require_once '../../includes/notifications.php';
require_once '../../includes/ai-rate-limiter.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', (string)getenv('LEAD_ALLOWED_ORIGINS'))));
$originAllowed = $origin !== '' && in_array($origin, $allowedOrigins, true);

if ($originAllowed) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code($originAllowed ? 204 : 403);
    exit;
}

/**
 * Send either a JSON (AJAX) or redirect response without leaking internals.
 */
function leadRespond($success, $message, $status = 200)
{
    $isAjax = isset($_POST['ajax'])
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }

    if ($success) {
        header('Location: ../../lead-form.php?success=1');
    } else {
        header('Location: ../../lead-form.php?error=' . urlencode($message));
    }
    exit;
}

try {
    // Real per-IP throttle; the old block only prepared a query and never ran.
    $ip = AiRateLimiter::getClientIP();
    if (!throttleCheck('lead_ip:' . $ip, 5, 3600)) {
        throw new InvalidArgumentException('Too many submissions. Please try again later.');
    }

    $name    = trim((string)($_POST['name'] ?? ''));
    $phone   = trim((string)($_POST['phone'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $interest= trim((string)($_POST['interest'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $sourceInput = $_POST['source'] ?? 'web';
    $source  = in_array($sourceInput, ['web','whatsapp','phone','referral','walk-in','other'], true)
                ? $sourceInput : 'web';

    if ($name === '' || $phone === '') {
        throw new InvalidArgumentException('Name and phone are required.');
    }
    if (function_exists('mb_strlen')) {
        if (mb_strlen($name) > 255 || mb_strlen($phone) > 50) {
            throw new InvalidArgumentException('Name or phone is too long.');
        }
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email address.');
    }

    // De-duplicate bursts for the same phone number.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE phone = ? AND created_at > NOW() - INTERVAL 10 MINUTE");
    $stmt->execute([$phone]);
    if ((int)$stmt->fetchColumn() >= 3) {
        throw new InvalidArgumentException('This phone number was submitted recently. Please wait a few minutes.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO leads (name, phone, email, source, interest, message, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())"
    );
    $stmt->execute([$name, $phone, $email ?: null, $source, $interest, $message]);
    $leadId = $pdo->lastInsertId();

    try {
        if (function_exists('logAudit')) {
            logAudit('create', 'lead', $leadId, ['source' => $source, 'name' => $name]);
        }
    } catch (Throwable $e) {
        error_log('Lead audit failed: ' . $e->getMessage());
    }

    // Instant owner alert via Telegram (if configured).
    try {
        if (function_exists('telegramConfigured') && telegramConfigured()) {
            $alert = "🔔 *New lead* (" . $source . ")\n"
                   . "*" . $name . "* — " . $phone . "\n"
                   . ($email ? $email . "\n" : "")
                   . ($interest ? "💡 " . $interest . "\n" : "")
                   . "Open: lead-form capture";
            sendTelegram($alert);
        }
    } catch (Throwable $e) {
        error_log('Lead alert failed: ' . $e->getMessage());
    }

    leadRespond(true, 'Lead captured');
} catch (InvalidArgumentException $e) {
    leadRespond(false, $e->getMessage(), 400);
} catch (Throwable $e) {
    error_log('Lead capture error: ' . $e->getMessage());
    leadRespond(false, 'We could not submit your details. Please try again.', 500);
}
