<?php
include '../includes/session-check.php';

// Only admins can upload logo
if (function_exists('requirePermission')) {
    requirePermission('manage_settings');
} else {
    if (function_exists('isAdmin')) {
        if (!isAdmin())
            die('Access Denied');
    } else {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            die('Access Denied');
        }
    }
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

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF allowed.');
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/logo/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'company_logo_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;

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

    // Get original dimensions
    $orig_width = imagesx($source);
    $orig_height = imagesy($source);

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

    // Preserve transparency for PNG and GIF
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $new_width, $new_height, $transparent);
    }

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

    // Save resized logo
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            imagejpeg($resized, $filepath, 90);
            break;
        case 'image/png':
            imagepng($resized, $filepath, 9);
            break;
        case 'image/gif':
            imagegif($resized, $filepath);
            break;
    }

    imagedestroy($source);
    imagedestroy($resized);

    // Create favicon (32x32)
    $favicon_source = imagecreatefromstring(file_get_contents($filepath));
    $favicon = imagecreatetruecolor(32, 32);

    // Preserve transparency
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($favicon, false);
        imagesavealpha($favicon, true);
        $transparent = imagecolorallocatealpha($favicon, 255, 255, 255, 127);
        imagefilledrectangle($favicon, 0, 0, 32, 32, $transparent);
    }

    imagecopyresampled($favicon, $favicon_source, 0, 0, 0, 0, 32, 32, $new_width, $new_height);
    imagepng($favicon, $upload_dir . 'favicon.png', 9);
    imagedestroy($favicon);
    imagedestroy($favicon_source);

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

} catch (Exception $e) {
    error_log("Logo upload error: " . $e->getMessage());
    header('Location: ../pages/settings.php?error=' . urlencode($e->getMessage()));
    exit;
}


?>