<?php
/**
 * Authentication API - Custom PHP
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in both email and password.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        $redirect = 'dashboard.php';
        if ($user['role'] === 'admin') {
            $redirect = 'admin.php';
        } elseif ($user['role'] === 'collector') {
            $redirect = 'collector.php';
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful! Redirecting...',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'redirect' => $redirect
        ]);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
        exit;
    }
}

if ($action === 'signup') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
        exit;
    }

    // Check if email already registered
    $stmt = $pdo->prepare("SELECT id FROM `users` WHERE `email` = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'This email is already registered. Please log in instead.']);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `password`, `role`, `city`, `barangay`, `eco_points`, `level`, `xp`) VALUES (?, ?, ?, 'user', 'Lapu-Lapu City', 'Subabasbas', 2450, 4, 2450)");
    $stmt->execute([$name, $email, $hashedPassword]);
    $newUserId = $pdo->lastInsertId();

    // Log welcome activity
    $logStmt = $pdo->prepare("INSERT INTO `user_activity_logs` (`user_id`, `action_text`) VALUES (?, ?)");
    $logStmt->execute([$newUserId, 'Created WasteWise account']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Account created successfully! Please log in.',
        'email' => $email
    ]);
    exit;
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    echo json_encode(['status' => 'success', 'message' => 'Logged out successfully.', 'redirect' => 'index.php']);
    exit;
}

if ($action === 'check') {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id, name, email, role, avatar, city, barangay, eco_points, level, xp FROM `users` WHERE `id` = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            echo json_encode(['logged_in' => true, 'user' => $user]);
            exit;
        }
    }
    echo json_encode(['logged_in' => false]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
