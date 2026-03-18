<?php
/**
 * API Authentication Token System
 */

/**
 * Generate a new API token for a user
 */
function generateApiToken($userId) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    
    $stmt = $pdo->prepare("INSERT INTO api_tokens (user_id, token_hash, created_at, last_used_at) VALUES (?, ?, NOW(), NULL)");
    $stmt->execute([$userId, $hash]);
    
    return $token;
}

/**
 * Verify an API token
 */
function verifyApiToken($token) {
    global $pdo;
    $hash = hash('sha256', $token);
    
    $stmt = $pdo->prepare("SELECT user_id FROM api_tokens WHERE token_hash = ? AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->execute([$hash]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update last used
        $updateStmt = $pdo->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?");
        $updateStmt->execute([$hash]);
        return $user['user_id'];
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
        $token = $matches[1];
        $userId = verifyApiToken($token);
        
        if ($userId) {
            // Set up a basic session for tools that expect it
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $userId;
            // Fetch user info for role
            global $pdo;
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                $_SESSION['role'] = $user['role'];
            }
            return true;
        }
    }
    
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid or missing API token']);
    exit;
}
