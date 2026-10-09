<?php
/**
 * User Profile API - Custom PHP
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'] ?? 1;
$action = $_REQUEST['action'] ?? 'update';

if ($action === 'update') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');

    if (empty($name) || empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Name and email are required.']);
        exit;
    }

    $avatarUrl = null;
    if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmp = $_FILES['avatar']['tmp_name'];
        $fileName = 'avatar_' . $userId . '_' . time() . '.' . pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($fileTmp, $targetFile)) {
            $avatarUrl = 'uploads/avatars/' . $fileName;
        }
    }

    try {
        if ($avatarUrl) {
            $stmt = $pdo->prepare("UPDATE `users` SET `name` = ?, `email` = ?, `barangay` = ?, `avatar` = ? WHERE `id` = ?");
            $stmt->execute([$name, $email, $barangay, $avatarUrl, $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE `users` SET `name` = ?, `email` = ?, `barangay` = ? WHERE `id` = ?");
            $stmt->execute([$name, $email, $barangay, $userId]);
        }

        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;

        echo json_encode([
            'status' => 'success',
            'message' => 'Profile updated successfully!',
            'user' => [
                'name' => $name,
                'email' => $email,
                'barangay' => $barangay,
                'avatar' => $avatarUrl
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'get') {
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, role, avatar, city, barangay, eco_points, level, xp FROM `users` WHERE `id` = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        echo json_encode(['status' => 'success', 'user' => $user]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
