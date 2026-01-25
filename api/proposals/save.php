<?php
header('Content-Type: application/json');
require_once '../../includes/session-check.php';

// Check permissions
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    $content = $input['content'] ?? '';
    $specs = $input['specs'] ?? [];
    $id = $input['id'] ?? null;
    $title = $input['title'] ?? 'Untitled Proposal';

    // Basic validation
    if (empty($content)) {
        throw new Exception('Proposal content cannot be empty');
    }

    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE proposals SET content = ?, system_specs = ?, title = ? WHERE id = ?");
        $stmt->execute([$content, json_encode($specs), $title, $id]);
        $message = "Proposal updated successfully";
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO proposals (title, content, system_specs, status) VALUES (?, ?, ?, 'draft')");
        $stmt->execute([$title, $content, json_encode($specs)]);
        $id = $pdo->lastInsertId();
        $message = "Proposal draft saved successfully";
    }

    echo json_encode(['success' => true, 'id' => $id, 'message' => $message]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
