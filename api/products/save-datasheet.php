<?php
require_once '../../includes/session-check.php';
requirePermission('manage_products');
require_once '../../includes/equipment-datasheets.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit;
}

$data = equipmentDatasheetNormalize($input, 'published');

if ($data['category'] === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a valid equipment category.']);
    exit;
}
if ($data['brand'] === '' || $data['model'] === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Brand and model are required.']);
    exit;
}

$requiredError = equipmentDatasheetRequiredError($data);
if ($requiredError !== null) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $requiredError['message'],
        'field' => $requiredError['field'],
    ]);
    exit;
}

$id = (int)($input['id'] ?? 0);

try {
    if ($id > 0) {
        $exists = $pdo->prepare('SELECT id FROM equipment_datasheets WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'That datasheet no longer exists.']);
            exit;
        }
    }

    // Keep one row per category + brand + model so the designer search never
    // shows two identical entries. Case-insensitive, trimmed.
    $dup = $pdo->prepare("
        SELECT id FROM equipment_datasheets
        WHERE category = ? AND LOWER(TRIM(brand)) = LOWER(?) AND LOWER(TRIM(model)) = LOWER(?)
          AND id <> ?
        LIMIT 1
    ");
    $dup->execute([$data['category'], $data['brand'], $data['model'], $id]);
    $duplicateId = $dup->fetchColumn();
    if ($duplicateId) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'A datasheet for this brand and model already exists.',
            'existing_id' => (int)$duplicateId,
        ]);
        exit;
    }

    $saved = equipmentDatasheetUpsert($pdo, $data, $id, $_SESSION['user_id'] ?? null);

    if ($saved['id'] < 1) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'That datasheet no longer exists.']);
        exit;
    }

    if (function_exists('logAudit')) {
        logAudit($saved['created'] ? 'create' : 'update', 'equipment_datasheet', $saved['id'], [
            'category' => $data['category'],
            'brand' => $data['brand'],
            'model' => $data['model'],
        ]);
    }

    echo json_encode([
        'success' => true,
        'id' => $saved['id'],
        'message' => $saved['created'] ? 'Datasheet created.' : 'Datasheet updated.',
    ]);
} catch (PDOException $e) {
    error_log('Datasheet save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the datasheet. Check the server log.']);
}
