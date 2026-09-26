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
        // Update: fetch first so ownership can be checked per row.
        $stmt = $pdo->prepare("SELECT created_by FROM proposals WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            throw new Exception('Proposal not found');
        }
        // WP3: creators/editors need create_document; sales reps touch own drafts only
        // (pre-ownership rows with NULL creator are manager+ only).
        requirePermission('create_document');
        if (getUserRole() === 'sales_rep' && (int)($existing['created_by'] ?? 0) !== (int)$_SESSION['user_id']) {
            throw new Exception('You do not have permission to edit this proposal', 403);
        }
        $stmt = $pdo->prepare("UPDATE proposals SET content = ?, system_specs = ?, title = ? WHERE id = ?");
        $stmt->execute([$content, json_encode($specs), $title, $id]);
        $message = "Proposal updated successfully";
    } else {
        // Insert
        requirePermission('create_document');
        $stmt = $pdo->prepare("INSERT INTO proposals (title, content, system_specs, status, created_by) VALUES (?, ?, ?, 'draft', ?)");
        $stmt->execute([$title, $content, json_encode($specs), $_SESSION['user_id']]);
        $id = $pdo->lastInsertId();
        $message = "Proposal draft saved successfully";
    }

    echo json_encode(['success' => true, 'id' => $id, 'message' => $message]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
