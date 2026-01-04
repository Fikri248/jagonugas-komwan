<?php
// check-session-status.php v2.1 - FIX: Return correct new bookings
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$lastcheck = $_GET['lastcheck'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));

try {
    $pdo = (new Database())->getConnection();
    
    $updates = [];
    
    if ($role === 'mentor') {
        // Mentor: Cek booking BARU yang created_at > lastcheck (bukan updated_at)
        $stmt = $pdo->prepare("
            SELECT s.id, s.status, s.created_at, s.updated_at, s.duration, s.price, s.notes,
                   u.name as student_name, u.program_studi as student_prodi
            FROM sessions s
            JOIN users u ON s.student_id = u.id
            WHERE s.mentor_id = ? 
              AND (s.created_at > ? OR s.updated_at > ?)
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$user_id, $lastcheck, $lastcheck]);
        $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($role === 'student') {
        // Student: Cek perubahan status sesi mereka
        $stmt = $pdo->prepare("
            SELECT s.id, s.status, s.updated_at, s.reject_reason,
                   u.name as mentor_name, c.id as conversation_id
            FROM sessions s
            JOIN users u ON s.mentor_id = u.id
            LEFT JOIN conversations c ON c.session_id = s.id
            WHERE s.student_id = ? 
              AND s.updated_at > ?
            ORDER BY s.updated_at DESC
        ");
        $stmt->execute([$user_id, $lastcheck]);
        $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Hitung stats terbaru
    if ($role === 'mentor') {
        $statsStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM sessions WHERE mentor_id = ? GROUP BY status");
    } else {
        $statsStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM sessions WHERE student_id = ? GROUP BY status");
    }
    $statsStmt->execute([$user_id]);
    
    $stats = ['pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
    foreach ($statsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stats[$row['status']] = (int)$row['count'];
    }
    
    echo json_encode([
        'success' => true,
        'updates' => $updates,
        'stats' => $stats,
        'server_time' => date('Y-m-d H:i:s'),
        'has_new' => count($updates) > 0
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
