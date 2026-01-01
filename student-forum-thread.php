<?php
// student-forum-thread.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$threadId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'] ?? null;
$name = $_SESSION['name'] ?? 'Guest';

if (!$threadId) {
    header("Location: " . $BASE . "/student-forum.php");
    exit;
}

$pdo = null;
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function untuk avatar URL (handle Google avatar)
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar, $base = '') {
        if (empty($avatar)) return '';
        if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
        return $base . '/' . ltrim($avatar, '/');
    }
}

// Get thread with details
$stmt = $pdo->prepare("
    SELECT ft.*, u.name as author_name, u.id as author_id, u.avatar as author_avatar,
           fc.name as category_name, fc.slug as category_slug, fc.color as category_color
    FROM forum_threads ft 
    JOIN users u ON ft.user_id = u.id 
    JOIN forum_categories fc ON ft.category_id = fc.id 
    WHERE ft.id = ?
");
$stmt->execute([$threadId]);
$thread = $stmt->fetch();

if (!$thread) {
    header("Location: " . $BASE . "/student-forum.php");
    exit;
}

// Cek apakah user adalah pemilik thread
$isOwner = ($userId == $thread['author_id']);

// Handle DELETE thread
if (isset($_POST['delete_thread']) && $isOwner) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM forum_replies WHERE thread_id = ? AND user_id != ?");
        $stmt->execute([$threadId, $userId]);
        $repliers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->prepare("SELECT file_path FROM forum_attachments WHERE thread_id = ?");
        $stmt->execute([$threadId]);
        $files = $stmt->fetchAll();
        foreach ($files as $file) {
            $filePath = __DIR__ . '/' . $file['file_path'];
            if (file_exists($filePath)) unlink($filePath);
        }
        
        $stmt = $pdo->prepare("SELECT ra.file_path FROM reply_attachments ra JOIN forum_replies fr ON ra.reply_id = fr.id WHERE fr.thread_id = ?");
        $stmt->execute([$threadId]);
        $replyFiles = $stmt->fetchAll();
        foreach ($replyFiles as $file) {
            $filePath = __DIR__ . '/' . $file['file_path'];
            if (file_exists($filePath)) unlink($filePath);
        }
        
        $pdo->prepare("DELETE ra FROM reply_attachments ra JOIN forum_replies fr ON ra.reply_id = fr.id WHERE fr.thread_id = ?")->execute([$threadId]);
        $pdo->prepare("DELETE FROM forum_attachments WHERE thread_id = ?")->execute([$threadId]);
        $pdo->prepare("DELETE FROM forum_upvotes WHERE reply_id IN (SELECT id FROM forum_replies WHERE thread_id = ?)")->execute([$threadId]);
        $pdo->prepare("DELETE FROM forum_replies WHERE thread_id = ?")->execute([$threadId]);
        
        $gemsRefunded = 0;
        if (!$thread['is_solved']) {
            $pdo->prepare("UPDATE users SET gems = gems + ? WHERE id = ?")->execute([$thread['gem_reward'], $userId]);
            $gemsRefunded = $thread['gem_reward'];
        }
        
        $pdo->prepare("DELETE FROM forum_threads WHERE id = ?")->execute([$threadId]);
        $pdo->commit();
        
        header("Location: " . $BASE . "/student-forum.php?deleted=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $deleteError = "Gagal menghapus thread";
    }
}

// Increment views
$viewKey = 'viewed_thread_' . $threadId;
if (!isset($_SESSION[$viewKey])) {
    $pdo->prepare("UPDATE forum_threads SET views = views + 1 WHERE id = ?")->execute([$threadId]);
    $_SESSION[$viewKey] = true;
    $thread['views']++;
}

// Get attachments
$stmt = $pdo->prepare("SELECT * FROM forum_attachments WHERE thread_id = ?");
$stmt->execute([$threadId]);
$attachments = $stmt->fetchAll();

