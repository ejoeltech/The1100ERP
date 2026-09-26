<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
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

/**
 * Enforce CSRF on a state-changing request (WP4).
 * Accepts the token from the POST field or the X-CSRF-TOKEN header
 * (injected into every fetch() by assets/js/helpers.js).
 * On failure: JSON 403 for API-ish callers, plain 403 otherwise.
 */
function requireCsrf()
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validateCSRFToken($token)) {
        http_response_code(403);
        $isApi = (defined('IS_API') && IS_API)
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            || (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false)
            || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        } else {
            echo 'Invalid CSRF token. Please go back and try again.';
        }
        exit;
    }
}

// ============================================
// Upload Validation (WP7)
// ============================================

define('UPLOAD_IMAGE_MAX_BYTES', 3 * 1024 * 1024);

/**
 * Ensure an upload directory exists with safe permissions and a guard
 * .htaccess so uploaded files can never execute as PHP — even if a
 * check is ever bypassed. Directories are created at runtime (gitignored),
 * so the guard is written here rather than committed per-directory.
 */
function ensureUploadDir($dir)
{
    $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ht = $dir . '.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "# WP7: uploads must never execute as PHP.\nphp_flag engine off\nRemoveHandler .php .phtml .phar\nRemoveType .php .phtml .phar\n<FilesMatch \"\\.(php|phtml|phar|phps|php\\d+)$\">\n    Order allow,deny\n    Deny from all\n</FilesMatch>\n");
    }
    return $dir;
}

/**
 * Validate an image upload by CONTENT (finfo MIME + getimagesize), not by
 * client filename. Returns the canonical extension. Throws on any failure.
 */
function validateImageUpload($file, $maxBytes = UPLOAD_IMAGE_MAX_BYTES)
{
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        throw new Exception('Invalid file size (images up to 3 MB).');
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new Exception('Invalid upload.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) {
        throw new Exception('Only JPG, PNG, GIF or WebP images are allowed.');
    }
    if (@getimagesize($file['tmp_name']) === false) {
        throw new Exception('File is not a valid image.');
    }
    return $map[$mime];
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

/**
 * Harden PHP session handling. MUST run before session_start() (WP4).
 * - strict mode, cookies-only, httponly, SameSite=Strict
 * - Secure flag when the request is HTTPS (auto; production terminates TLS)
 * - session files outside the web root (SESSION_PATH env or OS temp dir)
 * - GC lifetime aligned with the 8h absolute timeout
 */
function configureSessionCookies()
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    ini_set('session.cookie_secure', $https ? 1 : 0);
    $savePath = getenv('SESSION_PATH') ?: (rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'erp_sess');
    if (!is_dir($savePath)) {
        @mkdir($savePath, 0700, true);
    }
    if (is_dir($savePath) && is_writable($savePath)) {
        ini_set('session.save_path', $savePath);
    }
    ini_set('session.gc_maxlifetime', 28800);
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
    // Note: 'unsafe-inline' + 'unsafe-eval' currently required for Tailwind CDN (uses eval) and dynamic styles
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://fonts.googleapis.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; img-src 'self' data: https://api.qrserver.com https://ui-avatars.com; connect-src 'self'; font-src 'self' https://fonts.gstatic.com");
}

// ============================================
// Password Policy
// ============================================

// NOTE (WP2): password policy lives in includes/passwords.php
// (validatePasswordPolicy with blocklist + history check). The old
// single-argument complexity-only version was removed to avoid divergence.

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
