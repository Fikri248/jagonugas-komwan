<?php
// student-chat-send.php - API kirim pesan + file + edit message
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

// Validate input
$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$edit_message_id = (int)($_POST['edit_message_id'] ?? 0); // <-- Tambahan untuk edit

if (!$conversation_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
    exit;
}

// Verify conversation belongs to student
$stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND student_id = ?");
$stmt->execute([$conversation_id, $student_id]);
$conv = $stmt->fetch();

if (!$conv) {
    echo json_encode(['success' => false, 'error' => 'Conversation not found']);
    exit;
}

// ========================================
// MODE EDIT: Update existing message
// ========================================
if ($edit_message_id > 0) {
    // Verify message belongs to this user and conversation
    $stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE id = ? AND conversation_id = ? AND sender_id = ?
    ");
    $stmt->execute([$edit_message_id, $conversation_id, $student_id]);
    $existingMsg = $stmt->fetch();
    
    if (!$existingMsg) {
        echo json_encode(['success' => false, 'error' => 'Pesan tidak ditemukan atau bukan milik Anda']);
        exit;
    }
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Pesan tidak boleh kosong']);
        exit;
    }
    
    try {
        // UPDATE existing message + set edited_at
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET message = ?, edited_at = NOW() 
            WHERE id = ? AND sender_id = ?
        ");
        $stmt->execute([$message, $edit_message_id, $student_id]);
        
        // Update conversation timestamp
        $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$conversation_id]);
        
        echo json_encode([
            'success' => true,
            'edited' => true,
            'message' => [
                'id' => $edit_message_id,
                'message' => $message,
                'file_name' => $existingMsg['file_name'],
                'file_path' => $existingMsg['file_path'],
                'file_size' => $existingMsg['file_size'],
                'time' => date('H:i', strtotime($existingMsg['created_at'])),
                'edited_at' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Gagal mengedit pesan']);
        exit;
    }
}

// ========================================
// MODE NORMAL: Insert new message
// ========================================

// Handle file upload
$file_name = null;
$file_path = null;
$file_type = null;
$file_size = null;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];
    
    // Check if video
    $isVideo = in_array($file['type'], ['video/mp4', 'video/webm', 'video/quicktime']);
    
    // Allowed types
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'video/mp4', 'video/webm', 'video/quicktime'
    ];
    
    // Max size: 300MB for video, 5MB for others
    $maxSize = $isVideo ? 300 * 1024 * 1024 : 5 * 1024 * 1024;
    
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Tipe file tidak diizinkan']);
        exit;
    }
    
    if ($file['size'] > $maxSize) {
        $maxMB = $isVideo ? '300MB' : '5MB';
        echo json_encode(['success' => false, 'error' => "File terlalu besar (maks $maxMB)"]);
        exit;
    }
    
    // Create upload directory
    $uploadDir = __DIR__ . '/uploads/chat/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = 'chat_' . $conversation_id . '_' . time() . '_' . uniqid() . '.' . $ext;
    $uploadPath = $uploadDir . $newFileName;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $file_name = $file['name'];
        $file_path = 'uploads/chat/' . $newFileName;
        $file_type = $file['type'];
        $file_size = $file['size'];
    } else {
        echo json_encode(['success' => false, 'error' => 'Gagal upload file']);
        exit;
    }
}

// Require message or file
if (empty($message) && empty($file_path)) {
    echo json_encode(['success' => false, 'error' => 'Pesan atau file diperlukan']);
    exit;
}

try {
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, message, file_name, file_path, file_type, file_size, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $conversation_id,
        $student_id,
        $message ?: null,
        $file_name,
        $file_path,
        $file_type,
        $file_size
    ]);
    
    $msgId = $pdo->lastInsertId();
    
    // Update conversation timestamp
    $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$conversation_id]);
    
    echo json_encode([
        'success' => true,
        'edited' => false,
        'message' => [
            'id' => $msgId,
            'message' => $message,
            'file_name' => $file_name,
            'file_path' => $file_path,
            'file_size' => $file_size,
            'time' => date('H:i'),
            'edited_at' => null
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Gagal menyimpan pesan']);
}
