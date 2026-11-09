<?php
header('Content-Type: application/json');
require_once '../../includes/session-check.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $filter = getRoleFilter('p');
    $sql = "SELECT p.id, p.title, p.customer_name, p.status, p.converted_quote_id,
                   p.updated_at
            FROM proposals p
            WHERE 1=1" . $filter['sql'] . "
            ORDER BY p.updated_at DESC
            LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filter['params']);

    echo json_encode([
        'success' => true,
        'proposals' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    error_log('Proposal list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load proposals.']);
}
