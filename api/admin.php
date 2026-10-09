<?php
/**
 * WasteWise Admin API - Custom PHP
 * Handles: user management, report management, schedule management, stats
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$action = $_REQUEST['action'] ?? '';

/* ====== GET ALL USERS ====== */
if ($action === 'get_users') {
    try {
        $stmt = $pdo->query("SELECT id, name, email, role, city, barangay, eco_points, level, created_at FROM `users` ORDER BY id DESC");
        $users = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'users' => $users, 'count' => count($users)]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

/* ====== UPDATE USER ROLE ====== */
if ($action === 'update_user_role') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $newRole = trim($_POST['role'] ?? '');
    $allowed = ['user', 'collector', 'admin'];
    if (!$userId || !in_array($newRole, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user or role.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("UPDATE `users` SET `role` = ? WHERE `id` = ?");
        $stmt->execute([$newRole, $userId]);
        echo json_encode(['status' => 'success', 'message' => "User role updated to $newRole."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

/* ====== GET ALL REPORTS ====== */
if ($action === 'get_reports') {
    $statusFilter = trim($_GET['status'] ?? '');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    try {
        if ($statusFilter) {
            $stmt = $pdo->prepare("
                SELECT ir.*, u.name AS reporter_name
                FROM `illegal_reports` ir
                LEFT JOIN `users` u ON ir.user_id = u.id
                WHERE ir.status = ?
                ORDER BY ir.id DESC LIMIT ?
            ");
            $stmt->bindValue(1, $statusFilter, PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT ir.*, u.name AS reporter_name
                FROM `illegal_reports` ir
                LEFT JOIN `users` u ON ir.user_id = u.id
                ORDER BY ir.id DESC LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
        }
        $reports = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'reports' => $reports, 'count' => count($reports)]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

/* ====== UPDATE REPORT STATUS (Admin) ====== */
if ($action === 'update_report') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $priority  = trim($_POST['priority'] ?? '');
    $collectorId = (int)($_POST['collector_id'] ?? 0);

    $allowed = ['Pending', 'Under Review', 'Assigned', 'On the Way', 'Resolved'];
    if (!$reportId || !in_array($newStatus, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid report or status.']);
        exit;
    }

    try {
        $fields = ['status = ?'];
        $params = [$newStatus];

        if ($priority) {
            $fields[] = 'priority = ?';
            $params[] = $priority;
        }
        if ($collectorId) {
            $fields[] = 'collector_id = ?';
            $params[] = $collectorId;
        }

        $params[] = $reportId;
        $sql = "UPDATE `illegal_reports` SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'message' => "Report #$reportId updated to $newStatus."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

/* ====== DELETE REPORT ====== */
if ($action === 'delete_report') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    if (!$reportId) {
        echo json_encode(['status' => 'error', 'message' => 'Report ID required.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM `illegal_reports` WHERE id = ?");
        $stmt->execute([$reportId]);
        echo json_encode(['status' => 'success', 'message' => "Report #$reportId deleted."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

/* ====== GET SCHEDULES ====== */
if ($action === 'get_schedules') {
    try {
        $stmt = $pdo->query("
            SELECT cs.*, wc.name AS category_name, wc.color AS category_color, wc.slug AS category_slug
            FROM `collection_schedules` cs
            JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
            ORDER BY cs.id DESC
        ");
        $schedules = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'schedules' => $schedules]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

/* ====== ADD SCHEDULE ====== */
if ($action === 'add_schedule') {
    $city       = trim($_POST['city'] ?? 'Lapu-Lapu City');
    $barangay   = trim($_POST['barangay'] ?? '');
    $streetRoute = trim($_POST['street_route'] ?? '');
    $catId      = (int)($_POST['waste_category_id'] ?? 1);
    $dayOfWeek  = (int)($_POST['day_of_week'] ?? 1);
    $startTime  = trim($_POST['start_time'] ?? '07:00:00');
    $endTime    = trim($_POST['end_time'] ?? '10:00:00');
    $freq       = trim($_POST['frequency'] ?? 'Weekly');

    if (empty($barangay)) {
        echo json_encode(['status' => 'error', 'message' => 'Barangay is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `collection_schedules` (`city`, `barangay`, `street_route`, `waste_category_id`, `day_of_week`, `start_time`, `end_time`, `frequency`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled')
        ");
        $stmt->execute([$city, $barangay, $streetRoute, $catId, $dayOfWeek, $startTime, $endTime, $freq]);
        echo json_encode(['status' => 'success', 'message' => "Schedule added for $barangay.", 'id' => $pdo->lastInsertId()]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

/* ====== UPDATE SCHEDULE STATUS ====== */
if ($action === 'update_schedule') {
    $scheduleId = (int)($_POST['schedule_id'] ?? 0);
    $newStatus  = trim($_POST['status'] ?? '');
    $allowed    = ['Scheduled', 'In Progress', 'Completed', 'Cancelled'];

    if (!$scheduleId || !in_array($newStatus, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid schedule or status.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("UPDATE `collection_schedules` SET `status` = ? WHERE id = ?");
        $stmt->execute([$newStatus, $scheduleId]);
        echo json_encode(['status' => 'success', 'message' => "Schedule #$scheduleId updated to $newStatus."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

/* ====== GET DASHBOARD STATS ====== */
if ($action === 'get_stats') {
    try {
        $totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
        $totalReports   = (int)$pdo->query("SELECT COUNT(*) FROM `illegal_reports`")->fetchColumn();
        $pendingReports = (int)$pdo->query("SELECT COUNT(*) FROM `illegal_reports` WHERE status = 'Pending'")->fetchColumn();
        $resolvedReports= (int)$pdo->query("SELECT COUNT(*) FROM `illegal_reports` WHERE status = 'Resolved'")->fetchColumn();
        $totalSchedules = (int)$pdo->query("SELECT COUNT(*) FROM `collection_schedules`")->fetchColumn();
        $totalCenters   = (int)$pdo->query("SELECT COUNT(*) FROM `recycling_centers`")->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'stats' => [
                'total_users'      => $totalUsers,
                'total_reports'    => $totalReports,
                'pending_reports'  => $pendingReports,
                'resolved_reports' => $resolvedReports,
                'total_schedules'  => $totalSchedules,
                'total_centers'    => $totalCenters,
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action. Available: get_users, update_user_role, get_reports, update_report, delete_report, get_schedules, add_schedule, update_schedule, get_stats']);
