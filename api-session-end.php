<?php
// api-session-end.php v1.1 - API untuk mengakhiri sesi (auto-end saat timer 00:00)
// Bisa dipanggil oleh student ATAU mentor
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Harus login (bisa student atau mentor)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;

if (!$session_id) {
    echo json_encode(['success' => false, 'error' => 'Session ID diperlukan']);
    exit;
}

try {
    $pdo = (new Database())->getConnection();
    
    // Verify session belongs to this user (student OR mentor) and is ongoing
    $stmt = $pdo->prepare("
        SELECT id, mentor_id, student_id, status 
        FROM sessions 
        WHERE id = ? AND (student_id = ? OR mentor_id = ?)
    ");
    $stmt->execute([$session_id, $userId, $userId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Sesi tidak ditemukan']);
        exit;
    }
    
    if ($session['status'] !== 'ongoing') {
        // Sudah selesai, return success tapi kasih info
        echo json_encode([
            'success' => true, 
            'message' => 'Sesi sudah tidak aktif',
            'already_ended' => true,
            'session_id' => $session_id
        ]);
        exit;
    }
    
    // Update session status to completed
    $stmt = $pdo->prepare("
        UPDATE sessions 
        SET status = 'completed', 
            ended_at = NOW(),
            updated_at = NOW()
        WHERE id = ? AND status = 'ongoing'
    ");
    $result = $stmt->execute([$session_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Sesi berhasil diakhiri',
            'session_id' => $session_id
        ]);
    } else {
        // Mungkin race condition - session sudah di-end oleh pihak lain
        echo json_encode([
            'success' => true,
            'message' => 'Sesi sudah diakhiri',
            'already_ended' => true,
            'session_id' => $session_id
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
