<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$replyId = (int)($data['reply_id'] ?? 0);
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'Seseorang';

if (!$replyId) {
    echo json_encode(['success' => false, 'error' => 'Invalid reply']);
    exit;
}

try {
    // Get reply info (untuk notifikasi)
    $stmt = $pdo->prepare("
        SELECT fr.user_id as reply_owner_id, fr.thread_id, ft.title as thread_title
        FROM forum_replies fr
        JOIN forum_threads ft ON fr.thread_id = ft.id
        WHERE fr.id = ?
    ");
    $stmt->execute([$replyId]);
    $replyInfo = $stmt->fetch();
    
    if (!$replyInfo) {
        echo json_encode(['success' => false, 'error' => 'Reply not found']);
        exit;
    }
    
    // Check if already upvoted
    $stmt = $pdo->prepare("SELECT id FROM forum_upvotes WHERE user_id = ? AND reply_id = ?");
    $stmt->execute([$userId, $replyId]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Remove upvote
        $pdo->prepare("DELETE FROM forum_upvotes WHERE user_id = ? AND reply_id = ?")->execute([$userId, $replyId]);
        $pdo->prepare("UPDATE forum_replies SET upvotes = upvotes - 1 WHERE id = ?")->execute([$replyId]);
        $upvoted = false;
    } else {
        // Add upvote
        $pdo->prepare("INSERT INTO forum_upvotes (user_id, reply_id) VALUES (?, ?)")->execute([$userId, $replyId]);
        $pdo->prepare("UPDATE forum_replies SET upvotes = upvotes + 1 WHERE id = ?")->execute([$replyId]);
        $upvoted = true;
        
        // Kirim notifikasi ke pemilik reply (jika bukan diri sendiri)
        if ($replyInfo['reply_owner_id'] != $userId) {
            $notif = new NotificationHelper($pdo);
            $notif->create(
                $replyInfo['reply_owner_id'],
                'upvote_received',
                $userName . ' menyukai jawaban kamu di "' . mb_substr($replyInfo['thread_title'], 0, 40) . '..."',
                $replyInfo['thread_id'],
                'thread'
            );
        }
    }
    
    // Get new count
    $stmt = $pdo->prepare("SELECT upvotes FROM forum_replies WHERE id = ?");
    $stmt->execute([$replyId]);
    $count = $stmt->fetchColumn();
    
    echo json_encode(['success' => true, 'upvoted' => $upvoted, 'count' => $count]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
