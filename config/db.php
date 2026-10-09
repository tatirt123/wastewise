<?php
/**
 * WasteWise - Database Configuration & Connection
 * Database: tatang
 * User: root
 * Password: (empty)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'tatang';
$db_port = 3306;

try {
    $pdo = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // If database tatang doesn't exist yet, try creating it
    try {
        $tempPdo = new PDO(
            "mysql:host={$db_host};port={$db_port};charset=utf8mb4",
            $db_user,
            $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        $pdo = new PDO(
            "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $ex) {
        die("Database Connection Error: " . $ex->getMessage());
    }
}

/**
 * Helper to get currently logged-in user
 */
function getLoggedInUser($pdo) {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id, name, email, role, avatar, city, barangay, eco_points, level, xp FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}

/**
 * Helper to enforce authentication
 */
function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
}
