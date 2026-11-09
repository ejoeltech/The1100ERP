<?php
header('Content-Type: application/json');
require_once '../../includes/session-check.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input', false, null, 0, 524289);
if ($rawBody === false || strlen($rawBody) > 524288) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'Proposal is too large to save.']);
    exit;
}

$input = json_decode($rawBody, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request body.']);
    exit;
}

try {
    $content = $input['content'] ?? '';
    $specs = $input['specs'] ?? [];
    $id = $input['id'] ?? null;
    $title = trim((string) ($input['title'] ?? ''));
    $customerName = trim((string) ($input['customer_name'] ?? ''));

    if (!is_string($content) || trim(strip_tags($content)) === '') {
        throw new InvalidArgumentException('Proposal content cannot be empty.');
    }
    $content = sanitizeRichTextHtml($content);
    if (trim(strip_tags($content)) === '') {
        throw new InvalidArgumentException('Proposal content cannot be empty.');
    }
    if (strlen($content) > 200000) {
        throw new InvalidArgumentException('Proposal content is too long.');
    }
    if (!is_array($specs)) {
        throw new InvalidArgumentException('System specifications must be an object.');
    }
    $specsJson = json_encode($specs, JSON_UNESCAPED_UNICODE);
    if ($specsJson === false || strlen($specsJson) > 20000) {
        throw new InvalidArgumentException('System specifications are too large.');
    }
    if ($title === '') {
        $title = 'Untitled Proposal';
    }
    $title = mb_substr($title, 0, 255);
    $customerName = mb_substr($customerName, 0, 255);

    if ($id !== null && $id !== '') {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new InvalidArgumentException('Invalid proposal id.');
        }

        // Fetch first so ownership and status can be checked per row.
        $stmt = $pdo->prepare("SELECT created_by, status FROM proposals WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Proposal not found.']);
            exit;
        }
        if (($existing['status'] ?? '') === 'converted') {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Converted proposals cannot be edited.']);
            exit;
        }
        // Admins/managers can edit any draft; sales reps only their own.
        requirePermission('edit_document', [
            'status' => 'draft',
            'created_by' => $existing['created_by'] ?? null,
        ], $existing['created_by'] ?? null);

        $stmt = $pdo->prepare("UPDATE proposals SET content = ?, system_specs = ?, title = ?, customer_name = ? WHERE id = ?");
        $stmt->execute([$content, $specsJson, $title, $customerName !== '' ? $customerName : null, $id]);
        $message = 'Proposal updated successfully';
    } else {
        requirePermission('create_document');
        $stmt = $pdo->prepare("INSERT INTO proposals (title, customer_name, content, system_specs, status, created_by) VALUES (?, ?, ?, ?, 'draft', ?)");
        $stmt->execute([$title, $customerName !== '' ? $customerName : null, $content, $specsJson, $_SESSION['user_id']]);
        $id = $pdo->lastInsertId();
        $message = 'Proposal draft saved successfully';
    }

    echo json_encode(['success' => true, 'id' => (int) $id, 'message' => $message]);

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Proposal save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the proposal. Please try again.']);
}
