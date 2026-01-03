<?php
// api-typing-status.php - Handle typing indicator
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

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ============================================
// === GET: Check if other party is typing ===
// ============================================
if ($method === 'GET') {
    $conversation_id = (int)($_GET['conversation_id'] ?? 0);
    
    if (!$conversation_id) {
        echo json_encode(['success' => false, 'error' => 'Missing conversation_id']);
        exit;
    }
    
    // Verify user has access to conversation
    $stmt = $pdo->prepare("SELECT student_id, mentor_id FROM conversations WHERE id = ?");
    $stmt->execute([$conversation_id]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conv || ($conv['student_id'] != $user_id && $conv['mentor_id'] != $user_id)) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Determine other party's user_id
    $other_user_id = ($user_id == $conv['student_id']) ? $conv['mentor_id'] : $conv['student_id'];
    
    // Get other party's typing status (only if updated within last 5 seconds)
    $stmt = $pdo->prepare("
        SELECT ts.is_typing, u.name AS typer_name
        FROM typing_status ts
        JOIN users u ON ts.user_id = u.id
        WHERE ts.conversation_id = ? 
          AND ts.user_id = ? 
          AND ts.is_typing = 1
          AND ts.updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)
    ");
    $stmt->execute([$conversation_id, $other_user_id]);
    $typing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'is_typing' => (bool)$typing,
        'typer_name' => $typing['typer_name'] ?? null
    ]);
    exit;
}

// ============================================
// === POST: Set typing status ===
// ============================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $conversation_id = (int)($input['conversation_id'] ?? 0);
    $is_typing = (bool)($input['is_typing'] ?? false);
    
    if (!$conversation_id) {
        echo json_encode(['success' => false, 'error' => 'Missing conversation_id']);
        exit;
    }
    
    // Verify user has access to conversation
    $stmt = $pdo->prepare("SELECT student_id, mentor_id FROM conversations WHERE id = ?");
    $stmt->execute([$conversation_id]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conv || ($conv['student_id'] != $user_id && $conv['mentor_id'] != $user_id)) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Upsert typing status (INSERT or UPDATE if exists)
    $stmt = $pdo->prepare("
        INSERT INTO typing_status (conversation_id, user_id, is_typing, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            is_typing = VALUES(is_typing), 
            updated_at = NOW()
    ");
    $stmt->execute([$conversation_id, $user_id, $is_typing ? 1 : 0]);
    
    echo json_encode(['success' => true]);
    exit;
}

// ============================================
// === Invalid method ===
// ============================================
echo json_encode(['success' => false, 'error' => 'Invalid method']);
