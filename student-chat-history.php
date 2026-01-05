<?php
// student-chat-history.php v3.1 - Avatar Google Fix + Compact Button Layout
// HANYA tampilkan completed sessions (ended_at IS NOT NULL + status != 'cancelled')
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

// ✅ TRACK VISITOR
if (file_exists(__DIR__ . '/track-visitor.php')) {
    require_once __DIR__ . '/track-visitor.php';
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}

$student_id = $_SESSION['user_id'];

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed.');
}

// ===== v3.0: Query conversations - HANYA completed (ended_at IS NOT NULL + bukan cancelled) =====
$stmt = $pdo->prepare("
    SELECT 
        c.id AS conversation_id,
        c.created_at AS conversation_started,
        c.updated_at AS last_activity,
        c.session_id,
        u.id AS mentor_id,
        u.name AS mentor_name,
        u.avatar AS mentor_avatar,
        u.program_studi AS mentor_prodi,
        s.ended_at AS session_ended_at,
        s.status AS session_status,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) AS message_count,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND is_read = 0) AS unread_count,
        (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message,
        (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_time,
        (SELECT sender_id FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_sender_id
    FROM conversations c
    JOIN users u ON c.mentor_id = u.id
    INNER JOIN sessions s ON c.session_id = s.id
    WHERE c.student_id = ?
      AND s.ended_at IS NOT NULL
      AND s.status != 'cancelled'
    ORDER BY s.ended_at DESC, c.updated_at DESC
");
$stmt->execute([$student_id, $student_id]);
$chatSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query semua mentor untuk fitur pencarian (yang punya completed session)
$stmtMentors = $pdo->prepare("
    SELECT DISTINCT u.id, u.name, u.avatar, u.program_studi, u.expertise 
    FROM users u
    JOIN sessions s ON s.mentor_id = u.id
    WHERE s.student_id = ?
      AND s.ended_at IS NOT NULL
      AND s.status != 'cancelled'
      AND u.role = 'mentor' 
      AND u.is_verified = 1
    ORDER BY u.name ASC
");
$stmtMentors->execute([$student_id]);
$allMentors = $stmtMentors->fetchAll(PDO::FETCH_ASSOC);

// ===== v3.1: Helper untuk avatar URL - sama dengan student-settings.php =====
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar, $base = '') {
        if (empty($avatar)) return '';
        // Deteksi URL eksternal (Google, dll)
        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }
        return $base . '/' . ltrim($avatar, '/');
    }
}

// Helper untuk format waktu relatif
if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        if (!$datetime) return '';
        $tz = new DateTimeZone('Asia/Jakarta');
        $now = new DateTime('now', $tz);
        $ago = new DateTime($datetime, $tz);
        $diff = $now->diff($ago);
        
        if ($diff->y > 0) return $diff->y . ' tahun lalu';
        if ($diff->m > 0) return $diff->m . ' bulan lalu';
        if ($diff->d > 0) return $diff->d . ' hari lalu';
        if ($diff->h > 0) return $diff->h . ' jam lalu';
        if ($diff->i > 0) return $diff->i . ' menit lalu';
        return 'Baru saja';
    }
}

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histori Chat - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1a202c;
            background: #f8fafc;
            min-height: 100vh;
        }
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 40px;
        }

        /* ===== BUTTONS - v3.1 Compact & Aligned ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn i {
            font-size: 1.1rem;
            line-height: 1;
            display: inline-flex;
            align-items: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-outline {
            border: 2px solid #e2e8f0;
            color: #4a5568;
            background: transparent;
        }
        .btn-outline:hover {
            border-color: #667eea;
            color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.85rem;
        }
        .btn-sm i {
            font-size: 1rem;
        }

        /* ===== CHAT HISTORY CONTAINER ===== */
        .chat-history-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .chat-history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .chat-history-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .chat-history-header h1 i { color: #667eea; }

        /* ===== SEARCH MENTOR ===== */
        .search-mentor-wrapper {
            position: relative;
            min-width: 320px;
        }
        .search-mentor-input-group {
            display: flex;
            align-items: center;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .search-mentor-input-group:focus-within {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .search-mentor-input-group i.search-icon {
            padding: 0 12px;
            color: #94a3b8;
        }
        .search-mentor-input {
            flex: 1;
            border: none;
            outline: none;
            padding: 12px 0;
            font-size: 0.95rem;
            background: transparent;
        }
        .search-mentor-input::placeholder {
            color: #94a3b8;
        }
        .btn-reset-search {
            background: none;
            border: none;
            padding: 8px 12px;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s;
            display: none;
        }
        .btn-reset-search.show {
            display: block;
        }
        .btn-reset-search:hover {
            color: #ef4444;
        }

        /* ===== SEARCH RESULTS DROPDOWN ===== */
        .search-results-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            border: 1px solid #e2e8f0;
        }
        .search-results-dropdown.show {
            display: block;
            animation: slideDown 0.2s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .search-result-header {
            padding: 12px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .search-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            text-decoration: none;
            color: inherit;
            transition: background 0.15s;
            border-bottom: 1px solid #f1f5f9;
        }
        .search-result-item:last-child {
            border-bottom: none;
        }
        .search-result-item:hover {
            background: linear-gradient(135deg, #f8faff 0%, #f1f5f9 100%);
        }
        .search-result-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        .search-result-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .search-result-info {
            flex: 1;
            min-width: 0;
        }
        .search-result-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.95rem;
            margin-bottom: 2px;
        }
        .search-result-meta {
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .search-result-arrow {
            color: #cbd5e1;
            font-size: 1rem;
        }
        .search-result-item:hover .search-result-arrow {
            color: #667eea;
        }

        /* ===== SEARCH EMPTY/NOT FOUND STATE ===== */
        .search-empty-state {
            padding: 32px 20px;
            text-align: center;
        }
        .search-empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 12px;
        }
        .search-empty-state h4 {
            font-size: 1rem;
            color: #475569;
            margin-bottom: 6px;
        }
        .search-empty-state p {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 16px;
        }

        /* ===== CHAT LIST ===== */
        .chat-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .chat-item {
            background: #ffffff;
            border-radius: 16px;
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .chat-item:hover {
            border-color: #667eea;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.12);
            transform: translateY(-2px);
        }
        .chat-item.has-unread {
            border-left: 4px solid #667eea;
            background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
        }
        .chat-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
        }
        .chat-avatar img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }
        .unread-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }
        .chat-info { flex: 1; min-width: 0; }
        .chat-info-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        .chat-mentor-name { font-weight: 600; color: #1e293b; font-size: 1rem; }
        .chat-time { font-size: 0.8rem; color: #94a3b8; }
        .chat-preview {
            color: #64748b;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 6px;
        }
        .chat-preview.unread { font-weight: 600; color: #334155; }
        .chat-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        .chat-meta span { display: flex; align-items: center; gap: 4px; }
        .chat-meta .badge-completed {
            background: #e0e7ff;
            color: #4f46e5;
            padding: 2px 8px;
            border-radius: 6px;
            font-weight: 600;
        }
        .chat-arrow { color: #cbd5e1; font-size: 1.25rem; transition: transform 0.2s; }
        .chat-item:hover .chat-arrow { color: #667eea; transform: translateX(4px); }

        /* ===== EMPTY STATE - v3.1 Compact Button ===== */
        .empty-chat-history {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            border: 2px dashed #e2e8f0;
        }
        .empty-chat-history .empty-icon {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
            display: block;
        }
        .empty-chat-history h2 { 
            font-size: 1.25rem; 
            color: #475569; 
            margin-bottom: 0.5rem; 
            font-weight: 700;
        }
        .empty-chat-history p { 
            color: #94a3b8; 
            margin-bottom: 1.5rem; 
            max-width: 320px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ===== FILTER SECTION ===== */
        .filter-section {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }
        .filter-tabs {
            display: flex;
            gap: 8px;
        }
        .filter-tab {
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }
        .filter-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .chat-history-header {
                flex-direction: column;
                align-items: stretch;
            }
            .search-mentor-wrapper {
                min-width: 100%;
            }
        }
        @media (max-width: 640px) {
            .chat-item { padding: 1rem; }
            .chat-avatar { width: 48px; height: 48px; font-size: 1rem; }
            .chat-info-top { flex-direction: column; align-items: flex-start; gap: 4px; }
            .filter-section { flex-direction: column; align-items: stretch; }
            .btn { 
                padding: 0.65rem 1.25rem; 
                font-size: 0.9rem; 
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/student-navbar.php'; ?>

<div class="chat-history-container">
    <div class="chat-history-header">
        <h1>
            <i class="bi bi-clock-history"></i>
            Histori Chat
        </h1>
        
        <!-- Search Mentor Input -->
        <div class="search-mentor-wrapper">
            <div class="search-mentor-input-group">
                <i class="bi bi-search search-icon"></i>
                <input 
                    type="text" 
                    class="search-mentor-input" 
                    id="searchMentorInput" 
                    placeholder="Cari mentor untuk mulai chat..."
                    autocomplete="off"
                >
                <button type="button" class="btn-reset-search" id="btnResetSearch" title="Reset pencarian">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <!-- Search Results Dropdown -->
            <div class="search-results-dropdown" id="searchResultsDropdown">
                <div class="search-result-header">
                    <span id="searchResultCount">0 mentor ditemukan</span>
                </div>
                <div id="searchResultsList">
                    <!-- Results will be populated here -->
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($chatSessions)): ?>
        <div class="empty-chat-history">
            <i class="bi bi-chat-square-dots empty-icon"></i>
            <h2>Belum Ada Histori Chat</h2>
            <p>Histori chat akan muncul setelah Anda menyelesaikan sesi mentoring dengan mentor.</p>
            <a href="<?= $BASE ?>/student-mentor.php" class="btn btn-primary">
                <i class="bi bi-search"></i>
                <span>Cari Mentor</span>
            </a>
        </div>
    <?php else: ?>
        <div class="chat-list" id="chatList">
            <?php foreach ($chatSessions as $chat): ?>
                <?php 
                // v3.1: Avatar URL dengan filter_var untuk Google URL
                $avatarUrl = get_avatar_url($chat['mentor_avatar'] ?? '', $BASE);
                $hasUnread = (int)$chat['unread_count'] > 0;
                $isMyLastMessage = ($chat['last_sender_id'] == $student_id);
                $previewPrefix = $isMyLastMessage ? 'Anda: ' : '';
                $initial = mb_strtoupper(mb_substr($chat['mentor_name'], 0, 1, 'UTF-8'), 'UTF-8');
                ?>
                <a href="<?= $BASE ?>/student-chat.php?conversation_id=<?= $chat['conversation_id'] ?>" 
                   class="chat-item <?= $hasUnread ? 'has-unread' : '' ?>"
                   data-name="<?= htmlspecialchars(strtolower($chat['mentor_name'])) ?>"
                   data-has-unread="<?= $hasUnread ? '1' : '0' ?>">
                    <div class="chat-avatar">
                        <?php if ($avatarUrl): ?>
                            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <?= htmlspecialchars($initial) ?>
                        <?php endif; ?>
                        <?php if ($hasUnread): ?>
                            <span class="unread-badge"><?= $chat['unread_count'] > 9 ? '9+' : $chat['unread_count'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="chat-info">
                        <div class="chat-info-top">
                            <span class="chat-mentor-name"><?= htmlspecialchars($chat['mentor_name']) ?></span>
                            <span class="chat-time"><?= time_ago($chat['last_message_time'] ?? $chat['session_ended_at']) ?></span>
                        </div>
                        <div class="chat-preview <?= $hasUnread ? 'unread' : '' ?>">
                            <?php if (!empty($chat['last_message'])): ?>
                                <?= $previewPrefix ?><?= htmlspecialchars(mb_substr($chat['last_message'], 0, 50)) ?><?= mb_strlen($chat['last_message']) > 50 ? '...' : '' ?>
                            <?php else: ?>
                                Tidak ada pesan
                            <?php endif; ?>
                        </div>
                        <div class="chat-meta">
                            <span class="badge-completed"><i class="bi bi-check-circle"></i> Selesai</span>
                            <span><i class="bi bi-chat-dots"></i> <?= (int)$chat['message_count'] ?> pesan</span>
                            <?php if ($chat['mentor_prodi']): ?>
                                <span><i class="bi bi-mortarboard"></i> <?= htmlspecialchars($chat['mentor_prodi']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <i class="bi bi-chevron-right chat-arrow"></i>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Data mentor dari PHP
const mentorsData = <?= json_encode($allMentors) ?>;
const BASE_PATH = '<?= $BASE ?>';

// Elements
const searchInput = document.getElementById('searchMentorInput');
const searchDropdown = document.getElementById('searchResultsDropdown');
const searchResultsList = document.getElementById('searchResultsList');
const searchResultCount = document.getElementById('searchResultCount');
const btnResetSearch = document.getElementById('btnResetSearch');
const filterTabs = document.querySelectorAll('.filter-tab');
const chatList = document.getElementById('chatList');

// v3.1: Helper function untuk avatar URL - dengan deteksi Google URL
function getAvatarUrl(avatar) {
    if (!avatar) return '';
    // Cek apakah URL eksternal (Google, dll)
    if (avatar.startsWith('http://') || avatar.startsWith('https://')) return avatar;
    return BASE_PATH + '/' + avatar.replace(/^\//, '');
}

// Helper function untuk mendapatkan initial
function getInitial(name) {
    return name ? name.charAt(0).toUpperCase() : '?';
}

// Search function
function searchMentors(query) {
    query = query.toLowerCase().trim();
    
    if (query === '') {
        searchDropdown.classList.remove('show');
        btnResetSearch.classList.remove('show');
        if (chatList) {
            chatList.querySelectorAll('.chat-item').forEach(item => {
                item.style.display = '';
            });
        }
        return;
    }
    
    btnResetSearch.classList.add('show');
    
    if (chatList) {
        chatList.querySelectorAll('.chat-item').forEach(item => {
            const name = item.dataset.name || '';
            item.style.display = name.includes(query) ? '' : 'none';
        });
    }
    
    const results = mentorsData.filter(mentor => {
        const name = (mentor.name || '').toLowerCase();
        const prodi = (mentor.program_studi || '').toLowerCase();
        const expertise = (mentor.expertise || '').toLowerCase();
        return name.includes(query) || prodi.includes(query) || expertise.includes(query);
    });
    
    renderSearchResults(results, query);
    searchDropdown.classList.add('show');
}

// Render search results
function renderSearchResults(results, query) {
    if (results.length === 0) {
        searchResultCount.textContent = 'Mentor tidak ditemukan';
        searchResultsList.innerHTML = `
            <div class="search-empty-state">
                <i class="bi bi-person-x"></i>
                <h4>Tidak Ditemukan</h4>
                <p>Tidak ada histori chat dengan kata kunci "${escapeHtml(query)}"</p>
            </div>
        `;
        return;
    }
    
    searchResultCount.textContent = `${results.length} mentor ditemukan`;
    
    let html = '';
    results.forEach(mentor => {
        const avatarUrl = getAvatarUrl(mentor.avatar);
        // v3.1: Tambah referrerpolicy untuk Google avatar
        const avatarHtml = avatarUrl 
            ? `<img src="${escapeHtml(avatarUrl)}" alt="" referrerpolicy="no-referrer">`
            : getInitial(mentor.name);
        
        html += `
            <a href="${BASE_PATH}/student-chat.php?mentor_id=${mentor.id}" class="search-result-item">
                <div class="search-result-avatar">
                    ${avatarHtml}
                </div>
                <div class="search-result-info">
                    <div class="search-result-name">${highlightText(mentor.name, query)}</div>
                    <div class="search-result-meta">
                        ${mentor.program_studi ? `<span><i class="bi bi-mortarboard"></i> ${highlightText(mentor.program_studi, query)}</span>` : ''}
                        ${mentor.expertise ? `<span><i class="bi bi-star"></i> ${highlightText(mentor.expertise, query)}</span>` : ''}
                    </div>
                </div>
                <i class="bi bi-chevron-right search-result-arrow"></i>
            </a>
        `;
    });
    
    searchResultsList.innerHTML = html;
}

// Highlight matching text
function highlightText(text, query) {
    if (!text || !query) return escapeHtml(text || '');
    const escaped = escapeHtml(text);
    const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
    return escaped.replace(regex, '<strong style="color: #667eea;">$1</strong>');
}

// Escape HTML
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Escape regex special chars
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Reset search
function resetSearch() {
    searchInput.value = '';
    searchDropdown.classList.remove('show');
    btnResetSearch.classList.remove('show');
    if (chatList) {
        chatList.querySelectorAll('.chat-item').forEach(item => {
            item.style.display = '';
        });
    }
    searchInput.focus();
}

// Filter chat list
function filterChatList(filter) {
    if (!chatList) return;
    
    const items = chatList.querySelectorAll('.chat-item');
    items.forEach(item => {
        const hasUnread = item.dataset.hasUnread === '1';
        
        if (filter === 'all') {
            item.style.display = '';
        } else if (filter === 'unread') {
            item.style.display = hasUnread ? '' : 'none';
        }
    });
}

// Event Listeners
searchInput.addEventListener('input', (e) => {
    searchMentors(e.target.value);
});

searchInput.addEventListener('focus', () => {
    if (searchInput.value.trim()) {
        searchMentors(searchInput.value);
    }
});

btnResetSearch.addEventListener('click', resetSearch);

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-mentor-wrapper')) {
        searchDropdown.classList.remove('show');
    }
});

// Filter tabs
filterTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        filterTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        filterChatList(tab.dataset.filter);
    });
});

// Keyboard shortcut: Escape to reset search
searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        resetSearch();
    }
});
</script>

</body>
</html>
