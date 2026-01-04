<?php
// student-sessions.php - Halaman sesi konsultasi mahasiswa v2.5 - Fixed Layout
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

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

$stmt = $pdo->prepare("
    SELECT s.*, 
           u.name AS mentor_name, 
           u.program_studi AS mentor_prodi,
           u.avatar AS mentor_avatar,
           (SELECT c.id FROM conversations c 
            WHERE c.student_id = s.student_id AND c.mentor_id = s.mentor_id 
            ORDER BY c.id DESC LIMIT 1) AS conversation_id
    FROM sessions s
    JOIN users u ON s.mentor_id = u.id
    WHERE s.student_id = ?
    ORDER BY s.created_at DESC
");

$stmt->execute([$student_id]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = ['pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($sessions as $session) {
    if (isset($stats[$session['status']])) $stats[$session['status']]++;
}

$success = NotificationHelper::getSuccess();
$error = NotificationHelper::getError();

if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar, $base = '') {
        if (empty($avatar)) return '';
        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }
        return $base . '/' . ltrim($avatar, '/');
    }
}

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesi Konsultasi - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            color: #1e293b;
        }

        .sessions-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert i { font-size: 1.25rem; }

        .page-header { margin-bottom: 2rem; }
        .page-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
        }
        .page-title i { color: #667eea; font-size: 1.5rem; }

        .btn-book-new {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 12px;
            transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-book-new:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .total-count { font-size: 0.9rem; color: #64748b; font-weight: 500; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem 1rem;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            text-align: center;
        }
        .stat-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin: 0 auto 0.75rem;
        }
        .stat-icon.pending { background: #fef3c7; color: #d97706; }
        .stat-icon.ongoing { background: #dbeafe; color: #2563eb; }
        .stat-icon.completed { background: #d1fae5; color: #059669; }
        .stat-icon.cancelled { background: #fee2e2; color: #dc2626; }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        .stat-label { font-size: 0.85rem; color: #64748b; font-weight: 500; }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-tab:hover { border-color: #667eea; color: #667eea; }
        .filter-tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: transparent;
        }

        .empty-filter-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            display: none;
        }
        .empty-filter-state.show { display: block; }
        .empty-filter-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; }
        .empty-filter-state h3 { font-size: 1.1rem; color: #475569; margin-bottom: 0.5rem; font-weight: 600; }
        .empty-filter-state p { color: #94a3b8; font-size: 0.9rem; margin: 0; }

        .session-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .session-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .session-card:hover {
            border-color: #667eea;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.1);
        }
        .session-card.status-pending { border-left: 4px solid #f59e0b; }
        .session-card.status-ongoing { border-left: 4px solid #3b82f6; }
        .session-card.status-completed { border-left: 4px solid #10b981; }
        .session-card.status-cancelled { border-left: 4px solid #ef4444; }

        .session-main { padding: 1.25rem 1.5rem; }

        .session-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .mentor-info { display: flex; align-items: center; gap: 1rem; }

        .mentor-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        .mentor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mentor-details h3 { font-size: 1rem; font-weight: 600; color: #0f172a; margin-bottom: 2px; }
        .mentor-prodi { font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 4px; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.ongoing { background: #dbeafe; color: #1e40af; }
        .status-badge.completed { background: #d1fae5; color: #065f46; }
        .status-badge.cancelled { background: #fee2e2; color: #991b1b; }

        .session-details-row {
            display: flex;
            gap: 2rem;
            padding: 1rem 0;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 1rem;
        }
        .detail-item { display: flex; align-items: center; gap: 8px; }
        .detail-item i { color: #94a3b8; font-size: 1rem; }
        .detail-item span { font-size: 0.9rem; color: #475569; font-weight: 500; }

        .notes-display {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .notes-display i { color: #0284c7; font-size: 1rem; margin-top: 2px; }
        .notes-display .notes-content { flex: 1; }
        .notes-display .notes-label {
            font-size: 0.75rem;
            color: #0369a1;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .notes-display .notes-text { font-size: 0.9rem; color: #0c4a6e; line-height: 1.5; }

        .reject-reason-display {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .reject-reason-display i { color: #ef4444; font-size: 1rem; margin-top: 2px; }
        .reject-reason-display .reason-content { flex: 1; }
        .reject-reason-display .reason-label {
            font-size: 0.75rem;
            color: #dc2626;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .reject-reason-display .reason-text { font-size: 0.9rem; color: #991b1b; }

        .session-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-chat { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-chat:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-cancel { background: white; color: #ef4444; border: 1px solid #fecaca; }
        .btn-cancel:hover { background: #fef2f2; border-color: #ef4444; }
        .btn-rating { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn-rating:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
            color: white;
        }

        .rating-display {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #fffbeb;
            border-radius: 8px;
            border: 1px solid #fde68a;
        }
        .rating-display .stars { display: flex; gap: 2px; }
        .rating-display .stars i { color: #f59e0b; font-size: 0.9rem; }
        .rating-display .rating-text { font-size: 0.85rem; font-weight: 600; color: #92400e; }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            border: 2px dashed #e2e8f0;
        }
        .empty-state > i { 
            font-size: 4rem; 
            color: #cbd5e1; 
            margin-bottom: 1rem;
            display: block;
        }
        .empty-state h2 { font-size: 1.25rem; color: #475569; margin-bottom: 0.5rem; font-weight: 600; }
        .empty-state p { color: #94a3b8; font-size: 0.95rem; margin-bottom: 1.5rem; }

        .btn-empty-cta {
            display: inline-flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.625rem 1.25rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            line-height: 1;
            border-radius: 10px;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.25);
            white-space: nowrap;
        }
        .btn-empty-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.35);
            color: white;
        }
        .btn-empty-cta i {
            font-size: 0.9rem;
            line-height: 1;
            display: inline-flex;
            align-items: center;
        }
        .btn-empty-cta span {
            line-height: 1;
        }

        /* MODAL BASE */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }

        .modal-container {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 480px;
            margin: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-overlay.active .modal-container { transform: scale(1) translateY(0); }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .modal-header-left { display: flex; align-items: center; gap: 12px; }
        .modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .modal-icon.cancel { background: #fef2f2; color: #ef4444; }
        .modal-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; }
        .modal-subtitle { font-size: 0.85rem; color: #64748b; }

        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: #f1f5f9;
            color: #64748b;
            font-size: 1.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .modal-close:hover { background: #fee2e2; color: #ef4444; }

        .modal-body { padding: 1.5rem; }

        .modal-info-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .modal-mentor-avatar {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        .modal-mentor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .modal-mentor-info h4 { font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-bottom: 2px; }
        .modal-mentor-info p { font-size: 0.8rem; color: #64748b; margin: 0; }

        .modal-session-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .modal-detail-item {
            background: #f8fafc;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-detail-item i { color: #64748b; font-size: 1.1rem; }
        .modal-detail-item .detail-content { flex: 1; }
        .modal-detail-item .detail-label {
            font-size: 0.7rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .modal-detail-item .detail-value { font-size: 0.9rem; font-weight: 600; color: #0f172a; }

        .modal-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .modal-warning i { color: #d97706; font-size: 1.25rem; margin-top: 2px; }
        .modal-warning .warning-content h5 { font-size: 0.9rem; font-weight: 600; color: #92400e; margin-bottom: 4px; }
        .modal-warning .warning-content p { font-size: 0.85rem; color: #a16207; margin: 0; line-height: 1.5; }

        .modal-footer {
            display: flex;
            gap: 0.75rem;
            padding: 1.25rem 1.5rem;
            background: #f8fafc;
            border-radius: 0 0 20px 20px;
        }
        .btn-modal {
            flex: 1;
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-modal-back { background: white; border: 1px solid #e2e8f0; color: #64748b; }
        .btn-modal-back:hover { background: #f1f5f9; color: #475569; }
        .btn-modal-cancel { background: #ef4444; border: none; color: white; }
        .btn-modal-cancel:hover { background: #dc2626; }

        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .stat-card { padding: 1rem; }
            .stat-number { font-size: 1.5rem; }
            .page-header-top { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .session-details-row { flex-direction: column; gap: 0.75rem; }
            .session-actions { flex-direction: column; align-items: stretch; }
            .session-actions .btn { justify-content: center; }
            .modal-footer { flex-direction: column; }
            .modal-session-details { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/student-navbar.php'; ?>
    
    <div class="sessions-container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="page-header-top">
                <h1 class="page-title">
                    <i class="bi bi-calendar-check"></i>
                    Sesi Konsultasi
                </h1>
                <a href="<?= $BASE ?>/student-mentor.php" class="btn-book-new">
                    <i class="bi bi-plus-circle"></i>
                    Book Sesi Baru
                </a>
            </div>
            <span class="total-count"><?= count($sessions) ?> total sesi</span>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon pending"><i class="bi bi-clock"></i></div>
                <div class="stat-number"><?= $stats['pending'] ?></div>
                <div class="stat-label">Menunggu</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon ongoing"><i class="bi bi-play-circle"></i></div>
                <div class="stat-number"><?= $stats['ongoing'] ?></div>
                <div class="stat-label">Berlangsung</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon completed"><i class="bi bi-check-circle"></i></div>
                <div class="stat-number"><?= $stats['completed'] ?></div>
                <div class="stat-label">Selesai</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon cancelled"><i class="bi bi-x-circle"></i></div>
                <div class="stat-number"><?= $stats['cancelled'] ?></div>
                <div class="stat-label">Dibatalkan</div>
            </div>
        </div>

        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">Semua</button>
            <button class="filter-tab" data-filter="pending">Menunggu</button>
            <button class="filter-tab" data-filter="ongoing">Berlangsung</button>
            <button class="filter-tab" data-filter="completed">Selesai</button>
            <button class="filter-tab" data-filter="cancelled">Dibatalkan</button>
        </div>

        <div class="empty-filter-state" id="emptyFilterState">
            <i class="bi bi-inbox"></i>
            <h3 id="emptyFilterTitle">Tidak ada sesi</h3>
            <p id="emptyFilterDesc">Tidak ada sesi dengan status ini.</p>
        </div>

        <?php if (empty($sessions)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h2>Belum Ada Sesi</h2>
                <p>Kamu belum pernah booking sesi konsultasi dengan mentor.</p>
                <a href="<?= $BASE ?>/student-mentor.php" class="btn-empty-cta">
                    <i class="bi bi-search"></i>
                    <span>Cari Mentor</span>
                </a>
            </div>
        <?php else: ?>
            <div class="session-list" id="sessionList">
                <?php foreach ($sessions as $session): 
                    $mentorInitial = strtoupper(substr($session['mentor_name'], 0, 1));
                    $avatarUrl = get_avatar_url($session['mentor_avatar'] ?? '', $BASE);
                    $statusLabels = [
                        'pending' => 'Menunggu',
                        'ongoing' => 'Berlangsung',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan'
                    ];
                    $sessionNotes = $session['notes'] ?? '';
                    $rejectReason = $session['reject_reason'] ?? '';
                ?>
                <div class="session-card status-<?= $session['status'] ?>" data-status="<?= $session['status'] ?>" data-session-id="<?= $session['id'] ?>">
                    <div class="session-main">
                        <div class="session-top">
                            <div class="mentor-info">
                                <div class="mentor-avatar">
                                    <?php if ($avatarUrl): ?>
                                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="" referrerpolicy="no-referrer">
                                    <?php else: ?>
                                        <?= $mentorInitial ?>
                                    <?php endif; ?>
                                </div>
                                <div class="mentor-details">
                                    <h3><?= htmlspecialchars($session['mentor_name']) ?></h3>
                                    <span class="mentor-prodi">
                                        <i class="bi bi-book"></i>
                                        <?= htmlspecialchars($session['mentor_prodi'] ?? 'Mentor') ?>
                                    </span>
                                </div>
                            </div>
                            <span class="status-badge <?= $session['status'] ?>">
                                <?= $statusLabels[$session['status']] ?>
                            </span>
                        </div>

                        <div class="session-details-row">
                            <div class="detail-item">
                                <i class="bi bi-clock"></i>
                                <span><?= (int)$session['duration'] ?> menit</span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-gem"></i>
                                <span><?= number_format($session['price']) ?> Gems</span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-calendar3"></i>
                                <span><?= date('d M Y, H:i', strtotime($session['created_at'])) ?></span>
                            </div>
                        </div>

                        <?php if (!empty($sessionNotes)): ?>
                            <div class="notes-display">
                                <i class="bi bi-chat-quote"></i>
                                <div class="notes-content">
                                    <div class="notes-label">Catatan Kamu</div>
                                    <div class="notes-text"><?= htmlspecialchars($sessionNotes) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($session['status'] === 'cancelled' && !empty($rejectReason)): ?>
                            <div class="reject-reason-display">
                                <i class="bi bi-info-circle"></i>
                                <div class="reason-content">
                                    <div class="reason-label">Alasan Penolakan dari Mentor</div>
                                    <div class="reason-text"><?= htmlspecialchars($rejectReason) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- SESSION ACTIONS WRAPPER - INI YANG PENTING! -->
                        <div class="session-actions">
                            <?php if ($session['status'] === 'ongoing'): ?>
                                <a href="<?= $BASE ?>/student-chat.php?conversation_id=<?= $session['conversation_id'] ?>" class="btn btn-chat">
                                    <i class="bi bi-chat-dots-fill"></i>
                                    Chat Mentor
                                </a>
                            <?php endif; ?>

                            <?php if ($session['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-cancel" onclick="openCancelModal(<?= $session['id'] ?>, '<?= htmlspecialchars($session['mentor_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($session['mentor_prodi'] ?? 'Mentor', ENT_QUOTES) ?>', '<?= $mentorInitial ?>', '<?= htmlspecialchars($avatarUrl, ENT_QUOTES) ?>', <?= (int)$session['duration'] ?>, <?= (int)$session['price'] ?>)">
                                    <i class="bi bi-x-circle"></i>
                                    Batalkan
                                </button>
                            <?php endif; ?>

                            <?php if ($session['status'] === 'completed' && !$session['rating']): ?>
                                <a href="<?= $BASE ?>/session-rating.php?session_id=<?= $session['id'] ?>" class="btn btn-rating">
                                    <i class="bi bi-star-fill"></i>
                                    Beri Rating
                                </a>
                            <?php endif; ?>

                            <?php if ($session['rating']): ?>
                                <div class="rating-display">
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?= $i <= $session['rating'] ? '-fill' : '' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="rating-text"><?= $session['rating'] ?>/5</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- END SESSION ACTIONS -->

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- CANCEL MODAL -->
    <div class="modal-overlay" id="cancelModal">
        <div class="modal-container">
            <div class="modal-header">
                <div class="modal-header-left">
                    <div class="modal-icon cancel"><i class="bi bi-x-circle"></i></div>
                    <div>
                        <h3 class="modal-title">Batalkan Booking</h3>
                        <p class="modal-subtitle">Konfirmasi pembatalan sesi</p>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="closeCancelModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form method="POST" action="<?= $BASE ?>/student-session-cancel.php" id="cancelForm">
                <input type="hidden" name="session_id" id="cancelSessionId">
                
                <div class="modal-body">
                    <div class="modal-info-card">
                        <div class="modal-mentor-avatar" id="cancelModalMentorAvatar">M</div>
                        <div class="modal-mentor-info">
                            <h4 id="cancelModalMentorName">Nama Mentor</h4>
                            <p id="cancelModalMentorProdi">Program Studi</p>
                        </div>
                    </div>

                    <div class="modal-session-details">
                        <div class="modal-detail-item">
                            <i class="bi bi-clock"></i>
                            <div class="detail-content">
                                <div class="detail-label">Durasi</div>
                                <div class="detail-value" id="cancelModalDuration">15 menit</div>
                            </div>
                        </div>
                        <div class="modal-detail-item">
                            <i class="bi bi-gem"></i>
                            <div class="detail-content">
                                <div class="detail-label">Harga</div>
                                <div class="detail-value" id="cancelModalPrice">1,000 Gems</div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div class="warning-content">
                            <h5>Gems Akan Dikembalikan</h5>
                            <p>Jika kamu membatalkan booking ini, gems yang sudah dibayar akan dikembalikan ke saldo kamu secara otomatis.</p>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-modal-back" onclick="closeCancelModal()">
                        Kembali
                    </button>
                    <button type="submit" class="btn-modal btn-modal-cancel">
                        <i class="bi bi-x-circle"></i>
                        Ya, Batalkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-hide alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'all 0.3s';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });

        // Filter tabs
        const filterTabs = document.querySelectorAll('.filter-tab');
        const sessionList = document.getElementById('sessionList');
        const emptyFilterState = document.getElementById('emptyFilterState');
        const emptyFilterTitle = document.getElementById('emptyFilterTitle');
        const emptyFilterDesc = document.getElementById('emptyFilterDesc');

        const filterMessages = {
            pending: { title: 'Tidak ada sesi menunggu', desc: 'Tidak ada booking yang menunggu konfirmasi mentor.', icon: 'bi-clock' },
            ongoing: { title: 'Tidak ada sesi berlangsung', desc: 'Belum ada sesi konsultasi yang sedang aktif.', icon: 'bi-play-circle' },
            completed: { title: 'Tidak ada sesi selesai', desc: 'Belum ada sesi konsultasi yang telah diselesaikan.', icon: 'bi-check-circle' },
            cancelled: { title: 'Tidak ada sesi dibatalkan', desc: 'Tidak ada sesi yang dibatalkan. Bagus!', icon: 'bi-x-circle' },
            all: { title: 'Belum ada sesi', desc: 'Kamu belum pernah booking sesi konsultasi.', icon: 'bi-inbox' }
        };

        filterTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                filterTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                const filter = tab.dataset.filter;
                let visibleCount = 0;

                if (sessionList) {
                    sessionList.querySelectorAll('.session-card').forEach(card => {
                        if (filter === 'all' || card.dataset.status === filter) {
                            card.style.display = '';
                            visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    if (visibleCount === 0) {
                        sessionList.style.display = 'none';
                        emptyFilterState.classList.add('show');
                        emptyFilterState.querySelector('i').className = 'bi ' + filterMessages[filter].icon;
                        emptyFilterTitle.textContent = filterMessages[filter].title;
                        emptyFilterDesc.textContent = filterMessages[filter].desc;
                    } else {
                        sessionList.style.display = '';
                        emptyFilterState.classList.remove('show');
                    }
                }
            });
        });

        // Cancel Modal
        const cancelModal = document.getElementById('cancelModal');
        const cancelSessionId = document.getElementById('cancelSessionId');
        const cancelModalMentorName = document.getElementById('cancelModalMentorName');
        const cancelModalMentorProdi = document.getElementById('cancelModalMentorProdi');
        const cancelModalMentorAvatar = document.getElementById('cancelModalMentorAvatar');
        const cancelModalDuration = document.getElementById('cancelModalDuration');
        const cancelModalPrice = document.getElementById('cancelModalPrice');

        function openCancelModal(sessionId, mentorName, mentorProdi, initial, avatarUrl, duration, price) {
            cancelSessionId.value = sessionId;
            cancelModalMentorName.textContent = mentorName;
            cancelModalMentorProdi.textContent = mentorProdi;
            cancelModalDuration.textContent = duration + ' menit';
            cancelModalPrice.textContent = price.toLocaleString('id-ID') + ' Gems';

            if (avatarUrl) {
                cancelModalMentorAvatar.innerHTML = '<img src="' + avatarUrl + '" alt="" referrerpolicy="no-referrer">';
            } else {
                cancelModalMentorAvatar.innerHTML = initial;
            }

            cancelModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeCancelModal() {
            cancelModal.classList.remove('active');
            document.body.style.overflow = '';
        }

        cancelModal.addEventListener('click', (e) => {
            if (e.target === cancelModal) closeCancelModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && cancelModal.classList.contains('active')) {
                closeCancelModal();
            }
        });

        // Real-time polling for status updates
        (function() {
            let lastCheck = '<?php echo date('Y-m-d H:i:s'); ?>';
            const POLL_INTERVAL = 5000;
            let isPolling = false;
            
            function pollStatusUpdates() {
                if (isPolling) return;
                isPolling = true;
                
                fetch('<?php echo BASE_PATH; ?>/check-session-status.php?lastcheck=' + encodeURIComponent(lastCheck))
                    .then(res => res.json())
                    .then(data => {
                        if (data.updated && data.sessions) {
                            data.sessions.forEach(session => {
                                const card = document.querySelector(`[data-session-id="${session.id}"]`);
                                if (card) {
                                    // Update status class
                                    card.className = card.className.replace(/status-\w+/, 'status-' + session.status);
                                    card.dataset.status = session.status;
                                    
                                    // Update badge
                                    const badge = card.querySelector('.status-badge');
                                    if (badge) {
                                        badge.className = 'status-badge ' + session.status;
                                        const labels = {pending:'Menunggu',ongoing:'Berlangsung',completed:'Selesai',cancelled:'Dibatalkan'};
                                        badge.textContent = labels[session.status] || session.status;
                                    }
                                }
                            });
                            lastCheck = data.timestamp || new Date().toISOString();
                        }
                    })
                    .catch(() => {})
                    .finally(() => {
                        isPolling = false;
                    });
            }
            
            setInterval(pollStatusUpdates, POLL_INTERVAL);
        })();
    </script>
</body>
</html>
