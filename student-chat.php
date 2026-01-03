<?php
// student-chat.php v3.2 - Fixed Google Avatar dengan referrerpolicy
// Chat mahasiswa dengan edit/delete/copy + typing indicator + filter sidebar

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}

$student_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Mahasiswa';

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed.');
}

$currentConvId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;

// ===== v3.2: Helper Avatar URL (sama dengan student-settings.php) =====
function get_chat_avatar_url($avatar, $base = '') {
    if (empty($avatar)) return '';
    // Jika sudah URL lengkap (termasuk Google avatar), return langsung
    if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
    // Jika path lokal, tambahkan base path
    return $base . '/' . ltrim($avatar, '/');
}

// ===== v3.1: Query tetap load semua, filter di PHP =====
$stmt = $pdo->prepare("
    SELECT 
        c.id AS conversation_id,
        c.updated_at,
        c.mentor_id,
        c.session_id,
        u.name AS mentor_name,
        u.specialization AS mentor_spec,
        u.avatar AS mentor_avatar,
        s.id AS session_id,
        s.status AS session_status,
        s.ended_at AS session_ended_at,
        s.created_at AS session_created,
        (SELECT COUNT(*) FROM messages m 
         WHERE m.conversation_id = c.id 
         AND m.sender_id != ? AND m.is_read = 0) AS unread_count,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) AS message_count
    FROM conversations c
    JOIN users u ON c.mentor_id = u.id
    LEFT JOIN sessions s ON c.session_id = s.id
    WHERE c.student_id = ?
    ORDER BY 
        CASE 
            WHEN s.ended_at IS NOT NULL THEN 2
            WHEN s.status = 'ongoing' THEN 0
            WHEN s.status = 'pending' THEN 1
            ELSE 3
        END,
        c.updated_at DESC
