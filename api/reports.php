<?php
/**
 * Illegal Reports API - Custom PHP
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$action = $_REQUEST['action'] ?? 'submit';

if ($action === 'submit') {
    $userId = $_SESSION['user_id'] ?? 1;
    $wasteType = trim($_POST['waste_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $reportDate = $_POST['report_date'] ?? date('Y-m-d');
    $reportTime = $_POST['report_time'] ?? date('H:i:s');
    $location = trim($_POST['location'] ?? 'Subabasbas, Lapu-Lapu City');
    $city = trim($_POST['city'] ?? 'Lapu-Lapu City');
    $barangay = trim($_POST['barangay'] ?? 'Subabasbas');

    if (empty($wasteType)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select the type of waste.']);
        exit;
    }

    $photoUrl = null;
    if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmp = $_FILES['photo']['tmp_name'];
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['photo']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($fileTmp, $targetFile)) {
            $photoUrl = 'uploads/reports/' . $fileName;
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `illegal_reports` (`user_id`, `waste_type`, `description`, `report_date`, `report_time`, `location`, `city`, `barangay`, `photo_url`, `priority`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Normal', 'Pending')
        ");
        $stmt->execute([$userId, $wasteType, $description, $reportDate, $reportTime, $location, $city, $barangay, $photoUrl]);
        $newReportId = $pdo->lastInsertId();

        // Log user activity
        $actionText = 'Reported illegal dumping' . ($location ? ' at ' . $location : '');
        $logStmt = $pdo->prepare("INSERT INTO `user_activity_logs` (`user_id`, `action_text`) VALUES (?, ?)");
        $logStmt->execute([$userId, $actionText]);

        // Get updated total count
        $count = $pdo->query("SELECT COUNT(*) FROM `illegal_reports`")->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'message' => 'Report submitted successfully. Thank you for helping keep our community clean!',
            'report_id' => $newReportId,
            'total_reports' => $count,
            'location' => $location,
            'action_text' => $actionText,
            'date_formatted' => 'Just now'
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'list') {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $stmt = $pdo->prepare("
            SELECT ir.*, u.name AS reporter_name
            FROM `illegal_reports` ir
            LEFT JOIN `users` u ON ir.user_id = u.id
            ORDER BY ir.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $reports = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'reports' => $reports]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'update_status') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');

    $allowedStatuses = ['Pending', 'Under Review', 'Assigned', 'On the Way', 'Resolved'];
    if (!in_array($newStatus, $allowedStatuses)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE `illegal_reports` SET `status` = ? WHERE `id` = ?");
        $stmt->execute([$newStatus, $reportId]);
        echo json_encode(['status' => 'success', 'message' => 'Report status updated to ' . $newStatus]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
