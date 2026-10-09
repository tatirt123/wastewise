<?php
/**
 * Community Activities API - Custom PHP
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'] ?? 1;
$action = $_REQUEST['action'] ?? 'join';

if ($action === 'join') {
    $activityId = (int)($_POST['activity_id'] ?? 0);
    $activityName = trim($_POST['activity_name'] ?? '');

    try {
        if ($activityId > 0) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO `activity_participants` (`activity_id`, `user_id`) VALUES (?, ?)");
            $stmt->execute([$activityId, $userId]);
        }

        // Add to user activity logs
        $logText = 'Joined ' . ($activityName ?: 'Community Activity');
        $stmtLog = $pdo->prepare("INSERT INTO `user_activity_logs` (`user_id`, `action_text`) VALUES (?, ?)");
        $stmtLog->execute([$userId, $logText]);

        // Award eco points (+50 points)
        $stmtPts = $pdo->prepare("UPDATE `users` SET `eco_points` = `eco_points` + 50 WHERE `id` = ?");
        $stmtPts->execute([$userId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Successfully joined ' . ($activityName ?: 'the activity') . '! You earned 50 Eco Points.',
            'action_text' => $logText,
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
        $stmt = $pdo->query("SELECT * FROM `community_activities` ORDER BY `activity_date` ASC");
        $activities = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'activities' => $activities]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
