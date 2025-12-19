<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mentor') {
    header('Location: ' . BASE_PATH . '/mentor-login.php');
    exit;
}

$mentor_id       = $_SESSION['user_id'];
$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$message         = trim($_POST['message'] ?? '');

if (!$conversation_id || $message === '') {
    header('Location: ' . BASE_PATH . '/mentor-chat.php');
    exit;
}

$pdo = (new Database())->getConnection();

// pastikan conversation milik mentor
$stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND mentor_id = ?");
$stmt->execute([$conversation_id, $mentor_id]);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    header('Location: ' . BASE_PATH . '/mentor-chat.php');
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO messages (conversation_id, sender_id, message)
    VALUES (?, ?, ?)
");
$stmt->execute([$conversation_id, $mentor_id, $message]);

// update updated_at conversation
$stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
$stmt->execute([$conversation_id]);

header('Location: ' . BASE_PATH . '/mentor-chat.php?conversation_id=' . $conversation_id);
exit;
