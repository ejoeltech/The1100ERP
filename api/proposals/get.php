<?php
header('Content-Type: application/json');
require_once '../../includes/session-check.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A valid proposal ID is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, title, customer_name, content, system_specs, status,
                                  converted_quote_id, created_by, created_at, updated_at
                           FROM proposals WHERE id = ?");
    $stmt->execute([$id]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposal) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Proposal not found.']);
        exit;
    }

    // Sales reps may only open their own proposals; other roles use the
    // same ownership scoping as the list endpoint.
    if (getUserRole() === 'sales_rep' && (int) ($proposal['created_by'] ?? 0) !== (int) $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to view this proposal.']);
        exit;
    }

    $specs = json_decode($proposal['system_specs'] ?? '', true);
    if (!is_array($specs)) {
        $specs = [];
    }

    echo json_encode([
        'success' => true,
        'proposal' => [
            'id' => (int) $proposal['id'],
            'title' => $proposal['title'],
            'customer_name' => $proposal['customer_name'] ?? '',
            'content' => sanitizeRichTextHtml($proposal['content'] ?? ''),
            'specs' => $specs,
            'status' => $proposal['status'] ?? 'draft',
            'converted_quote_id' => $proposal['converted_quote_id'] !== null
                ? (int) $proposal['converted_quote_id'] : null,
            'created_at' => $proposal['created_at'],
            'updated_at' => $proposal['updated_at'],
        ],
    ]);
} catch (Throwable $e) {
    error_log('Proposal load failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load the proposal.']);
}