");
$stmt->execute([$student_id, $student_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== v3.1: Filter untuk sidebar - exclude cancelled & empty =====
$filteredConversations = array_filter($conversations, function($conv) {
    $isCancelled = ($conv['session_status'] === 'cancelled');
    $isEmpty = ((int)$conv['message_count'] === 0);
    $isActiveSession = in_array($conv['session_status'], ['ongoing', 'pending'], true);
    return !$isCancelled && (!$isEmpty || $isActiveSession);
});

$validConvIds = array_column($conversations, 'conversation_id');
if ($currentConvId && !in_array($currentConvId, $validConvIds)) {
    header('Location: ' . BASE_PATH . '/student-chat.php');
    exit;
}

if (!$currentConvId && !empty($filteredConversations)) {
    $firstFiltered = reset($filteredConversations);
    $currentConvId = (int)$firstFiltered['conversation_id'];
}

$currentConv = null;
$currentMsgs = [];
$currentSession = null;
$canChat = false;

if ($currentConvId) {
    foreach ($conversations as $conv) {
        if ((int)$conv['conversation_id'] === $currentConvId) {
            $currentConv = $conv;
            break;
        }
    }

    if ($currentConv) {
        $stmt = $pdo->prepare("
            SELECT m.*, u.name AS sender_name, u.role AS sender_role, u.avatar AS sender_avatar
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$currentConvId]);
        $currentMsgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?");
        $stmt->execute([$currentConvId, $student_id]);

        $actualStatus = 'none';
        if ($currentConv['session_ended_at']) {
            $actualStatus = 'completed';
        } elseif ($currentConv['session_status']) {
            $actualStatus = $currentConv['session_status'];
        }

        $currentSession = [
            'id' => $currentConv['session_id'],
            'status' => $actualStatus,
            'ended_at' => $currentConv['session_ended_at']
        ];

        $canChat = ($actualStatus === 'ongoing' && empty($currentConv['session_ended_at']));
    }
}

$maxFileSize = 300 * 1024 * 1024;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Mentor - JagoNugas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }

        .chat-container {
            display: flex;
            height: calc(100vh - 70px);
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Sidebar */
        .chat-sidebar {
            width: 340px;
            background: white;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a202c;
            margin: 0;
        }

        .conversation-list {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 12px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            margin-bottom: 4px;
        }

        .conversation-item:hover { background: #f8f9fa; }
        .conversation-item.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .conv-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
            flex-shrink: 0;
            overflow: hidden;
        }

        /* v3.2: Styling untuk avatar image */
        .conv-avatar img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }
        
        .conv-info { flex: 1; min-width: 0; }
        .conv-name { font-weight: 600; color: #1a202c; margin-bottom: 2px; }
        .conv-spec { font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 8px; }
        
        .conv-session-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 6px;
            font-weight: 500;
        }
        .conv-session-badge.ongoing { background: #dcfce7; color: #16a34a; }
        .conv-session-badge.pending { background: #fef3c7; color: #d97706; }
        .conv-session-badge.completed { background: #e0e7ff; color: #4f46e5; }
        
        .conv-unread { background: #ef4444; color: white; font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 10px; }

        .empty-conversations { padding: 40px 20px; text-align: center; color: #64748b; }
        .empty-conversations i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }

        /* Chat Main */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
        }

        .chat-header {
            padding: 16px 24px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-header-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            overflow: hidden;
        }

        .chat-header-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .chat-header-info { flex: 1; }
        .chat-header-info h3 { font-size: 1rem; font-weight: 600; color: #1a202c; margin: 0; }
        .chat-header-info span { font-size: 0.85rem; color: #64748b; }
        .header-actions { display: flex; align-items: center; gap: 10px; }

        .session-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .session-badge.ongoing { background: #dcfce7; color: #16a34a; }
        .session-badge.pending { background: #fef3c7; color: #d97706; }
        .session-badge.completed { background: #e0e7ff; color: #4f46e5; }
        .session-badge.cancelled { background: #fee2e2; color: #dc2626; }
        .session-badge.none { background: #f1f5f9; color: #64748b; }
        .session-badge i { font-size: 0.5rem; }

        .btn-end-session {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 2px solid #ef4444;
            background: white;
            color: #ef4444;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-end-session:hover { background: #ef4444; color: white; }

        /* Session Ended Notice */
        .session-ended-notice {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px 24px;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-top: 1px solid #fcd34d;
            color: #92400e;
            font-size: 0.95rem;
        }

        .session-ended-notice i { font-size: 1.25rem; }
        .session-ended-notice .btn-book-again {
            padding: 8px 16px;
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .session-ended-notice .btn-book-again:hover { background: #d97706; }

        /* Messages */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .message-row {
            display: flex;
            flex-direction: column;
            max-width: 70%;
            position: relative;
        }

        .message-row.me { align-self: flex-end; }
        .message-row.other { align-self: flex-start; }

        .message-wrapper {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .message-row.me .message-wrapper { flex-direction: row-reverse; }

        .message-bubble {
            padding: 12px 16px;
            border-radius: 16px;
            position: relative;
            min-width: 80px;
        }

        .message-row.me .message-bubble {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message-row.other .message-bubble {
            background: white;
            color: #1a202c;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .message-bubble p { margin: 0; line-height: 1.5; word-wrap: break-word; }
        .message-time { font-size: 0.7rem; opacity: 0.7; margin-top: 4px; display: block; }
        .message-row.me .message-time { text-align: right; }
        .message-edited { font-size: 0.65rem; opacity: 0.6; font-style: italic; margin-left: 6px; }

        /* Message Actions */
        .message-actions {
            display: none;
            align-items: center;
            gap: 4px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .can-send-message .message-wrapper:hover .message-actions { display: flex; opacity: 1; }

        .msg-action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: #f1f5f9;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .msg-action-btn:hover { background: #e2e8f0; color: #1a202c; }
        .msg-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

        /* File & Video */
        .message-file {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            margin-top: 8px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
        }

        .message-row.other .message-file { background: #f1f5f9; }
        .message-file:hover { background: rgba(255,255,255,0.25); }
        .message-row.other .message-file:hover { background: #e2e8f0; }
        .message-file i { font-size: 1.5rem; }
        .file-info { flex: 1; min-width: 0; }
        .file-name { font-weight: 500; font-size: 0.9rem; }
        .file-size { font-size: 0.75rem; opacity: 0.7; }

        .message-video-player { margin-top: 8px; border-radius: 12px; overflow: hidden; max-width: 100%; }
        .message-video-player video { width: 100%; max-height: 300px; border-radius: 12px; }

        /* Input Area */
        .chat-input-area {
            padding: 16px 24px;
            background: white;
            border-top: 1px solid #e5e7eb;
        }

        .chat-input-area.disabled {
            pointer-events: none;
            opacity: 0.5;
        }

        .edit-indicator {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: #fef3c7;
            border-radius: 12px;
            margin-bottom: 12px;
            font-size: 0.9rem;
            color: #92400e;
        }

        .edit-indicator.show { display: flex; }
        .edit-indicator i { font-size: 1.1rem; }
        .edit-indicator span { flex: 1; }

        .btn-cancel-edit {
            background: transparent;
            border: none;
            color: #92400e;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .btn-cancel-edit:hover { background: rgba(0,0,0,0.1); }

        .file-preview {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            background: #f1f5f9;
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .file-preview.show { display: flex; }

        .file-preview-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }

        .file-preview-info { flex: 1; }
        .file-preview-name { font-weight: 500; font-size: 0.9rem; }
        .file-preview-size { font-size: 0.8rem; color: #64748b; }

        .btn-remove-file {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .upload-progress { display: none; margin-bottom: 12px; }
        .upload-progress.show { display: block; }

        .progress-bar-container {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 4px;
            transition: width 0.3s;
            width: 0%;
        }

        .progress-text { font-size: 0.8rem; color: #64748b; margin-top: 6px; text-align: center; }

        .input-wrapper {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            background: #f8f9fa;
            border-radius: 24px;
            padding: 8px 8px 8px 20px;
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .input-wrapper:focus-within { border-color: #667eea; background: white; }

        .input-wrapper textarea {
            flex: 1;
            border: none;
            background: transparent;
            resize: none;
            padding: 8px 0;
            font-size: 0.95rem;
            line-height: 1.5;
            max-height: 120px;
            outline: none;
            font-family: inherit;
        }

        .input-actions { display: flex; align-items: center; gap: 4px; }

        .btn-attach, .btn-send {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-attach { background: transparent; color: #64748b; }
        .btn-attach:hover { background: #e2e8f0; color: #667eea; }
        .btn-send { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-send:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .btn-send:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .file-hint { font-size: 0.8rem; color: #94a3b8; margin-top: 8px; text-align: center; }

        .empty-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #64748b;
            text-align: center;
            padding: 40px;
        }

        .empty-chat i { font-size: 4rem; color: #cbd5e1; margin-bottom: 20px; }
        .empty-chat h2 { font-size: 1.25rem; color: #1a202c; margin-bottom: 8px; }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show { display: flex; }

        .modal-box {
            background: white;
            border-radius: 20px;
            padding: 32px;
            max-width: 420px;
            width: 90%;
            text-align: center;
        }

        .modal-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }

        .modal-icon.warning { background: #fef2f2; color: #ef4444; }
        .modal-icon.danger { background: #fef2f2; color: #dc2626; }
        .modal-box h3 { font-size: 1.25rem; font-weight: 700; margin-bottom: 12px; }
        .modal-box p { color: #64748b; margin-bottom: 24px; }
        .modal-actions { display: flex; gap: 12px; justify-content: center; }

        .btn-modal {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-modal-cancel { background: #f1f5f9; color: #475569; }
        .btn-modal-cancel:hover { background: #e2e8f0; }
        .btn-modal-confirm { background: #ef4444; color: white; }
        .btn-modal-confirm:hover { background: #dc2626; }

        /* Toast */
        .toast-notification {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1a202c;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.9rem;
            z-index: 1001;
            opacity: 0;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast-notification.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast-notification.success { background: #16a34a; }
        .toast-notification.error { background: #dc2626; }

        /* Typing Indicator */
        .typing-indicator {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: white;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            max-width: fit-content;
            margin-bottom: 8px;
            align-self: flex-start;
        }

        .typing-indicator.show { display: flex; }

        .typing-dots { display: flex; gap: 4px; }

        .typing-dots span {
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            animation: typingBounce 1.4s infinite ease-in-out;
        }

        .typing-dots span:nth-child(1) { animation-delay: 0s; }
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typingBounce {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-6px); }
        }

        .typing-text { font-size: 0.85rem; color: #64748b; font-style: italic; }

        @media (max-width: 768px) {
            .chat-sidebar {
                width: 100%;
                position: absolute;
                left: 0; top: 70px; bottom: 0;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .chat-sidebar.show { transform: translateX(0); }
            .message-row { max-width: 85%; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/student-navbar.php'; ?>

<div class="chat-container">
    <!-- SIDEBAR -->
    <aside class="chat-sidebar" id="chatSidebar">
        <div class="sidebar-header">
            <h2><i class="bi bi-chat-dots me-2"></i>Chat Mentor</h2>
        </div>
        <div class="conversation-list">
            <?php if (empty($filteredConversations)): ?>
                <div class="empty-conversations">
                    <i class="bi bi-chat-square-text"></i>
                    <p>Belum ada percakapan.<br>Pesan mentor setelah booking sesi.</p>
                </div>
            <?php else: ?>
                <?php foreach ($filteredConversations as $conv):
                    $cId = (int)$conv['conversation_id'];
                    $mentorInitial = mb_strtoupper(mb_substr($conv['mentor_name'], 0, 1, 'UTF-8'), 'UTF-8');
                    // v3.2: Gunakan helper function untuk avatar URL
                    $mentorAvatarUrl = get_chat_avatar_url($conv['mentor_avatar'] ?? '', BASE_PATH);
                    $isActive = $currentConvId === $cId;
                    
                    $badgeStatus = 'none';
                    if ($conv['session_ended_at']) {
                        $badgeStatus = 'completed';
                    } elseif ($conv['session_status']) {
                        $badgeStatus = $conv['session_status'];
                    }
                ?>
                    <a href="?conversation_id=<?= $cId ?>" class="conversation-item <?= $isActive ? 'active' : '' ?>">
                        <div class="conv-avatar">
                            <?php if ($mentorAvatarUrl): ?>
                                <!-- v3.2: referrerpolicy untuk Google avatar -->
                                <img src="<?= htmlspecialchars($mentorAvatarUrl) ?>" alt="" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <?= htmlspecialchars($mentorInitial) ?>
                            <?php endif; ?>
                        </div>
                        <div class="conv-info">
                            <div class="conv-name"><?= htmlspecialchars($conv['mentor_name']) ?></div>
                            <div class="conv-spec">
                                <?= htmlspecialchars($conv['mentor_spec'] ?? 'Mentor') ?>
                                <?php if ($badgeStatus !== 'none'): ?>
                                    <span class="conv-session-badge <?= $badgeStatus ?>">
                                        <?php
                                        if ($badgeStatus === 'ongoing') echo 'Aktif';
                                        elseif ($badgeStatus === 'pending') echo 'Pending';
                                        elseif ($badgeStatus === 'completed') echo 'Selesai';
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($conv['unread_count'] > 0): ?>
                            <span class="conv-unread"><?= $conv['unread_count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="chat-main">
        <?php if (!$currentConvId || !$currentConv): ?>
            <div class="empty-chat">
                <i class="bi bi-chat-dots"></i>
                <h2>Pilih Percakapan</h2>
                <p>Pilih mentor di sebelah kiri untuk mulai chat.</p>
            </div>
        <?php else: ?>
            <?php
            $mentorInitial = mb_strtoupper(mb_substr($currentConv['mentor_name'], 0, 1, 'UTF-8'), 'UTF-8');
            // v3.2: Gunakan helper function untuk header avatar
            $headerAvatarUrl = get_chat_avatar_url($currentConv['mentor_avatar'] ?? '', BASE_PATH);
            $sessionStatus = $currentSession['status'] ?? 'none';
            ?>
            <div class="chat-header">
                <div class="chat-header-avatar">
                    <?php if ($headerAvatarUrl): ?>
                        <!-- v3.2: referrerpolicy untuk Google avatar -->
                        <img src="<?= htmlspecialchars($headerAvatarUrl) ?>" alt="" referrerpolicy="no-referrer">
                    <?php else: ?>
                        <?= htmlspecialchars($mentorInitial) ?>
                    <?php endif; ?>
                </div>
                <div class="chat-header-info">
                    <h3><?= htmlspecialchars($currentConv['mentor_name']) ?></h3>
                    <span><?= htmlspecialchars($currentConv['mentor_spec'] ?? 'Mentor') ?></span>
                </div>
                <div class="header-actions">
                    <?php if ($sessionStatus === 'ongoing'): ?>
                        <span class="session-badge ongoing">
                            <i class="bi bi-circle-fill"></i> Sesi Aktif
                        </span>
                        <?php if ($currentSession['id']): ?>
                            <button type="button" class="btn-end-session" id="btnEndSession" data-session-id="<?= $currentSession['id'] ?>">
                                <i class="bi bi-stop-circle"></i> Akhiri Sesi
                            </button>
                        <?php endif; ?>
                    <?php elseif ($sessionStatus === 'pending'): ?>
                        <span class="session-badge pending">
                            <i class="bi bi-circle-fill"></i> Menunggu
                        </span>
                    <?php elseif ($sessionStatus === 'completed'): ?>
                        <span class="session-badge completed">
                            <i class="bi bi-circle-fill"></i> Sesi Selesai
                        </span>
                    <?php else: ?>
                        <span class="session-badge none">
                            <i class="bi bi-circle-fill"></i> Tidak Ada Sesi
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-messages <?= $canChat ? 'can-send-message' : '' ?>" id="chatMessages" data-conversation="<?= $currentConvId ?>">
                <?php if (empty($currentMsgs)): ?>
                    <div class="empty-chat" style="padding: 60px 20px;">
                        <i class="bi bi-chat-text" style="font-size: 3rem;"></i>
                        <p>Belum ada pesan. Mulai percakapan!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($currentMsgs as $msg):
                        $isMe = $msg['sender_id'] == $student_id;
                        $isVideo = !empty($msg['file_path']) && preg_match('/\.(mp4|webm|mov)$/i', $msg['file_path']);
                        $isEdited = !empty($msg['edited_at']);
                    ?>
                        <div class="message-row <?= $isMe ? 'me' : 'other' ?>" data-message-id="<?= $msg['id'] ?>" data-edited="<?= $isEdited ? '1' : '0' ?>">
                            <div class="message-wrapper">
                                <?php if ($canChat): ?>
                                <div class="message-actions">
                                    <?php if ($isMe): ?>
                                        <button type="button" class="msg-action-btn edit" title="Edit"
                                            data-message-id="<?= $msg['id'] ?>"
                                            data-message-text="<?= htmlspecialchars($msg['message'] ?? '', ENT_QUOTES) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="msg-action-btn delete" title="Hapus"
                                            data-message-id="<?= $msg['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="msg-action-btn copy" title="Salin"
                                            data-message-text="<?= htmlspecialchars($msg['message'] ?? '', ENT_QUOTES) ?>">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <div class="message-bubble">
                                    <?php if (!empty($msg['message'])): ?>
                                        <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                    <?php endif; ?>

                                    <?php if (!empty($msg['file_path'])): ?>
                                        <?php if ($isVideo): ?>
                                            <div class="message-video-player">
                                                <video controls preload="metadata">
                                                    <source src="<?= BASE_PATH ?>/<?= htmlspecialchars($msg['file_path']) ?>" type="video/mp4">
                                                </video>
                                            </div>
                                            <a href="<?= BASE_PATH ?>/<?= htmlspecialchars($msg['file_path']) ?>" target="_blank" class="message-file" download>
                                                <i class="bi bi-file-earmark-play"></i>
                                                <div class="file-info">
                                                    <div class="file-name"><?= htmlspecialchars($msg['file_name'] ?? 'Video') ?></div>
                                                    <div class="file-size"><?= round(($msg['file_size'] ?? 0) / (1024*1024), 1) ?> MB</div>
                                                </div>
                                                <i class="bi bi-download"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= BASE_PATH ?>/<?= htmlspecialchars($msg['file_path']) ?>" target="_blank" class="message-file">
                                                <i class="bi bi-file-earmark"></i>
                                                <div class="file-info">
                                                    <div class="file-name"><?= htmlspecialchars($msg['file_name'] ?? 'File') ?></div>
                                                    <div class="file-size"><?= round(($msg['file_size'] ?? 0) / 1024, 1) ?> KB</div>
                                                </div>
                                                <i class="bi bi-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <span class="message-time">
                                        <?= date('H:i', strtotime($msg['created_at'])) ?>
                                        <?php if ($isEdited): ?>
                                            <span class="message-edited">(diedit)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Typing Indicator -->
                <div class="typing-indicator" id="typingIndicator">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="typing-text" id="typingText">sedang mengetik...</span>
                </div>
            </div>

            <?php if (!$canChat): ?>
                <!-- Session Ended Notice -->
                <div class="session-ended-notice">
                    <i class="bi bi-info-circle"></i>
                    <span>
                        <?php if ($sessionStatus === 'completed'): ?>
                            Sesi mentoring telah selesai. Booking sesi baru untuk melanjutkan chat.
                        <?php elseif ($sessionStatus === 'cancelled'): ?>
                            Sesi dibatalkan. Booking sesi baru untuk melanjutkan chat.
                        <?php elseif ($sessionStatus === 'pending'): ?>
                            Menunggu konfirmasi mentor. Chat akan aktif setelah sesi disetujui.
                        <?php else: ?>
                            Tidak ada sesi aktif. Booking sesi untuk mulai chat.
                        <?php endif; ?>
                    </span>
                    <?php if ($sessionStatus === 'completed' || $sessionStatus === 'cancelled' || $sessionStatus === 'none'): ?>
                        <a href="<?= BASE_PATH ?>/student-mentor.php" class="btn-book-again">
                            <i class="bi bi-calendar-plus"></i> Booking Sesi
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Input Area (only show if canChat) -->
                <form class="chat-input-area" id="chatForm" enctype="multipart/form-data">
                    <input type="hidden" name="conversation_id" value="<?= $currentConvId ?>">
                    <input type="hidden" name="edit_message_id" id="editMessageId" value="">

                    <div class="edit-indicator" id="editIndicator">
                        <i class="bi bi-pencil-square"></i>
                        <span>Mengedit pesan</span>
                        <button type="button" class="btn-cancel-edit" id="btnCancelEdit">
                            <i class="bi bi-x-lg"></i> Batal
                        </button>
                    </div>

                    <div class="file-preview" id="filePreview">
                        <div class="file-preview-icon" id="previewIcon">
                            <i class="bi bi-file-earmark"></i>
                        </div>
                        <div class="file-preview-info">
                            <div class="file-preview-name" id="previewFileName">-</div>
                            <div class="file-preview-size" id="previewFileSize">-</div>
                        </div>
                        <button type="button" class="btn-remove-file" id="btnRemoveFile">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>

                    <div class="upload-progress" id="uploadProgress">
                        <div class="progress-bar-container">
                            <div class="progress-bar" id="progressBar"></div>
                        </div>
                        <div class="progress-text" id="progressText">Mengupload... 0%</div>
                    </div>

                    <div class="input-wrapper">
                        <textarea name="message" id="messageInput" rows="1" placeholder="Tulis pesan..."></textarea>
                        <div class="input-actions">
                            <input type="file" name="attachment" id="fileInput" hidden accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.mp4,.webm,.mov">
                            <button type="button" class="btn-attach" id="btnAttach" title="Lampirkan file">
                                <i class="bi bi-paperclip"></i>
                            </button>
                            <button type="submit" class="btn-send" id="btnSend">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </div>
                    <p class="file-hint"><i class="bi bi-info-circle"></i> JPG, PNG, GIF, PDF, DOC, TXT (5MB) | MP4, WebM, MOV (300MB)</p>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<!-- Modal Hapus -->
<div class="modal-overlay" id="modalDeleteMessage">
    <div class="modal-box">
        <div class="modal-icon danger">
            <i class="bi bi-trash"></i>
        </div>
        <h3>Hapus Pesan?</h3>
        <p>Pesan akan dihapus untuk Anda dan mentor. Tindakan ini tidak dapat dibatalkan.</p>
        <div class="modal-actions">
            <button type="button" class="btn-modal btn-modal-cancel" id="btnCancelDelete">Batal</button>
            <button type="button" class="btn-modal btn-modal-confirm" id="btnConfirmDelete">Ya, Hapus</button>
        </div>
    </div>
</div>

<!-- Modal End Session -->
<div class="modal-overlay" id="modalEndSession">
    <div class="modal-box">
        <div class="modal-icon warning">
            <i class="bi bi-stop-circle"></i>
        </div>
        <h3>Akhiri Sesi?</h3>
        <p>Setelah diakhiri, Anda tidak dapat mengirim pesan lagi di sesi ini.</p>
        <div class="modal-actions">
            <button type="button" class="btn-modal btn-modal-cancel" id="btnCancelEnd">Batal</button>
            <button type="button" class="btn-modal btn-modal-confirm" id="btnConfirmEnd">Ya, Akhiri</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast-notification" id="toast"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const previewFileName = document.getElementById('previewFileName');
    const previewFileSize = document.getElementById('previewFileSize');
    const previewIcon = document.getElementById('previewIcon');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const editIndicator = document.getElementById('editIndicator');
    const editMessageId = document.getElementById('editMessageId');
    const btnAttach = document.getElementById('btnAttach');
    const btnRemoveFile = document.getElementById('btnRemoveFile');
    const btnCancelEdit = document.getElementById('btnCancelEdit');
    const btnSend = document.getElementById('btnSend');
    const modalDeleteMessage = document.getElementById('modalDeleteMessage');
    const modalEndSession = document.getElementById('modalEndSession');
    const btnCancelDelete = document.getElementById('btnCancelDelete');
    const btnConfirmDelete = document.getElementById('btnConfirmDelete');
    const btnEndSession = document.getElementById('btnEndSession');
    const btnCancelEnd = document.getElementById('btnCancelEnd');
    const btnConfirmEnd = document.getElementById('btnConfirmEnd');
    const toast = document.getElementById('toast');

    const canChat = <?= $canChat ? 'true' : 'false' ?>;
    let deleteMessageId = null;

    // Auto-scroll
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Toast
    function showToast(message, type = 'success') {
        toast.textContent = message;
        toast.className = 'toast-notification ' + type + ' show';
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    // Textarea auto-resize
    messageInput?.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // Only setup interactive elements if canChat
    if (canChat) {
        // File attach
        btnAttach?.addEventListener('click', () => fileInput?.click());

        fileInput?.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const isVideo = /\.(mp4|webm|mov)$/i.test(file.name);
                const maxSize = isVideo ? 300 * 1024 * 1024 : 5 * 1024 * 1024;
                
                if (file.size > maxSize) {
                    showToast(`File terlalu besar. Maksimal ${isVideo ? '300MB' : '5MB'}`, 'error');
                    this.value = '';
                    return;
                }

                previewFileName.textContent = file.name;
                previewFileSize.textContent = isVideo 
                    ? (file.size / (1024 * 1024)).toFixed(1) + ' MB'
                    : (file.size / 1024).toFixed(1) + ' KB';
                previewIcon.innerHTML = isVideo ? '<i class="bi bi-film"></i>' : '<i class="bi bi-file-earmark"></i>';
                previewIcon.style.background = isVideo ? 'linear-gradient(135deg, #ef4444, #dc2626)' : 'linear-gradient(135deg, #667eea, #764ba2)';
                filePreview.classList.add('show');
            }
        });

        btnRemoveFile?.addEventListener('click', function() {
            fileInput.value = '';
            filePreview.classList.remove('show');
        });

        // Edit buttons
        document.querySelectorAll('.msg-action-btn.edit').forEach(btn => {
            btn.addEventListener('click', function() {
                editMessageId.value = this.dataset.messageId;
                messageInput.value = this.dataset.messageText;
                messageInput.focus();
                messageInput.style.height = 'auto';
                messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
                editIndicator.classList.add('show');
            });
        });

        btnCancelEdit?.addEventListener('click', function() {
            editMessageId.value = '';
            messageInput.value = '';
            messageInput.style.height = 'auto';
            editIndicator.classList.remove('show');
        });

        // Delete buttons
        document.querySelectorAll('.msg-action-btn.delete').forEach(btn => {
            btn.addEventListener('click', function() {
                deleteMessageId = this.dataset.messageId;
                modalDeleteMessage.classList.add('show');
            });
        });
    }

    // Copy buttons (always available)
    document.querySelectorAll('.msg-action-btn.copy').forEach(btn => {
        btn.addEventListener('click', async function() {
            try {
                await navigator.clipboard.writeText(this.dataset.messageText);
                showToast('Pesan disalin ke clipboard');
            } catch (err) {
                const ta = document.createElement('textarea');
                ta.value = this.dataset.messageText;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast('Pesan disalin ke clipboard');
            }
        });
    });

    btnCancelDelete?.addEventListener('click', () => {
        modalDeleteMessage.classList.remove('show');
        deleteMessageId = null;
    });

    btnConfirmDelete?.addEventListener('click', async function() {
        if (!deleteMessageId) return;

        this.disabled = true;
        this.textContent = 'Menghapus...';

        try {
            const response = await fetch('<?= BASE_PATH ?>/api-message-delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: deleteMessageId })
            });

            const result = await response.json();

            if (result.success) {
                const msgRow = document.querySelector(`.message-row[data-message-id="${deleteMessageId}"]`);
                if (msgRow) {
                    msgRow.style.opacity = '0';
                    msgRow.style.transform = 'translateX(20px)';
                    setTimeout(() => msgRow.remove(), 300);
                }
                showToast('Pesan berhasil dihapus');
            } else {
                showToast(result.error || 'Gagal menghapus pesan', 'error');
            }
        } catch (err) {
            showToast('Terjadi kesalahan', 'error');
        }

        this.disabled = false;
        this.textContent = 'Ya, Hapus';
        modalDeleteMessage.classList.remove('show');
        deleteMessageId = null;
    });

    // End Session
    btnEndSession?.addEventListener('click', () => {
        modalEndSession.classList.add('show');
    });

    btnCancelEnd?.addEventListener('click', () => {
        modalEndSession.classList.remove('show');
    });

    btnConfirmEnd?.addEventListener('click', async function() {
        const sessionId = btnEndSession.dataset.sessionId;
        if (!sessionId) return;

        this.disabled = true;
        this.textContent = 'Mengakhiri...';

        try {
            const response = await fetch('<?= BASE_PATH ?>/api-session-end.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId })
            });

            const result = await response.json();

            if (result.success) {
                showToast('Sesi berhasil diakhiri');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(result.error || 'Gagal mengakhiri sesi', 'error');
            }
        } catch (err) {
            showToast('Terjadi kesalahan', 'error');
        }

        this.disabled = false;
        this.textContent = 'Ya, Akhiri';
        modalEndSession.classList.remove('show');
    });

    // Form submit (only if canChat)
    if (canChat && chatForm) {
        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const message = messageInput.value.trim();
            const file = fileInput.files[0];
            const isEditing = editMessageId.value !== '';

            if (!message && !file) return;

            btnSend.disabled = true;

            const formData = new FormData(this);
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable && file) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressText.textContent = `Mengupload... ${percent}%`;
                    uploadProgress.classList.add('show');
                }
            });

            xhr.addEventListener('load', function() {
                uploadProgress.classList.remove('show');
                progressBar.style.width = '0%';

                try {
                    const result = JSON.parse(xhr.responseText);

                    if (result.success) {
                        messageInput.value = '';
                        messageInput.style.height = 'auto';
                        fileInput.value = '';
                        filePreview.classList.remove('show');
                        editIndicator.classList.remove('show');
                        editMessageId.value = '';

                        if (isEditing) {
                            const msgRow = document.querySelector(`.message-row[data-message-id="${result.message_id}"]`);
                            if (msgRow) {
                                const bubble = msgRow.querySelector('.message-bubble p');
                                if (bubble) {
                                    bubble.innerHTML = result.message.replace(/\n/g, '<br>');
                                }
                                const timeSpan = msgRow.querySelector('.message-time');
                                if (timeSpan && !timeSpan.querySelector('.message-edited')) {
                                    timeSpan.innerHTML += ' <span class="message-edited">(diedit)</span>';
                                }
                                msgRow.dataset.edited = '1';
                            }
                            showToast('Pesan berhasil diedit');
                        } else {
                            appendMyMessage(result);
                            showToast('Pesan terkirim');
                        }
                    } else {
                        showToast(result.error || 'Gagal mengirim pesan', 'error');
                    }
                } catch (err) {
                    showToast('Terjadi kesalahan', 'error');
                }

                btnSend.disabled = false;
            });

            xhr.addEventListener('error', function() {
                uploadProgress.classList.remove('show');
                showToast('Gagal mengirim pesan', 'error');
                btnSend.disabled = false;
            });

            xhr.open('POST', '<?= BASE_PATH ?>/api-chat-send.php');
            xhr.send(formData);
        });
    }

    function appendMyMessage(data) {
        const emptyChat = chatMessages?.querySelector('.empty-chat');
        if (emptyChat) emptyChat.remove();

        const row = document.createElement('div');
        row.className = 'message-row me';
        row.dataset.messageId = data.message_id;
        row.dataset.edited = '0';

        let fileHtml = '';
        if (data.file_path) {
            const isVideo = /\.(mp4|webm|mov)$/i.test(data.file_path);
            if (isVideo) {
                fileHtml = `<div class="message-video-player"><video controls preload="metadata"><source src="<?= BASE_PATH ?>/${data.file_path}" type="video/mp4"></video></div><a href="<?= BASE_PATH ?>/${data.file_path}" target="_blank" class="message-file" download><i class="bi bi-file-earmark-play"></i><div class="file-info"><div class="file-name">${data.file_name || 'Video'}</div><div class="file-size">${((data.file_size || 0) / (1024*1024)).toFixed(1)} MB</div></div><i class="bi bi-download"></i></a>`;
            } else {
                fileHtml = `<a href="<?= BASE_PATH ?>/${data.file_path}" target="_blank" class="message-file"><i class="bi bi-file-earmark"></i><div class="file-info"><div class="file-name">${data.file_name || 'File'}</div><div class="file-size">${((data.file_size || 0) / 1024).toFixed(1)} KB</div></div><i class="bi bi-download"></i></a>`;
            }
        }

        const escapedMsg = data.message ? data.message.replace(/"/g, '&quot;') : '';

        row.innerHTML = `
            <div class="message-wrapper">
                <div class="message-actions">
                    <button type="button" class="msg-action-btn edit" title="Edit" data-message-id="${data.message_id}" data-message-text="${escapedMsg}">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="msg-action-btn delete" title="Hapus" data-message-id="${data.message_id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="message-bubble">
                    ${data.message ? `<p>${data.message.replace(/\n/g, '<br>')}</p>` : ''}
                    ${fileHtml}
                    <span class="message-time">${data.time}</span>
                </div>
            </div>`;

        // Re-attach event listeners
        row.querySelector('.msg-action-btn.edit')?.addEventListener('click', function() {
            editMessageId.value = this.dataset.messageId;
            messageInput.value = this.dataset.messageText;
            messageInput.focus();
            editIndicator.classList.add('show');
        });

        row.querySelector('.msg-action-btn.delete')?.addEventListener('click', function() {
            deleteMessageId = this.dataset.messageId;
            modalDeleteMessage.classList.add('show');
        });

        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) chatMessages.insertBefore(row, typingIndicator);
        else chatMessages.appendChild(row);

        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Polling messages
    if (chatMessages) {
        const convId = chatMessages.dataset.conversation;
        let lastId = 0;
        const allMessages = chatMessages.querySelectorAll('.message-row[data-message-id]');
        if (allMessages.length > 0) {
            lastId = parseInt(allMessages[allMessages.length - 1].dataset.messageId) || 0;
        }

        async function pollMessages() {
            try {
                const response = await fetch(`<?= BASE_PATH ?>/api-chat-messages.php?conversation_id=${convId}&last_id=${lastId}&include_updated=1`);
                const data = await response.json();

                if (!data.success) return;

                if (data.last_id) {
                    lastId = data.last_id;
                }

                // Handle deleted messages
                if (data.existing_ids) {
                    const existingSet = new Set(data.existing_ids);
                    document.querySelectorAll('.message-row[data-message-id]').forEach(row => {
                        const msgId = parseInt(row.dataset.messageId);
                        if (!existingSet.has(msgId)) {
                            row.style.transition = 'opacity 0.3s, transform 0.3s';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(20px)';
                            setTimeout(() => row.remove(), 300);
                        }
                    });
                }

                // Handle new or updated messages
                if (data.messages?.length > 0) {
                    data.messages.forEach(msg => {
                        const existingRow = document.querySelector(`.message-row[data-message-id="${msg.id}"]`);
                        if (existingRow) {
                            const wasEdited = existingRow.dataset.edited === '1';
                            const nowEdited = msg.is_edited || msg.edited_at;

                            if (nowEdited && !wasEdited) {
                                const bubble = existingRow.querySelector('.message-bubble p');
                                if (bubble && msg.message) {
                                    bubble.innerHTML = msg.message.replace(/\n/g, '<br>');
                                }

                                const timeSpan = existingRow.querySelector('.message-time');
                                if (timeSpan && !timeSpan.querySelector('.message-edited')) {
                                    timeSpan.innerHTML += ' <span class="message-edited">(diedit)</span>';
                                }

                                existingRow.dataset.edited = '1';

                                existingRow.style.transition = 'background 0.3s';
                                existingRow.style.background = 'rgba(102, 126, 234, 0.1)';
                                setTimeout(() => existingRow.style.background = '', 1500);
                            }
                        } else if (msg.sender_id != <?= $student_id ?>) {
                            appendOtherMessage(msg);
                        }
                    });
                }
            } catch (err) {
                console.error('Poll messages error:', err);
            }
        }

        setInterval(pollMessages, 3000);
    }

    function appendOtherMessage(msg) {
        const emptyChat = chatMessages?.querySelector('.empty-chat');
        if (emptyChat) emptyChat.remove();

        const row = document.createElement('div');
        row.className = 'message-row other';
        row.dataset.messageId = msg.id;
        row.dataset.edited = (msg.is_edited || msg.edited_at) ? '1' : '0';

        let fileHtml = '';
        if (msg.file_path) {
            const isVideo = /\.(mp4|webm|mov)$/i.test(msg.file_path);
            if (isVideo) {
                fileHtml = `<div class="message-video-player"><video controls preload="metadata"><source src="<?= BASE_PATH ?>/${msg.file_path}" type="video/mp4"></video></div><a href="<?= BASE_PATH ?>/${msg.file_path}" target="_blank" class="message-file" download><i class="bi bi-file-earmark-play"></i><div class="file-info"><div class="file-name">${msg.file_name || 'Video'}</div><div class="file-size">${((msg.file_size || 0) / (1024*1024)).toFixed(1)} MB</div></div><i class="bi bi-download"></i></a>`;
            } else {
                fileHtml = `<a href="<?= BASE_PATH ?>/${msg.file_path}" target="_blank" class="message-file"><i class="bi bi-file-earmark"></i><div class="file-info"><div class="file-name">${msg.file_name || 'File'}</div><div class="file-size">${((msg.file_size || 0) / 1024).toFixed(1)} KB</div></div><i class="bi bi-download"></i></a>`;
            }
        }

        const escapedMsg = msg.message ? msg.message.replace(/"/g, '&quot;') : '';
        const editedIndicator = (msg.is_edited || msg.edited_at) ? ' <span class="message-edited">(diedit)</span>' : '';

        row.innerHTML = `
            <div class="message-wrapper">
                <div class="message-actions">
                    <button type="button" class="msg-action-btn copy" title="Salin" data-message-text="${escapedMsg}">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <div class="message-bubble">
                    ${msg.message ? `<p>${msg.message.replace(/\n/g, '<br>')}</p>` : ''}
                    ${fileHtml}
                    <span class="message-time">${msg.time}${editedIndicator}</span>
                </div>
            </div>`;

        row.querySelector('.msg-action-btn.copy')?.addEventListener('click', async function() {
            try {
                await navigator.clipboard.writeText(this.dataset.messageText);
                showToast('Pesan disalin ke clipboard');
            } catch (err) {
                const ta = document.createElement('textarea');
                ta.value = this.dataset.messageText;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast('Pesan disalin ke clipboard');
            }
        });

        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) chatMessages.insertBefore(row, typingIndicator);
        else chatMessages.appendChild(row);

        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});
</script>
</body>
</html>
