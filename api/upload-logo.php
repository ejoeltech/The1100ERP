<?php
require_once __DIR__ . '/../includes/session-check.php';

// Only privileged users can upload logo (WP3: manage_settings; the raw
// role fallback below only runs if the permission system is missing).
if (function_exists('requirePermission')) {
    requirePermission('manage_settings');
} elseif (function_exists('isAdmin')) {
    if (!isAdmin())
        die('Access Denied');
} else {
    die('Access Denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['company_logo']) || $_FILES['company_logo']['error'] === UPLOAD_ERR_NO_FILE) {
        header('Location: ../pages/settings.php?error=' . urlencode('No file uploaded'));
        exit;
    }

    $file = $_FILES['company_logo'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $file['error']);
    }

    // Validate file size (3MB max)
    if ($file['size'] > 3145728) {
        throw new Exception('File size must be less than 3MB');
    }

    // Validate file type (finfo may be missing on minimal builds)
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    if ($finfo === false) {
        throw new Exception('Image validation is unavailable on this server (fileinfo missing). Save settings without a logo, or ask your host to enable it.');
    }
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF allowed.');
    }

    // Create uploads directory if it doesn't exist (WP7: guarded, no PHP exec)
    require_once __DIR__ . '/../includes/security.php';
    $upload_dir = ensureUploadDir(__DIR__ . '/../uploads/logo/');

    // Generate unique filename and sanitize extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        throw new Exception('Invalid file extension');
    }
    $filename = 'company_logo_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $filepath = $upload_dir . $filename;

    // GD is optional on many shared hosts: fail with guidance, never a 500.
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        throw new Exception('Logo resizing needs the GD image library, which is not enabled on this server. Save settings without a logo, or enable the gd extension in cPanel (PHP Selector).');
    }

    // Read dimensions before decoding: huge images can exhaust memory and
    // produce an uncatchable 500 on shared hosting.
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo || empty($imageInfo[0]) || empty($imageInfo[1])) {
        throw new Exception('Could not read the image dimensions.');
    }
    $orig_width = (int)$imageInfo[0];
    $orig_height = (int)$imageInfo[1];
    if ($orig_width > 6000 || $orig_height > 6000 || ($orig_width * $orig_height) > 24000000) {
        throw new Exception('The logo image is too large. Use an image under 24 megapixels and 6000 px per side.');
    }

    // Load image
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $source = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $source = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($file['tmp_name']);
            break;
        default:
            throw new Exception('Unsupported image type');
    }
    if ($source === false) {
        throw new Exception('Could not read the image file. It may be corrupt — try another file.');
    }

    // Resize logo (max 300px width, maintain aspect ratio)
    $max_width = 300;
    if ($orig_width > $max_width) {
        $ratio = $max_width / $orig_width;
        $new_width = $max_width;
        $new_height = (int) ($orig_height * $ratio);
    } else {
        $new_width = $orig_width;
        $new_height = $orig_height;
    }

    // Create resized image
    $resized = imagecreatetruecolor($new_width, $new_height);
    if ($resized === false) {
        throw new Exception('Could not create the resized logo.');
    }

    // Preserve transparency for PNG and GIF
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $new_width, $new_height, $transparent);
    }

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

    // Save resized logo
    $saved = false;
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $saved = imagejpeg($resized, $filepath, 90);
            break;
        case 'image/png':
            $saved = imagepng($resized, $filepath, 9);
            break;
        case 'image/gif':
            $saved = imagegif($resized, $filepath);
            break;
    }
    if (!$saved) {
        throw new Exception('Could not save the resized logo. Check upload folder permissions.');
    }

    // Create favicon (32x32) from the already-resized resource; do not decode
    // the file again, which doubles memory use.
    $favicon = imagecreatetruecolor(32, 32);
    if ($favicon === false) {
        throw new Exception('Could not create the favicon.');
    }

    // Preserve transparency
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($favicon, false);
        imagesavealpha($favicon, true);
        $transparent = imagecolorallocatealpha($favicon, 255, 255, 255, 127);
        imagefilledrectangle($favicon, 0, 0, 32, 32, $transparent);
    }

    imagecopyresampled($favicon, $resized, 0, 0, 0, 0, 32, 32, $new_width, $new_height);
    if (!imagepng($favicon, $upload_dir . 'favicon.png', 9)) {
        throw new Exception('Could not save the favicon. Check upload folder permissions.');
    }
    imagedestroy($favicon);

    imagedestroy($source);
    imagedestroy($resized);

    // Save to settings
    $relative_path = 'uploads/logo/' . $filename;
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES ('company_logo', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$relative_path]);

    // Also save favicon path
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) 
        VALUES ('company_favicon', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute(['uploads/logo/favicon.png']);

    // Delete old logo if exists
    $old_logo = getSetting('company_logo');
    if ($old_logo && file_exists(__DIR__ . '/../' . $old_logo) && $old_logo !== $relative_path) {
        @unlink(__DIR__ . '/../' . $old_logo);
    }

    header('Location: ../pages/settings.php?success=1&logo_uploaded=1');
    exit;

} catch (Error $e) {
    // Engine-level failure (missing extension, type error, ...): never a bare 500.
    error_log("Logo upload error: " . $e->getMessage());
    header('Location: ../pages/settings.php?error=' . urlencode('Logo processing failed on this server. Check server logs.'));
    exit;
} catch (Exception $e) {
    error_log("Logo upload error: " . $e->getMessage());
    header('Location: ../pages/settings.php?error=' . urlencode($e->getMessage()));
    exit;
}


?>