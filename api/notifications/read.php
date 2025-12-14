<?php
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$notifId = (int)($data['id'] ?? 0);

$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$success = $stmt->execute([$notifId, $_SESSION['user_id']]);

echo json_encode(['success' => $success]);
