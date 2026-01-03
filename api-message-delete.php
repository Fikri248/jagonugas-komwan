<?php
// api-message-delete.php - Hapus pesan chat
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Ambil input JSON
$input = json_decode(file_get_contents('php://input'), true);
$message_id = isset($input['message_id']) ? (int)$input['message_id'] : 0;

if (!$message_id) {
    echo json_encode(['success' => false, 'error' => 'Message ID required']);
    exit;
}

try {
    $pdo = (new Database())->getConnection();
    
    // Cek kepemilikan pesan - hanya sender yang bisa hapus
    $stmt = $pdo->prepare("
        SELECT m.*, c.student_id, c.mentor_id 
        FROM messages m
        JOIN conversations c ON m.conversation_id = c.id
        WHERE m.id = ?
    ");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'Pesan tidak ditemukan']);
        exit;
    }
    
    // Validasi: hanya sender yang bisa hapus pesannya sendiri
    if ($message['sender_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Tidak diizinkan menghapus pesan ini']);
        exit;
    }
    
    // Validasi: user harus bagian dari conversation
    $isParticipant = ($user_role === 'student' && $message['student_id'] == $user_id) ||
                     ($user_role === 'mentor' && $message['mentor_id'] == $user_id);
    
    if (!$isParticipant) {
        echo json_encode(['success' => false, 'error' => 'Akses ditolak']);
        exit;
    }
    
    // Hapus file attachment jika ada
    if (!empty($message['file_path'])) {
        $filePath = __DIR__ . '/' . $message['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    // Hapus pesan dari database
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$message_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log('Delete message error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Gagal menghapus pesan']);
}
