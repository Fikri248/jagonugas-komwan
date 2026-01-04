<?php
// mentor-chat.php v4.6 - FIXED DELETE DETECTION + SESSION AUTO-SYNC
// Fix 1-11: Sama seperti v4.4
// Fix 12: Auto sinkron status sesi dengan api-session-end.php
// Fix 13: FIXED - Polling delete detection pakai DOM query langsung (bukan knownMessageIds)
//         - Bug di v4.5: pesan hilang karena knownMessageIds tidak di-init dengan benar

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mentor') {
    header('Location: ' . BASE_PATH . '/mentor-login.php');
    exit;
}

$mentor_id = $_SESSION['user_id'];

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed.');
}

$currentConvId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;

$stmt = $pdo->prepare("
    SELECT 
        c.id AS conversation_id,
        c.student_id,
        c.mentor_id,
        c.session_id,
        u.name AS student_name,
        u.program_studi AS student_prodi,
        u.avatar AS student_avatar,
        s.id AS session_id,
        s.status AS session_status,
        s.ended_at AS session_ended_at,
        s.started_at AS session_started_at,
        s.duration AS session_duration,
        s.created_at AS session_created,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND is_read = 0) AS unread_count,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) AS message_count
    FROM conversations c
    INNER JOIN users u ON c.student_id = u.id
    LEFT JOIN sessions s ON c.session_id = s.id
    WHERE c.mentor_id = ?
    ORDER BY 
        CASE 
            WHEN s.ended_at IS NOT NULL THEN 2
            WHEN s.status = 'ongoing' THEN 0
            WHEN s.status = 'pending' THEN 1
            ELSE 3
        END,
        c.updated_at DESC
");
$stmt->execute([$mentor_id, $mentor_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filteredConversations = array_filter($conversations, function($conv) {
    $isCancelled = ($conv['session_status'] === 'cancelled');
    $isEmpty = ((int)$conv['message_count'] === 0);
    $isActiveSession = in_array($conv['session_status'], ['ongoing', 'pending'], true);
    return !$isCancelled && (!$isEmpty || $isActiveSession);
});

$validConvIds = array_column($conversations, 'conversation_id');
if ($currentConvId && !in_array($currentConvId, $validConvIds)) {
    header('Location: ' . BASE_PATH . '/mentor-chat.php');
    exit;
}

if (!$currentConvId && !empty($filteredConversations)) {
    $firstFiltered = reset($filteredConversations);
    $currentConvId = (int)$firstFiltered['conversation_id'];
}

$currentConv = null;
$currentMsgs = [];
$currentSession = null;
$canSendMessage = false;

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
        $stmt->execute([$currentConvId, $mentor_id]);

        $actualStatus = 'none';
        if ($currentConv['session_ended_at']) {
            $actualStatus = 'completed';
        } elseif ($currentConv['session_status']) {
            $actualStatus = $currentConv['session_status'];
        }

        $currentSession = [
            'id' => $currentConv['session_id'],
            'status' => $actualStatus,
            'ended_at' => $currentConv['session_ended_at'],
            'started_at' => $currentConv['session_started_at'],
            'duration' => $currentConv['session_duration']
        ];

        $canSendMessage = ($actualStatus === 'ongoing' && empty($currentConv['session_ended_at']));
    }
}

function get_chat_avatar($avatar, $base = '') {
    if (empty($avatar)) return '';
    if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
    return $base . '/' . ltrim($avatar, '/');
}