// Get replies
$stmt = $pdo->prepare("
    SELECT fr.*, u.name as author_name, u.id as author_id, u.avatar as author_avatar,
           (SELECT COUNT(*) FROM forum_upvotes WHERE reply_id = fr.id) as upvote_count,
           " . ($userId ? "(SELECT COUNT(*) FROM forum_upvotes WHERE reply_id = fr.id AND user_id = ?) as user_upvoted" : "0 as user_upvoted") . "
    FROM forum_replies fr JOIN users u ON fr.user_id = u.id WHERE fr.thread_id = ?
    ORDER BY fr.is_best_answer DESC, fr.upvotes DESC, fr.created_at ASC
");
$stmt->execute($userId ? [$userId, $threadId] : [$threadId]);
$replies = $stmt->fetchAll();

foreach ($replies as &$reply) {
    $stmt = $pdo->prepare("SELECT * FROM reply_attachments WHERE reply_id = ?");
    $stmt->execute([$reply['id']]);
    $reply['attachments'] = $stmt->fetchAll();
}
unset($reply);

// Handle new reply - HANYA untuk non-owner
$replyError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_content'])) {
    if (!$userId) {
        header("Location: " . $BASE . "/login.php?redirect=student-forum-thread.php?id=$threadId");
        exit;
    }
    
    // Cegah owner menjawab thread sendiri
    if ($isOwner) {
        $replyError = "Kamu tidak bisa menjawab pertanyaanmu sendiri";
    } else {
        $replyContent = trim($_POST['reply_content']);
        
        if (empty($replyContent)) {
            $replyError = "Jawaban tidak boleh kosong";
        } elseif (strlen($replyContent) < 10) {
            $replyError = "Jawaban minimal 10 karakter";
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("INSERT INTO forum_replies (thread_id, user_id, content) VALUES (?, ?, ?)");
                $stmt->execute([$threadId, $userId, $replyContent]);
                $replyId = $pdo->lastInsertId();
                
                if (!empty($_FILES['attachments']['name'][0])) {
                    $uploadDir = __DIR__ . '/uploads/replies/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    
                    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $fileName = $_FILES['attachments']['name'][$key];
                            $fileSize = $_FILES['attachments']['size'][$key];
                            $fileType = $_FILES['attachments']['type'][$key];
                            
                            if ($fileSize > 5 * 1024 * 1024) continue;
                            
                            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                            $newName = uniqid() . '_' . time() . '.' . $ext;
                            $filePath = 'uploads/replies/' . $newName;
                            
                            if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                                $stmt = $pdo->prepare("INSERT INTO reply_attachments (reply_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$replyId, $fileName, $filePath, $fileType, $fileSize]);
                            }
                        }
                    }
                }
                
                $notif = new NotificationHelper($pdo);
                $notif->newReplyToThread($thread['author_id'], $name, $threadId, $thread['title']);
                
                $pdo->commit();
                header("Location: " . $BASE . "/student-forum-thread.php?id=$threadId&success=1#reply-$replyId");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $replyError = "Gagal mengirim jawaban.";
            }
        }
    }
}

