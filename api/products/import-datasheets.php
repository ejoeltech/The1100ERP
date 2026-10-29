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

$file = $_FILES['file'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a CSV file to import.']);
    exit;
}
if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The CSV file is larger than 2 MB. Split it into smaller files.']);
    exit;
}

$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ['csv', 'txt'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Upload a .csv file (the template is available on this page).']);
    exit;
}

$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not read the uploaded file.']);
    exit;
}

$rawHeader = fgetcsv($handle, 0, ',', '"', '\\');
if (!is_array($rawHeader) || $rawHeader === []) {
    fclose($handle);
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The CSV file is empty.']);
    exit;
}

// Normalise headers: "Max Voltage (V)" -> "max_voltage", strip a UTF-8 BOM.
$aliases = [
    'type' => 'category',
    'equipment_type' => 'category',
    'manufacturer' => 'brand',
    'make' => 'brand',
    'module' => 'model',
    'wattage' => 'power_w',
    'watts' => 'power_w',
    'rated_power_w' => 'power_w',
    'voc_v' => 'voc',
    'isc_a' => 'isc',
    'vmp_v' => 'vmp',
    'imp_a' => 'imp',
    'capacity_kva' => 'capacity_w',
    'inverter_capacity_w' => 'capacity_w',
    'controller_capacity_w' => 'controller_capacity_w',
    'max_charge_a' => 'max_charge_current',
    'max_discharge_a' => 'max_discharge_current',
];
$header = [];
foreach ($rawHeader as $index => $column) {
    $column = preg_replace('/^\xEF\xBB\xBF/', '', (string)$column);
    $key = strtolower(trim($column));
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    $key = trim($key, '_');
    if (isset($aliases[$key])) {
        $key = $aliases[$key];
    }
    $header[$index] = $key;
}

$allowed = array_merge(
    ['category', 'brand', 'model', 'title', 'status', 'notes', 'source_url'],
    equipmentDatasheetColumns()
);
if (!in_array('category', $header, true) || !in_array('brand', $header, true) || !in_array('model', $header, true)) {
    fclose($handle);
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'The CSV needs at least category, brand and model columns. Download the template for the exact format.',
    ]);
    exit;
}

$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];
$rowNumber = 1;
$userId = $_SESSION['user_id'] ?? null;

try {
    $pdo->beginTransaction();

    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $rowNumber++;
        if ($row === [null] || $row === []) {
            continue;
        }

        $input = [];
        foreach ($header as $index => $key) {
            if ($key !== '' && in_array($key, $allowed, true)) {
                $input[$key] = $row[$index] ?? '';
            }
        }

        if (trim((string)($input['brand'] ?? '')) === '' && trim((string)($input['model'] ?? '')) === '') {
            continue;
        }

        $data = equipmentDatasheetNormalize($input, 'published');
        $validation = equipmentDatasheetRequiredError($data);
        if ($validation !== null || $data['brand'] === '' || $data['model'] === '') {
            $skipped++;
            if (count($errors) < 20) {
                $errors[] = 'Row ' . $rowNumber . ': ' . ($validation['message'] ?? 'brand and model are required.');
            }
            continue;
        }

        $find = $pdo->prepare("
            SELECT id FROM equipment_datasheets
            WHERE category = ? AND LOWER(TRIM(brand)) = LOWER(?) AND LOWER(TRIM(model)) = LOWER(?)
            LIMIT 1
        ");
        $find->execute([$data['category'], $data['brand'], $data['model']]);
        $existingId = (int)($find->fetchColumn() ?: 0);

        $saved = equipmentDatasheetUpsert($pdo, $data, $existingId, $userId);
        if ($saved['created']) {
            $inserted++;
        } else {
            $updated++;
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fclose($handle);
    error_log('Datasheet import failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The import stopped and was rolled back. Check the server log.']);
    exit;
}
fclose($handle);

if (function_exists('logAudit')) {
    logAudit('import', 'equipment_datasheet', null, [
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
    ]);
}

echo json_encode([
    'success' => true,
    'inserted' => $inserted,
    'updated' => $updated,
    'skipped' => $skipped,
    'errors' => $errors,
    'message' => "Import finished: $inserted added, $updated updated, $skipped skipped.",
]);
