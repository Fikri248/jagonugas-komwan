<?php
// student-dashboard.php - v4.3 REMOVE CREATE BUTTON FROM EMPTY STATE
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ✅ TRACK VISITOR
if (file_exists(__DIR__ . '/track-visitor.php')) {
    require_once __DIR__ . '/track-visitor.php';
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = time();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: " . $BASE . "/login.php");
    exit;
}

$userId    = (int)$_SESSION['user_id'];
$loginTime = (int)($_SESSION['login_time'] ?? time());

$pdo = null;
try {
    $pdo = (new Database())->getConnection();
} catch (Throwable $e) {
    $pdo = null;
}

$currentUser = null;
$gemBalance  = 0;
$name        = 'User';

// Helper: Get Avatar URL
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar, $base = '') {
        if (empty($avatar)) return '';
        if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
        return $base . '/' . ltrim($avatar, '/');
    }
}

$successMsg = '';
if (isset($_GET['profile_updated'])) $successMsg = 'Profil berhasil diperbarui!';
if (isset($_GET['rated'])) $successMsg = 'Review berhasil dikirim! Terima kasih atas feedback Anda.';
if (isset($_GET['booking_success'])) $successMsg = 'Booking mentor berhasil! Mentor akan segera menghubungi Anda.';

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();
        if ($currentUser) {
            $gemBalance = (int)($currentUser['gems'] ?? 0);
            $name = (string)($currentUser['name'] ?? 'User');
        }
    } catch (Throwable $e) {
        $gemBalance = (int)($_SESSION['gems'] ?? 0);
        $name = (string)($_SESSION['name'] ?? 'User');
    }
} else {
    $gemBalance = (int)($_SESSION['gems'] ?? 0);
    $name = (string)($_SESSION['name'] ?? 'User');
}

$activeSeconds = time() - $loginTime;

function formatActiveTime(int $seconds): string {
    $minutes = (int)floor($seconds / 60);
    if ($minutes < 5) return '< 5 menit';
    $roundedMinutes = (int)(floor($minutes / 5) * 5);
    $hours = (int)floor($roundedMinutes / 60);
    $mins = $roundedMinutes % 60;
    if ($hours > 0) return $mins > 0 ? $hours . ' jam ' . $mins . ' menit' : $hours . ' jam';
    return $roundedMinutes . ' menit';
}
$activeTimeFormatted = formatActiveTime($activeSeconds);

function time_elapsed(string $datetime): string {
    $tz = new DateTimeZone('Asia/Jakarta');
    $now = new DateTime('now', $tz);
    try { $ago = new DateTime($datetime, $tz); } catch (Throwable $e) { return 'Baru saja'; }
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . ' hari yang lalu';
    if ($diff->h > 0) return $diff->h . ' jam yang lalu';
    if ($diff->i > 0) return $diff->i . ' menit yang lalu';
    return 'Baru saja';
}

// Stats Forum
$totalReplies = 0;
$totalGemsEarned = 0;
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT fr.thread_id) FROM forum_replies fr JOIN forum_threads ft ON fr.thread_id = ft.id WHERE fr.user_id = ? AND ft.user_id != ?");
        $stmt->execute([$userId, $userId]);
        $totalReplies = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(ft.gem_reward), 0) FROM forum_replies fr JOIN forum_threads ft ON fr.thread_id = ft.id WHERE fr.user_id = ? AND fr.is_best_answer = 1");
        $stmt->execute([$userId]);
        $totalGemsEarned = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}
}

