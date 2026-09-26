<?php
require_once '../config.php';
require_once '../includes/session-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['image']) || empty($input['image'])) {
        throw new Exception('No image data received');
    }

    $data = $input['image'];

    // Remove the "data:image/png;base64," part
    // Remove the "data:image/png;base64," part
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
        // CSRF Check
        require_once '../includes/security.php';
        if (!validateCsrfToken($input['csrf_token'] ?? '')) {
            throw new Exception('CSRF validation failed');
        }

        $data = substr($data, strpos($data, ',') + 1);
        $type = strtolower($type[1]); // jpg, png, gif
        if ($type === 'jpeg') $type = 'jpg';

        if (!in_array($type, ['jpg', 'gif', 'png'])) {
            throw new Exception('Invalid image type');
        }

        $data = base64_decode($data);

        if ($data === false) {
            throw new Exception('Base64 decode failed');
        }

        // WP7: cap decoded size and verify real image content.
        if (strlen($data) > UPLOAD_IMAGE_MAX_BYTES) {
            throw new Exception('Image too large (max 3 MB).');
        }
        if (@getimagesizefromstring($data) === false) {
            throw new Exception('File is not a valid image.');
        }
    } else {
        throw new Exception('Did not match data URI with image data');
    }

    // Create uploads directory if it doesn't exist (WP7: guarded, no PHP exec)
    require_once '../includes/security.php';
    $uploadDir = ensureUploadDir('../uploads/signatures/');

    // Generate filename: signature_USERID_RANDOM.png
    $userId = $_SESSION['user_id'];
    $filename = 'signature_' . $userId . '_' . bin2hex(random_bytes(16)) . '.' . $type;
    $filepath = $uploadDir . $filename;

    // Save File
    if (file_put_contents($filepath, $data)) {

        // Delete old signature if exists
        $stmt = $pdo->prepare("SELECT signature_file FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldFile = $stmt->fetchColumn();

        if ($oldFile && file_exists($uploadDir . $oldFile)) {
            @unlink($uploadDir . $oldFile);
        }

        // Update Database
        $stmt = $pdo->prepare("UPDATE users SET signature_file = ? WHERE id = ?");
        $stmt->execute([$filename, $userId]);

        // Update Session
        $_SESSION['user_signature'] = $filename; // Cache it in session if needed

        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        throw new Exception('Failed to save file to disk');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>