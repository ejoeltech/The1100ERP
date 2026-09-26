<?php
// Internal Accessories Store API (tools/consumables owned by the business, not for sale).
require_once '../../config.php';
include '../../includes/session-check.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// WP3: accessories writes need manage_accessories. Reads (list/categories/
// transactions) stay open for logged-in users; the manage page itself is gated.
if (!in_array($action, ['list', 'categories', 'transactions', ''], true)) {
    requirePermission('manage_accessories');
}

try {
    switch ($action) {
        case 'list':
            $search = $_GET['search'] ?? '';
            $category = $_GET['category'] ?? '';
            $status = $_GET['status'] ?? 'active';

            $query = "SELECT * FROM accessories WHERE 1=1";
            $params = [];

            if ($search) {
                $query .= " AND (name LIKE ? OR code LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            if ($category) {
                $query .= " AND category = ?";
                $params[] = $category;
            }
            if ($status) {
                $query .= " AND status = ?";
                $params[] = $status;
            }

            $query .= " ORDER BY name ASC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'categories':
            $stmt = $pdo->query("SELECT DISTINCT category FROM accessories WHERE status = 'active' ORDER BY category ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
            break;

        case 'save':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);

            $id = $data['id'] ?? null;
            $name = trim($data['name'] ?? '');
            $code = trim($data['code'] ?? '');
            $category = trim($data['category'] ?? 'General') ?: 'General';
            $description = trim($data['description'] ?? '');
            $unit = trim($data['unit'] ?? 'pcs') ?: 'pcs';
            $unit_cost = floatval($data['unit_cost'] ?? 0);
            $stock_quantity = intval($data['stock_quantity'] ?? 0);
            $minimum_stock = intval($data['minimum_stock'] ?? 0);
            $location = trim($data['location'] ?? '');
            $condition_status = in_array($data['condition_status'] ?? '', ['good', 'fair', 'faulty']) ? $data['condition_status'] : 'good';
            $status = in_array($data['status'] ?? '', ['active', 'archived']) ? $data['status'] : 'active';

            if (empty($name)) throw new Exception('Accessory name is required');
            if ($stock_quantity < 0 || $minimum_stock < 0) throw new Exception('Stock values cannot be negative');
            if ($unit_cost < 0) throw new Exception('Unit cost cannot be negative');

            if ($id) {
                $stmt = $pdo->prepare("UPDATE accessories SET name = ?, code = ?, category = ?, description = ?, unit = ?, unit_cost = ?, stock_quantity = ?, minimum_stock = ?, location = ?, condition_status = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $code, $category, $description, $unit, $unit_cost, $stock_quantity, $minimum_stock, $location, $condition_status, $status, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO accessories (name, code, category, description, unit, unit_cost, stock_quantity, minimum_stock, location, condition_status, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $code, $category, $description, $unit, $unit_cost, $stock_quantity, $minimum_stock, $location, $condition_status, $status, $_SESSION['user_id'] ?? null]);
                $id = $pdo->lastInsertId();
            }
            echo json_encode(['success' => true, 'id' => (int)$id]);
            break;

        case 'adjust':
            // Stock in (restock/return) or out (issue to technician/job)
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);

            $id = intval($data['id'] ?? 0);
            $type = $data['type'] ?? '';
            $quantity = intval($data['quantity'] ?? 0);
            $technician = trim($data['technician'] ?? '');
            $purpose = trim($data['purpose'] ?? '');
            $notes = trim($data['notes'] ?? '');

            if (!$id) throw new Exception('Accessory ID is required');
            if (!in_array($type, ['in', 'out'])) throw new Exception('Invalid adjustment type');
            if ($quantity <= 0) throw new Exception('Quantity must be greater than zero');

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT stock_quantity FROM accessories WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) throw new Exception('Accessory not found');

            $balance = (int)$row['stock_quantity'] + ($type === 'in' ? $quantity : -$quantity);
            if ($balance < 0) throw new Exception('Insufficient stock (have ' . $row['stock_quantity'] . ')');

            $stmt = $pdo->prepare("UPDATE accessories SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$balance, $id]);

            $stmt = $pdo->prepare("INSERT INTO accessory_transactions (accessory_id, type, quantity, balance_after, technician, purpose, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $type, $quantity, $balance, $technician ?: null, $purpose ?: null, $notes ?: null, $_SESSION['user_id'] ?? null]);

            $pdo->commit();
            echo json_encode(['success' => true, 'balance' => $balance]);
            break;

        case 'transactions':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) throw new Exception('Accessory ID is required');
            $stmt = $pdo->prepare("SELECT t.*, u.full_name AS recorded_by FROM accessory_transactions t LEFT JOIN users u ON u.id = t.created_by WHERE t.accessory_id = ? ORDER BY t.created_at DESC LIMIT 100");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'delete':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('ID is required');
            $stmt = $pdo->prepare("DELETE FROM accessories WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'archive':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('ID is required');
            $stmt = $pdo->prepare("UPDATE accessories SET status = 'archived' WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'bulk_save':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);
            $items = $data['items'] ?? [];
            if (empty($items)) throw new Exception('No items to save');

            $pdo->beginTransaction();
            $count = 0;
            foreach ($items as $item) {
                $name = trim($item['name'] ?? '');
                if (empty($name)) continue;
                $stmt = $pdo->prepare("INSERT INTO accessories (name, code, category, description, unit, unit_cost, stock_quantity, minimum_stock, location, condition_status, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $name,
                    trim($item['code'] ?? ''),
                    trim($item['category'] ?? 'General') ?: 'General',
                    trim($item['description'] ?? ''),
                    trim($item['unit'] ?? 'pcs') ?: 'pcs',
                    floatval($item['unit_cost'] ?? $item['price'] ?? 0),
                    intval($item['stock_quantity'] ?? 0),
                    intval($item['minimum_stock'] ?? 0),
                    trim($item['location'] ?? ''),
                    'good',
                    'active',
                    $_SESSION['user_id'] ?? null,
                ]);
                $count++;
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'saved' => $count]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