// Mentor Booking Stats
$active_sessions = 0; $pending_sessions = 0; $completed_sessions = 0; $need_rating = 0;
if ($pdo) {
    try { $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE student_id = ? AND status = 'ongoing'"); $stmt->execute([$userId]); $active_sessions = (int)$stmt->fetchColumn(); } catch (Throwable $e) {}
    try { $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE student_id = ? AND status = 'pending'"); $stmt->execute([$userId]); $pending_sessions = (int)$stmt->fetchColumn(); } catch (Throwable $e) {}
    try { $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE student_id = ? AND status = 'completed'"); $stmt->execute([$userId]); $completed_sessions = (int)$stmt->fetchColumn(); } catch (Throwable $e) {}
    try { $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE student_id = ? AND status = 'completed' AND rating IS NULL"); $stmt->execute([$userId]); $need_rating = (int)$stmt->fetchColumn(); } catch (Throwable $e) {}
}

// My Questions - MAX 2 CARDS
$myQuestions = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT ft.*, u.name AS author_name, u.avatar AS author_avatar, fc.name AS category_name, (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id) AS reply_count FROM forum_threads ft JOIN users u ON ft.user_id = u.id JOIN forum_categories fc ON ft.category_id = fc.id WHERE ft.user_id = ? ORDER BY ft.created_at DESC LIMIT 2");
        $stmt->execute([$userId]);
        $myQuestions = $stmt->fetchAll();
    } catch (Throwable $e) {}
}

// v4.2: Recent Questions from others - MAX 2 CARDS (bukan 3)
$recentQuestionsLimit = empty($myQuestions) ? 1 : 2;
$recentQuestions = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT ft.*, u.name AS author_name, u.avatar AS author_avatar, fc.name AS category_name, (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id) AS reply_count FROM forum_threads ft JOIN users u ON ft.user_id = u.id JOIN forum_categories fc ON ft.category_id = fc.id WHERE ft.user_id != ? ORDER BY ft.created_at DESC LIMIT {$recentQuestionsLimit}");
        $stmt->execute([$userId]);
        $recentQuestions = $stmt->fetchAll();
    } catch (Throwable $e) {}
}

