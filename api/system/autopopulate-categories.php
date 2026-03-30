<?php
/**
 * API: Autopopulate Readymade Quote Categories
 * Extracts unique categories from products and syncs them to readymade_quote_categories
 */
include '../../includes/session-check.php';
require_once '../../config.php';

// Check permission
if (!requirePermission('manage_settings', true)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    // 1. Get unique categories from products
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND category != 'General'");
    $prod_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($prod_categories)) {
        echo json_encode([
            'success' => true,
            'message' => 'No new categories found in inventory.',
            'added_count' => 0
        ]);
        exit;
    }

    // 2. Get existing readymade categories
    $stmt = $pdo->query("SELECT category_name FROM readymade_quote_categories");
    $existing_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 3. Find missing ones
    $to_add = array_diff($prod_categories, $existing_categories);

    if (empty($to_add)) {
        echo json_encode([
            'success' => true,
            'message' => 'All inventory categories already exist in Readymade Quotes.',
            'added_count' => 0
        ]);
        exit;
    }

    // 4. Insert missing ones
    $stmt_insert = $pdo->prepare("INSERT INTO readymade_quote_categories (category_name, description, is_active) VALUES (?, ?, 1)");
    
    $added = 0;
    foreach ($to_add as $cat_name) {
        $stmt_insert->execute([$cat_name, 'Auto-populated from Inventory']);
        $added++;
    }

    // Log the activity
    logActivity($_SESSION['user_id'], 'maintenance', "Autopopulated $added categories from inventory");

    echo json_encode([
        'success' => true,
        'message' => "Successfully added $added new categories from inventory.",
        'added_count' => $added,
        'categories' => array_values($to_add)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