// Handle mark best answer
if (isset($_GET['best']) && $isOwner && !$thread['is_solved']) {
    $replyId = (int)$_GET['best'];
    $stmt = $pdo->prepare("SELECT user_id FROM forum_replies WHERE id = ? AND thread_id = ?");
    $stmt->execute([$replyId, $threadId]);
    $reply = $stmt->fetch();
    
    if ($reply && $reply['user_id'] != $userId) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE forum_threads SET is_solved = 1, best_answer_id = ? WHERE id = ?")->execute([$replyId, $threadId]);
            $pdo->prepare("UPDATE forum_replies SET is_best_answer = 1 WHERE id = ?")->execute([$replyId]);
            $pdo->prepare("UPDATE users SET gems = gems + ? WHERE id = ?")->execute([$thread['gem_reward'], $reply['user_id']]);
            
            $notif = new NotificationHelper($pdo);
            $notif->bestAnswer($reply['user_id'], $thread['gem_reward'], $threadId);
            
            $pdo->commit();
            header("Location: " . $BASE . "/student-forum-thread.php?id=$threadId&best_selected=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

$successMsg = '';
if (isset($_GET['success'])) $successMsg = 'Jawaban berhasil dikirim!';
elseif (isset($_GET['best_selected'])) $successMsg = 'Jawaban terbaik berhasil dipilih!';
elseif (isset($_GET['updated'])) $successMsg = 'Pertanyaan berhasil diperbarui!';

function time_elapsed($datetime) {
    $tz = new DateTimeZone('Asia/Jakarta');
    $now = new DateTime('now', $tz);
    $ago = new DateTime($datetime, $tz);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 7) return date('d M Y', strtotime($datetime));
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($thread['title']); ?> - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f8fafc; min-height: 100vh; }
        .forum-page { background: #f8fafc; }
        
        .btn { padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; }
        .btn-sm { padding: 8px 14px; font-size: 0.85rem; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4); }
        .btn-outline { border: 2px solid #e2e8f0; color: #475569; background: white; }
        .btn-outline:hover { border-color: #667eea; color: #667eea; }
        .btn-danger-outline { border-color: #fecaca; color: #dc2626; }
        .btn-danger-outline:hover { background: #fef2f2; border-color: #ef4444; }
        .btn-danger-solid { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .btn-success-solid { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; transition: all 0.3s; }
        .alert-success { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; border: 1px solid #fecaca; }
        
        .thread-container { max-width: 900px; margin: 0 auto; padding: 32px 24px; }
        
        .thread-breadcrumb { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; font-size: 0.9rem; flex-wrap: wrap; }
        .thread-breadcrumb a { color: #64748b; text-decoration: none; transition: color 0.2s; }
        .thread-breadcrumb a:hover { color: #667eea; }
        .thread-breadcrumb i { color: #cbd5e1; font-size: 0.7rem; }
        .thread-breadcrumb span { color: #94a3b8; }
        
        .thread-question { background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); overflow: hidden; margin-bottom: 32px; }
        .thread-question-header { padding: 28px 28px 0; }
        .thread-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
        .thread-category { padding: 6px 14px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
        .thread-solved { display: flex; align-items: center; gap: 6px; padding: 6px 14px; background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #16a34a; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
        .thread-reward { display: flex; align-items: center; gap: 6px; padding: 6px 14px; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
        .thread-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; line-height: 1.4; }
        
        .thread-question-body { padding: 24px 28px; }
        .thread-author-inline { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .thread-avatar { width: 44px; height: 44px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1rem; overflow: hidden; flex-shrink: 0; }
        .thread-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .thread-author-name { font-weight: 600; color: #1e293b; }
        .thread-time { font-size: 0.85rem; color: #94a3b8; }
        .edited-badge { color: #64748b; font-style: italic; }
        .thread-text { color: #475569; line-height: 1.8; font-size: 1rem; white-space: pre-wrap; }
        
        .thread-attachments { margin-top: 24px; padding-top: 20px; border-top: 1px solid #f1f5f9; }
        .thread-attachments h4 { font-size: 0.9rem; color: #64748b; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
        .attachment-list { display: flex; flex-wrap: wrap; gap: 12px; }
        .attachment-item { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; text-decoration: none; color: #475569; transition: all 0.2s; }
        .attachment-item:hover { border-color: #667eea; background: #f0f4ff; }
        .attachment-item img { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; }
        .attachment-item i { font-size: 1.5rem; color: #667eea; }
        
        .thread-question-footer { display: flex; justify-content: space-between; align-items: center; padding: 16px 28px; background: #f8fafc; border-top: 1px solid #f1f5f9; }
        .thread-stats { display: flex; gap: 20px; color: #64748b; font-size: 0.9rem; }
        .thread-stats span { display: flex; align-items: center; gap: 6px; }
        .thread-actions { display: flex; gap: 10px; }
        
        .thread-replies { margin-bottom: 32px; }
        .thread-replies > h2 { font-size: 1.15rem; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .thread-replies > h2 i { color: #667eea; }
        
        .thread-no-replies { background: white; border-radius: 16px; padding: 48px; text-align: center; border: 2px dashed #e2e8f0; }
        .thread-no-replies i { font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; display: block; }
        .thread-no-replies h3 { font-size: 1.15rem; color: #64748b; margin-bottom: 8px; }
        .thread-no-replies p { color: #94a3b8; }
        
        .thread-reply { background: white; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .thread-reply.best-answer { border: 2px solid #10b981; box-shadow: 0 4px 20px rgba(16, 185, 129, 0.15); }
        .best-answer-badge { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 10px 20px; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        
        .reply-body { padding: 24px; }
        .reply-author-inline { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .reply-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.9rem; overflow: hidden; flex-shrink: 0; }
        .reply-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .reply-author-name { font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px; }
        .author-badge { background: linear-gradient(135deg, #eef2ff, #e0e7ff); color: #667eea; padding: 3px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
        .reply-time { font-size: 0.85rem; color: #94a3b8; }
        .reply-content { color: #475569; line-height: 1.8; white-space: pre-wrap; }
        
        .reply-attachments { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 16px; }
        .reply-attachment.image img { max-width: 200px; border-radius: 8px; }
        .reply-attachment.file { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: #475569; font-size: 0.85rem; }
        
        .reply-footer { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; background: #f8fafc; border-top: 1px solid #f1f5f9; }
        .reply-upvote, .reply-upvote-count { display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 0.9rem; background: white; border: 1px solid #e2e8f0; color: #64748b; cursor: pointer; transition: all 0.2s; }
        .reply-upvote:hover { border-color: #667eea; color: #667eea; }
        .reply-upvote.upvoted { background: linear-gradient(135deg, #eef2ff, #e0e7ff); border-color: #667eea; color: #667eea; }
        .reply-upvote-count { cursor: default; }
        
        .thread-reply-form { background: white; border-radius: 16px; padding: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .thread-reply-form h3 { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .thread-reply-form h3 i { color: #667eea; }
        .thread-reply-form textarea { width: 100%; padding: 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; resize: vertical; min-height: 140px; outline: none; transition: all 0.2s; font-family: inherit; }
        .thread-reply-form textarea:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .form-group { margin-bottom: 20px; }
        .form-actions { display: flex; justify-content: flex-end; }
        
        .reply-upload-section { margin-bottom: 20px; }
        .reply-upload-area { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border: 2px dashed #e2e8f0; border-radius: 10px; cursor: pointer; transition: all 0.2s; background: #f8fafc; }
        .reply-upload-area:hover { border-color: #667eea; background: #f0f4ff; }
        .reply-upload-area.dragover { border-color: #667eea; background: #eef2ff; }
        .reply-upload-area i { font-size: 1.2rem; color: #64748b; }
        .reply-upload-area span { color: #64748b; font-weight: 500; }
        .reply-upload-area small { color: #94a3b8; font-size: 0.8rem; }
        .reply-upload-area input { display: none; }
        .reply-file-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .file-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f1f5f9; border-radius: 8px; font-size: 0.85rem; color: #475569; }
        .file-item i { color: #667eea; }
        
        .thread-login-prompt { background: white; border-radius: 16px; padding: 32px; text-align: center; border: 2px dashed #e2e8f0; }
        .thread-login-prompt i { font-size: 2rem; color: #cbd5e1; margin-bottom: 12px; display: block; }
        .thread-login-prompt a { color: #667eea; font-weight: 600; }
        
        /* Owner info box */
        .thread-owner-info { background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 16px; padding: 24px; text-align: center; border: 1px solid #bfdbfe; }
        .thread-owner-info i { font-size: 2rem; color: #3b82f6; margin-bottom: 12px; display: block; }
        .thread-owner-info p { color: #1e40af; font-weight: 500; }
        
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; opacity: 0; visibility: hidden; transition: all 0.3s; padding: 20px; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-container { background: white; border-radius: 20px; padding: 32px; max-width: 420px; width: 100%; text-align: center; transform: scale(0.9); transition: all 0.3s; }
        .modal-overlay.active .modal-container { transform: scale(1); }
        .modal-icon { width: 72px; height: 72px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; }
        .modal-icon.danger { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #ef4444; }
        .modal-icon.success { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #10b981; }
        .modal-container h3 { font-size: 1.25rem; color: #1e293b; margin-bottom: 8px; }
        .modal-container p { color: #64748b; margin-bottom: 20px; }
        .modal-info { text-align: left; background: #f8fafc; border-radius: 12px; padding: 16px; margin-bottom: 24px; list-style: none; }
        .modal-info li { display: flex; align-items: center; gap: 10px; padding: 8px 0; color: #475569; font-size: 0.9rem; border-bottom: 1px solid #e2e8f0; }
        .modal-info li:last-child { border-bottom: none; }
        .modal-info li i { color: #64748b; width: 20px; }
        .modal-actions { display: flex; gap: 12px; justify-content: center; }
        .modal-actions .btn { flex: 1; justify-content: center; }
        
        @media (max-width: 768px) {
            .thread-container { padding: 20px 16px; }
            .thread-question-header, .thread-question-body { padding: 20px; }
            .thread-question-footer { padding: 16px 20px; flex-direction: column; gap: 16px; }
            .thread-title { font-size: 1.25rem; }
            .reply-body { padding: 20px; }
            .reply-footer { flex-direction: column; gap: 12px; align-items: stretch; }
            .reply-footer .btn { width: 100%; justify-content: center; }
            .thread-reply-form { padding: 20px; }
            .modal-actions { flex-direction: column; }
        }
    </style>
</head>
<body class="forum-page">
    <?php include __DIR__ . '/student-navbar.php'; ?>

    <div class="thread-container">
        <nav class="thread-breadcrumb">
            <a href="<?php echo $BASE; ?>/student-forum.php">Forum</a>
            <i class="bi bi-chevron-right"></i>
            <a href="<?php echo $BASE; ?>/student-forum.php?category=<?php echo $thread['category_slug']; ?>"><?php echo htmlspecialchars($thread['category_name']); ?></a>
            <i class="bi bi-chevron-right"></i>
            <span>Pertanyaan</span>
        </nav>

        <?php if ($successMsg): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $successMsg; ?></div>
        <?php endif; ?>

        <?php if (isset($deleteError)): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?php echo $deleteError; ?></div>
        <?php endif; ?>

        <article class="thread-question">
            <div class="thread-question-header">
                <div class="thread-meta">
                    <span class="thread-category" style="background: <?php echo $thread['category_color']; ?>20; color: <?php echo $thread['category_color']; ?>"><?php echo htmlspecialchars($thread['category_name']); ?></span>
                    <?php if ($thread['is_solved']): ?>
                    <span class="thread-solved"><i class="bi bi-check-circle-fill"></i> Terjawab</span>
                    <?php else: ?>
                    <span class="thread-reward"><i class="bi bi-gem"></i> +<?php echo $thread['gem_reward']; ?> gem</span>
                    <?php endif; ?>
                </div>
                <h1 class="thread-title"><?php echo htmlspecialchars($thread['title']); ?></h1>
            </div>

            <div class="thread-question-body">
                <div class="thread-author-inline">
                    <div class="thread-avatar">
                        <?php $authorAvatarUrl = get_avatar_url($thread['author_avatar'], $BASE); ?>
                        <?php if ($authorAvatarUrl): ?>
                            <img src="<?php echo htmlspecialchars($authorAvatarUrl); ?>" alt="" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <?php echo strtoupper(substr($thread['author_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="thread-author-name"><?php echo htmlspecialchars($thread['author_name']); ?></span>
                        <div class="thread-time">
                            <?php echo time_elapsed($thread['created_at']); ?>
                            <?php if ($thread['updated_at'] && $thread['updated_at'] != $thread['created_at']): ?>
                            <span class="edited-badge">(diedit)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="thread-text"><?php echo nl2br(htmlspecialchars($thread['content'])); ?></div>
                
                <?php if (!empty($attachments)): ?>
                <div class="thread-attachments">
                    <h4><i class="bi bi-paperclip"></i> Lampiran</h4>
                    <div class="attachment-list">
                        <?php foreach ($attachments as $att): ?>
                        <a href="<?php echo $BASE . '/' . $att['file_path']; ?>" target="_blank" class="attachment-item">
                            <?php if (strpos($att['file_type'], 'image') !== false): ?>
                            <img src="<?php echo $BASE . '/' . $att['file_path']; ?>" alt="">
                            <?php else: ?>
                            <i class="bi bi-file-earmark"></i>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($att['file_name']); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="thread-question-footer">
                <div class="thread-stats">
                    <span><i class="bi bi-eye"></i> <?php echo $thread['views']; ?> views</span>
                    <span><i class="bi bi-chat-dots"></i> <?php echo count($replies); ?> jawaban</span>
                </div>
                
                <?php if ($isOwner): ?>
                <div class="thread-actions">
                    <a href="<?php echo $BASE; ?>/student-forum-edit.php?id=<?php echo $threadId; ?>" class="btn btn-sm btn-outline"><i class="bi bi-pencil"></i> Edit</a>
                    <button type="button" class="btn btn-sm btn-danger-outline" onclick="openDeleteModal()"><i class="bi bi-trash"></i> Hapus</button>
                </div>
                <form id="deleteForm" method="POST" style="display:none;"><input type="hidden" name="delete_thread" value="1"></form>
                <?php endif; ?>
            </div>
        </article>

        <section class="thread-replies" id="replies">
            <h2><i class="bi bi-chat-left-text"></i> <?php echo count($replies); ?> Jawaban</h2>

            <?php if (empty($replies)): ?>
            <div class="thread-no-replies">
                <i class="bi bi-chat-square-text"></i>
                <h3>Belum Ada Jawaban</h3>
                <?php if ($isOwner): ?>
                <p>Tunggu jawaban dari pengguna lain</p>
                <?php else: ?>
                <p>Jadilah yang pertama membantu menjawab pertanyaan ini!</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <?php foreach ($replies as $reply): ?>
                <article class="thread-reply <?php echo $reply['is_best_answer'] ? 'best-answer' : ''; ?>" id="reply-<?php echo $reply['id']; ?>">
                    <?php if ($reply['is_best_answer']): ?>
                    <div class="best-answer-badge"><i class="bi bi-trophy-fill"></i> Jawaban Terbaik</div>
                    <?php endif; ?>
                    
                    <div class="reply-body">
                        <div class="reply-author-inline">
                            <div class="reply-avatar">
                                <?php $replyAvatarUrl = get_avatar_url($reply['author_avatar'], $BASE); ?>
                                <?php if ($replyAvatarUrl): ?>
                                    <img src="<?php echo htmlspecialchars($replyAvatarUrl); ?>" alt="" referrerpolicy="no-referrer">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($reply['author_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="reply-author-name">
                                    <?php echo htmlspecialchars($reply['author_name']); ?>
                                    <?php if ($reply['author_id'] == $thread['author_id']): ?>
                                    <span class="author-badge">Penanya</span>
                                    <?php endif; ?>
                                </span>
                                <div class="reply-time"><?php echo time_elapsed($reply['created_at']); ?></div>
                            </div>
                        </div>
                        
                        <div class="reply-content"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></div>
                        
                        <?php if (!empty($reply['attachments'])): ?>
                        <div class="reply-attachments">
                            <?php foreach ($reply['attachments'] as $att): ?>
                                <?php if (strpos($att['file_type'], 'image') !== false): ?>
                                <a href="<?php echo $BASE . '/' . $att['file_path']; ?>" target="_blank" class="reply-attachment image">
                                    <img src="<?php echo $BASE . '/' . $att['file_path']; ?>" alt="">
                                </a>
                                <?php else: ?>
                                <a href="<?php echo $BASE . '/' . $att['file_path']; ?>" target="_blank" class="reply-attachment file">
                                    <i class="bi bi-file-earmark"></i>
                                    <span><?php echo htmlspecialchars($att['file_name']); ?></span>
                                </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="reply-footer">
                        <div>
                            <?php if ($userId && $userId != $reply['author_id']): ?>
                            <button class="reply-upvote <?php echo $reply['user_upvoted'] ? 'upvoted' : ''; ?>" data-reply-id="<?php echo $reply['id']; ?>">
                                <i class="bi bi-hand-thumbs-up<?php echo $reply['user_upvoted'] ? '-fill' : ''; ?>"></i>
                                <span><?php echo $reply['upvote_count']; ?></span>
                            </button>
                            <?php else: ?>
                            <span class="reply-upvote-count"><i class="bi bi-hand-thumbs-up"></i> <span><?php echo $reply['upvote_count']; ?></span></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($isOwner && !$thread['is_solved'] && $reply['author_id'] != $userId): ?>
                        <button type="button" class="btn btn-sm btn-success-solid" onclick="openBestAnswerModal(<?php echo $reply['id']; ?>)">
                            <i class="bi bi-check-lg"></i> Pilih Jawaban Terbaik
                        </button>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- FORM JAWABAN: Hanya muncul untuk NON-OWNER yang login -->
        <?php if ($userId && !$isOwner): ?>
        <section class="thread-reply-form" id="reply-form">
            <h3><i class="bi bi-reply"></i> Tulis Jawabanmu</h3>
            
            <?php if ($replyError): ?>
            <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?php echo $replyError; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <textarea name="reply_content" rows="5" placeholder="Tulis jawabanmu di sini..." required><?php echo htmlspecialchars($_POST['reply_content'] ?? ''); ?></textarea>
                </div>
                
                <div class="reply-upload-section">
                    <div class="reply-upload-area" id="replyDropZone">
                        <i class="bi bi-paperclip"></i>
                        <span>Lampirkan file (opsional)</span>
                        <small>Maks 5MB</small>
                        <input type="file" id="replyAttachments" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.txt">
                    </div>
                    <div id="replyFileList" class="reply-file-list"></div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Kirim Jawaban</button>
                </div>
            </form>
        </section>
        <?php elseif ($userId && $isOwner): ?>
        <!-- Info untuk owner -->
        <div class="thread-owner-info">
            <i class="bi bi-info-circle"></i>
            <p>Ini adalah pertanyaanmu. Tunggu jawaban dari pengguna lain.</p>
        </div>
        <?php else: ?>
        <!-- Belum login -->
        <div class="thread-login-prompt">
            <i class="bi bi-lock"></i>
            <p>Silakan <a href="<?php echo $BASE; ?>/login.php?redirect=student-forum-thread.php?id=<?php echo $threadId; ?>">login</a> untuk menjawab.</p>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isOwner): ?>
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-container">
            <div class="modal-icon danger"><i class="bi bi-trash"></i></div>
            <h3>Hapus Pertanyaan?</h3>
            <p>Yakin ingin menghapus pertanyaan ini?</p>
            <ul class="modal-info">
                <li><i class="bi bi-chat-dots"></i> Semua jawaban akan ikut terhapus</li>
                <?php if (!$thread['is_solved']): ?>
                <li><i class="bi bi-gem"></i> <strong><?php echo $thread['gem_reward']; ?> gem</strong> akan dikembalikan</li>
                <?php endif; ?>
            </ul>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Batal</button>
                <button type="button" class="btn btn-danger-solid" onclick="submitDelete()"><i class="bi bi-trash"></i> Ya, Hapus</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isOwner && !$thread['is_solved']): ?>
    <div class="modal-overlay" id="bestAnswerModal">
        <div class="modal-container">
            <div class="modal-icon success"><i class="bi bi-trophy"></i></div>
            <h3>Pilih Jawaban Terbaik?</h3>
            <p>Kamu yakin ingin memilih jawaban ini sebagai yang terbaik?</p>
            <ul class="modal-info">
                <li><i class="bi bi-gem"></i> <strong><?php echo $thread['gem_reward']; ?> gem</strong> akan diberikan ke penjawab</li>
                <li><i class="bi bi-lock"></i> Pilihan ini tidak bisa diubah</li>
            </ul>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeBestAnswerModal()">Batal</button>
                <a href="#" id="confirmBestAnswerLink" class="btn btn-success-solid"><i class="bi bi-trophy"></i> Ya, Pilih Ini</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    const deleteModal = document.getElementById('deleteModal');
    const bestAnswerModal = document.getElementById('bestAnswerModal');

    function openDeleteModal() { deleteModal?.classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeDeleteModal() { deleteModal?.classList.remove('active'); document.body.style.overflow = ''; }
    function submitDelete() { document.getElementById('deleteForm').submit(); }

    function openBestAnswerModal(replyId) {
        document.getElementById('confirmBestAnswerLink').href = '?id=<?php echo $threadId; ?>&best=' + replyId;
        bestAnswerModal?.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeBestAnswerModal() { bestAnswerModal?.classList.remove('active'); document.body.style.overflow = ''; }

    document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) { m.classList.remove('active'); document.body.style.overflow = ''; }}));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.active').forEach(m => { m.classList.remove('active'); document.body.style.overflow = ''; }); });

    document.querySelectorAll('.reply-upvote').forEach(btn => {
        btn.addEventListener('click', async function() {
            const res = await fetch('<?php echo $BASE; ?>/api-forum-upvote.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({reply_id: this.dataset.replyId})
            });
            const data = await res.json();
            if (data.success) {
                this.classList.toggle('upvoted');
                this.querySelector('i').className = data.upvoted ? 'bi bi-hand-thumbs-up-fill' : 'bi bi-hand-thumbs-up';
                this.querySelector('span').textContent = data.count;
            }
        });
    });

    const replyDropZone = document.getElementById('replyDropZone');
    const replyFileInput = document.getElementById('replyAttachments');
    const replyFileList = document.getElementById('replyFileList');

    replyDropZone?.addEventListener('click', () => replyFileInput.click());
    replyDropZone?.addEventListener('dragover', e => { e.preventDefault(); replyDropZone.classList.add('dragover'); });
    replyDropZone?.addEventListener('dragleave', () => replyDropZone.classList.remove('dragover'));
    replyDropZone?.addEventListener('drop', e => { e.preventDefault(); replyDropZone.classList.remove('dragover'); replyFileInput.files = e.dataTransfer.files; updateReplyFileList(); });
    replyFileInput?.addEventListener('change', updateReplyFileList);

    function updateReplyFileList() {
        replyFileList.innerHTML = '';
        Array.from(replyFileInput.files).forEach(f => {
            const d = document.createElement('div'); d.className = 'file-item';
            let icon = f.type.startsWith('image/') ? 'bi-file-image' : f.type === 'application/pdf' ? 'bi-file-pdf' : 'bi-file-earmark';
            d.innerHTML = `<i class="bi ${icon}"></i><span>${f.name}</span><small>(${(f.size/1024).toFixed(1)} KB)</small>`;
            replyFileList.appendChild(d);
        });
    }

    setTimeout(() => { document.querySelector('.alert-success')?.remove(); }, 5000);
    </script>
</body>
</html>
