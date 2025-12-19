<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = (new Database())->getConnection();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
} catch (Exception $e) {
    // optional: error_log($e->getMessage());
}

$role = $_SESSION['role'] ?? '';
if ($role === 'mentor') {
    $redirect = BASE_PATH . '/mentor-dashboard.php';
} elseif ($role === 'student') {
    $redirect = BASE_PATH . '/student-sessions.php';
} else {
    $redirect = BASE_PATH . '/index.php';
}

header('Location: ' . $redirect);
exit;