$maxFileSize = 300 * 1024 * 1024;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Mentor - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; min-height: 100vh; }
        .chat-container { display: flex; height: calc(100vh - 70px); max-width: 1400px; margin: 0 auto; position: relative; }
        
        /* Sidebar */
     .chat-sidebar { 
    width: 340px; 
    background: white; 
    border-right: 1px solid #e5e7eb; 
    display: flex; 
    flex-direction: column; 
    transition: width 0.3s ease, min-width 0.3s ease;
    position: relative;
    flex-shrink: 0;
}
.chat-sidebar.collapsed { 
    width: 0; 
    min-width: 0; 
    overflow: hidden; 
}
        .sidebar-header { padding: 20px; border-bottom: 1px solid #e5e7eb; background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); display: flex; align-items: center; justify-content: space-between; }
        .sidebar-header h2 { font-size: 1.25rem; font-weight: 700; color: #065f46; margin: 0; display: flex; align-items: center; gap: 10px; }
        .btn-collapse-sidebar { background: transparent; border: none; color: #065f46; cursor: pointer; padding: 8px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .btn-collapse-sidebar:hover { background: rgba(6, 95, 70, 0.1); }
        .conversation-list { flex: 1; overflow-y: auto; padding: 12px; list-style: none; }
        .conversation-item { display: flex; align-items: center; gap: 12px; padding: 14px; border-radius: 12px; text-decoration: none; color: inherit; transition: all 0.2s; margin-bottom: 4px; }
        .conversation-item:hover { background: #f8f9fa; }
        .conversation-item.active { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 1px solid #10b981; }
        .conv-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.1rem; flex-shrink: 0; overflow: hidden; }
        .conv-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .conv-info { flex: 1; min-width: 0; }
        .conv-name { font-weight: 600; color: #1a202c; margin-bottom: 2px; }
        .conv-sub { font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .conv-session-badge { font-size: 0.7rem; padding: 2px 6px; border-radius: 6px; font-weight: 500; }
        .conv-session-badge.ongoing { background: #dcfce7; color: #16a34a; }
        .conv-session-badge.pending { background: #fef3c7; color: #d97706; }
        .conv-session-badge.completed { background: #e0e7ff; color: #4f46e5; }
        .conv-unread { background: #ef4444; color: white; font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
        .empty-conversations { padding: 40px 20px; text-align: center; color: #64748b; }
        .empty-conversations i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }

        /* Toggle Sidebar Button */
.btn-toggle-sidebar { 
    position: absolute; 
    left: 340px;
    top: 50%; 
    transform: translateY(-50%); 
    z-index: 100; 
    width: 32px; 
    height: 64px; 
    background: white; 
    border: 1px solid #e5e7eb; 
    border-left: none; 
    border-radius: 0 12px 12px 0; 
    cursor: pointer; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #64748b; 
    transition: left 0.3s ease, background 0.2s; 
    box-shadow: 2px 0 8px rgba(0,0,0,0.08); 
}
.btn-toggle-sidebar:hover { background: #f8f9fa; color: #10b981; }
.btn-toggle-sidebar.collapsed { left: 0; }
.btn-toggle-sidebar i { transition: transform 0.3s; }
.btn-toggle-sidebar.collapsed i { transform: rotate(180deg); }

        /* Chat Main */
        .chat-main { flex: 1; display: flex; flex-direction: column; background: #f8f9fa; }
        .chat-header { padding: 16px 24px; background: white; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 12px; }
        .chat-header-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; overflow: hidden; }
        .chat-header-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .chat-header-info { flex: 1; }
        .chat-header-info h3 { font-size: 1rem; font-weight: 600; color: #1a202c; margin: 0; }
        .chat-header-info span { font-size: 0.85rem; color: #64748b; }
        .header-actions { display: flex; align-items: center; gap: 10px; }

        /* Session Timer */
        .session-timer { display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 2px solid #86efac; border-radius: 20px; font-weight: 700; font-size: 1rem; color: #16a34a; font-variant-numeric: tabular-nums; }
        .session-timer.warning { background: linear-gradient(135deg, #fef3c7, #fde68a); border-color: #fcd34d; color: #d97706; animation: timerPulse 1s infinite; }
        .session-timer.danger { background: linear-gradient(135deg, #fee2e2, #fecaca); border-color: #f87171; color: #dc2626; animation: timerPulse 0.5s infinite; }
        @keyframes timerPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.02); } }
        .session-timer i { font-size: 1.1rem; }

        .session-badge { padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .session-badge.ongoing { background: #dcfce7; color: #16a34a; }
        .session-badge.pending { background: #fef3c7; color: #d97706; }
        .session-badge.completed { background: #e0e7ff; color: #4f46e5; }
        .session-badge.none { background: #f1f5f9; color: #64748b; }
        .session-badge i { font-size: 0.5rem; }

        .btn-end-session { padding: 8px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; border: 2px solid #ef4444; background: white; color: #ef4444; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .btn-end-session:hover { background: #ef4444; color: white; }

        /* Messages */
        .chat-messages { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 16px; }
        .message-row { display: flex; flex-direction: column; max-width: 70%; position: relative; }
        .message-row.me { align-self: flex-end; }
        .message-row.other { align-self: flex-start; }
        .message-wrapper { position: relative; display: flex; align-items: flex-start; gap: 8px; }
        .message-row.me .message-wrapper { flex-direction: row-reverse; }
        .message-bubble { padding: 12px 16px; border-radius: 16px; position: relative; min-width: 80px; }
        .message-row.me .message-bubble { background: linear-gradient(135deg, #10b981, #059669); color: white; border-bottom-right-radius: 4px; }
        .message-row.other .message-bubble { background: white; color: #1a202c; border-bottom-left-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .message-bubble p { margin: 0; line-height: 1.5; word-wrap: break-word; }
        .message-time { font-size: 0.7rem; opacity: 0.7; margin-top: 4px; display: block; }
        .message-row.me .message-time { text-align: right; }
        .message-edited { font-size: 0.65rem; opacity: 0.6; font-style: italic; margin-left: 6px; }

        .message-actions { display: none; align-items: center; gap: 4px; opacity: 0; transition: opacity 0.2s; }
        .can-send-message .message-wrapper:hover .message-actions { display: flex; opacity: 1; }
        .msg-action-btn { width: 30px; height: 30px; border-radius: 50%; border: none; background: #f1f5f9; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 0.85rem; }
        .msg-action-btn:hover { background: #e2e8f0; color: #1a202c; }
        .msg-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

        .message-file { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: rgba(255,255,255,0.15); border-radius: 10px; margin-top: 8px; text-decoration: none; color: inherit; transition: all 0.2s; }
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
        .chat-input-area { padding: 16px 24px; background: white; border-top: 1px solid #e5e7eb; }
        .chat-input-disabled { padding: 20px 24px; background: #f8f9fa; border-top: 1px solid #e5e7eb; text-align: center; color: #64748b; }
        .chat-input-disabled i { font-size: 1.5rem; margin-bottom: 8px; display: block; }
        .chat-input-disabled.pending i { color: #d97706; }
        .chat-input-disabled.completed i { color: #4f46e5; }
        .chat-input-disabled.no-session i { color: #94a3b8; }
        .chat-input-disabled p { margin: 0; font-size: 0.9rem; }
        .edit-indicator { display: none; align-items: center; gap: 10px; padding: 10px 14px; background: #fef3c7; border-radius: 12px; margin-bottom: 12px; font-size: 0.9rem; color: #92400e; }
        .edit-indicator.show { display: flex; }
        .edit-indicator i { font-size: 1.1rem; }
        .edit-indicator span { flex: 1; }
        .btn-cancel-edit { background: transparent; border: none; color: #92400e; cursor: pointer; padding: 4px 8px; border-radius: 6px; }
        .btn-cancel-edit:hover { background: rgba(0,0,0,0.1); }
        .file-preview { display: none; align-items: center; gap: 10px; padding: 12px 14px; background: #f1f5f9; border-radius: 12px; margin-bottom: 12px; }
        .file-preview.show { display: flex; }
        .file-preview-icon { width: 44px; height: 44px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem; }
        .file-preview-info { flex: 1; }
        .file-preview-name { font-weight: 500; font-size: 0.9rem; }
        .file-preview-size { font-size: 0.8rem; color: #64748b; }
        .btn-remove-file { background: #fee2e2; color: #dc2626; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .upload-progress { display: none; margin-bottom: 12px; }
        .upload-progress.show { display: block; }
        .progress-bar-container { height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
        .progress-bar { height: 100%; background: linear-gradient(135deg, #10b981, #059669); border-radius: 4px; transition: width 0.3s; width: 0%; }
        .progress-text { font-size: 0.8rem; color: #64748b; margin-top: 6px; text-align: center; }
        .input-wrapper { display: flex; align-items: flex-end; gap: 12px; background: #f8f9fa; border-radius: 24px; padding: 8px 8px 8px 20px; border: 2px solid transparent; transition: all 0.2s; }
        .input-wrapper:focus-within { border-color: #10b981; background: white; }
        .input-wrapper textarea { flex: 1; border: none; background: transparent; resize: none; padding: 8px 0; font-size: 0.95rem; line-height: 1.5; max-height: 120px; outline: none; font-family: inherit; }
        .input-actions { display: flex; align-items: center; gap: 4px; }
        .btn-attach, .btn-send { width: 40px; height: 40px; border-radius: 50%; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
        .btn-attach { background: transparent; color: #64748b; }
        .btn-attach:hover { background: #e2e8f0; color: #10b981; }
        .btn-send { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-send:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); }
        .btn-send:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .file-hint { font-size: 0.8rem; color: #94a3b8; margin-top: 8px; text-align: center; }
        .empty-chat { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #64748b; text-align: center; padding: 40px; }
        .empty-chat i { font-size: 4rem; color: #cbd5e1; margin-bottom: 20px; }
        .empty-chat h2 { font-size: 1.25rem; color: #1a202c; margin-bottom: 8px; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: white; border-radius: 20px; padding: 32px; max-width: 420px; width: 90%; text-align: center; }
        .modal-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; }
        .modal-icon.danger { background: #fef2f2; color: #dc2626; }
        .modal-icon.warning { background: #fef3c7; color: #f59e0b; }
        .modal-box h3 { font-size: 1.25rem; font-weight: 700; margin-bottom: 12px; }
        .modal-box p { color: #64748b; margin-bottom: 24px; }
        .modal-actions { display: flex; gap: 12px; justify-content: center; }
        .btn-modal { padding: 12px 24px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; }
        .btn-modal-cancel { background: #f1f5f9; color: #475569; }
        .btn-modal-cancel:hover { background: #e2e8f0; }
        .btn-modal-confirm { background: #ef4444; color: white; }
        .btn-modal-confirm:hover { background: #dc2626; }

        /* Toast */
        .toast-notification { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(100px); background: #1a202c; color: white; padding: 12px 24px; border-radius: 12px; font-size: 0.9rem; z-index: 1001; opacity: 0; transition: all 0.3s; display: flex; align-items: center; gap: 10px; }
        .toast-notification.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast-notification.success { background: #16a34a; }
        .toast-notification.error { background: #dc2626; }

        /* Typing Indicator */
        .typing-indicator { display: none; align-items: center; gap: 8px; padding: 12px 16px; background: white; border-radius: 16px; border-bottom-left-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); max-width: fit-content; margin-bottom: 8px; align-self: flex-start; }
        .typing-indicator.show { display: flex; }
        .typing-dots { display: flex; gap: 4px; }
        .typing-dots span { width: 8px; height: 8px; background: #10b981; border-radius: 50%; animation: typingBounce 1.4s infinite ease-in-out; }
        .typing-dots span:nth-child(1) { animation-delay: 0s; }
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typingBounce { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-6px); } }
        .typing-text { font-size: 0.85rem; color: #64748b; font-style: italic; }

        /* Timer Warning Modal */
        .timer-warning-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1002; align-items: center; justify-content: center; }
        .timer-warning-modal.show { display: flex; }
        .timer-warning-box { background: white; border-radius: 24px; padding: 40px; max-width: 400px; width: 90%; text-align: center; animation: modalBounce 0.3s; }
        @keyframes modalBounce { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        .timer-warning-icon { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2.5rem; }
        .timer-warning-icon.warning { background: #fef3c7; color: #f59e0b; }
        .timer-warning-icon.danger { background: #fee2e2; color: #ef4444; }
        .timer-warning-icon.success { background: #d1fae5; color: #10b981; }
        .timer-warning-box h3 { font-size: 1.5rem; font-weight: 700; margin-bottom: 12px; color: #1a202c; }
        .timer-warning-box p { color: #64748b; margin-bottom: 24px; line-height: 1.6; }
        .timer-warning-box .remaining-time { font-size: 2.5rem; font-weight: 800; color: #ef4444; margin: 20px 0; font-variant-numeric: tabular-nums; }
        .btn-warning-ok { padding: 14px 40px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-warning-ok:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4); }

@media (max-width: 768px) {
    .chat-sidebar { position: absolute; left: 0; top: 0; bottom: 0; z-index: 50; width: 100%; max-width: 100%; }
    .chat-sidebar.collapsed { width: 0; }
    .btn-toggle-sidebar { left: 100%; }
    .btn-toggle-sidebar.collapsed { left: 0; }
    .message-row { max-width: 85%; }
    .session-timer { padding: 6px 12px; font-size: 0.9rem; }
}
    </style>
</head>
<body>

<?php include __DIR__ . '/mentor-navbar.php'; ?>

<div class="chat-container">
    <!-- Toggle Sidebar Button -->
<button type="button" class="btn-toggle-sidebar" id="btnToggleSidebar" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- SIDEBAR -->
    <aside class="chat-sidebar" id="chatSidebar">
        <div class="sidebar-header">
            <h2><i class="bi bi-chat-heart"></i> Chat Mahasiswa</h2>
            <button type="button" class="btn-collapse-sidebar" id="btnCollapseSidebar" title="Tutup Sidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <ul class="conversation-list">
            <?php if (empty($filteredConversations)): ?>
                <div class="empty-conversations">
                    <i class="bi bi-inbox"></i>
                    <p>Belum ada percakapan.<br>Chat akan muncul setelah ada sesi dengan mahasiswa.</p>
                </div>
            <?php else: ?>
                <?php foreach ($filteredConversations as $conv): 
                    $cId = (int)$conv['conversation_id'];
                    $studentInitial = mb_strtoupper(mb_substr($conv['student_name'] ?? 'S', 0, 1, 'UTF-8'), 'UTF-8');
                    $studentAvatarUrl = get_chat_avatar($conv['student_avatar'] ?? '', BASE_PATH);
                    $isActive = ($currentConvId === $cId);
                    
                    $badgeStatus = 'none';
                    if ($conv['session_ended_at']) {
                        $badgeStatus = 'completed';
                    } elseif ($conv['session_status']) {
                        $badgeStatus = $conv['session_status'];
                    }
                ?>
                <li>
                    <a href="?conversation_id=<?= $cId ?>" class="conversation-item <?= $isActive ? 'active' : '' ?>">
                        <div class="conv-avatar">
                            <?php if ($studentAvatarUrl): ?>
                                <img src="<?= htmlspecialchars($studentAvatarUrl) ?>" alt="" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <?= htmlspecialchars($studentInitial) ?>
                            <?php endif; ?>
                        </div>
                        <div class="conv-info">
                            <div class="conv-name"><?= htmlspecialchars($conv['student_name']) ?></div>
                            <div class="conv-sub">
                                <?= htmlspecialchars($conv['student_prodi'] ?? 'Mahasiswa') ?>
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
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </aside>

    <!-- MAIN -->
    <main class="chat-main">
        <?php if (!$currentConvId || !$currentConv): ?>
            <div class="empty-chat">
                <i class="bi bi-chat-dots"></i>
                <h2>Pilih Percakapan</h2>
                <p>Pilih mahasiswa di sebelah kiri untuk mulai chat.</p>
            </div>
        <?php else: ?>
            <?php 
            $studentInitial = mb_strtoupper(mb_substr($currentConv['student_name'], 0, 1, 'UTF-8'), 'UTF-8');
            $headerAvatarUrl = get_chat_avatar($currentConv['student_avatar'] ?? '', BASE_PATH);
            $sessionStatus = $currentSession['status'] ?? '';
            ?>
            <div class="chat-header">
                <div class="chat-header-avatar">
                    <?php if ($headerAvatarUrl): ?>
                        <img src="<?= htmlspecialchars($headerAvatarUrl) ?>" alt="" referrerpolicy="no-referrer">
                    <?php else: ?>
                        <?= htmlspecialchars($studentInitial) ?>
                    <?php endif; ?>
                </div>
                <div class="chat-header-info">
                    <h3><?= htmlspecialchars($currentConv['student_name']) ?></h3>
                    <span><?= htmlspecialchars($currentConv['student_prodi'] ?? 'Mahasiswa') ?></span>
                </div>
                <div class="header-actions" id="headerActions">
                    <?php if ($sessionStatus === 'ongoing'): ?>
                        <div class="session-timer" id="sessionTimer" 
                             data-session-id="<?= (int)$currentSession['id'] ?>"
                             data-started-at="<?= htmlspecialchars($currentSession['started_at'] ?? '') ?>"
                             data-duration="<?= (int)($currentSession['duration'] ?? 60) ?>">
                            <i class="bi bi-clock"></i>
                            <span id="timerDisplay">--:--</span>
                        </div>
                        <span class="session-badge ongoing" id="sessionBadge"><i class="bi bi-circle-fill"></i> Sesi Aktif</span>
                        <?php if ($currentSession['id']): ?>
                            <button type="button" class="btn-end-session" id="btnEndSession" data-session-id="<?= (int)$currentSession['id'] ?>">
                                <i class="bi bi-stop-circle"></i> Akhiri
                            </button>
                        <?php endif; ?>
                    <?php elseif ($sessionStatus === 'pending'): ?>
                        <span class="session-badge pending" id="sessionBadge"><i class="bi bi-circle-fill"></i> Menunggu Konfirmasi</span>
                    <?php elseif ($sessionStatus === 'completed'): ?>
                        <span class="session-badge completed" id="sessionBadge"><i class="bi bi-circle-fill"></i> Sesi Selesai</span>
                    <?php else: ?>
                        <span class="session-badge none" id="sessionBadge"><i class="bi bi-circle-fill"></i> Tidak Ada Sesi</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-messages <?= $canSendMessage ? 'can-send-message' : '' ?>" id="chatMessages" 
                 data-conversation="<?= $currentConvId ?>" data-can-send="<?= $canSendMessage ? '1' : '0' ?>">
                <?php if (empty($currentMsgs)): ?>
                    <div class="empty-chat" style="padding: 60px 20px;">
                        <i class="bi bi-chat-text" style="font-size: 3rem;"></i>
                        <p>Belum ada pesan. <?= $canSendMessage ? 'Mulai percakapan!' : '' ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($currentMsgs as $msg): 
                        $isMe = ($msg['sender_id'] == $mentor_id);
                        $isVideo = !empty($msg['file_path']) && preg_match('/\.(mp4|webm|mov)$/i', $msg['file_path']);
                        $isEdited = !empty($msg['edited_at']);
                        $hasText = !empty($msg['message']);
                    ?>
                        <div class="message-row <?= $isMe ? 'me' : 'other' ?>" data-message-id="<?= $msg['id'] ?>" data-edited="<?= $isEdited ? '1' : '0' ?>">
                            <div class="message-wrapper">
                                <div class="message-actions">
                                    <?php if ($isMe): ?>
                                        <button type="button" class="msg-action-btn edit" title="Edit" data-message-id="<?= $msg['id'] ?>" data-message-text="<?= htmlspecialchars($msg['message'] ?? '', ENT_QUOTES) ?>"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="msg-action-btn delete" title="Hapus" data-message-id="<?= $msg['id'] ?>"><i class="bi bi-trash"></i></button>
                                    <?php else: ?>
                                        <button type="button" class="msg-action-btn copy" title="Salin" data-message-text="<?= htmlspecialchars($msg['message'] ?? '', ENT_QUOTES) ?>"><i class="bi bi-clipboard"></i></button>
                                    <?php endif; ?>
                                </div>
                                <div class="message-bubble">
                                    <?php if ($hasText): ?>
                                        <p class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
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
                                        <?php if ($isEdited): ?><span class="message-edited">(diedit)</span><?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="typing-indicator" id="typingIndicator">
                    <div class="typing-dots"><span></span><span></span><span></span></div>
                    <span class="typing-text" id="typingText">sedang mengetik...</span>
                </div>
            </div>

            <!-- Input Area -->
            <?php if ($canSendMessage): ?>
                <div class="chat-input-area" id="chatInputArea">
                    <div class="edit-indicator" id="editIndicator">
                        <i class="bi bi-pencil"></i>
                        <span>Mengedit pesan</span>
                        <button type="button" class="btn-cancel-edit" id="btnCancelEdit"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="file-preview" id="filePreview">
                        <div class="file-preview-icon"><i class="bi bi-file-earmark"></i></div>
                        <div class="file-preview-info">
                            <div class="file-preview-name" id="filePreviewName"></div>
                            <div class="file-preview-size" id="filePreviewSize"></div>
                        </div>
                        <button type="button" class="btn-remove-file" id="btnRemoveFile"><i class="bi bi-x"></i></button>
                    </div>
                    <div class="upload-progress" id="uploadProgress">
                        <div class="progress-bar-container"><div class="progress-bar" id="progressBar"></div></div>
                        <div class="progress-text" id="progressText">Mengupload...</div>
                    </div>
                    <form id="chatForm" enctype="multipart/form-data">
                        <input type="hidden" name="conversation_id" value="<?= $currentConvId ?>">
                        <input type="hidden" name="edit_message_id" id="editMessageId" value="0">
                        <div class="input-wrapper">
                            <textarea name="message" id="messageInput" placeholder="Ketik pesan..." rows="1"></textarea>
                            <div class="input-actions">
                                <input type="file" name="attachment" id="fileInput" hidden accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">

                                <button type="button" class="btn-attach" id="btnAttach" title="Lampirkan file"><i class="bi bi-paperclip"></i></button>
                                <button type="submit" class="btn-send" id="btnSend" title="Kirim"><i class="bi bi-send-fill"></i></button>
                            </div>
                        </div>
                    </form>
                    <div class="file-hint">Maks 300MB untuk video, 5MB untuk file lain</div>
                </div>
            <?php else: ?>
                <div class="chat-input-disabled <?= $sessionStatus ?>" id="chatInputArea">
                    <?php if ($sessionStatus === 'pending'): ?>
                        <i class="bi bi-hourglass-split"></i>
                        <p>Menunggu sesi dimulai. Chat akan aktif setelah sesi berjalan.</p>
                    <?php elseif ($sessionStatus === 'completed'): ?>
                        <i class="bi bi-check-circle"></i>
                        <p>Sesi telah selesai. Chat tidak dapat dilanjutkan.</p>
                    <?php else: ?>
                        <i class="bi bi-lock"></i>
                        <p>Tidak ada sesi aktif. Chat hanya bisa dilakukan saat sesi berlangsung.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<!-- Modal End Session -->
<div class="modal-overlay" id="modalEndSession">
    <div class="modal-box">
        <div class="modal-icon danger"><i class="bi bi-stop-circle"></i></div>
        <h3>Akhiri Sesi?</h3>
        <p>Sesi mentoring akan diakhiri dan mahasiswa tidak dapat melanjutkan chat.</p>
        <div class="modal-actions">
            <button type="button" class="btn-modal btn-modal-cancel" id="btnCancelEnd">Batal</button>
            <button type="button" class="btn-modal btn-modal-confirm" id="btnConfirmEnd">Ya, Akhiri</button>
        </div>
    </div>
</div>

<!-- Modal Delete Message -->
<div class="modal-overlay" id="modalDeleteMsg">
    <div class="modal-box">
        <div class="modal-icon warning"><i class="bi bi-trash"></i></div>
        <h3>Hapus Pesan?</h3>
        <p>Pesan akan dihapus secara permanen.</p>
        <div class="modal-actions">
            <button type="button" class="btn-modal btn-modal-cancel" id="btnCancelDelete">Batal</button>
            <button type="button" class="btn-modal btn-modal-confirm" id="btnConfirmDelete">Ya, Hapus</button>
        </div>
    </div>
</div>

<!-- Timer Warning Modal -->
<div class="timer-warning-modal" id="timerWarningModal">
    <div class="timer-warning-box">
        <div class="timer-warning-icon warning" id="warningIcon"><i class="bi bi-clock-history"></i></div>
        <h3 id="warningTitle">Waktu Hampir Habis!</h3>
        <p id="warningText">Sesi mentoring akan berakhir.</p>
        <div class="remaining-time" id="warningTime">05:00</div>
        <button type="button" class="btn-warning-ok" id="btnWarningOk">Mengerti</button>
    </div>
</div>

<!-- Toast -->
<div class="toast-notification" id="toast"><i class="bi bi-check-circle"></i><span id="toastText"></span></div>

<script>
(function() {
    const BASE_PATH = '<?= BASE_PATH ?>';
    const POLL_INTERVAL = 2000;
    const TYPING_POLL_INTERVAL = 1500;
    const SESSION_CHECK_INTERVAL = 5000;
    const MAX_FILE_SIZE = <?= $maxFileSize ?>;

    // Elements
    const chatMessages = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const fileInput = document.getElementById('fileInput');
    const btnAttach = document.getElementById('btnAttach');
    const btnSend = document.getElementById('btnSend');
    const filePreview = document.getElementById('filePreview');
    const filePreviewName = document.getElementById('filePreviewName');
    const filePreviewSize = document.getElementById('filePreviewSize');
    const btnRemoveFile = document.getElementById('btnRemoveFile');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const editIndicator = document.getElementById('editIndicator');
    const editMessageId = document.getElementById('editMessageId');
    const btnCancelEdit = document.getElementById('btnCancelEdit');
    const chatSidebar = document.getElementById('chatSidebar');
    const btnToggleSidebar = document.getElementById('btnToggleSidebar');
    const btnCollapseSidebar = document.getElementById('btnCollapseSidebar');
    const sessionTimer = document.getElementById('sessionTimer');
    const timerDisplay = document.getElementById('timerDisplay');
    const btnEndSession = document.getElementById('btnEndSession');
    const modalEndSession = document.getElementById('modalEndSession');
    const btnCancelEnd = document.getElementById('btnCancelEnd');
    const btnConfirmEnd = document.getElementById('btnConfirmEnd');
    const modalDeleteMsg = document.getElementById('modalDeleteMsg');
    const btnCancelDelete = document.getElementById('btnCancelDelete');
    const btnConfirmDelete = document.getElementById('btnConfirmDelete');
    const timerWarningModal = document.getElementById('timerWarningModal');
    const warningIcon = document.getElementById('warningIcon');
    const warningTitle = document.getElementById('warningTitle');
    const warningText = document.getElementById('warningText');
    const warningTime = document.getElementById('warningTime');
    const btnWarningOk = document.getElementById('btnWarningOk');
    const typingIndicator = document.getElementById('typingIndicator');
    const typingText = document.getElementById('typingText');
    const toast = document.getElementById('toast');
    const toastText = document.getElementById('toastText');
    const chatInputArea = document.getElementById('chatInputArea');

    // State
    const currentConversationId = chatMessages ? parseInt(chatMessages.dataset.conversation || 0) : 0;
    const currentSessionId = sessionTimer ? parseInt(sessionTimer.dataset.sessionId || 0) : 0;
    let lastMessageId = 0;
    let sessionEnded = false;
    let pollTimer = null;
    let typingPollTimer = null;
    let sessionCheckTimer = null;
    let timerInterval = null;
    let globalEndTime = 0;
    let warned5min = false;
    let warned1min = false;
    let warningModalInterval = null;
    let isTyping = false;
    let typingTimeout = null;
    let deleteMessageId = null;
    let endSessionId = null;

    // ===== UTILITIES =====
    function showToast(message, type = 'success') {
        toastText.textContent = message;
        toast.className = 'toast-notification ' + type + ' show';
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function formatTime(ms) {
        const totalSeconds = Math.floor(ms / 1000);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function scrollToBottom() {
        if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // ===== SIDEBAR TOGGLE =====
function toggleSidebar() {
    if (!chatSidebar || !btnToggleSidebar) return;
    
    if (chatSidebar.classList.contains('collapsed')) {
        chatSidebar.classList.remove('collapsed');
        btnToggleSidebar.classList.remove('collapsed');
    } else {
        chatSidebar.classList.add('collapsed');
        btnToggleSidebar.classList.add('collapsed');
    }
}

if (btnToggleSidebar) {
    btnToggleSidebar.addEventListener('click', toggleSidebar);
}

if (btnCollapseSidebar) {
    btnCollapseSidebar.addEventListener('click', () => {
        if (!chatSidebar || !btnToggleSidebar) return;
        chatSidebar.classList.add('collapsed');
        btnToggleSidebar.classList.add('collapsed');
    });
}


    // ===== FILE HANDLING =====
    if (btnAttach) btnAttach.addEventListener('click', () => fileInput.click());

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (file) {
                if (file.size > MAX_FILE_SIZE) {
                    showToast(`File terlalu besar. Max ${formatFileSize(MAX_FILE_SIZE)}`, 'error');
                    fileInput.value = '';
                    return;
                }
                filePreviewName.textContent = file.name;
                filePreviewSize.textContent = formatFileSize(file.size);
                filePreview.classList.add('show');
            }
        });
    }

    if (btnRemoveFile) {
        btnRemoveFile.addEventListener('click', () => {
            fileInput.value = '';
            filePreview.classList.remove('show');
        });
    }

    function clearFilePreview() {
        fileInput.value = '';
        filePreview.classList.remove('show');
    }

    // ===== EDIT MESSAGE =====
    function startEdit(msgId, msgText) {
        editMessageId.value = msgId;
        messageInput.value = msgText;
        messageInput.focus();
        editIndicator.classList.add('show');
    }

    function cancelEdit() {
        editMessageId.value = '0';
        messageInput.value = '';
        editIndicator.classList.remove('show');
    }

    if (btnCancelEdit) btnCancelEdit.addEventListener('click', cancelEdit);

    // ===== MESSAGE ACTIONS (Edit, Delete, Copy) =====
    if (chatMessages) {
        chatMessages.addEventListener('click', (e) => {
            const btn = e.target.closest('.msg-action-btn');
            if (!btn) return;

            const msgId = btn.dataset.messageId;
            const msgText = btn.dataset.messageText || '';

            if (btn.classList.contains('edit')) {
                startEdit(msgId, msgText);
            } else if (btn.classList.contains('delete')) {
                deleteMessageId = msgId;
                modalDeleteMsg.classList.add('show');
            } else if (btn.classList.contains('copy')) {
                navigator.clipboard.writeText(msgText).then(() => showToast('Teks disalin'));
            }
        });
    }

    // ===== DELETE MESSAGE =====
    if (btnCancelDelete) btnCancelDelete.addEventListener('click', () => {
        deleteMessageId = null;
        modalDeleteMsg.classList.remove('show');
    });

    if (btnConfirmDelete) {
        btnConfirmDelete.addEventListener('click', async () => {
            if (!deleteMessageId) return;

            try {
                const res = await fetch(`${BASE_PATH}/api-message-delete.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message_id: parseInt(deleteMessageId), conversation_id: currentConversationId })
                });
                const data = await res.json();

                if (data.success) {
                    const row = chatMessages.querySelector(`.message-row[data-message-id="${deleteMessageId}"]`);
                    if (row) row.remove();
                    showToast('Pesan dihapus');
                } else {
                    showToast(data.error || 'Gagal menghapus', 'error');
                }
            } catch (err) {
                showToast('Gagal menghapus pesan', 'error');
            }

            deleteMessageId = null;
            modalDeleteMsg.classList.remove('show');
        });
    }

    // ===== TYPING INDICATOR =====
    async function sendTypingStatus(typing) {
        if (!currentConversationId || sessionEnded) return;
        try {
            await fetch(`${BASE_PATH}/api-typing-status.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conversation_id: currentConversationId, is_typing: typing })
            });
        } catch (e) {}
    }

    async function pollTypingStatus() {
        if (!currentConversationId || sessionEnded) return;
        try {
            const res = await fetch(`${BASE_PATH}/api-typing-status.php?conversation_id=${currentConversationId}`);
            const data = await res.json();
            if (data.success && typingIndicator) {
                if (data.is_typing) {
                    typingIndicator.classList.add('show');
                    if (typingText && data.typer_name) {
                        typingText.textContent = `${data.typer_name} sedang mengetik...`;
                    }
                    scrollToBottom();
                } else {
                    typingIndicator.classList.remove('show');
                }
            }
        } catch (e) {
            if (typingIndicator) typingIndicator.classList.remove('show');
        }
    }

    if (messageInput) {
        messageInput.addEventListener('input', () => {
            if (!isTyping) {
                isTyping = true;
                sendTypingStatus(true);
            }
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => {
                isTyping = false;
                sendTypingStatus(false);
            }, 3000);
        });

        // Enter to send, Shift+Enter for newline
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });
    }

    // ===== POLLING MESSAGES (FIXED - v4.4 style) =====
    async function pollNewMessages() {
        if (!currentConversationId || sessionEnded) return;

        try {
            const url = `${BASE_PATH}/api-chat-messages.php?conversation_id=${currentConversationId}&last_id=${lastMessageId}&include_updated=1`;
            const res = await fetch(url);
            const data = await res.json();

            if (!data.success) return;

            // FIXED: Handle deleted messages by querying DOM directly (v4.4 approach)
            if (data.existing_ids && Array.isArray(data.existing_ids)) {
                handleDeletedMessages(data.existing_ids);
            }

            // Process new/updated messages
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    const existingRow = chatMessages.querySelector(`.message-row[data-message-id="${msg.id}"]`);

                    if (existingRow) {
                        // Update edited message
                        if (msg.is_edited) {
                            updateExistingMessage(existingRow, msg);
                        }
                    } else if (!msg.is_mine) {
                        // New message from other party
                        appendReceivedMessage(msg);
                    }
                });

                if (data.last_id > lastMessageId) {
                    lastMessageId = data.last_id;
                }
            }
        } catch (err) {
            console.error('Polling error:', err);
        }
    }

    // FIXED: Query DOM directly instead of using knownMessageIds Set
    function handleDeletedMessages(existingIds) {
        if (!chatMessages) return;
        chatMessages.querySelectorAll('.message-row[data-message-id]').forEach(row => {
            const msgId = parseInt(row.dataset.messageId);
            if (!existingIds.includes(msgId)) {
                row.remove();
            }
        });
    }

    function updateExistingMessage(row, msg) {
        const bubble = row.querySelector('.message-bubble');
        if (!bubble) return;

        let textEl = bubble.querySelector('.message-text');
        if (msg.message) {
            if (textEl) {
                textEl.innerHTML = msg.message.replace(/\n/g, '<br>');
            } else {
                textEl = document.createElement('p');
                textEl.className = 'message-text';
                textEl.innerHTML = msg.message.replace(/\n/g, '<br>');
                bubble.insertBefore(textEl, bubble.firstChild);
            }
        }

        const timeSpan = bubble.querySelector('.message-time');
        if (timeSpan && !timeSpan.querySelector('.message-edited')) {
            timeSpan.innerHTML += ' <span class="message-edited">(diedit)</span>';
        }
        row.dataset.edited = '1';

        // Update edit button data
        const editBtn = row.querySelector('.msg-action-btn.edit');
        if (editBtn) editBtn.dataset.messageText = msg.message || '';
    }

    function appendReceivedMessage(msg) {
        if (!chatMessages) return;

        const emptyChat = chatMessages.querySelector('.empty-chat');
        if (emptyChat) emptyChat.remove();

        const row = document.createElement('div');
        row.className = 'message-row other';
        row.dataset.messageId = msg.id;
        row.dataset.edited = msg.is_edited ? '1' : '0';

        const isVideo = msg.file_path && /\.(mp4|webm|mov)$/i.test(msg.file_path);

        let fileHtml = '';
        if (msg.file_path) {
            if (isVideo) {
                fileHtml = `
                    <div class="message-video-player">
                        <video controls preload="metadata">
                            <source src="${BASE_PATH}/${msg.file_path}" type="video/mp4">
                        </video>
                    </div>
                    <a href="${BASE_PATH}/${msg.file_path}" target="_blank" class="message-file" download>
                        <i class="bi bi-file-earmark-play"></i>
                        <div class="file-info">
                            <div class="file-name">${msg.file_name || 'Video'}</div>
                            <div class="file-size">${((msg.file_size || 0) / (1024*1024)).toFixed(1)} MB</div>
                        </div>
                        <i class="bi bi-download"></i>
                    </a>`;
            } else {
                fileHtml = `
                    <a href="${BASE_PATH}/${msg.file_path}" target="_blank" class="message-file">
                        <i class="bi bi-file-earmark"></i>
                        <div class="file-info">
                            <div class="file-name">${msg.file_name || 'File'}</div>
                            <div class="file-size">${((msg.file_size || 0) / 1024).toFixed(1)} KB</div>
                        </div>
                        <i class="bi bi-download"></i>
                    </a>`;
            }
        }

        row.innerHTML = `
            <div class="message-wrapper">
                <div class="message-actions">
                    <button type="button" class="msg-action-btn copy" title="Salin" data-message-text="${(msg.message || '').replace(/"/g, '&quot;')}"><i class="bi bi-clipboard"></i></button>
                </div>
                <div class="message-bubble">
                    ${msg.message ? `<p class="message-text">${msg.message.replace(/\n/g, '<br>')}</p>` : ''}
                    ${fileHtml}
                    <span class="message-time">${msg.time}${msg.is_edited ? ' <span class="message-edited">(diedit)</span>' : ''}</span>
                </div>
            </div>`;

        chatMessages.insertBefore(row, typingIndicator);
        scrollToBottom();
    }

    function appendSentMessage(msg) {
        if (!chatMessages) return;

        const emptyChat = chatMessages.querySelector('.empty-chat');
        if (emptyChat) emptyChat.remove();

        const row = document.createElement('div');
        row.className = 'message-row me';
        row.dataset.messageId = msg.id;
        row.dataset.edited = '0';

        const isVideo = msg.file_path && /\.(mp4|webm|mov)$/i.test(msg.file_path);

        let fileHtml = '';
        if (msg.file_path) {
            if (isVideo) {
                fileHtml = `
                    <div class="message-video-player">
                        <video controls preload="metadata">
                            <source src="${BASE_PATH}/${msg.file_path}" type="video/mp4">
                        </video>
                    </div>
                    <a href="${BASE_PATH}/${msg.file_path}" target="_blank" class="message-file" download>
                        <i class="bi bi-file-earmark-play"></i>
                        <div class="file-info">
                            <div class="file-name">${msg.file_name || 'Video'}</div>
                            <div class="file-size">${((msg.file_size || 0) / (1024*1024)).toFixed(1)} MB</div>
                        </div>
                        <i class="bi bi-download"></i>
                    </a>`;
            } else {
                fileHtml = `
                    <a href="${BASE_PATH}/${msg.file_path}" target="_blank" class="message-file">
                        <i class="bi bi-file-earmark"></i>
                        <div class="file-info">
                            <div class="file-name">${msg.file_name || 'File'}</div>
                            <div class="file-size">${((msg.file_size || 0) / 1024).toFixed(1)} KB</div>
                        </div>
                        <i class="bi bi-download"></i>
                    </a>`;
            }
        }

        row.innerHTML = `
            <div class="message-wrapper">
                <div class="message-actions">
                    <button type="button" class="msg-action-btn edit" title="Edit" data-message-id="${msg.id}" data-message-text="${(msg.message || '').replace(/"/g, '&quot;')}"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="msg-action-btn delete" title="Hapus" data-message-id="${msg.id}"><i class="bi bi-trash"></i></button>
                </div>
                <div class="message-bubble">
                    ${msg.message ? `<p class="message-text">${msg.message.replace(/\n/g, '<br>')}</p>` : ''}
                    ${fileHtml}
                    <span class="message-time">${msg.time}</span>
                </div>
            </div>`;

        chatMessages.insertBefore(row, typingIndicator);
        scrollToBottom();
    }

    // ===== SESSION STATUS POLLING (NEW in v4.5, kept) =====
    async function checkSessionStatus() {
        if (!currentSessionId || sessionEnded) return;
        try {
            const res = await fetch(`${BASE_PATH}/api-session-timer.php?session_id=${currentSessionId}`);
            const data = await res.json();

            if (data.success) {
                if (data.status === 'completed' || data.is_expired) {
                    handleSessionEnded('Sesi telah berakhir');
                }
            }
        } catch (e) {
            console.error('Session check error:', e);
        }
    }

    function handleSessionEnded(message) {
        if (sessionEnded) return;
        sessionEnded = true;
        
        stopPolling();
        if (timerInterval) clearInterval(timerInterval);

        if (sessionTimer) {
            sessionTimer.classList.remove('warning');
            sessionTimer.classList.add('danger');
            if (timerDisplay) timerDisplay.textContent = '00:00';
        }

        const sessionBadge = document.getElementById('sessionBadge');
        if (sessionBadge) {
            sessionBadge.className = 'session-badge completed';
            sessionBadge.innerHTML = '<i class="bi bi-circle-fill"></i> Sesi Selesai';
        }

        if (btnEndSession) btnEndSession.style.display = 'none';
        if (chatMessages) chatMessages.classList.remove('can-send-message');
        
        if (chatInputArea) {
            chatInputArea.className = 'chat-input-disabled completed';
            chatInputArea.innerHTML = `
                <i class="bi bi-check-circle"></i>
                <p>Sesi telah selesai. Chat tidak dapat dilanjutkan.</p>
            `;
        }

        showTimerWarning('success', 'Sesi Berakhir', message, 0);
        warningIcon.className = 'timer-warning-icon success';
        warningIcon.innerHTML = '<i class="bi bi-check-circle"></i>';
        warningTime.textContent = 'Selesai';

        setTimeout(() => location.reload(), 3000);
    }

    // ===== POLLING INIT =====
    function initPolling() {
        if (!chatMessages || !currentConversationId) return;

        // Init lastMessageId from existing messages
        chatMessages.querySelectorAll('.message-row[data-message-id]').forEach(row => {
            const id = parseInt(row.dataset.messageId || 0);
            if (id > lastMessageId) lastMessageId = id;
        });

        startPolling();
    }

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollNewMessages, POLL_INTERVAL);

        if (typingPollTimer) clearInterval(typingPollTimer);
        typingPollTimer = setInterval(pollTypingStatus, TYPING_POLL_INTERVAL);

        // Session status polling (new in v4.5)
        if (currentSessionId && !sessionEnded) {
            if (sessionCheckTimer) clearInterval(sessionCheckTimer);
            sessionCheckTimer = setInterval(checkSessionStatus, SESSION_CHECK_INTERVAL);
        }
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        if (typingPollTimer) { clearInterval(typingPollTimer); typingPollTimer = null; }
        if (sessionCheckTimer) { clearInterval(sessionCheckTimer); sessionCheckTimer = null; }
    }

    // ===== SEND MESSAGE =====
    if (chatForm) {
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const message = messageInput.value.trim();
            const file = fileInput.files[0];
            const isEditMode = editMessageId.value !== '0';

            if (!message && !file && !isEditMode) return;
            if (isEditMode && !message) {
                showToast('Pesan tidak boleh kosong', 'error');
                return;
            }

            if (isTyping) {
                isTyping = false;
                sendTypingStatus(false);
            }

            btnSend.disabled = true;
            const formData = new FormData(chatForm);

            try {
                if (file) uploadProgress.classList.add('show');

                const xhr = new XMLHttpRequest();
                xhr.open('POST', `${BASE_PATH}/mentor-chat-send.php`, true);

                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const pct = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = pct + '%';
                        progressText.textContent = `Mengupload... ${pct}%`;
                    }
                };

                xhr.onload = function() {
                    uploadProgress.classList.remove('show');
                    progressBar.style.width = '0%';
                    btnSend.disabled = false;

                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            if (data.edited) {
                                const row = chatMessages.querySelector(`.message-row[data-message-id="${data.message.id}"]`);
                                if (row) {
                                    const bubble = row.querySelector('.message-bubble');
                                    let textEl = bubble.querySelector('.message-text');
                                    if (data.message.message) {
                                        if (textEl) {
                                            textEl.innerHTML = data.message.message.replace(/\n/g, '<br>');
                                        } else {
                                            textEl = document.createElement('p');
                                            textEl.className = 'message-text';
                                            textEl.innerHTML = data.message.message.replace(/\n/g, '<br>');
                                            bubble.insertBefore(textEl, bubble.firstChild);
                                        }
                                    }
                                    const timeSpan = bubble.querySelector('.message-time');
                                    if (timeSpan && !timeSpan.querySelector('.message-edited')) {
                                        timeSpan.innerHTML += ' <span class="message-edited">(diedit)</span>';
                                    }
                                    row.dataset.edited = '1';
                                    const editBtn = row.querySelector('.msg-action-btn.edit');
                                    if (editBtn) editBtn.dataset.messageText = data.message.message || '';
                                }
                                cancelEdit();
                            } else {
                                appendSentMessage(data.message);
                                if (data.message.id > lastMessageId) {
                                    lastMessageId = data.message.id;
                                }
                            }
                            messageInput.value = '';
                            messageInput.style.height = 'auto';
                            clearFilePreview();
                        } else {
                            showToast(data.error || 'Gagal mengirim', 'error');
                        }
                    } catch (err) {
                        showToast('Gagal memproses respons', 'error');
                    }
                };

                xhr.onerror = function() {
                    uploadProgress.classList.remove('show');
                    btnSend.disabled = false;
                    showToast('Gagal mengirim pesan', 'error');
                };

                xhr.send(formData);
            } catch (err) {
                uploadProgress.classList.remove('show');
                btnSend.disabled = false;
                showToast('Terjadi kesalahan', 'error');
            }
        });
    }

    // ===== END SESSION =====
    if (btnEndSession) {
        btnEndSession.addEventListener('click', () => {
            endSessionId = btnEndSession.dataset.sessionId;
            modalEndSession.classList.add('show');
        });
    }

    if (btnCancelEnd) {
        btnCancelEnd.addEventListener('click', () => {
            endSessionId = null;
            modalEndSession.classList.remove('show');
        });
    }

    if (btnConfirmEnd) {
        btnConfirmEnd.addEventListener('click', async () => {
            if (!endSessionId) return;

            try {
                const res = await fetch(`${BASE_PATH}/api-session-end.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_id: parseInt(endSessionId) })
                });
                const data = await res.json();

                if (data.success) {
                    handleSessionEnded('Sesi berhasil diakhiri');
                } else {
                    showToast(data.error || 'Gagal mengakhiri sesi', 'error');
                }
            } catch (err) {
                showToast('Gagal mengakhiri sesi', 'error');
            }

            endSessionId = null;
            modalEndSession.classList.remove('show');
        });
    }

    // ===== SESSION TIMER =====
    function initTimer() {
        if (!sessionTimer || !timerDisplay) return;

        const startedAt = sessionTimer.dataset.startedAt;
        const duration = parseInt(sessionTimer.dataset.duration) || 60;

        if (!startedAt) return;

        const startTime = new Date(startedAt).getTime();
        const durationMs = duration * 60 * 1000;
        globalEndTime = startTime + durationMs;

        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);
    }

    function updateTimer() {
        const now = Date.now();
        const remaining = globalEndTime - now;

        if (remaining <= 0) {
            timerDisplay.textContent = '00:00';
            sessionTimer.classList.remove('warning');
            sessionTimer.classList.add('danger');
            clearInterval(timerInterval);
            
            if (!sessionEnded) {
                autoEndSession();
            }
            return;
        }

        timerDisplay.textContent = formatTime(remaining);

        if (remaining <= 60000) {
            sessionTimer.classList.remove('warning');
            sessionTimer.classList.add('danger');
            
            if (!warned1min) {
                warned1min = true;
                showTimerWarning('danger', 'Waktu Hampir Habis!', 'Sesi akan berakhir dalam kurang dari 1 menit.', remaining);
            }
        } else if (remaining <= 300000) {
            sessionTimer.classList.add('warning');
            sessionTimer.classList.remove('danger');
            
            if (!warned5min) {
                warned5min = true;
                showTimerWarning('warning', '5 Menit Lagi!', 'Sesi mentoring akan berakhir dalam 5 menit.', remaining);
            }
        }
    }

    function showTimerWarning(type, title, text, remaining) {
        if (!timerWarningModal) return;

        warningIcon.className = 'timer-warning-icon ' + type;
        warningIcon.innerHTML = type === 'danger' ? '<i class="bi bi-exclamation-triangle"></i>' : '<i class="bi bi-clock-history"></i>';
        warningTitle.textContent = title;
        warningText.textContent = text;
        warningTime.textContent = formatTime(remaining);

        timerWarningModal.classList.add('show');

        if (warningModalInterval) clearInterval(warningModalInterval);
        warningModalInterval = setInterval(() => {
            const now = Date.now();
            const rem = globalEndTime - now;
            if (rem > 0) {
                warningTime.textContent = formatTime(rem);
            } else {
                warningTime.textContent = '00:00';
                clearInterval(warningModalInterval);
            }
        }, 1000);
    }

    if (btnWarningOk) {
        btnWarningOk.addEventListener('click', () => {
            timerWarningModal.classList.remove('show');
            if (warningModalInterval) {
                clearInterval(warningModalInterval);
                warningModalInterval = null;
            }
        });
    }

    async function autoEndSession() {
        if (sessionEnded || !currentSessionId) return;
        sessionEnded = true;
        
        try {
            const res = await fetch(`${BASE_PATH}/api-session-end.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: currentSessionId })
            });
            const data = await res.json();
            
            if (data.success) {
                stopPolling();
                showTimerWarning('success', 'Sesi Berakhir', 'Waktu mentoring telah habis. Sesi telah otomatis diakhiri.', 0);
                warningIcon.className = 'timer-warning-icon success';
                warningIcon.innerHTML = '<i class="bi bi-check-circle"></i>';
                warningTime.textContent = 'Selesai';
                
                setTimeout(() => location.reload(), 3000);
            }
        } catch (err) {
            console.error('Auto-end session error:', err);
        }
    }

    // ===== INIT =====
    scrollToBottom();
    initPolling();
    initTimer();

    window.addEventListener('beforeunload', () => {
        if (isTyping) sendTypingStatus(false);
    });

})();
</script>
</body>
</html>
