<?php
/**
 * Waste Search API - Custom PHP
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$query = trim($_GET['q'] ?? '');
$category = trim($_GET['cat'] ?? '');

try {
    if (!empty($category)) {
        $stmt = $pdo->prepare("
            SELECT wi.*, wc.name AS category_name, wc.slug AS category_slug, wc.color, wc.dark_color, wc.bg_color, wc.icon AS category_icon
            FROM `waste_items` wi
            JOIN `waste_categories` wc ON wi.category_id = wc.id
            WHERE wc.slug = ?
            LIMIT 1
        ");
        $stmt->execute([$category]);
        $item = $stmt->fetch();
        if ($item) {
            $item['steps'] = json_decode($item['steps_json'], true);
            echo json_encode(['status' => 'success', 'item' => $item]);
            exit;
        }
    }

    if (!empty($query)) {
        $searchTerm = '%' . strtolower($query) . '%';
        $stmt = $pdo->prepare("
            SELECT wi.*, wc.name AS category_name, wc.slug AS category_slug, wc.color, wc.dark_color, wc.bg_color, wc.icon AS category_icon
            FROM `waste_items` wi
            JOIN `waste_categories` wc ON wi.category_id = wc.id
            WHERE LOWER(wi.name) LIKE ? OR LOWER(wi.keywords) LIKE ? OR LOWER(wc.name) LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $item = $stmt->fetch();

        if ($item) {
            $item['steps'] = json_decode($item['steps_json'], true);
            echo json_encode(['status' => 'success', 'item' => $item]);
            exit;
        } else {
            echo json_encode(['status' => 'not_found', 'message' => 'No matching waste item found. Try "plastic bottle", "battery", or "banana peel".']);
            exit;
        }
    }

    // Default item: Plastic Bottle
    $stmt = $pdo->prepare("
        SELECT wi.*, wc.name AS category_name, wc.slug AS category_slug, wc.color, wc.dark_color, wc.bg_color, wc.icon AS category_icon
        FROM `waste_items` wi
        JOIN `waste_categories` wc ON wi.category_id = wc.id
        WHERE wi.item_key = 'plasticBottle'
        LIMIT 1
    ");
    $stmt->execute();
    $item = $stmt->fetch();
    if ($item) {
        $item['steps'] = json_decode($item['steps_json'], true);
    }
    echo json_encode(['status' => 'success', 'item' => $item]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
