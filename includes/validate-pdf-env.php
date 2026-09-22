<?php
/**
 * PDF Environment Validator
 * Checks for required extensions and folder permissions for mPDF
 */

function validatePdfEnvironment($tempDir) {
    $errors = [];

    // 1. Check PHP Version
    if (PHP_VERSION_ID < 70200) {
        $errors[] = "PHP 7.2.0 or higher is required for mPDF. Current version: " . PHP_VERSION;
    }

    // 2. Check Required Extensions
    $required_extensions = ['mbstring', 'gd', 'zlib'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Required PHP extension '{$ext}' is not loaded on this server.";
        }
    }

    // 3. Check for vendor/autoload.php
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        $errors[] = "Composer autoloader not found. Please run 'composer install' or upload the 'vendor' directory.";
    }

    // 4. Check/Create Temp Directory
    if (!file_exists($tempDir)) {
        if (!@mkdir($tempDir, 0775, true)) {
            $errors[] = "Temporary directory '{$tempDir}' does not exist and could not be created. Please create it manually.";
        }
    }

    if (file_exists($tempDir) && !is_writable($tempDir)) {
        // Try to chmod if possible
        if (!@chmod($tempDir, 0775)) {
            $errors[] = "Temporary directory '{$tempDir}' is not writable. Please set permissions to 775 or 777 via FTP/cPanel.";
        }
    }

    // 5. Check subdirectories for mPDF
    $subs = ['ttfontdata', 'tmp'];
    foreach ($subs as $sub) {
        $subPath = $tempDir . DIRECTORY_SEPARATOR . $sub;
        if (!file_exists($subPath)) {
            @mkdir($subPath, 0775, true);
        }
        if (file_exists($subPath) && !is_writable($subPath)) {
             @chmod($subPath, 0775);
             if (!is_writable($subPath)) {
                $errors[] = "Sub-directory '{$subPath}' is not writable.";
             }
        }
    }

    // If there are errors, display a user-friendly error page
    if (!empty($errors)) {
        displayPdfErrorPage($errors);
        exit;
    }

    return true;
}

function displayPdfErrorPage($errors) {
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>PDF Generation Error</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 40px auto; padding: 20px; background: #f4f7f9; }
            .error-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 5px solid #dc2626; }
            h1 { color: #dc2626; margin-top: 0; }
            .error-list { background: #fef2f2; border: 1px solid #fee2e2; padding: 15px; border-radius: 4px; margin: 20px 0; }
            .error-item { margin-bottom: 10px; color: #991b1b; display: flex; align-items: flex-start; }
            .error-item:before { content: "•"; margin-right: 10px; font-weight: bold; }
            .hint { font-size: 0.9em; color: #666; font-style: italic; }
            .btn { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 4px; margin-top: 20px; font-weight: bold; }
            .btn:hover { background: #1d4ed8; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>PDF Generation Error</h1>
            <p>The system encountered the following environment issues while trying to generate your PDF:</p>
            
            <div class="error-list">
                <?php foreach ($errors as $error): ?>
                    <div class="error-item"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>

            <p class="hint"><strong>Note for Developers:</strong> These errors typically occur when deploying to a new server. Ensure all PHP extensions are enabled in your cPanel/Hosting settings and that the <code>tmp/mpdf</code> folder has write permissions (chmod 775 or 777).</p>
            
            <a href="javascript:history.back()" class="btn">Go Back</a>
        </div>
    </body>
    </html>
    <?php
}
