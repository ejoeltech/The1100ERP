<?php
require_once '../../config.php';
require_once '../../includes/session-check.php';
require_once '../../includes/groq-config.php';

requirePermission('manage_settings');

header('Content-Type: application/json');

try {
    // 1. Locate Log File
    $logFile = ini_get('error_log');

    // Fallback for XAMPP default
    if (!$logFile || !file_exists($logFile)) {
        $logFile = 'c:/xampp/php/logs/php_error_log';
    }

    if (!$logFile || !file_exists($logFile)) {
        // Try Apache error log
        $logFile = 'c:/xampp/apache/logs/error.log';
    }

    if (!file_exists($logFile)) {
        echo json_encode(['status' => 'clean', 'message' => 'No error log file found directly. System appears clean or logging is disabled.']);
        exit;
    }

    // 2. Read Last N Lines
    $lines = 50;
    $content = tailCustom($logFile, $lines);

    if (empty(trim($content))) {
        echo json_encode(['status' => 'clean', 'message' => ' Log file is empty. No recent errors.']);
        exit;
    }

    // 3. AI Analysis
    $prompt = "Analyze the following PHP error log entries. Group duplicates. For each unique error, explain the root cause and specific code fix. 
    Format as HTML list.
    
    LOGS:
    $content";

    $analysis = callGroqAPI($prompt, "You are a Senior PHP DevOps Engineer. Be concise and technical.");

    echo json_encode([
        'status' => 'issues_found',
        'log_preview' => $content,
        'analysis' => $analysis
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Read last N lines of file
 */
function tailCustom($filepath, $lines = 1)
{
    $f = @fopen($filepath, "rb");
    if ($f === false)
        return false;

    fseek($f, -1, SEEK_END);
    if (ftell($f) <= 0)
        return '';

    $buffer = '';
    $eol = false;

    // Efficiently read backwards
    while (ftell($f) > 0 && $lines >= 0) {
        $char = fgetc($f);
        if ($char === "\n") {
            $lines--;
        }
        $buffer = $char . $buffer;
        fseek($f, -2, SEEK_CUR);
    }

    fclose($f);
    return $buffer;
}
?>