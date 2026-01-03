<?php
// api-chat-messages.php - Polling untuk pesan baru + deteksi edited/deleted messages
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$conversation_id = (int)($_GET['conversation_id'] ?? 0);
$last_id = (int)($_GET['last_id'] ?? 0);
$include_updated = isset($_GET['include_updated']) && $_GET['include_updated'] == '1';

if (!$conversation_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
    exit;
}

// Verify conversation access
$stmt = $pdo->prepare("
    SELECT * FROM conversations 
    WHERE id = ? AND (student_id = ? OR mentor_id = ?)
");
$stmt->execute([$conversation_id, $user_id, $user_id]);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get ALL existing message IDs for delete detection
$stmt = $pdo->prepare("SELECT id FROM messages WHERE conversation_id = ?");
$stmt->execute([$conversation_id]);
$existingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get new/updated messages
if ($include_updated) {
    // Get new messages AND recently edited messages (within last 30 seconds)
    $stmt = $pdo->prepare("
        SELECT m.*, u.name AS sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        AND (
            m.id > ?
            OR (m.edited_at IS NOT NULL AND m.edited_at > DATE_SUB(NOW(), INTERVAL 30 SECOND))
        )
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversation_id, $last_id]);
} else {
    // Just new messages
    $stmt = $pdo->prepare("
        SELECT m.*, u.name AS sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversation_id, $last_id]);
}

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark as read (only messages from other party)
if (!empty($messages)) {
    $stmt = $pdo->prepare("
        UPDATE messages SET is_read = 1 
        WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
    ");
    $stmt->execute([$conversation_id, $user_id]);
}

// Format messages
$formatted = [];
$maxId = $last_id;

foreach ($messages as $msg) {
    $msgId = (int)$msg['id'];
    if ($msgId > $maxId) {
        $maxId = $msgId;
    }
    
    $formatted[] = [
        'id' => $msgId,
        'sender_id' => (int)$msg['sender_id'],
        'sender_name' => $msg['sender_name'],
        'message' => $msg['message'] ?? '',
        'file_name' => $msg['file_name'] ?? null,
        'file_path' => $msg['file_path'] ?? null,
        'file_size' => isset($msg['file_size']) ? (int)$msg['file_size'] : null,
        'time' => date('H:i', strtotime($msg['created_at'])),
        'created_at' => $msg['created_at'],
        'edited_at' => $msg['edited_at'] ?? null,
        'is_edited' => !empty($msg['edited_at']),
        'is_mine' => ((int)$msg['sender_id'] === $user_id)
    ];
}

echo json_encode([
    'success' => true,
    'messages' => $formatted,
    'last_id' => $maxId,
    'user_id' => $user_id,
    'existing_ids' => array_map('intval', $existingIds)
]);