// Popular Mentors (Max 3)
$popularMentors = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, avatar, specialization, program_studi, hourly_rate, is_verified, total_rating, review_count, CASE WHEN review_count > 0 THEN ROUND(total_rating / review_count, 1) ELSE 0 END AS rating FROM users WHERE role = 'mentor' AND is_verified = 1 AND review_count > 0 AND (total_rating / review_count) >= 4.5 ORDER BY rating DESC LIMIT 3");
        $popularMentors = $stmt->fetchAll();
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #1a202c; background: #f8fafc; min-height: 100vh; }
        
        /* ===== BUTTONS ===== */
        .btn { padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; border: 2px solid transparent; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
        .btn-outline { border: 2px solid #e2e8f0; color: #475569; background: white; }
        .btn-outline:hover { border-color: #667eea; color: #667eea; }
        .btn-light { background: white; color: #667eea; }
        .btn-light:hover { background: #f8fafc; transform: translateY(-2px); }
        .btn-text { background: transparent; color: #667eea; padding: 8px 12px; }
        .btn-text:hover { background: rgba(102, 126, 234, 0.1); }
        .btn-sm { padding: 10px 16px; font-size: 0.85rem; }
        .btn-full { width: 100%; justify-content: center; }
        
        /* ===== ALERTS ===== */
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #16a34a; border: 1px solid #bbf7d0; }
        
        /* ===== DASHBOARD CONTAINER ===== */
        .dash-container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 32px 24px; 
            display: grid; 
            grid-template-columns: 1fr 320px; 
            gap: 32px; 
        }
        .dash-main { 
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }
        
        /* ===== HERO SECTION ===== */
        .dash-hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 32px; color: white; margin-bottom: 32px; position: relative; overflow: hidden; }
        .dash-hero::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100%25" height="100%25" fill="url(%23grid)"/></svg>'); opacity: 0.3; }
        .dash-hero-content { position: relative; z-index: 1; margin-bottom: 24px; }
        .dash-hero-content h1 { font-size: 1.75rem; margin-bottom: 8px; font-weight: 700; }
        .dash-hero-content p { opacity: 0.9; margin-bottom: 20px; max-width: 500px; }
        .dash-hero-stats { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .dash-stat-card { background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 16px; }
        .dash-stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; background: rgba(255,255,255,0.2); }
        .dash-stat-info { display: flex; flex-direction: column; }
        .dash-stat-value { font-size: 1.5rem; font-weight: 700; }
        .dash-stat-label { font-size: 0.8rem; opacity: 0.85; }
        
        /* ===== QUESTIONS SECTION - v4.2 MAX 2 CARDS ONLY ===== */
        .dash-questions { 
            margin-bottom: 32px; 
            width: 100%;
            max-width: 100%;
        }
        .dash-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .dash-section-header h2 { font-size: 1.25rem; color: #1e293b; font-weight: 700; }
        
        /* v4.2: GRID LAYOUT - ONLY 1 OR 2 COLUMNS, NO SPACE */
        .dash-questions-grid {
            display: grid;
            gap: 16px;
            width: 100%;
        }
        
        /* 1 card = full width */
        .dash-questions-grid.cols-1 {
            grid-template-columns: 1fr;
        }
        
        /* 2 cards = 2 columns equal width, NO right space */
        .dash-questions-grid.cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }
        
        /* Empty Placeholder Styling */
        .dash-questions-placeholder {
            background: white;
            border-radius: 16px;
            padding: 60px 40px;
            text-align: center;
            border: 2px dashed #e2e8f0;
        }
        .dash-questions-placeholder i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
        .dash-questions-placeholder h3 { font-size: 1.1rem; color: #475569; margin-bottom: 8px; }
        .dash-questions-placeholder p { color: #94a3b8; font-size: 0.9rem; }
        
        /* Question Card */
        .dash-question-card { 
            background: white; 
            border-radius: 16px; 
            padding: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.04); 
            transition: all 0.2s; 
            display: flex;
            flex-direction: column;
        }
        .dash-question-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .dash-question-card.my-question { border-left: 4px solid #667eea; }
        .dash-question-card.solved { border-left: 4px solid #10b981; }
        .dash-question-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
        .dash-question-meta { display: flex; gap: 12px; font-size: 0.75rem; color: #64748b; flex-wrap: wrap; }
        .dash-question-meta span { display: flex; align-items: center; gap: 4px; white-space: nowrap; }
        .dash-question-reward { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .badge-solved { background: #d1fae5; color: #059669; padding: 4px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 600; display: flex; align-items: center; gap: 4px; white-space: nowrap; }
        .badge-waiting { background: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 600; display: flex; align-items: center; gap: 4px; white-space: nowrap; }
        .gem-reward { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 4px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
        .dash-question-title-link { text-decoration: none; }
        .dash-question-title { 
            font-size: 0.95rem; 
            color: #1e293b; 
            margin-bottom: 8px; 
            font-weight: 600; 
            transition: color 0.2s;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }
        .dash-question-title-link:hover .dash-question-title { color: #667eea; }
        .dash-question-excerpt { 
            font-size: 0.85rem; 
            color: #64748b; 
            line-height: 1.5; 
            margin-bottom: 16px; 
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }
        .dash-question-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid #f1f5f9; margin-top: auto; }
        .dash-question-author { display: flex; align-items: center; gap: 10px; }
        .author-avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.8rem; overflow: hidden; flex-shrink: 0; }
        .author-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .dash-question-author span { font-size: 0.85rem; color: #475569; font-weight: 500; }
        
        /* Empty State */
        .dash-empty-state { background: #fff; border-radius: 16px; padding: 60px 40px; text-align: center; border: 1px solid #e5e7eb; }
        .dash-empty-state i { font-size: 48px; color: #d1d5db; margin-bottom: 16px; display: block; }
        .dash-empty-state h3 { font-size: 18px; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .dash-empty-state p { color: #6b7280; margin-bottom: 20px; font-size: 14px; }
        
        /* ===== SIDEBAR ===== */
        .dash-sidebar { display: flex; flex-direction: column; gap: 20px; }
        .dash-sidebar-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .dash-sidebar-card h3 { font-size: 1rem; color: #1e293b; margin-bottom: 16px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        
        /* ===== MENTOR POPULER WITH ENHANCED ANIMATIONS ===== */
        .mentor-section-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: badgePulse 2s ease-in-out infinite;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4);
        }
        @keyframes badgePulse {
            0%, 100% { transform: scale(1); box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 6px 25px rgba(251, 191, 36, 0.6); }
        }
        
        .dash-mentor-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px; }
        
        .dash-mentor-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideInRight 0.5s ease forwards;
            opacity: 0;
            position: relative;
            overflow: visible;
            border: 2px solid transparent;
        }
        .dash-mentor-item:nth-child(1) { animation-delay: 0.1s; }
        .dash-mentor-item:nth-child(2) { animation-delay: 0.2s; }
        .dash-mentor-item:nth-child(3) { animation-delay: 0.3s; }
        
        .dash-mentor-item:hover {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            transform: translateX(6px);
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }
        
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .mentor-star-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            animation: starFloat 3s ease-in-out infinite, starGlow 2s ease-in-out infinite;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.5);
            z-index: 10;
        }
        .dash-mentor-item:nth-child(1) .mentor-star-badge { animation-delay: 0s; }
        .dash-mentor-item:nth-child(2) .mentor-star-badge { animation-delay: 0.3s; }
        .dash-mentor-item:nth-child(3) .mentor-star-badge { animation-delay: 0.6s; }
        
        @keyframes starFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-6px) rotate(10deg); }
            50% { transform: translateY(-3px) rotate(0deg); }
            75% { transform: translateY(-8px) rotate(-10deg); }
        }
        @keyframes starGlow {
            0%, 100% { box-shadow: 0 4px 15px rgba(251, 191, 36, 0.5); }
            50% { box-shadow: 0 4px 25px rgba(251, 191, 36, 0.9), 0 0 40px rgba(251, 191, 36, 0.4); }
        }
        
        .mentor-rank {
            position: absolute;
            top: -6px;
            left: -6px;
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
        }
        
        .dash-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.9rem; overflow: hidden; flex-shrink: 0; }
        .dash-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .dash-mentor-info { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .dash-mentor-info .name { font-weight: 600; color: #1e293b; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dash-mentor-info .expertise { font-size: 0.75rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dash-mentor-rating { color: #fbbf24; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 4px; background: linear-gradient(135deg, #fffbeb, #fef3c7); padding: 4px 10px; border-radius: 20px; }
        
        .dash-mentor-empty { text-align: center; padding: 32px 16px; color: #94a3b8; }
        .dash-mentor-empty i { font-size: 2.5rem; margin-bottom: 12px; display: block; color: #cbd5e1; }
        .dash-mentor-empty p { font-size: 0.85rem; margin-bottom: 16px; }
        
        /* ===== SESSION STATS CARD ===== */
        .session-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .session-stat-item { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 16px 8px; background: #f8fafc; border-radius: 12px; transition: all 0.2s; text-decoration: none; }
        .session-stat-item:hover { background: linear-gradient(135deg, #eff6ff, #dbeafe); transform: translateY(-2px); }
        .session-stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 10px; }
        .session-stat-icon.ongoing { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
        .session-stat-icon.pending { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; }
        .session-stat-icon.rating { background: linear-gradient(135deg, #fce7f3, #fbcfe8); color: #db2777; }
        .session-stat-number { font-size: 1.5rem; font-weight: 700; color: #1e293b; line-height: 1; margin-bottom: 4px; }
        .session-stat-label { font-size: 0.7rem; color: #64748b; font-weight: 500; }
        .session-stat-item.highlight .session-stat-number { color: #db2777; }
        .session-stat-item.highlight { background: linear-gradient(135deg, #fdf2f8, #fce7f3); border: 1px solid #fbcfe8; }
        .session-stat-item.highlight:hover { background: linear-gradient(135deg, #fce7f3, #fbcfe8); }
        
        /* ===== FULL WIDTH SECTION - v4.2 NO RIGHT SPACE ===== */
        .dash-full-section { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 0 24px 32px; 
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .dash-container { grid-template-columns: 1fr; }
            .dash-sidebar { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
            .dash-sidebar-card { grid-column: span 1; }
            .dash-sidebar-card:first-child { grid-column: 1 / -1; }
        }
        @media (max-width: 768px) {
            .dash-hero-stats { grid-template-columns: 1fr; }
            .dash-container { padding: 20px 16px; }
            .dash-full-section { padding: 0 16px 24px; }
            
            /* All single column on mobile */
            .dash-questions-grid.cols-2 {
                grid-template-columns: 1fr;
            }
            
            .dash-sidebar { grid-template-columns: 1fr; }
            .session-stats-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
            .session-stat-item { padding: 12px 6px; }
            .session-stat-icon { width: 32px; height: 32px; font-size: 0.95rem; }
            .session-stat-number { font-size: 1.25rem; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/student-navbar.php'; ?>

<div class="dash-container">
    <main class="dash-main">
        <?php if ($successMsg): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
        </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <section class="dash-hero">
            <div class="dash-hero-content">
                <h1>Lagi Kesulitan?</h1>
                <p>Tulis pertanyaan lo dan tunggu mentor atau mahasiswa lain bantu jawabnya.</p>
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-create.php" class="btn btn-light">
                    <i class="bi bi-pencil-square"></i> Tanya Sekarang
                </a>
            </div>
            <div class="dash-hero-stats">
                <div class="dash-stat-card">
                    <div class="dash-stat-icon"><i class="bi bi-chat-heart"></i></div>
                    <div class="dash-stat-info">
                        <span class="dash-stat-value"><?php echo (int)$totalReplies; ?></span>
                        <span class="dash-stat-label">Jawaban yang lo bantu</span>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <div class="dash-stat-icon"><i class="bi bi-gem"></i></div>
                    <div class="dash-stat-info">
                        <span class="dash-stat-value"><?php echo (int)$totalGemsEarned; ?></span>
                        <span class="dash-stat-label">Gem yang lo dapet</span>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <div class="dash-stat-icon"><i class="bi bi-clock-history"></i></div>
                    <div class="dash-stat-info">
                        <span class="dash-stat-value"><?php echo htmlspecialchars($activeTimeFormatted); ?></span>
                        <span class="dash-stat-label">Waktu aktif</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- My Questions Section - MAX 2 CARDS -->
        <section class="dash-questions">
            <div class="dash-section-header">
                <h2>Pertanyaan yang Lo Ajukan</h2>
                <?php if (!empty($myQuestions)): ?>
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php?filter=my" class="btn btn-text">Lihat Semua</a>
                <?php endif; ?>
            </div>
            
            <?php if (empty($myQuestions)): ?>
            <!-- v4.3: TOMBOL BUAT PERTANYAAN DIHAPUS -->
            <div class="dash-questions-grid cols-1">
                <div class="dash-questions-placeholder">
                    <i class="bi bi-chat-square-text"></i>
                    <h3>Belum Ada Pertanyaan</h3>
                    <p>Lo belum pernah bikin pertanyaan. Yuk mulai tanya sekarang!</p>
                </div>
            </div>
            <?php else: ?>
            <?php $qCount = count($myQuestions); ?>
            <div class="dash-questions-grid cols-<?php echo $qCount; ?>">
                <?php foreach ($myQuestions as $q): ?>
                <article class="dash-question-card my-question <?php echo !empty($q['is_solved']) ? 'solved' : ''; ?>">
                    <div class="dash-question-header">
                        <div class="dash-question-meta">
                            <span class="category"><i class="bi bi-folder"></i> <?php echo htmlspecialchars((string)$q['category_name']); ?></span>
                            <span class="time"><i class="bi bi-clock"></i> <?php echo htmlspecialchars(time_elapsed((string)$q['created_at'])); ?></span>
                            <span class="replies"><i class="bi bi-chat-dots"></i> <?php echo (int)$q['reply_count']; ?> jawaban</span>
                        </div>
                        <div class="dash-question-reward">
                            <?php if (!empty($q['is_solved'])): ?>
                                <span class="badge-solved"><i class="bi bi-check-circle-fill"></i> Terjawab</span>
                            <?php else: ?>
                                <span class="badge-waiting"><i class="bi bi-hourglass-split"></i> Menunggu Jawaban</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="dash-question-title-link">
                        <h3 class="dash-question-title"><?php echo htmlspecialchars((string)$q['title']); ?></h3>
                    </a>
                    <p class="dash-question-excerpt"><?php echo htmlspecialchars(mb_substr((string)($q['content'] ?? ''), 0, 100)) . '...'; ?></p>
                    <div class="dash-question-footer">
                        <div class="dash-question-author"></div>
                        <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="btn btn-outline btn-sm">
                            <?php echo ((int)$q['reply_count'] > 0) ? 'Lihat Jawaban' : 'Lihat Detail'; ?>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Sidebar -->
    <aside class="dash-sidebar">
        <div class="dash-sidebar-card">
            <h3>
                Mentor Populer
                <span class="mentor-section-badge"><i class="bi bi-fire"></i> TOP 3</span>
            </h3>
            <?php if (empty($popularMentors)): ?>
            <div class="dash-mentor-empty">
                <i class="bi bi-person-video3"></i>
                <p>Belum ada mentor yang memenuhi kriteria saat ini.</p>
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-mentor.php" class="btn btn-primary btn-sm btn-full">Lihat Semua Mentor</a>
            </div>
            <?php else: ?>
            <div class="dash-mentor-list">
                <?php $rank = 1; foreach ($popularMentors as $mentor): ?>
                <div class="dash-mentor-item">
                    <span class="mentor-rank"><?php echo $rank; ?></span>
                    <span class="mentor-star-badge">⭐</span>
                    <div class="dash-avatar">
                        <?php $mentorAvatarUrl = get_avatar_url($mentor['avatar'] ?? '', $BASE); ?>
                        <?php if ($mentorAvatarUrl): ?><img src="<?php echo htmlspecialchars($mentorAvatarUrl); ?>" alt="Avatar" referrerpolicy="no-referrer"><?php else: echo strtoupper(substr((string)$mentor['name'], 0, 1)); endif; ?>
                    </div>
                    <div class="dash-mentor-info">
                        <span class="name"><?php echo htmlspecialchars((string)$mentor['name']); ?></span>
                        <span class="expertise"><?php echo htmlspecialchars((string)($mentor['specialization'] ?? '')); ?></span>
                    </div>
                    <div class="dash-mentor-rating"><i class="bi bi-star-fill"></i> <?php echo number_format((float)($mentor['rating'] ?? 0), 1); ?></div>
                </div>
                <?php $rank++; endforeach; ?>
            </div>
            <a href="<?php echo htmlspecialchars($BASE); ?>/student-mentor.php" class="btn btn-outline btn-full btn-sm">Lihat Semua Mentor</a>
            <?php endif; ?>
        </div>
        
        <div class="dash-sidebar-card">
            <h3><i class="bi bi-calendar-check"></i> Status Sesi</h3>
            <div class="session-stats-grid">
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-sessions.php?filter=ongoing" class="session-stat-item">
                    <div class="session-stat-icon ongoing"><i class="bi bi-play-circle-fill"></i></div>
                    <span class="session-stat-number"><?php echo $active_sessions; ?></span>
                    <span class="session-stat-label">Berlangsung</span>
                </a>
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-sessions.php?filter=pending" class="session-stat-item">
                    <div class="session-stat-icon pending"><i class="bi bi-hourglass-split"></i></div>
                    <span class="session-stat-number"><?php echo $pending_sessions; ?></span>
                    <span class="session-stat-label">Menunggu</span>
                </a>
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-sessions.php?filter=completed" class="session-stat-item <?php echo $need_rating > 0 ? 'highlight' : ''; ?>">
                    <div class="session-stat-icon rating"><i class="bi bi-star-fill"></i></div>
                    <span class="session-stat-number"><?php echo $need_rating; ?></span>
                    <span class="session-stat-label">Perlu Rating</span>
                </a>
            </div>
        </div>
    </aside>
</div>

<?php $showRecentFullWidth = !empty($myQuestions); ?>

<?php if ($showRecentFullWidth): ?>
<!-- v4.2: Section Pertanyaan Mahasiswa Lain - MAX 2 CARDS, NO RIGHT SPACE -->
<div class="dash-full-section">
    <section class="dash-questions">
        <div class="dash-section-header">
            <h2>Pertanyaan dari Mahasiswa Lain</h2>
            <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php" class="btn btn-text">Lihat Semua</a>
        </div>
        <?php $rCount = count($recentQuestions); ?>
        <div class="dash-questions-grid cols-<?php echo max(1, $rCount); ?>">
            <?php if (empty($recentQuestions)): ?>
            <div class="dash-empty-state"><i class="bi bi-chat-square-text"></i><h3>Belum Ada Pertanyaan</h3><p>Belum ada pertanyaan dari mahasiswa lain.</p><a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php" class="btn btn-primary">Lihat Forum</a></div>
            <?php else: ?>
                <?php foreach ($recentQuestions as $q): ?>
                <article class="dash-question-card <?php echo !empty($q['is_solved']) ? 'solved' : ''; ?>">
                    <div class="dash-question-header">
                        <div class="dash-question-meta">
                            <span class="category"><i class="bi bi-folder"></i> <?php echo htmlspecialchars((string)$q['category_name']); ?></span>
                            <span class="time"><i class="bi bi-clock"></i> <?php echo htmlspecialchars(time_elapsed((string)$q['created_at'])); ?></span>
                        </div>
                        <div class="dash-question-reward">
                            <?php if (!empty($q['is_solved'])): ?><span class="badge-solved"><i class="bi bi-check-circle-fill"></i> Terjawab</span><?php endif; ?>
                            <span class="gem-reward">+<?php echo (int)($q['gem_reward'] ?? 0); ?> gem</span>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="dash-question-title-link"><h3 class="dash-question-title"><?php echo htmlspecialchars((string)$q['title']); ?></h3></a>
                    <p class="dash-question-excerpt"><?php echo htmlspecialchars(mb_substr((string)($q['content'] ?? ''), 0, 80)) . '...'; ?></p>
                    <div class="dash-question-footer">
                        <div class="dash-question-author">
                            <div class="author-avatar"><?php $authorAvatarUrl = get_avatar_url($q['author_avatar'] ?? '', $BASE); if ($authorAvatarUrl): ?><img src="<?php echo htmlspecialchars($authorAvatarUrl); ?>" alt="Avatar" referrerpolicy="no-referrer"><?php else: echo strtoupper(substr((string)$q['author_name'], 0, 1)); endif; ?></div>
                            <span><?php echo htmlspecialchars((string)$q['author_name']); ?></span>
                        </div>
                        <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="btn btn-outline btn-sm"><?php echo !empty($q['is_solved']) ? 'Lihat Jawaban' : 'Jawab'; ?></a>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php else: ?>
<div class="dash-container">
    <main class="dash-main" style="grid-column: 1 / -1;">
        <section class="dash-questions">
            <div class="dash-section-header"><h2>Pertanyaan dari Mahasiswa Lain</h2><a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php" class="btn btn-text">Lihat Semua</a></div>
            <div class="dash-questions-grid cols-1">
                <?php if (empty($recentQuestions)): ?>
                <div class="dash-empty-state"><i class="bi bi-chat-square-text"></i><h3>Belum Ada Pertanyaan</h3><p>Belum ada pertanyaan dari mahasiswa lain.</p><a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php" class="btn btn-primary">Lihat Forum</a></div>
                <?php else: ?>
                    <?php foreach ($recentQuestions as $q): ?>
                    <article class="dash-question-card <?php echo !empty($q['is_solved']) ? 'solved' : ''; ?>">
                        <div class="dash-question-header">
                            <div class="dash-question-meta">
                                <span class="category"><i class="bi bi-folder"></i> <?php echo htmlspecialchars((string)$q['category_name']); ?></span>
                                <span class="time"><i class="bi bi-clock"></i> <?php echo htmlspecialchars(time_elapsed((string)$q['created_at'])); ?></span>
                                <span class="replies"><i class="bi bi-chat-dots"></i> <?php echo (int)$q['reply_count']; ?> jawaban</span>
                            </div>
                            <div class="dash-question-reward">
                                <?php if (!empty($q['is_solved'])): ?><span class="badge-solved"><i class="bi bi-check-circle-fill"></i> Terjawab</span><?php endif; ?>
                                <span class="gem-reward">+<?php echo (int)($q['gem_reward'] ?? 0); ?> gem</span>
                            </div>
                        </div>
                        <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="dash-question-title-link"><h3 class="dash-question-title"><?php echo htmlspecialchars((string)$q['title']); ?></h3></a>
                        <p class="dash-question-excerpt"><?php echo htmlspecialchars(mb_substr((string)($q['content'] ?? ''), 0, 100)) . '...'; ?></p>
                        <div class="dash-question-footer">
                            <div class="dash-question-author">
                                <div class="author-avatar"><?php $authorAvatarUrl = get_avatar_url($q['author_avatar'] ?? '', $BASE); if ($authorAvatarUrl): ?><img src="<?php echo htmlspecialchars($authorAvatarUrl); ?>" alt="Avatar" referrerpolicy="no-referrer"><?php else: echo strtoupper(substr((string)$q['author_name'], 0, 1)); endif; ?></div>
                                <span><?php echo htmlspecialchars((string)$q['author_name']); ?></span>
                            </div>
                            <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="btn btn-outline btn-sm"><?php echo !empty($q['is_solved']) ? 'Lihat Jawaban' : 'Jawab'; ?></a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<?php endif; ?>

</body>
</html>
