<?php
// mentor-sessions.php - Halaman booking sesi mentor (v2.2 - Accept Modal + Notes Display)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

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

// Get sessions with conversation info
$stmt = $pdo->prepare("
    SELECT s.*,
           u.name AS student_name,
           u.avatar AS student_avatar,
           u.program_studi AS student_prodi,
           c.id AS conversation_id
    FROM sessions s
    JOIN users u ON s.student_id = u.id
    LEFT JOIN conversations c ON c.student_id = s.student_id AND c.mentor_id = s.mentor_id
    WHERE s.mentor_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$mentor_id]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = ['pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($sessions as $session) {
    if (isset($stats[$session['status']])) {
        $stats[$session['status']]++;
    }
}

$success = NotificationHelper::getSuccess();
$error = NotificationHelper::getError();

// Helper untuk avatar URL
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar, $base = '') {
        if (empty($avatar)) return '';
        if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
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
    <title>Booking Sesi - JagoNugas Mentor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            color: #1e293b;
        }

        /* ===== CONTAINER ===== */
        .sessions-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* ===== ALERTS ===== */
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

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert i { font-size: 1.25rem; }

        /* ===== PAGE HEADER ===== */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
        }

        .page-title i {
            color: #10b981;
            font-size: 1.5rem;
        }

        .total-count {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 500;
        }

        /* ===== STATS GRID ===== */
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

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        /* ===== FILTER TABS ===== */
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

        .filter-tab:hover {
            border-color: #10b981;
            color: #10b981;
        }

        .filter-tab.active {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }

        /* ===== EMPTY FILTER STATE ===== */
        .empty-filter-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            display: none;
        }

        .empty-filter-state.show {
            display: block;
        }

        .empty-filter-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-filter-state h3 {
            font-size: 1.1rem;
            color: #475569;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .empty-filter-state p {
            color: #94a3b8;
            font-size: 0.9rem;
            margin: 0;
        }

        /* ===== SESSION CARD ===== */
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
            border-color: #10b981;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.1);
        }

        .session-card.status-pending { border-left: 4px solid #f59e0b; }
        .session-card.status-ongoing { border-left: 4px solid #3b82f6; }
        .session-card.status-completed { border-left: 4px solid #10b981; }
        .session-card.status-cancelled { border-left: 4px solid #ef4444; }

        .session-main {
            padding: 1.25rem 1.5rem;
        }

        .session-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .student-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
            overflow: hidden;
        }

        .student-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .student-details h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .student-prodi {
            font-size: 0.85rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

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

        /* Session Details Row */
        .session-details-row {
            display: flex;
            gap: 2rem;
            padding: 1rem 0;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-item i {
            color: #94a3b8;
            font-size: 1rem;
        }

        .detail-item span {
            font-size: 0.9rem;
            color: #475569;
            font-weight: 500;
        }

        /* Notes Display */
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

        .notes-display i {
            color: #0284c7;
            font-size: 1rem;
            margin-top: 2px;
        }

        .notes-display .notes-content {
            flex: 1;
        }

        .notes-display .notes-label {
            font-size: 0.75rem;
            color: #0369a1;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .notes-display .notes-text {
            font-size: 0.9rem;
            color: #0c4a6e;
            line-height: 1.5;
        }

        /* Reject Reason Display */
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

        .reject-reason-display i {
            color: #ef4444;
            font-size: 1rem;
            margin-top: 2px;
        }

        .reject-reason-display .reason-content {
            flex: 1;
        }

        .reject-reason-display .reason-label {
            font-size: 0.75rem;
            color: #dc2626;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .reject-reason-display .reason-text {
            font-size: 0.9rem;
            color: #991b1b;
        }

        /* Session Actions */
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

        .btn-chat {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-chat:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            color: white;
        }

        .btn-accept {
            background: #10b981;
            color: white;
        }

        .btn-accept:hover {
            background: #059669;
            color: white;
        }

        .btn-reject {
            background: white;
            color: #ef4444;
            border: 1px solid #fecaca;
        }

        .btn-reject:hover {
            background: #fef2f2;
            border-color: #ef4444;
        }

        .btn-complete {
            background: #3b82f6;
            color: white;
        }

        .btn-complete:hover {
            background: #2563eb;
            color: white;
        }

        /* Rating Display */
        .rating-display {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #fffbeb;
            border-radius: 8px;
            border: 1px solid #fde68a;
        }

        .rating-display .stars {
            display: flex;
            gap: 2px;
        }

        .rating-display .stars i {
            color: #f59e0b;
            font-size: 0.9rem;
        }

        .rating-display .rating-text {
            font-size: 0.85rem;
            font-weight: 600;
            color: #92400e;
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            border: 2px dashed #e2e8f0;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h2 {
            font-size: 1.25rem;
            color: #475569;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .empty-state p {
            color: #94a3b8;
            font-size: 0.95rem;
        }

        /* ===== MODAL BASE ===== */
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

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

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

        .modal-overlay.active .modal-container {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .modal-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .modal-icon.reject {
            background: #fef2f2;
            color: #ef4444;
        }

        .modal-icon.accept {
            background: #d1fae5;
            color: #10b981;
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
        }

        .modal-subtitle {
            font-size: 0.85rem;
            color: #64748b;
        }

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

        .modal-close:hover {
            background: #e2e8f0;
            color: #475569;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-info-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-student-avatar {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
            overflow: hidden;
        }

        .modal-student-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-student-info h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .modal-student-info p {
            font-size: 0.8rem;
            color: #64748b;
            margin: 0;
        }

        /* Modal Session Details */
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

        .modal-detail-item i {
            color: #64748b;
            font-size: 1.1rem;
        }

        .modal-detail-item .detail-content {
            flex: 1;
        }

        .modal-detail-item .detail-label {
            font-size: 0.7rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-detail-item .detail-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #0f172a;
        }

        /* Modal Notes Section */
        .modal-notes-section {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .modal-notes-section .notes-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 0.5rem;
        }

        .modal-notes-section .notes-header i {
            color: #0284c7;
            font-size: 1rem;
        }

        .modal-notes-section .notes-header span {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-notes-section .notes-body {
            font-size: 0.9rem;
            color: #0c4a6e;
            line-height: 1.6;
        }

        .modal-notes-section.empty {
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .modal-notes-section.empty .notes-header i,
        .modal-notes-section.empty .notes-header span {
            color: #94a3b8;
        }

        .modal-notes-section.empty .notes-body {
            color: #94a3b8;
            font-style: italic;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-label span {
            color: #94a3b8;
            font-weight: 400;
        }

        .form-textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            transition: all 0.2s;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-textarea.reject:focus {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-textarea::placeholder {
            color: #94a3b8;
        }

        .reason-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .reason-chip {
            padding: 0.5rem 0.875rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 50px;
            font-size: 0.8rem;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
        }

        .reason-chip:hover {
            border-color: #ef4444;
            background: #fef2f2;
            color: #ef4444;
        }

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

        .btn-modal-cancel {
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .btn-modal-cancel:hover {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-modal-reject {
            background: #ef4444;
            border: none;
            color: white;
        }

        .btn-modal-reject:hover {
            background: #dc2626;
        }

        .btn-modal-accept {
            background: #10b981;
            border: none;
            color: white;
        }

        .btn-modal-accept:hover {
            background: #059669;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .page-header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .session-details-row {
                flex-direction: column;
                gap: 0.75rem;
            }

            .session-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .session-actions .btn {
                justify-content: center;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-session-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/mentor-navbar.php'; ?>

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

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-top">
            <h1 class="page-title">
                <i class="bi bi-calendar-check"></i>
                Booking Sesi
            </h1>
            <span class="total-count"><?= count($sessions) ?> total sesi</span>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stat-number"><?= $stats['pending'] ?></div>
            <div class="stat-label">Menunggu</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon ongoing">
                <i class="bi bi-play-circle"></i>
            </div>
            <div class="stat-number"><?= $stats['ongoing'] ?></div>
            <div class="stat-label">Berlangsung</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon completed">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-number"><?= $stats['completed'] ?></div>
            <div class="stat-label">Selesai</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon cancelled">
                <i class="bi bi-x-circle"></i>
            </div>
            <div class="stat-number"><?= $stats['cancelled'] ?></div>
            <div class="stat-label">Dibatalkan</div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all">Semua</button>
        <button class="filter-tab" data-filter="pending">Menunggu</button>
        <button class="filter-tab" data-filter="ongoing">Berlangsung</button>
        <button class="filter-tab" data-filter="completed">Selesai</button>
        <button class="filter-tab" data-filter="cancelled">Dibatalkan</button>
    </div>

    <!-- Empty Filter State -->
    <div class="empty-filter-state" id="emptyFilterState">
        <i class="bi bi-inbox"></i>
        <h3 id="emptyFilterTitle">Tidak ada sesi</h3>
        <p id="emptyFilterDesc">Tidak ada sesi dengan status ini.</p>
    </div>

    <!-- Session List -->
    <?php if (empty($sessions)): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h2>Belum Ada Booking</h2>
            <p>Belum ada mahasiswa yang booking sesi dengan Anda.</p>
        </div>
    <?php else: ?>
        <div class="session-list" id="sessionList">
            <?php foreach ($sessions as $session): 
                $studentInitial = strtoupper(substr($session['student_name'], 0, 1));
                $avatarUrl = get_avatar_url($session['student_avatar'] ?? '', $BASE);
                $statusLabels = [
                    'pending' => 'Menunggu',
                    'ongoing' => 'Berlangsung',
                    'completed' => 'Selesai',
                    'cancelled' => 'Dibatalkan'
                ];
                $sessionNotes = $session['notes'] ?? '';
            ?>
                <div class="session-card status-<?= $session['status'] ?>" data-status="<?= $session['status'] ?>">
                    <div class="session-main">
                        <div class="session-top">
                            <div class="student-info">
                                <div class="student-avatar">
                                    <?php if ($avatarUrl): ?>
                                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="" referrerpolicy="no-referrer">
                                    <?php else: ?>
                                        <?= $studentInitial ?>
                                    <?php endif; ?>
                                </div>
                                <div class="student-details">
                                    <h3><?= htmlspecialchars($session['student_name']) ?></h3>
                                    <span class="student-prodi">
                                        <i class="bi bi-mortarboard"></i>
                                        <?= htmlspecialchars($session['student_prodi'] ?? 'Mahasiswa') ?>
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
                                    <div class="notes-label">Catatan dari Mahasiswa</div>
                                    <div class="notes-text"><?= htmlspecialchars($sessionNotes) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($session['status'] === 'cancelled' && !empty($session['reject_reason'])): ?>
                            <div class="reject-reason-display">
                                <i class="bi bi-info-circle"></i>
                                <div class="reason-content">
                                    <div class="reason-label">Alasan Penolakan</div>
                                    <div class="reason-text"><?= htmlspecialchars($session['reject_reason']) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="session-actions">
                            <?php if ($session['status'] === 'ongoing'): ?>
                                <a href="<?= $BASE ?>/mentor-chat.php?student_id=<?= $session['student_id'] ?>" class="btn btn-chat">
                                    <i class="bi bi-chat-dots-fill"></i>
                                    Chat Mahasiswa
                                </a>
                            <?php endif; ?>

                            <?php if ($session['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-accept" 
                                        onclick="openAcceptModal(<?= $session['id'] ?>, '<?= htmlspecialchars($session['student_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($session['student_prodi'] ?? 'Mahasiswa', ENT_QUOTES) ?>', '<?= $studentInitial ?>', '<?= htmlspecialchars($avatarUrl, ENT_QUOTES) ?>', <?= (int)$session['duration'] ?>, <?= (int)$session['price'] ?>, '<?= htmlspecialchars(addslashes($sessionNotes), ENT_QUOTES) ?>')">
                                    <i class="bi bi-check-lg"></i> Terima
                                </button>
                                <button type="button" class="btn btn-reject" 
                                        onclick="openRejectModal(<?= $session['id'] ?>, '<?= htmlspecialchars($session['student_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($session['student_prodi'] ?? 'Mahasiswa', ENT_QUOTES) ?>', '<?= $studentInitial ?>', '<?= htmlspecialchars($avatarUrl, ENT_QUOTES) ?>')">
                                    <i class="bi bi-x-lg"></i> Tolak
                                </button>
                            <?php endif; ?>

                            <?php if ($session['status'] === 'ongoing'): ?>
                                <form method="POST" action="<?= $BASE ?>/mentor-session-action.php" style="display:inline;"
                                      onsubmit="return confirm('Tandai sesi sebagai selesai?');">
                                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn btn-complete">
                                        <i class="bi bi-check2-square"></i> Selesaikan
                                    </button>
                                </form>
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
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ACCEPT MODAL -->
<div class="modal-overlay" id="acceptModal">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon accept">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <h3 class="modal-title">Terima Booking</h3>
                    <p class="modal-subtitle">Konfirmasi penerimaan sesi</p>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeAcceptModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <form method="POST" action="<?= $BASE ?>/mentor-session-action.php" id="acceptForm">
            <input type="hidden" name="session_id" id="acceptSessionId">
            <input type="hidden" name="action" value="accept">
            
            <div class="modal-body">
                <!-- Student Info Card -->
                <div class="modal-info-card">
                    <div class="modal-student-avatar" id="acceptModalStudentAvatar">R</div>
                    <div class="modal-student-info">
                        <h4 id="acceptModalStudentName">Nama Mahasiswa</h4>
                        <p id="acceptModalStudentProdi">Program Studi</p>
                    </div>
                </div>

                <!-- Session Details -->
                <div class="modal-session-details">
                    <div class="modal-detail-item">
                        <i class="bi bi-clock"></i>
                        <div class="detail-content">
                            <div class="detail-label">Durasi</div>
                            <div class="detail-value" id="acceptModalDuration">15 menit</div>
                        </div>
                    </div>
                    <div class="modal-detail-item">
                        <i class="bi bi-gem"></i>
                        <div class="detail-content">
                            <div class="detail-label">Harga</div>
                            <div class="detail-value" id="acceptModalPrice">1,000 Gems</div>
                        </div>
                    </div>
                </div>

                <!-- Notes Section -->
                <div class="modal-notes-section" id="acceptModalNotesSection">
                    <div class="notes-header">
                        <i class="bi bi-chat-quote"></i>
                        <span>Catatan dari Mahasiswa</span>
                    </div>
                    <div class="notes-body" id="acceptModalNotes">
                        Tidak ada catatan
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeAcceptModal()">
                    Batal
                </button>
                <button type="submit" class="btn-modal btn-modal-accept">
                    <i class="bi bi-check-circle"></i>
                    Terima Booking
                </button>
            </div>
        </form>
    </div>
</div>

<!-- REJECT MODAL -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon reject">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div>
                    <h3 class="modal-title">Tolak Booking</h3>
                    <p class="modal-subtitle">Berikan alasan penolakan</p>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeRejectModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        
        <form method="POST" action="<?= $BASE ?>/mentor-session-action.php" id="rejectForm">
            <input type="hidden" name="session_id" id="rejectSessionId">
            <input type="hidden" name="action" value="reject">
            
            <div class="modal-body">
                <!-- Student Info Card -->
                <div class="modal-info-card">
                    <div class="modal-student-avatar" id="rejectModalStudentAvatar">R</div>
                    <div class="modal-student-info">
                        <h4 id="rejectModalStudentName">Nama Mahasiswa</h4>
                        <p id="rejectModalStudentProdi">Program Studi</p>
                    </div>
                </div>
                
                <!-- Quick Reason Chips -->
                <div class="reason-chips">
                    <span class="reason-chip" onclick="setReason('Jadwal saya sedang penuh')">Jadwal penuh</span>
                    <span class="reason-chip" onclick="setReason('Materi di luar keahlian saya')">Bukan keahlian saya</span>
                    <span class="reason-chip" onclick="setReason('Saya sedang tidak tersedia')">Tidak tersedia</span>
                    <span class="reason-chip" onclick="setReason('Durasi sesi terlalu singkat')">Durasi kurang</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Alasan Penolakan <span>(opsional)</span>
                    </label>
                    <textarea class="form-textarea reject" name="reject_reason" id="rejectReason" 
                              placeholder="Tuliskan alasan penolakan untuk mahasiswa..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeRejectModal()">
                    Batal
                </button>
                <button type="submit" class="btn-modal btn-modal-reject">
                    <i class="bi bi-x-circle"></i>
                    Tolak Booking
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Auto dismiss alerts
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.transition = 'all 0.3s';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
});

// Filter tabs with empty state
const filterTabs = document.querySelectorAll('.filter-tab');
const sessionList = document.getElementById('sessionList');
const emptyFilterState = document.getElementById('emptyFilterState');
const emptyFilterTitle = document.getElementById('emptyFilterTitle');
const emptyFilterDesc = document.getElementById('emptyFilterDesc');

const filterMessages = {
    pending: {
        title: 'Tidak ada sesi menunggu',
        desc: 'Belum ada mahasiswa yang menunggu konfirmasi booking.',
        icon: 'bi-clock'
    },
    ongoing: {
        title: 'Tidak ada sesi berlangsung',
        desc: 'Belum ada sesi mentoring yang sedang aktif.',
        icon: 'bi-play-circle'
    },
    completed: {
        title: 'Tidak ada sesi selesai',
        desc: 'Belum ada sesi mentoring yang telah diselesaikan.',
        icon: 'bi-check-circle'
    },
    cancelled: {
        title: 'Tidak ada sesi dibatalkan',
        desc: 'Tidak ada sesi yang dibatalkan. Bagus!',
        icon: 'bi-x-circle'
    },
    all: {
        title: 'Belum ada booking',
        desc: 'Belum ada mahasiswa yang booking sesi dengan Anda.',
        icon: 'bi-inbox'
    }
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

// ===== ACCEPT MODAL FUNCTIONS =====
const acceptModal = document.getElementById('acceptModal');
const acceptSessionId = document.getElementById('acceptSessionId');
const acceptModalStudentName = document.getElementById('acceptModalStudentName');
const acceptModalStudentProdi = document.getElementById('acceptModalStudentProdi');
const acceptModalStudentAvatar = document.getElementById('acceptModalStudentAvatar');
const acceptModalDuration = document.getElementById('acceptModalDuration');
const acceptModalPrice = document.getElementById('acceptModalPrice');
const acceptModalNotes = document.getElementById('acceptModalNotes');
const acceptModalNotesSection = document.getElementById('acceptModalNotesSection');

function openAcceptModal(sessionId, studentName, studentProdi, initial, avatarUrl, duration, price, notes) {
    acceptSessionId.value = sessionId;
    acceptModalStudentName.textContent = studentName;
    acceptModalStudentProdi.textContent = studentProdi;
    acceptModalDuration.textContent = duration + ' menit';
    acceptModalPrice.textContent = price.toLocaleString('id-ID') + ' Gems';
    
    // Set avatar
    if (avatarUrl) {
        acceptModalStudentAvatar.innerHTML = `<img src="${avatarUrl}" alt="" referrerpolicy="no-referrer">`;
    } else {
        acceptModalStudentAvatar.innerHTML = initial;
    }
    
    // Set notes
    if (notes && notes.trim() !== '') {
        acceptModalNotes.textContent = notes;
        acceptModalNotesSection.classList.remove('empty');
    } else {
        acceptModalNotes.textContent = 'Tidak ada catatan dari mahasiswa';
        acceptModalNotesSection.classList.add('empty');
    }
    
    // Show modal
    acceptModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAcceptModal() {
    acceptModal.classList.remove('active');
    document.body.style.overflow = '';
}

// Close accept modal on overlay click
acceptModal.addEventListener('click', (e) => {
    if (e.target === acceptModal) {
        closeAcceptModal();
    }
});

// ===== REJECT MODAL FUNCTIONS =====
const rejectModal = document.getElementById('rejectModal');
const rejectSessionId = document.getElementById('rejectSessionId');
const rejectModalStudentName = document.getElementById('rejectModalStudentName');
const rejectModalStudentProdi = document.getElementById('rejectModalStudentProdi');
const rejectModalStudentAvatar = document.getElementById('rejectModalStudentAvatar');
const rejectReason = document.getElementById('rejectReason');

function openRejectModal(sessionId, studentName, studentProdi, initial, avatarUrl) {
    rejectSessionId.value = sessionId;
    rejectModalStudentName.textContent = studentName;
    rejectModalStudentProdi.textContent = studentProdi;
    
    // Set avatar
    if (avatarUrl) {
        rejectModalStudentAvatar.innerHTML = `<img src="${avatarUrl}" alt="" referrerpolicy="no-referrer">`;
    } else {
        rejectModalStudentAvatar.innerHTML = initial;
    }
    
    // Reset form
    rejectReason.value = '';
    
    // Show modal
    rejectModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeRejectModal() {
    rejectModal.classList.remove('active');
    document.body.style.overflow = '';
}

function setReason(reason) {
    rejectReason.value = reason;
    rejectReason.focus();
}

// Close reject modal on overlay click
rejectModal.addEventListener('click', (e) => {
    if (e.target === rejectModal) {
        closeRejectModal();
    }
});

// Close modals on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (acceptModal.classList.contains('active')) {
            closeAcceptModal();
        }
        if (rejectModal.classList.contains('active')) {
            closeRejectModal();
        }
    }
});
</script>

</body>
</html>
