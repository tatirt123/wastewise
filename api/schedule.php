<?php
/**
 * Collection Schedule API - Custom PHP
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$action = $_REQUEST['action'] ?? 'get';

if ($action === 'get') {
    $city = trim($_GET['city'] ?? 'Lapu-Lapu City');
    $barangay = trim($_GET['barangay'] ?? 'Subabasbas');

    try {
        $stmt = $pdo->prepare("
            SELECT cs.*, wc.name AS category_name, wc.slug AS category_slug, wc.color AS category_color, wc.icon AS category_icon
            FROM `collection_schedules` cs
            JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
            WHERE cs.city = ? AND cs.barangay = ?
            ORDER BY cs.day_of_week ASC, cs.start_time ASC
        ");
        $stmt->execute([$city, $barangay]);
        $schedules = $stmt->fetchAll();

        // If no specific schedules found for this barangay, return generic city schedules
        if (empty($schedules)) {
            $stmtFallback = $pdo->prepare("
                SELECT cs.*, wc.name AS category_name, wc.slug AS category_slug, wc.color AS category_color, wc.icon AS category_icon
                FROM `collection_schedules` cs
                JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
                WHERE cs.city = ?
                ORDER BY cs.day_of_week ASC, cs.start_time ASC
            ");
            $stmtFallback->execute([$city]);
            $schedules = $stmtFallback->fetchAll();
        }

        echo json_encode(['status' => 'success', 'schedules' => $schedules]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'add') {
    $city = trim($_POST['city'] ?? 'Lapu-Lapu City');
    $barangay = trim($_POST['barangay'] ?? 'Subabasbas');
    $streetRoute = trim($_POST['street_route'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 1);
    $dayOfWeek = (int)($_POST['day_of_week'] ?? 1);
    $startTime = $_POST['start_time'] ?? '07:00:00';
    $endTime = $_POST['end_time'] ?? '10:00:00';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `collection_schedules` (`city`, `barangay`, `street_route`, `waste_category_id`, `day_of_week`, `start_time`, `end_time`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Scheduled')
        ");
        $stmt->execute([$city, $barangay, $streetRoute, $categoryId, $dayOfWeek, $startTime, $endTime]);
        echo json_encode(['status' => 'success', 'message' => 'Schedule added successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
