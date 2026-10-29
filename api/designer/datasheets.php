<?php
// Public read-only catalogue search used by the System Designer.
require_once '../../includes/security.php';
configureSessionCookies();
session_start();
define('IS_API', true);
define('ALLOW_PUBLIC', true);

require_once '../../config.php';
require_once '../../includes/db.php';
require_once '../../includes/equipment-datasheets.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$query = trim((string)($_GET['q'] ?? ''));
$category = strtolower(trim((string)($_GET['category'] ?? '')));

if (function_exists('mb_substr')) {
    $query = mb_substr($query, 0, 80);
} else {
    $query = substr($query, 0, 80);
}

if ($query === '' && $category === '') {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

if ($category !== '' && !equipmentDatasheetCategory($category)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Unknown equipment category.']);
    exit;
}

try {
    $rows = equipmentDatasheetSearch($pdo, $query, $category, 12);
    echo json_encode([
        'success' => true,
        'results' => array_map('equipmentDatasheetPublicPayload', $rows),
    ]);
} catch (PDOException $e) {
    error_log('Datasheet search failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The equipment catalogue is unavailable right now.']);
}
