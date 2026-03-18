<?php
require_once '../../config.php';
include '../../includes/session-check.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $search = $_GET['search'] ?? '';
            $category_id = $_GET['category_id'] ?? '';
            $status = $_GET['status'] ?? 'active';

            $query = "SELECT i.*, c.name as category_name 
                      FROM items i 
                      LEFT JOIN item_categories c ON i.category_id = c.id 
                      WHERE 1=1";
            $params = [];

            if ($search) {
                $query .= " AND (i.name LIKE ? OR i.sku LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            if ($category_id) {
                $query .= " AND i.category_id = ?";
                $params[] = $category_id;
            }
            if ($status) {
                $query .= " AND i.status = ?";
                $params[] = $status;
            }

            $query .= " ORDER BY i.name ASC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'save':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = $data['id'] ?? null;
            $name = trim($data['name'] ?? '');
            $sku = trim($data['sku'] ?? '');
            $category_id = $data['category_id'] ?: null;
            $description = trim($data['description'] ?? '');
            $unit = trim($data['unit'] ?? '');
            $price = floatval($data['price'] ?? 0);
            $cost_price = floatval($data['cost_price'] ?? 0);
            $stock_quantity = intval($data['stock_quantity'] ?? 0);
            $minimum_stock = intval($data['minimum_stock'] ?? 0);
            $status = $data['status'] ?? 'active';

            if (empty($name)) throw new Exception('Item name is required');

            if ($id) {
                $stmt = $pdo->prepare("UPDATE items SET name = ?, sku = ?, category_id = ?, description = ?, unit = ?, price = ?, cost_price = ?, stock_quantity = ?, minimum_stock = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $sku, $category_id, $description, $unit, $price, $cost_price, $stock_quantity, $minimum_stock, $status, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO items (name, sku, category_id, description, unit, price, cost_price, stock_quantity, minimum_stock, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $sku, $category_id, $description, $unit, $price, $cost_price, $stock_quantity, $minimum_stock, $status]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('ID is required');

            // Hard delete or Soft delete? I'll do hard delete for now as per instructions.
            $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'archive':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('ID is required');

            $stmt = $pdo->prepare("UPDATE items SET status = 'archived' WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'bulk_save':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);
            $items = $data['items'] ?? [];
            if (empty($items)) throw new Exception('No items to save');

            $pdo->beginTransaction();
            foreach ($items as $item) {
                $category_name = trim($item['category_name'] ?? 'General');
                
                // Ensure category exists
                $stmt = $pdo->prepare("SELECT id FROM item_categories WHERE LOWER(name) = LOWER(?)");
                $stmt->execute([$category_name]);
                $cat = $stmt->fetch();
                
                if ($cat) {
                    $category_id = $cat['id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO item_categories (name) VALUES (?)");
                    $stmt->execute([$category_name]);
                    $category_id = $pdo->lastInsertId();
                }

                $name = trim($item['name'] ?? '');
                $sku = trim($item['sku'] ?? '');
                $description = trim($item['description'] ?? '');
                $unit = trim($item['unit'] ?? '');
                $price = floatval($item['price'] ?? 0);
                $cost_price = floatval($item['cost_price'] ?? 0);
                $status = 'active';

                if (empty($name)) continue;

                $stmt = $pdo->prepare("INSERT INTO items (name, sku, category_id, description, unit, price, cost_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $sku, $category_id, $description, $unit, $price, $cost_price, $status]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'bulk_delete':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);
            $ids = $data['ids'] ?? [];
            if (empty($ids)) throw new Exception('No items selected');

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM items WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
