<?php
// mentor-chat-send.php v1.1 - FIXED
// Fix: Bisa kirim file tanpa teks (handle NULL message properly)

// Set PHP limits for large file upload
@ini_set('upload_max_filesize', '300M');
@ini_set('post_max_size', '310M');
@ini_set('max_execution_time', '600');
@ini_set('max_input_time', '600');
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mentor') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$mentor_id = $_SESSION['user_id'];

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

// Validate input
$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$edit_message_id = (int)($_POST['edit_message_id'] ?? 0);

if (!$conversation_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
    exit;
}

// Verify conversation belongs to mentor
$stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND mentor_id = ?");
$stmt->execute([$conversation_id, $mentor_id]);
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
    $stmt->execute([$edit_message_id, $conversation_id, $mentor_id]);
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
        $stmt->execute([$message, $edit_message_id, $mentor_id]);
        
        // Update conversation timestamp
        $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$conversation_id]);
        
        echo json_encode([
            'success' => true,
            'edited' => true,
            'message' => [
                'id' => $edit_message_id,
                'message' => $message,
                'file_name' => $existingMsg['file_name'] ?? null,
                'file_path' => $existingMsg['file_path'] ?? null,
                'file_size' => $existingMsg['file_size'] ?? null,
                'time' => date('H:i', strtotime($existingMsg['created_at'])),
                'edited_at' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Gagal mengedit pesan: ' . $e->getMessage()]);
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
$hasFile = false;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];
    
    // Check if video
    $isVideo = in_array($file['type'], ['video/mp4', 'video/webm', 'video/quicktime']);
    
    // Allowed types
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
        $hasFile = true;
    } else {
        echo json_encode(['success' => false, 'error' => 'Gagal upload file']);
        exit;
    }
}

// FIX: Validasi - butuh minimal salah satu: message ATAU file
$hasMessage = !empty($message);

if (!$hasMessage && !$hasFile) {
    echo json_encode(['success' => false, 'error' => 'Tulis pesan atau pilih file untuk dikirim']);
    exit;
}

try {
    // FIX: Jika hanya file tanpa teks, set message ke empty string (bukan NULL)
    // Ini untuk handle database yang mungkin punya constraint NOT NULL
    $messageToSave = $hasMessage ? $message : '';
    
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, message, file_name, file_path, file_type, file_size, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $conversation_id,
        $mentor_id,
        $messageToSave,
        $file_name,
        $file_path,
        $file_type,
        $file_size
    ]);
    
    if (!$result) {
        throw new Exception('Execute failed: ' . implode(', ', $stmt->errorInfo()));
    }
    
    $msgId = $pdo->lastInsertId();
    
    if (!$msgId) {
        throw new Exception('Failed to get last insert ID');
    }
    
    // Update conversation timestamp
    $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$conversation_id]);
    
    echo json_encode([
        'success' => true,
        'edited' => false,
        'message' => [
            'id' => $msgId,
            'message' => $messageToSave,
            'file_name' => $file_name,
            'file_path' => $file_path,
            'file_size' => $file_size,
            'time' => date('H:i'),
            'edited_at' => null
        ]
    ]);
    
} catch (Exception $e) {
    // Cleanup file if DB failed
    if ($file_path && file_exists(__DIR__ . '/' . $file_path)) {
        @unlink(__DIR__ . '/' . $file_path);
    }
    
    // FIX: Return detailed error untuk debugging
    echo json_encode([
        'success' => false, 
        'error' => 'Gagal menyimpan pesan',
        'debug' => $e->getMessage() // Hapus ini di production
    ]);
}
