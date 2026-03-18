<?php
require_once '../../config.php';
include '../../includes/session-check.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT * FROM item_categories ORDER BY name ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'save':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');

            if (empty($name)) throw new Exception('Category name is required');

            if ($id) {
                $stmt = $pdo->prepare("UPDATE item_categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO item_categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            if ($method !== 'POST') throw new Exception('Invalid method');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;

            if (!$id) throw new Exception('ID is required');

            // Check if items are using this category
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE category_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete category as it is currently assigned to items');
            }

            $stmt = $pdo->prepare("DELETE FROM item_categories WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
