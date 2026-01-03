<?php
// api-session-end.php - API untuk mengakhiri sesi oleh mahasiswa
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya student yang bisa end session dari chat
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;

if (!$session_id) {
    echo json_encode(['success' => false, 'error' => 'Session ID diperlukan']);
    exit;
}

try {
    $pdo = (new Database())->getConnection();
    
    // Verify session belongs to this student and is ongoing
    $stmt = $pdo->prepare("
        SELECT id, mentor_id, status 
        FROM sessions 
        WHERE id = ? AND student_id = ?
    ");
    $stmt->execute([$session_id, $student_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'Sesi tidak ditemukan']);
        exit;
    }
    
    if ($session['status'] !== 'ongoing') {
        echo json_encode(['success' => false, 'error' => 'Sesi tidak dalam status aktif']);
        exit;
    }
    
    // Update session status to completed
    $stmt = $pdo->prepare("
        UPDATE sessions 
        SET status = 'completed', 
            ended_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$session_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Sesi berhasil diakhiri',
        'session_id' => $session_id
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
