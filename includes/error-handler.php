<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Error Handling Configuration
 */

// Disable error display in production (change to 1 for development)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-errors.log');

// Error reporting level
error_reporting(E_ALL);

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER ERROR',
        E_USER_WARNING => 'USER WARNING',
        E_USER_NOTICE => 'USER NOTICE'
    ];
    
    $type = $errorTypes[$errno] ?? 'UNKNOWN';
    error_log("[$type] $errstr in $errfile on line $errline");
    
    // Don't execute PHP internal error handler
    return true;
}

set_error_handler('customErrorHandler');

// Exception handler
function customExceptionHandler($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage() . " in " . 
              $exception->getFile() . " on line " . $exception->getLine());
    
    // Show generic message to user
    echo "An error occurred. Please contact support if this persists.";
}

set_exception_handler('customExceptionHandler');