<?php
// student-chat.php v4.7 - COPY BUTTON FOR MENTOR MESSAGES
// Fix 1-14: Sama seperti v4.6
// Fix 15: NEW - Tombol copy dengan cursor pointer untuk pesan mentor

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

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

function get_chat_avatar_url($avatar, $base) {
    if (empty($avatar)) return '';
    if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
    return $base . '/' . ltrim($avatar, '/');
}

$stmt = $pdo->prepare("
    SELECT 
        c.id AS conversation_id, c.updated_at, c.mentor_id, c.session_id,
        u.name AS mentor_name, u.specialization AS mentor_spec, u.avatar AS mentor_avatar,
        s.id AS session_id, s.status AS session_status, s.ended_at AS session_ended_at,
        s.started_at AS session_started_at, s.duration AS session_duration, s.created_at AS session_created,
        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread_count,
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

$filteredConversations = array_filter($conversations, function($conv) {
    $isCancelled = $conv['session_status'] === 'cancelled';
    $isEmpty = (int)$conv['message_count'] === 0;
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
            'ended_at' => $currentConv['session_ended_at'],
            'started_at' => $currentConv['session_started_at'],
            'duration' => $currentConv['session_duration']
        ];

        $canChat = ($actualStatus === 'ongoing') && empty($currentConv['session_ended_at']);
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

        .sidebar-header { padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; }
        .sidebar-header h2 { font-size: 1.25rem; font-weight: 700; color: #1a202c; margin: 0; display: flex; align-items: center; gap: 8px; }
        .btn-collapse-sidebar { background: transparent; border: none; color: #64748b; cursor: pointer; padding: 8px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .btn-collapse-sidebar:hover { background: #f1f5f9; color: #1a202c; }
        .conversation-list { flex: 1; overflow-y: auto; padding: 12px; }
        .conversation-item { display: flex; align-items: center; gap: 12px; padding: 14px; border-radius: 12px; text-decoration: none; color: inherit; transition: all 0.2s; margin-bottom: 4px; }
        .conversation-item:hover { background: #f8f9fa; }
        .conversation-item.active { background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border: 1px solid rgba(102, 126, 234, 0.2); }
        .conv-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.1rem; flex-shrink: 0; overflow: hidden; }
        .conv-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .conv-info { flex: 1; min-width: 0; }
        .conv-name { font-weight: 600; color: #1a202c; margin-bottom: 2px; }
        .conv-spec { font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 8px; }
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
    left: 340px; /* Nempel di tepi kanan sidebar */
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
.btn-toggle-sidebar:hover { background: #f8f9fa; color: #667eea; }
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
        .session-badge.cancelled { background: #fee2e2; color: #dc2626; }
        .session-badge.none { background: #f1f5f9; color: #64748b; }
        .session-badge i { font-size: 0.5rem; }

        .btn-end-session { padding: 8px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; border: 2px solid #ef4444; background: white; color: #ef4444; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .btn-end-session:hover { background: #ef4444; color: white; }

        .session-ended-notice { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 16px 24px; background: linear-gradient(135deg, #fef3c7, #fde68a); border-top: 1px solid #fcd34d; color: #92400e; font-size: 0.95rem; }
        .session-ended-notice i { font-size: 1.25rem; }
        .session-ended-notice .btn-book-again { padding: 8px 16px; background: #f59e0b; color: white; border: none; border-radius: 20px; font-weight: 600; font-size: 0.85rem; text-decoration: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .session-ended-notice .btn-book-again:hover { background: #d97706; }

        /* Messages */
        .chat-messages { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 16px; }
        .message-row { display: flex; flex-direction: column; max-width: 70%; position: relative; }
        .message-row.me { align-self: flex-end; }
        .message-row.other { align-self: flex-start; }
        .message-wrapper { position: relative; display: flex; align-items: flex-start; gap: 8px; }
        .message-row.me .message-wrapper { flex-direction: row-reverse; }
        .message-bubble { padding: 12px 16px; border-radius: 16px; position: relative; min-width: 80px; }
        .message-row.me .message-bubble { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-bottom-right-radius: 4px; }
        .message-row.other .message-bubble { background: white; color: #1a202c; border-bottom-left-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .message-bubble p { margin: 0; line-height: 1.5; word-wrap: break-word; }
        .message-time { font-size: 0.7rem; opacity: 0.7; margin-top: 4px; display: block; }
        .message-row.me .message-time { text-align: right; }
        .message-edited { font-size: 0.65rem; opacity: 0.6; font-style: italic; margin-left: 6px; }

        /* Message Actions - FIX 15: Cursor pointer + copy button */
        .message-actions { display: none; align-items: center; gap: 4px; opacity: 0; transition: opacity 0.2s; }
        .message-wrapper:hover .message-actions { display: flex; opacity: 1; }
        .msg-action-btn { width: 30px; height: 30px; border-radius: 50%; border: none; background: #f1f5f9; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 0.85rem; }
        .msg-action-btn:hover { background: #e2e8f0; color: #1a202c; }
        .msg-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }
        .msg-action-btn.copy:hover { background: #dbeafe; color: #3b82f6; }

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
        .chat-input-area.disabled { pointer-events: none; opacity: 0.5; }
        .edit-indicator { display: none; align-items: center; gap: 10px; padding: 10px 14px; background: #fef3c7; border-radius: 12px; margin-bottom: 12px; font-size: 0.9rem; color: #92400e; }
        .edit-indicator.show { display: flex; }
        .edit-indicator i { font-size: 1.1rem; }
        .edit-indicator span { flex: 1; }
        .btn-cancel-edit { background: transparent; border: none; color: #92400e; cursor: pointer; padding: 4px 8px; border-radius: 6px; }
        .btn-cancel-edit:hover { background: rgba(0,0,0,0.1); }

        .file-preview { display: none; align-items: center; gap: 10px; padding: 12px 14px; background: #f1f5f9; border-radius: 12px; margin-bottom: 12px; }
        .file-preview.show { display: flex; }
        .file-preview-icon { width: 44px; height: 44px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem; }
        .file-preview-info { flex: 1; }
        .file-preview-name { font-weight: 500; font-size: 0.9rem; }
        .file-preview-size { font-size: 0.8rem; color: #64748b; }
        .btn-remove-file { background: #fee2e2; color: #dc2626; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }

        .upload-progress { display: none; margin-bottom: 12px; }
        .upload-progress.show { display: block; }
        .progress-bar-container { height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
        .progress-bar { height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 4px; transition: width 0.3s; width: 0; }
        .progress-text { font-size: 0.8rem; color: #64748b; margin-top: 6px; text-align: center; }

        .input-wrapper { display: flex; align-items: flex-end; gap: 12px; background: #f8f9fa; border-radius: 24px; padding: 8px 8px 8px 20px; border: 2px solid transparent; transition: all 0.2s; }
        .input-wrapper:focus-within { border-color: #667eea; background: white; }
        .input-wrapper textarea { flex: 1; border: none; background: transparent; resize: none; padding: 8px 0; font-size: 0.95rem; line-height: 1.5; max-height: 120px; outline: none; font-family: inherit; }
        .input-actions { display: flex; align-items: center; gap: 4px; }
        .btn-attach, .btn-send { width: 40px; height: 40px; border-radius: 50%; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
        .btn-attach { background: transparent; color: #64748b; }
        .btn-attach:hover { background: #e2e8f0; color: #667eea; }
        .btn-send { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-send:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .btn-send:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .file-hint { font-size: 0.8rem; color: #94a3b8; margin-top: 8px; text-align: center; }
        .empty-chat { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #64748b; text-align: center; padding: 40px; }
        .empty-chat i { font-size: 4rem; color: #cbd5e1; margin-bottom: 20px; }
        .empty-chat h2 { font-size: 1.25rem; color: #1a202c; margin-bottom: 8px; }

        /* Disabled Input State */
        .chat-input-disabled { padding: 20px 24px; background: #f8f9fa; border-top: 1px solid #e5e7eb; text-align: center; color: #64748b; }
        .chat-input-disabled i { font-size: 1.5rem; margin-bottom: 8px; display: block; }
        .chat-input-disabled.pending i { color: #d97706; }
        .chat-input-disabled.completed i { color: #4f46e5; }
        .chat-input-disabled.no-session i { color: #94a3b8; }
        .chat-input-disabled p { margin: 0; font-size: 0.9rem; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: white; border-radius: 20px; padding: 32px; max-width: 420px; width: 90%; text-align: center; }
        .modal-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; }
        .modal-icon.warning { background: #fef2f2; color: #ef4444; }
        .modal-icon.danger { background: #fef2f2; color: #dc2626; }
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
        .typing-dots span { width: 8px; height: 8px; background: #667eea; border-radius: 50%; animation: typingBounce 1.4s infinite ease-in-out; }
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
        .timer-warning-icon.success { background: #dbeafe; color: #3b82f6; }
        .timer-warning-box h3 { font-size: 1.5rem; font-weight: 700; margin-bottom: 12px; color: #1a202c; }
        .timer-warning-box p { color: #64748b; margin-bottom: 24px; line-height: 1.6; }
        .timer-warning-box .remaining-time { font-size: 2.5rem; font-weight: 800; color: #ef4444; margin: 20px 0; font-variant-numeric: tabular-nums; }
        .btn-warning-ok { padding: 14px 40px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-warning-ok:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); }

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
<?php include __DIR__ . '/student-navbar.php'; ?>

<div class="chat-container">
    <!-- Toggle Sidebar Button -->
<button type="button" class="btn-toggle-sidebar" id="btnToggleSidebar" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- SIDEBAR -->
    <aside class="chat-sidebar" id="chatSidebar">
        <div class="sidebar-header">
            <h2><i class="bi bi-chat-dots"></i> Chat Mentor</h2>
            <button type="button" class="btn-collapse-sidebar" id="btnCollapseSidebar" title="Tutup Sidebar">
                <i class="bi bi-x-lg"></i>
            </button>
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
                $mentorAvatarUrl = get_chat_avatar_url($conv['mentor_avatar'] ?? '', BASE_PATH);
                $isActive = ($currentConvId === $cId);
                $badgeStatus = 'none';
                if ($conv['session_ended_at']) $badgeStatus = 'completed';
                elseif ($conv['session_status']) $badgeStatus = $conv['session_status'];
            ?>
            <a href="?conversation_id=<?= $cId ?>" class="conversation-item <?= $isActive ? 'active' : '' ?>">
                <div class="conv-avatar">
                    <?php if ($mentorAvatarUrl): ?>
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
            $headerAvatarUrl = get_chat_avatar_url($currentConv['mentor_avatar'] ?? '', BASE_PATH);
            $sessionStatus = $currentSession['status'] ?? 'none';
        ?>
        <div class="chat-header">
            <div class="chat-header-avatar">
                <?php if ($headerAvatarUrl): ?>
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
                <div class="session-timer" id="sessionTimer"
                     data-session-id="<?= (int)$currentSession['id'] ?>"
                     data-started-at="<?= htmlspecialchars($currentSession['started_at'] ?? '') ?>"
                     data-duration="<?= (int)($currentSession['duration'] ?? 60) ?>">
                    <i class="bi bi-clock"></i>
                    <span id="timerDisplay">--:--</span>
                </div>
                <span class="session-badge ongoing"><i class="bi bi-circle-fill"></i> Sesi Aktif</span>
                <?php if ($currentSession['id']): ?>
                <button type="button" class="btn-end-session" id="btnEndSession" data-session-id="<?= (int)$currentSession['id'] ?>">
                    <i class="bi bi-stop-circle"></i> Akhiri
                </button>
                <?php endif; ?>
                <?php elseif ($sessionStatus === 'pending'): ?>
                <span class="session-badge pending"><i class="bi bi-circle-fill"></i> Menunggu</span>
                <?php elseif ($sessionStatus === 'completed'): ?>
                <span class="session-badge completed"><i class="bi bi-circle-fill"></i> Sesi Selesai</span>
                <?php else: ?>
                <span class="session-badge none"><i class="bi bi-circle-fill"></i> Tidak Ada Sesi</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Messages -->
        <div class="chat-messages <?= $canChat ? 'can-send-message' : '' ?>" id="chatMessages" data-conversation="<?= $currentConvId ?>">
            <?php if (empty($currentMsgs)): ?>
            <div class="empty-chat">
                <i class="bi bi-chat-heart"></i>
                <h2>Belum ada pesan</h2>
                <p>Mulai percakapan dengan mentor Anda.</p>
            </div>
            <?php else: ?>
            <?php foreach ($currentMsgs as $msg):
                $isMine = ((int)$msg['sender_id'] === $student_id);
                $msgClass = $isMine ? 'me' : 'other';
                $hasFile = !empty($msg['file_path']);
                $isVideo = $hasFile && preg_match('/\.(mp4|webm|mov)$/i', $msg['file_path']);
                $isEdited = !empty($msg['edited_at']);
            ?>
            <div class="message-row <?= $msgClass ?>" data-message-id="<?= (int)$msg['id'] ?>" data-edited="<?= $isEdited ? '1' : '0' ?>">
                <div class="message-wrapper">
                    <?php if ($isMine && $canChat): ?>
                    <!-- Edit/Delete untuk pesan sendiri -->
                    <div class="message-actions">
                        <button type="button" class="msg-action-btn edit" title="Edit" 
                            data-message-id="<?= (int)$msg['id'] ?>" 
                            data-message-text="<?= htmlspecialchars($msg['message'] ?? '', ENT_QUOTES) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="msg-action-btn delete" title="Hapus" data-message-id="<?= (int)$msg['id'] ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <?php elseif (!$isMine && !empty($msg['message'])): ?>
                    <!-- FIX 15: Copy button untuk pesan mentor -->
                    <div class="message-actions">
                        <button type="button" class="msg-action-btn copy" title="Salin teks" 
                            data-message-text="<?= htmlspecialchars($msg['message'] ?? '', ENT_QUOTES) ?>">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                    <div class="message-bubble">
                        <?php if (!empty($msg['message'])): ?>
                        <p class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                        <?php endif; ?>
                        <?php if ($hasFile): ?>
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
                                    <div class="file-size"><?= number_format(($msg['file_size'] ?? 0) / (1024*1024), 1) ?> MB</div>
                                </div>
                                <i class="bi bi-download"></i>
                            </a>
                            <?php else: ?>
                            <a href="<?= BASE_PATH ?>/<?= htmlspecialchars($msg['file_path']) ?>" target="_blank" class="message-file">
                                <i class="bi bi-file-earmark"></i>
                                <div class="file-info">
                                    <div class="file-name"><?= htmlspecialchars($msg['file_name'] ?? 'File') ?></div>
                                    <div class="file-size"><?= number_format(($msg['file_size'] ?? 0) / 1024, 1) ?> KB</div>
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
            
            <!-- Typing Indicator -->
            <div class="typing-indicator" id="typingIndicator">
                <div class="typing-dots">
                    <span></span><span></span><span></span>
                </div>
                <span class="typing-text">Mentor sedang mengetik...</span>
            </div>
        </div>

        <!-- Input Area -->
        <?php if ($canChat): ?>
        <div class="chat-input-area" id="chatInputArea">
            <div class="edit-indicator" id="editIndicator">
                <i class="bi bi-pencil-square"></i>
                <span>Mengedit pesan...</span>
                <button type="button" class="btn-cancel-edit" id="btnCancelEdit">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="file-preview" id="filePreview">
                <div class="file-preview-icon"><i class="bi bi-file-earmark"></i></div>
                <div class="file-preview-info">
                    <div class="file-preview-name" id="filePreviewName">file.pdf</div>
                    <div class="file-preview-size" id="filePreviewSize">1.2 MB</div>
                </div>
                <button type="button" class="btn-remove-file" id="btnRemoveFile"><i class="bi bi-x"></i></button>
            </div>
            <div class="upload-progress" id="uploadProgress">
                <div class="progress-bar-container">
                    <div class="progress-bar" id="progressBar"></div>
                </div>
                <div class="progress-text" id="progressText">Uploading... 0%</div>
            </div>
            <form id="chatForm" enctype="multipart/form-data">
                <input type="hidden" name="conversation_id" value="<?= $currentConvId ?>">
                <input type="hidden" name="edit_message_id" id="editMessageId" value="">
                <div class="input-wrapper">
                    <textarea name="message" id="messageInput" placeholder="Ketik pesan..." rows="1"></textarea>
                    <div class="input-actions">
                        <input type="file" name="attachment" id="fileInput" hidden accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                        <button type="button" class="btn-attach" id="btnAttach" title="Lampirkan file">
                            <i class="bi bi-paperclip"></i>
                        </button>
                        <button type="submit" class="btn-send" id="btnSend" title="Kirim">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </div>
                <div class="file-hint">Maks 300MB untuk video, 5MB untuk file lain</div>
            </form>
        </div>
        <?php elseif ($sessionStatus === 'pending'): ?>
        <div class="chat-input-disabled pending">
            <i class="bi bi-hourglass-split"></i>
            <p>Menunggu mentor memulai sesi...</p>
        </div>
        <?php elseif ($sessionStatus === 'completed'): ?>
        <div class="session-ended-notice">
            <i class="bi bi-check-circle"></i>
            <span>Sesi telah berakhir.</span>
            <?php if ($currentSession['id'] && empty($currentConv['rating'] ?? null)): ?>
            <a href="<?= BASE_PATH ?>/session-rating.php?session_id=<?= (int)$currentSession['id'] ?>" class="btn-book-again">
                <i class="bi bi-star"></i> Beri Rating
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="chat-input-disabled no-session">
            <i class="bi bi-calendar-x"></i>
            <p>Tidak ada sesi aktif. Booking sesi terlebih dahulu untuk chat.</p>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<!-- End Session Modal -->
<div class="modal-overlay" id="endSessionModal">
    <div class="modal-box">
        <div class="modal-icon warning"><i class="bi bi-exclamation-triangle"></i></div>
        <h3>Akhiri Sesi?</h3>
        <p>Apakah Anda yakin ingin mengakhiri sesi ini? Setelah diakhiri, Anda tidak bisa lagi mengirim pesan.</p>
        <div class="modal-actions">
            <button type="button" class="btn-modal btn-modal-cancel" id="btnCancelEnd">Batal</button>
            <button type="button" class="btn-modal btn-modal-confirm" id="btnConfirmEnd">Ya, Akhiri</button>
        </div>
    </div>
</div>

<!-- Delete Message Modal -->
<div class="modal-overlay" id="deleteMessageModal">
    <div class="modal-box">
        <div class="modal-icon danger"><i class="bi bi-trash"></i></div>
        <h3>Hapus Pesan?</h3>
        <p>Pesan yang dihapus tidak dapat dikembalikan.</p>
        <div class="modal-actions">
            <button type="button" class="btn-modal btn-modal-cancel" id="btnCancelDelete">Batal</button>
            <button type="button" class="btn-modal btn-modal-confirm" id="btnConfirmDelete">Hapus</button>
        </div>
    </div>
</div>

<!-- Timer Warning Modal -->
<div class="timer-warning-modal" id="timerWarningModal">
    <div class="timer-warning-box">
        <div class="timer-warning-icon warning" id="warningIcon"><i class="bi bi-clock"></i></div>
        <h3 id="warningTitle">Sesi Hampir Berakhir!</h3>
        <p id="warningMessage">Waktu sesi Anda akan segera habis.</p>
        <div class="remaining-time" id="warningRemainingTime">05:00</div>
        <button type="button" class="btn-warning-ok" id="btnWarningOk">Mengerti</button>
    </div>
</div>

<!-- Toast -->
<div class="toast-notification" id="toast">
    <i class="bi bi-check-circle"></i>
    <span id="toastMessage">Pesan terkirim</span>
</div>

<script>
(function() {
    'use strict';
    
    const BASE_PATH = '<?= BASE_PATH ?>';
    const SESSION_ID = <?= (int)($currentSession['id'] ?? 0) ?>;
    const INITIAL_SESSION_STATUS = '<?= $sessionStatus ?>';
    const USER_ID = <?= (int)$student_id ?>;
    const POLL_INTERVAL = 2000;
    const SESSION_POLL_INTERVAL = 3000;
    
    let lastMessageId = 0;
    let pollTimer = null;
    let sessionPollTimer = null;
    let typingPollTimer = null;
    let typingTimeout = null;
    let sessionEnded = (INITIAL_SESSION_STATUS === 'completed');
    let deleteTargetId = null;
    let timerInterval = null;
    let warned5Min = false;
    let warned1Min = false;
    let sessionEndedByOther = false;

    // DOM Elements
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
    const endSessionModal = document.getElementById('endSessionModal');
    const btnEndSession = document.getElementById('btnEndSession');
    const btnCancelEnd = document.getElementById('btnCancelEnd');
    const btnConfirmEnd = document.getElementById('btnConfirmEnd');
    const deleteMessageModal = document.getElementById('deleteMessageModal');
    const btnCancelDelete = document.getElementById('btnCancelDelete');
    const btnConfirmDelete = document.getElementById('btnConfirmDelete');
    const timerWarningModal = document.getElementById('timerWarningModal');
    const warningIcon = document.getElementById('warningIcon');
    const warningTitle = document.getElementById('warningTitle');
    const warningMessage = document.getElementById('warningMessage');
    const warningRemainingTime = document.getElementById('warningRemainingTime');
    const btnWarningOk = document.getElementById('btnWarningOk');
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    const chatSidebar = document.getElementById('chatSidebar');
    const btnToggleSidebar = document.getElementById('btnToggleSidebar');
    const btnCollapseSidebar = document.getElementById('btnCollapseSidebar');
    const sessionTimer = document.getElementById('sessionTimer');
    const timerDisplay = document.getElementById('timerDisplay');
    const typingIndicator = document.getElementById('typingIndicator');

    // ===== SIDEBAR TOGGLE =====
    function initSidebar() {
        if (btnToggleSidebar) {
            btnToggleSidebar.addEventListener('click', toggleSidebar);
        }
        if (btnCollapseSidebar) {
            btnCollapseSidebar.addEventListener('click', collapseSidebar);
        }
    }

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

function collapseSidebar() {
    if (!chatSidebar || !btnToggleSidebar) return;
    chatSidebar.classList.add('collapsed');
    btnToggleSidebar.classList.add('collapsed');
}


    // ===== TOAST =====
    function showToast(message, type = 'success') {
        if (!toast || !toastMessage) return;
        toastMessage.textContent = message;
        toast.className = 'toast-notification ' + type;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    // ===== SCROLL =====
    function scrollToBottom() {
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    // ===== SESSION TIMER =====
    function initSessionTimer() {
        if (!sessionTimer || sessionEnded) return;

        const startedAt = sessionTimer.dataset.startedAt;
        const duration = parseInt(sessionTimer.dataset.duration) || 60;

        if (!startedAt) {
            timerDisplay.textContent = `${duration}:00`;
            return;
        }

        const startTime = new Date(startedAt).getTime();
        const durationMs = duration * 60 * 1000;

        function updateTimer() {
            const now = Date.now();
            const elapsed = now - startTime;
            const remaining = Math.max(0, durationMs - elapsed);

            if (remaining <= 0) {
                clearInterval(timerInterval);
                timerDisplay.textContent = '00:00';
                sessionTimer.classList.remove('warning');
                sessionTimer.classList.add('danger');
                handleSessionExpired();
                return;
            }

            const mins = Math.floor(remaining / 60000);
            const secs = Math.floor((remaining % 60000) / 1000);
            timerDisplay.textContent = `${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;

            if (remaining <= 60000) {
                sessionTimer.classList.remove('warning');
                sessionTimer.classList.add('danger');
                if (!warned1Min) {
                    warned1Min = true;
                    showTimerWarning('danger', 'Waktu Hampir Habis!', 'Sesi akan berakhir dalam 1 menit.', remaining);
                }
            } else if (remaining <= 300000) {
                sessionTimer.classList.add('warning');
                if (!warned5Min) {
                    warned5Min = true;
                    showTimerWarning('warning', 'Sesi Hampir Berakhir', 'Waktu sesi Anda tinggal 5 menit lagi.', remaining);
                }
            }

            if (timerWarningModal.classList.contains('show')) {
                warningRemainingTime.textContent = `${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;
            }
        }

        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);
    }

    function showTimerWarning(type, title, message, remainingMs) {
        if (!timerWarningModal) return;
        warningIcon.className = 'timer-warning-icon ' + type;
        warningIcon.innerHTML = type === 'danger' ? '<i class="bi bi-exclamation-triangle"></i>' : '<i class="bi bi-clock"></i>';
        warningTitle.textContent = title;
        warningMessage.textContent = message;
        const mins = Math.floor(remainingMs / 60000);
        const secs = Math.floor((remainingMs % 60000) / 1000);
        warningRemainingTime.textContent = `${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;
        
        btnWarningOk.textContent = 'Mengerti';
        btnWarningOk.onclick = () => timerWarningModal.classList.remove('show');
        
        timerWarningModal.classList.add('show');
    }

    async function handleSessionExpired() {
        sessionEnded = true;
        stopPolling();

        try {
            await fetch(`${BASE_PATH}/api-session-end.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: SESSION_ID })
            });
        } catch (e) {
            console.error('Error ending session:', e);
        }

        showSessionEndedModal();
    }

    function showSessionEndedModal() {
        if (!timerWarningModal) return;
        
        warningIcon.className = 'timer-warning-icon success';
        warningIcon.innerHTML = '<i class="bi bi-check-circle"></i>';
        warningTitle.textContent = 'Sesi Telah Berakhir';
        warningMessage.textContent = 'Terima kasih telah menggunakan JagoNugas! Silakan beri rating untuk mentor Anda.';
        warningRemainingTime.style.display = 'none';

        btnWarningOk.textContent = 'Beri Rating';
        btnWarningOk.onclick = () => {
            window.location.href = `${BASE_PATH}/session-rating.php?session_id=${SESSION_ID}`;
        };

        timerWarningModal.classList.add('show');
        disableChatInput();
    }

    function disableChatInput() {
        const chatInputArea = document.getElementById('chatInputArea');
        if (chatInputArea) {
            chatInputArea.classList.add('disabled');
            chatInputArea.innerHTML = `
                <div class="chat-input-disabled completed" style="padding:20px;text-align:center;color:#64748b;">
                    <i class="bi bi-check-circle" style="font-size:1.5rem;display:block;margin-bottom:8px;color:#4f46e5;"></i>
                    <p style="margin:0;">Sesi telah berakhir.</p>
                </div>
            `;
        }
        
        const headerActions = document.querySelector('.header-actions');
        if (headerActions) {
            headerActions.innerHTML = '<span class="session-badge completed"><i class="bi bi-circle-fill"></i> Sesi Selesai</span>';
        }
    }

    // ===== SESSION STATUS POLLING =====
    function startSessionStatusPolling() {
        if (!SESSION_ID || sessionEnded) return;
        sessionPollTimer = setInterval(pollSessionStatus, SESSION_POLL_INTERVAL);
    }

    function stopSessionStatusPolling() {
        if (sessionPollTimer) {
            clearInterval(sessionPollTimer);
            sessionPollTimer = null;
        }
    }

    async function pollSessionStatus() {
        if (sessionEnded || sessionEndedByOther) return;

        try {
            const response = await fetch(`${BASE_PATH}/api-session-timer.php?session_id=${SESSION_ID}`);
            const data = await response.json();

            if (!data.success) return;

            if (data.status === 'completed' || data.is_expired) {
                sessionEndedByOther = true;
                sessionEnded = true;
                stopPolling();
                stopSessionStatusPolling();
                showSessionEndedByMentorModal();
            }
        } catch (err) {
            console.error('Session poll error:', err);
        }
    }

    function showSessionEndedByMentorModal() {
        if (!timerWarningModal) {
            window.location.href = `${BASE_PATH}/session-rating.php?session_id=${SESSION_ID}`;
            return;
        }
        
        warningIcon.className = 'timer-warning-icon success';
        warningIcon.innerHTML = '<i class="bi bi-check-circle"></i>';
        warningTitle.textContent = 'Sesi Telah Berakhir';
        warningMessage.textContent = 'Mentor telah mengakhiri sesi. Silakan beri rating untuk pengalaman Anda.';
        warningRemainingTime.style.display = 'none';

        btnWarningOk.textContent = 'Beri Rating';
        btnWarningOk.onclick = () => {
            window.location.href = `${BASE_PATH}/session-rating.php?session_id=${SESSION_ID}`;
        };

        timerWarningModal.classList.add('show');
        disableChatInput();
        
        setTimeout(() => {
            if (sessionEndedByOther) {
                window.location.href = `${BASE_PATH}/session-rating.php?session_id=${SESSION_ID}`;
            }
        }, 5000);
    }

    // ===== END SESSION =====
    if (btnEndSession) {
        btnEndSession.addEventListener('click', () => {
            endSessionModal?.classList.add('show');
        });
    }
    if (btnCancelEnd) {
        btnCancelEnd.addEventListener('click', () => {
            endSessionModal?.classList.remove('show');
        });
    }
    if (btnConfirmEnd) {
        btnConfirmEnd.addEventListener('click', async () => {
            endSessionModal?.classList.remove('show');
            try {
                const response = await fetch(`${BASE_PATH}/api-session-end.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_id: SESSION_ID })
                });
                const data = await response.json();
                if (data.success) {
                    sessionEnded = true;
                    stopPolling();
                    stopSessionStatusPolling();
                    showToast('Sesi berhasil diakhiri', 'success');
                    
                    setTimeout(() => {
                        window.location.href = `${BASE_PATH}/session-rating.php?session_id=${SESSION_ID}`;
                    }, 1000);
                } else {
                    showToast(data.error || 'Gagal mengakhiri sesi', 'error');
                }
            } catch (e) {
                showToast('Error mengakhiri sesi', 'error');
            }
        });
    }

    // ===== TYPING INDICATOR =====
    function startTypingPoll() {
        if (!chatMessages || sessionEnded) return;
        const conversationId = chatMessages.dataset.conversation;
        if (!conversationId || conversationId === '0') return;
        
        typingPollTimer = setInterval(() => pollTypingStatus(conversationId), 2000);
    }

    function stopTypingPoll() {
        if (typingPollTimer) {
            clearInterval(typingPollTimer);
            typingPollTimer = null;
        }
    }

    async function pollTypingStatus(conversationId) {
        try {
            const response = await fetch(`${BASE_PATH}/api-typing-status.php?conversation_id=${conversationId}`);
            const data = await response.json();
            if (data.success && typingIndicator) {
                if (data.is_typing) {
                    typingIndicator.classList.add('show');
                    scrollToBottom();
                } else {
                    typingIndicator.classList.remove('show');
                }
            }
        } catch (e) {
            console.error('Typing poll error:', e);
        }
    }

    function setTypingStatus(isTyping) {
        if (!chatMessages || sessionEnded) return;
        const conversationId = chatMessages.dataset.conversation;
        if (!conversationId || conversationId === '0') return;
        
        fetch(`${BASE_PATH}/api-typing-status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: parseInt(conversationId), is_typing: isTyping })
        }).catch(() => {});
    }

    if (messageInput) {
        messageInput.addEventListener('input', () => {
            setTypingStatus(true);
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => setTypingStatus(false), 3000);
        });
    }

    // ===== POLLING MESSAGES =====
    function initPolling() {
        if (!chatMessages) return;

        const conversationId = chatMessages.dataset.conversation;
        if (!conversationId || conversationId === '0') return;

        const allMessages = chatMessages.querySelectorAll('.message-row[data-message-id]');
        allMessages.forEach(row => {
            const id = parseInt(row.dataset.messageId || 0);
            if (id > lastMessageId) lastMessageId = id;
        });

        startPolling(conversationId);
        startTypingPoll();
        startSessionStatusPolling();
    }

    function startPolling(conversationId) {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(() => pollNewMessages(conversationId), POLL_INTERVAL);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        stopTypingPoll();
        stopSessionStatusPolling();
    }

    async function pollNewMessages(conversationId) {
        if (sessionEnded) return;

        try {
            const url = `${BASE_PATH}/api-chat-messages.php?conversation_id=${conversationId}&last_id=${lastMessageId}&include_updated=1`;
            const response = await fetch(url);
            const data = await response.json();

            if (!data.success) return;

            if (data.existing_ids && Array.isArray(data.existing_ids)) {
                handleDeletedMessages(data.existing_ids);
            }

            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    const existingRow = chatMessages.querySelector(`.message-row[data-message-id="${msg.id}"]`);

                    if (existingRow) {
                        if (msg.is_edited) {
                            updateExistingMessage(existingRow, msg);
                        }
                    } else if (!msg.is_mine) {
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

    function handleDeletedMessages(existingIds) {
        const allMessageRows = chatMessages.querySelectorAll('.message-row[data-message-id]');
        allMessageRows.forEach(row => {
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
    }

    function appendReceivedMessage(msg) {
        if (!chatMessages) return;

        const emptyChat = chatMessages.querySelector('.empty-chat');
        if (emptyChat) emptyChat.remove();

        const row = document.createElement('div');
        row.className = 'message-row other';
        row.dataset.messageId = msg.id;
        row.dataset.edited = msg.is_edited ? '1' : '0';

        let fileHtml = '';
        if (msg.file_path) {
            const isVideo = /\.(mp4|webm|mov)$/i.test(msg.file_path);
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

        const textHtml = msg.message ? `<p class="message-text">${msg.message.replace(/\n/g, '<br>')}</p>` : '';
        const editedHtml = msg.is_edited ? '<span class="message-edited">(diedit)</span>' : '';

        // FIX 15: Tambah tombol copy untuk pesan yang diterima
        const copyBtnHtml = msg.message ? `
            <div class="message-actions">
                <button type="button" class="msg-action-btn copy" title="Salin teks" data-message-text="${(msg.message || '').replace(/"/g, '&quot;')}">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
        ` : '';

        row.innerHTML = `
            <div class="message-wrapper">
                ${copyBtnHtml}
                <div class="message-bubble">
                    ${textHtml}
                    ${fileHtml}
                    <span class="message-time">${msg.time} ${editedHtml}</span>
                </div>
            </div>
        `;

        const typingInd = document.getElementById('typingIndicator');
        if (typingInd) {
            chatMessages.insertBefore(row, typingInd);
        } else {
            chatMessages.appendChild(row);
        }

        scrollToBottom();
    }

    // ===== FILE HANDLING =====
    if (btnAttach && fileInput) {
        btnAttach.addEventListener('click', () => fileInput.click());
    }

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                if (filePreviewName) filePreviewName.textContent = file.name;
                if (filePreviewSize) filePreviewSize.textContent = (file.size / (1024*1024)).toFixed(2) + ' MB';
                filePreview?.classList.add('show');
            }
        });
    }

    if (btnRemoveFile) {
        btnRemoveFile.addEventListener('click', () => {
            if (fileInput) fileInput.value = '';
            filePreview?.classList.remove('show');
        });
    }

    // ===== EDIT MESSAGE =====
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.msg-action-btn.edit');
        if (editBtn) {
            const msgId = editBtn.dataset.messageId;
            const msgText = editBtn.dataset.messageText || '';
            if (editMessageId) editMessageId.value = msgId;
            if (messageInput) {
                messageInput.value = msgText;
                messageInput.focus();
            }
            editIndicator?.classList.add('show');
        }
    });

    if (btnCancelEdit) {
        btnCancelEdit.addEventListener('click', () => {
            if (editMessageId) editMessageId.value = '';
            if (messageInput) messageInput.value = '';
            editIndicator?.classList.remove('show');
        });
    }

    // ===== DELETE MESSAGE =====
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.msg-action-btn.delete');
        if (deleteBtn) {
            deleteTargetId = deleteBtn.dataset.messageId;
            deleteMessageModal?.classList.add('show');
        }
    });

    if (btnCancelDelete) {
        btnCancelDelete.addEventListener('click', () => {
            deleteMessageModal?.classList.remove('show');
            deleteTargetId = null;
        });
    }

    if (btnConfirmDelete) {
        btnConfirmDelete.addEventListener('click', async () => {
            if (!deleteTargetId) return;
            deleteMessageModal?.classList.remove('show');

            try {
                const response = await fetch(`${BASE_PATH}/api-message-delete.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message_id: parseInt(deleteTargetId) })
                });
                const data = await response.json();

                if (data.success) {
                    const row = chatMessages?.querySelector(`.message-row[data-message-id="${deleteTargetId}"]`);
                    if (row) row.remove();
                    showToast('Pesan dihapus', 'success');
                } else {
                    showToast(data.error || 'Gagal menghapus', 'error');
                }
            } catch (e) {
                showToast('Error menghapus pesan', 'error');
            }

            deleteTargetId = null;
        });
    }

    // ===== FIX 15: COPY MESSAGE =====
    document.addEventListener('click', function(e) {
        const copyBtn = e.target.closest('.msg-action-btn.copy');
        if (copyBtn) {
            const text = copyBtn.dataset.messageText || '';
            if (text) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('Pesan disalin ke clipboard', 'success');
                    // Ubah icon sementara sebagai feedback
                    const icon = copyBtn.querySelector('i');
                    if (icon) {
                        icon.className = 'bi bi-clipboard-check';
                        setTimeout(() => {
                            icon.className = 'bi bi-clipboard';
                        }, 2000);
                    }
                }).catch(() => {
                    // Fallback untuk browser lama
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    showToast('Pesan disalin', 'success');
                });
            }
        }
    });

    // ===== SEND MESSAGE =====
    if (messageInput) {
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm?.dispatchEvent(new Event('submit'));
            }
        });
        
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    }

    chatForm?.addEventListener('submit', function(e) {
        e.preventDefault();

        const message = messageInput?.value.trim() || '';
        const hasFile = fileInput?.files && fileInput.files.length > 0;

        if (!message && !hasFile) return;

        setTypingStatus(false);

        const formData = new FormData(this);

        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                if (progressBar) progressBar.style.width = percent + '%';
                if (progressText) progressText.textContent = `Uploading... ${percent}%`;
                if (hasFile) uploadProgress?.classList.add('show');
            }
        });

        xhr.addEventListener('load', function() {
            uploadProgress?.classList.remove('show');
            if (progressBar) progressBar.style.width = '0%';

            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    if (data.edited) {
                        const row = chatMessages?.querySelector(`.message-row[data-message-id="${data.message.id}"]`);
                        if (row) {
                            const bubble = row.querySelector('.message-bubble');
                            let textEl = bubble?.querySelector('.message-text');
                            if (data.message.message) {
                                if (textEl) {
                                    textEl.innerHTML = data.message.message.replace(/\n/g, '<br>');
                                } else {
                                    textEl = document.createElement('p');
                                    textEl.className = 'message-text';
                                    textEl.innerHTML = data.message.message.replace(/\n/g, '<br>');
                                    bubble?.insertBefore(textEl, bubble.firstChild);
                                }
                            }
                            const timeSpan = bubble?.querySelector('.message-time');
                            if (timeSpan && !timeSpan.querySelector('.message-edited')) {
                                timeSpan.innerHTML += ' <span class="message-edited">(diedit)</span>';
                            }
                            row.dataset.edited = '1';
                            const editBtn = row.querySelector('.msg-action-btn.edit');
                            if (editBtn) editBtn.dataset.messageText = data.message.message || '';
                        }
                        showToast('Pesan diedit', 'success');
                    } else {
                        appendMyMessage(data.message);
                        showToast('Pesan terkirim', 'success');
                    }

                    if (messageInput) {
                        messageInput.value = '';
                        messageInput.style.height = 'auto';
                    }
                    if (fileInput) fileInput.value = '';
                    if (editMessageId) editMessageId.value = '';
                    filePreview?.classList.remove('show');
                    editIndicator?.classList.remove('show');
                } else {
                    showToast(data.error || 'Gagal mengirim', 'error');
                }
            } catch (err) {
                showToast('Error parsing response', 'error');
            }

            if (btnSend) btnSend.disabled = false;
        });

        xhr.addEventListener('error', function() {
            uploadProgress?.classList.remove('show');
            showToast('Gagal mengirim. Periksa koneksi.', 'error');
            if (btnSend) btnSend.disabled = false;
        });

        xhr.open('POST', `${BASE_PATH}/student-chat-send.php`);
        xhr.send(formData);
    });

    // ===== APPEND MY MESSAGE =====
    function appendMyMessage(msg) {
        if (!chatMessages) return;

        const emptyChat = chatMessages.querySelector('.empty-chat');
        if (emptyChat) emptyChat.remove();

        const row = document.createElement('div');
        row.className = 'message-row me';
        row.dataset.messageId = msg.id;
        row.dataset.edited = '0';

        let fileHtml = '';
        if (msg.file_path) {
            const isVideo = /\.(mp4|webm|mov)$/i.test(msg.file_path);
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
                            <div class="file-name">${msg.file_name}</div>
                            <div class="file-size">${(msg.file_size / (1024*1024)).toFixed(1)} MB</div>
                        </div>
                        <i class="bi bi-download"></i>
                    </a>`;
            } else {
                fileHtml = `
                    <a href="${BASE_PATH}/${msg.file_path}" target="_blank" class="message-file">
                        <i class="bi bi-file-earmark"></i>
                        <div class="file-info">
                            <div class="file-name">${msg.file_name}</div>
                            <div class="file-size">${(msg.file_size / 1024).toFixed(1)} KB</div>
                        </div>
                        <i class="bi bi-download"></i>
                    </a>`;
            }
        }

        const textHtml = msg.message ? `<p class="message-text">${msg.message.replace(/\n/g, '<br>')}</p>` : '';

        row.innerHTML = `
            <div class="message-wrapper">
                <div class="message-actions">
                    <button type="button" class="msg-action-btn edit" title="Edit" data-message-id="${msg.id}" data-message-text="${(msg.message || '').replace(/"/g, '&quot;')}">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="msg-action-btn delete" title="Hapus" data-message-id="${msg.id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="message-bubble">
                    ${textHtml}
                    ${fileHtml}
                    <span class="message-time">${msg.time}</span>
                </div>
            </div>
        `;

        const typingInd = document.getElementById('typingIndicator');
        if (typingInd) {
            chatMessages.insertBefore(row, typingInd);
        } else {
            chatMessages.appendChild(row);
        }

        const newId = parseInt(msg.id);
        if (newId > lastMessageId) {
            lastMessageId = newId;
        }

        scrollToBottom();
    }

    // ===== INIT =====
    document.addEventListener('DOMContentLoaded', () => {
        initSidebar();
        initPolling();
        initSessionTimer();
        scrollToBottom();
    });

    window.addEventListener('beforeunload', () => {
        setTypingStatus(false);
        stopPolling();
    });

})();
</script>
</body>
</html>
