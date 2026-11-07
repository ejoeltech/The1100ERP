<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * API Authentication Token System
 *
 * Optional facility for future machine-to-machine endpoints. No shipped
 * endpoint currently issues or accepts Bearer tokens; keep it included only
 * by code that has explicitly opted in.
 */

/**
 * Generate a new API token for an active user. Tokens always expire; there is
 * no non-expiring mode.
 */
function generateApiToken($userId, $ttlDays = 90) {
    global $pdo;
    $ttlDays = max(1, min(365, (int)$ttlDays));

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([(int)$userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Cannot issue an API token to an inactive or missing user.');
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $stmt = $pdo->prepare("INSERT INTO api_tokens (user_id, token_hash, expires_at, created_at, last_used_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW(), NULL)");
    $stmt->execute([(int)$userId, $hash, $ttlDays]);

    return $token;
}

/**
 * Verify an API token. Returns the active user row, or false.
 * Expired and non-expiring tokens are rejected, as are inactive users.
 */
function verifyApiToken($token) {
    global $pdo;
    if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
        return false;
    }
    $hash = hash('sha256', strtolower($token));

    $stmt = $pdo->prepare("SELECT t.id AS token_id, u.id, u.username, u.full_name, u.role
        FROM api_tokens t
        JOIN users u ON u.id = t.user_id
        WHERE t.token_hash = ?
          AND u.is_active = 1
          AND t.expires_at IS NOT NULL
          AND t.expires_at > NOW()
        LIMIT 1");
    $stmt->execute([$hash]);
    $user = $stmt->fetch();

    if ($user) {
        $updateStmt = $pdo->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?");
        $updateStmt->execute([(int)$user['token_id']]);
        unset($user['token_id']);
        return $user;
    }

    return false;
}

/**
 * Require API token for an endpoint
 */
function requireApiAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $user = verifyApiToken($matches[1]);

        if ($user) {
            // Rotate the session id before attaching the token identity, then
            // populate the same session keys the normal login flow uses.
            if (session_status() === PHP_SESSION_NONE) {
                if (function_exists('configureSessionCookies')) {
                    configureSessionCookies();
                }
                session_start();
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_regeneration'] = time();

            if (function_exists('logAudit')) {
                logAudit('api_token_auth', 'user', (int)$user['id'], [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ]);
            }
            return true;
        }
    }

    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid or missing API token']);
    exit;
}
