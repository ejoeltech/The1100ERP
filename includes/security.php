<?php
/**
 * Security Functions
 * CSRF, Rate Limiting, Input Validation
 */

// ============================================
// CSRF Protection
// ============================================

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// ============================================
// Rate Limiting (Login Attempts)
// ============================================

function checkLoginAttempts($username) {
    $maxAttempts = 5;
    $lockoutTime = 900; // 15 minutes
    
    $key = "login_attempts_" . md5($username);
    $attempts = $_SESSION[$key] ?? ['count' => 0, 'time' => time()];
    
    if ($attempts['count'] >= $maxAttempts) {
        if (time() - $attempts['time'] < $lockoutTime) {
            $minutesLeft = ceil(($lockoutTime - (time() - $attempts['time'])) / 60);
            return "Too many login attempts. Try again in $minutesLeft minutes.";
        } else {
            // Reset after lockout period
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
        }
    }
    
    return true;
}

function recordFailedLogin($username) {
    $key = "login_attempts_" . md5($username);
    $attempts = $_SESSION[$key] ?? ['count' => 0, 'time' => time()];
    $attempts['count']++;
    $attempts['time'] = time();
    $_SESSION[$key] = $attempts;
}

function clearLoginAttempts($username) {
    $key = "login_attempts_" . md5($username);
    unset($_SESSION[$key]);
}

// ============================================
// Output Escaping
// ============================================

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Short helper for escape()
 */
if (!function_exists('h')) {
    function h($string) {
        return escape($string);
    }
}

function escapeJS($data) {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// ============================================
// Input Validation
// ============================================

function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        
        // Required check
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = "$field is required";
            continue;
        }
        
        // Skip other checks if empty and not required
        if (empty($value)) {
            continue;
        }
        
        // Type checking
        if (isset($rule['type'])) {
            switch ($rule['type']) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field] = "Invalid email format";
                    }
                    break;
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[$field] = "$field must be a number";
                    }
                    break;
                case 'decimal':
                    if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
                        $errors[$field] = "$field must be a valid decimal";
                    }
                    break;
            }
        }
        
        // Min/Max length
        if (isset($rule['min']) && strlen($value) < $rule['min']) {
            $errors[$field] = "$field must be at least {$rule['min']} characters";
        }
        if (isset($rule['max']) && strlen($value) > $rule['max']) {
            $errors[$field] = "$field must be less than {$rule['max']} characters";
        }
    }
    
    return $errors;
}

// ============================================
// Session Security
// ============================================

// Configure session security settings BEFORE session_start()
// These must be set at file-include time, not inside a function
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
}

function secureSession() {
    // Regenerate session ID periodically to prevent session fixation
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    }
    
    if (time() - $_SESSION['last_regeneration'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// ============================================
// Security Headers
// ============================================

function setSecurityHeaders() {
    if (headers_sent()) {
        return;
    }
    
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Content Security Policy
    // Note: 'unsafe-inline' is currently required for Tailwind CDN and some dynamic styles
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; connect-src 'self'; font-src 'self' https://fonts.gstatic.com");
}

// ============================================
// Password Policy
// ============================================

function validatePasswordPolicy($password) {
    if (strlen($password) < 12) {
        return "Password must be at least 12 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number.";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return "Password must contain at least one special character.";
    }
    return true;
}

// ============================================
// PII Encryption (Field-Level)
// ============================================

/**
 * Encrypt sensitive PII using AES-256-GCM
 * Requires ENCRYPTION_KEY in .env
 */
function encryptPII($data) {
    if (empty($data)) return $data;
    $key = getenv('ENCRYPTION_KEY');
    if (!$key) return $data; // Fallback if no key (not ideal for security)
    
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Decrypt sensitive PII
 */
function decryptPII($data) {
    if (empty($data) || strlen($data) < 30) return $data;
    $key = getenv('ENCRYPTION_KEY');
    if (!$key) return $data;
    
    $decoded = base64_decode($data);
    $iv = substr($decoded, 0, 12);
    $tag = substr($decoded, 12, 16);
    $ciphertext = substr($decoded, 28);
    return openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
}
