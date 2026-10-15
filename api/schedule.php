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
            ORDER BY 
                CASE WHEN cs.collection_date IS NOT NULL THEN cs.collection_date ELSE '9999-12-31' END ASC,
                cs.day_of_week ASC, 
                cs.start_time ASC
        ");
        $stmt->execute([$city, $barangay]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no specific schedules found for this barangay, return generic city schedules
        if (empty($schedules)) {
            $stmtFallback = $pdo->prepare("
                SELECT cs.*, wc.name AS category_name, wc.slug AS category_slug, wc.color AS category_color, wc.icon AS category_icon
                FROM `collection_schedules` cs
                JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
                WHERE cs.city = ?
                ORDER BY 
                    CASE WHEN cs.collection_date IS NOT NULL THEN cs.collection_date ELSE '9999-12-31' END ASC,
                    cs.day_of_week ASC, 
                    cs.start_time ASC
            ");
            $stmtFallback->execute([$city]);
            $schedules = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
        }

        // If still empty, return all schedules
        if (empty($schedules)) {
            $stmtAll = $pdo->query("
                SELECT cs.*, wc.name AS category_name, wc.slug AS category_slug, wc.color AS category_color, wc.icon AS category_icon
                FROM `collection_schedules` cs
                JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
                ORDER BY cs.day_of_week ASC, cs.start_time ASC
                LIMIT 10
            ");
            $schedules = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['status' => 'success', 'schedules' => $schedules]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'add' || $action === 'add_task') {
    $city = trim($_POST['city'] ?? 'North District');
    $barangay = trim($_POST['barangay'] ?? $city);
    $streetRoute = trim($_POST['street_route'] ?? $_POST['location'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? $_POST['waste_category_id'] ?? 1);
    $status = trim($_POST['status'] ?? 'Scheduled');
    $collectionDate = !empty($_POST['collection_date']) ? trim($_POST['collection_date']) : null;
    
    if ($collectionDate) {
        $dayOfWeek = (int)date('w', strtotime($collectionDate));
    } else {
        $dayOfWeek = isset($_POST['day_of_week']) ? (int)$_POST['day_of_week'] : (int)date('w');
    }

    $startTime = !empty($_POST['start_time']) ? trim($_POST['start_time']) : '08:00:00';
    $endTime = !empty($_POST['end_time']) ? trim($_POST['end_time']) : '09:00:00';
    if (strlen($startTime) === 5) $startTime .= ':00';
    if (strlen($endTime) === 5) $endTime .= ':00';

    try {
        // Calculate route_order
        $orderStmt = $pdo->query("SELECT COALESCE(MAX(route_order), 0) + 1 AS next_order FROM `collection_schedules`");
        $nextOrder = (int)$orderStmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO `collection_schedules` (`city`, `barangay`, `street_route`, `waste_category_id`, `day_of_week`, `collection_date`, `start_time`, `end_time`, `status`, `route_order`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$city, $barangay, $streetRoute, $categoryId, $dayOfWeek, $collectionDate, $startTime, $endTime, $status, $nextOrder]);
        $newId = $pdo->lastInsertId();

        // Fetch newly created record with category info
        $stmtGet = $pdo->prepare("
            SELECT cs.*, wc.name AS category_name, wc.slug AS category_slug, wc.color AS category_color, wc.icon AS category_icon
            FROM `collection_schedules` cs
            LEFT JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
            WHERE cs.id = ?
        ");
        $stmtGet->execute([$newId]);
        $item = $stmtGet->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'message' => 'Route task added successfully!', 'schedule' => $item]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'update_status' || $action === 'update_schedule' || $action === 'update_task') {
    $scheduleId = (int)($_POST['schedule_id'] ?? $_POST['id'] ?? 0);
    if (!$scheduleId) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid schedule ID.']);
        exit;
    }

    $updates = [];
    $params  = [];

    // 1. Status update
    if (!empty($_POST['status'])) {
        $allowed = ['Scheduled', 'In Progress', 'Completed', 'Cancelled', 'Pending'];
        $newStatus = trim($_POST['status']);
        if (in_array($newStatus, $allowed)) {
            $updates[] = "`status` = ?";
            $params[]  = $newStatus;
        }
    }

    // 2. Location / Street update
    if (isset($_POST['street_route']) || isset($_POST['location'])) {
        $location = trim($_POST['street_route'] ?? $_POST['location']);
        $updates[] = "`street_route` = ?";
        $params[]  = $location;
    }

    // 3. Start time update
    if (!empty($_POST['start_time'])) {
        $st = trim($_POST['start_time']);
        if (strlen($st) === 5) $st .= ':00';
        $updates[] = "`start_time` = ?";
        $params[]  = $st;
    }

    // 4. End time update
    if (!empty($_POST['end_time'])) {
        $et = trim($_POST['end_time']);
        if (strlen($et) === 5) $et .= ':00';
        $updates[] = "`end_time` = ?";
        $params[]  = $et;
    }

    // 5. Waste category update
    if (!empty($_POST['category_id']) || !empty($_POST['waste_category_id'])) {
        $catId = (int)($_POST['category_id'] ?? $_POST['waste_category_id']);
        if ($catId > 0) {
            $updates[] = "`waste_category_id` = ?";
            $params[]  = $catId;
        }
    }

    if (empty($updates)) {
        echo json_encode(['status' => 'error', 'message' => 'No changes specified.']);
        exit;
    }

    try {
        $params[] = $scheduleId;
        $sql = "UPDATE `collection_schedules` SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Fetch updated record with category details
        $stmtGet = $pdo->prepare("
            SELECT cs.*, wc.name AS category_name, wc.slug AS category_slug, wc.color AS category_color, wc.icon AS category_icon
            FROM `collection_schedules` cs
            LEFT JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
            WHERE cs.id = ?
        ");
        $stmtGet->execute([$scheduleId]);
        $item = $stmtGet->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'message' => "Schedule #$scheduleId updated successfully.", 'schedule' => $item]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);

