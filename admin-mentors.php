<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function url_path(string $path = ''): string {
    $base = defined('BASE_PATH') ? (string) constant('BASE_PATH') : '';
    $path = '/' . ltrim($path, '/');
    return rtrim($base, '/') . $path;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . url_path('login.php'));
    exit;
}

$success = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $mentorId = intval($_POST['mentor_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($mentorId > 0 && in_array($action, ['approve', 'reject'], true)) {
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE users SET is_approved = 1, updated_at = NOW() WHERE id = ? AND role = 'mentor'");
                $stmt->execute([$mentorId]);

                $stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, type, title, message, icon, color)
                    VALUES (?, 'approval', '✅ Akun Mentor Disetujui', 'Selamat! Akun mentor Anda telah disetujui. Anda sekarang dapat menerima mentoring request.', 'check-circle', '#10b981')
                ");
                $stmt->execute([$mentorId]);

                $success = 'Mentor berhasil disetujui!';
            }

            if ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE users SET is_approved = 2, updated_at = NOW() WHERE id = ? AND role = 'mentor'");
                $stmt->execute([$mentorId]);

                $stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, type, title, message, icon, color)
                    VALUES (?, 'approval', '❌ Akun Mentor Ditolak', 'Maaf, akun mentor Anda ditolak. Silakan hubungi admin untuk informasi lebih lanjut.', 'x-circle', '#ef4444')
                ");
                $stmt->execute([$mentorId]);

                $error = 'Mentor berhasil ditolak.';
            }
        } catch (PDOException $e) {
            $error = 'Gagal memproses aksi: ' . $e->getMessage();
        }
    }
}

// Filter
$filter = $_GET['filter'] ?? 'pending';

// Query mentors
$sql = "SELECT * FROM users WHERE role = 'mentor'";
if ($filter === 'pending') $sql .= " AND is_approved = 0";
elseif ($filter === 'approved') $sql .= " AND is_approved = 1";
elseif ($filter === 'rejected') $sql .= " AND is_approved = 2";
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$mentors = $stmt->fetchAll();

// Counts
$pendingCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor' AND is_approved = 0")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor' AND is_approved = 1")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor' AND is_approved = 2")->fetchColumn();

// Session counts
$sessionCounts = [];
$stmtSessions = $pdo->query("SELECT mentor_id, COUNT(*) as total FROM sessions WHERE status = 'completed' GROUP BY mentor_id");
while ($row = $stmtSessions->fetch()) {
    $sessionCounts[$row['mentor_id']] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mentor - JagoNugas Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 0;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header p {
            color: #718096;
            margin: 0;
        }

        .filter-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.75rem 1.5rem;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            color: #64748b;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .tab-badge {
            background: rgba(255, 255, 255, 0.3);
            padding: 0.15rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .filter-tab:not(.active) .tab-badge {
            background: #ef4444;
            color: white;
        }

        .alert-custom {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 2px solid #10b981;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 2px solid #dc2626;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 2px solid #f59e0b;
        }

        /* ========================================
           GRID LAYOUT - SIMETRIS
           ======================================== */
        .mentor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            align-items: start; /* Prevent stretching */
        }

        .mentor-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            
            /* KEY: Bikin card flexible tapi simetris */
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .mentor-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .mentor-card.pending {
            border-color: #fbbf24;
        }

        .mentor-card.approved {
            border-color: #10b981;
        }

        .mentor-card.rejected {
            border-color: #ef4444;
        }

        .mentor-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #e2e8f0;
            flex-shrink: 0; /* Prevent header from growing */
        }

        .mentor-profile {
            display: flex;
            align-items: start;
            gap: 1rem;
        }

        .mentor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            flex-shrink: 0;
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .mentor-info {
            flex: 1;
        }

        .mentor-info h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a202c;
        }

        .mentor-info .email {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0.25rem 0;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .mentor-body {
            padding: 1.5rem;
            flex: 1; /* Take remaining space */
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .info-row i {
            color: #667eea;
            width: 20px;
            text-align: center;
        }

        .info-row .label {
            color: #64748b;
            min-width: 80px;
        }

        .info-row .value {
            color: #1a202c;
            font-weight: 600;
        }

        .expertise-section {
            margin-top: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .expertise-section strong {
            display: block;
            margin-bottom: 0.5rem;
            color: #4a5568;
            font-size: 0.9rem;
        }

        .expertise-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .expertise-tag {
            padding: 0.35rem 0.75rem;
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            color: #3730a3;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .bio-section {
            margin-top: 0.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }

        .bio-section strong {
            display: block;
            margin-bottom: 0.5rem;
            color: #4a5568;
            font-size: 0.9rem;
        }

        .bio-section p {
            margin: 0;
            color: #64748b;
            line-height: 1.6;
        }

        /* ========================================
           TRANSKRIP SECTION - SIMETRIS IMAGE
           ======================================== */
        .transkrip-section {
            margin-top: 0.5rem;
            padding: 1rem;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 8px;
        }

        .transkrip-section strong {
            display: block;
            margin-bottom: 0.75rem;
            color: #92400e;
            font-size: 0.9rem;
        }

        .transkrip-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .btn-transkrip {
            flex: 1;
            padding: 0.5rem 1rem;
            background: white;
            color: #92400e;
            border: 2px solid #f59e0b;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-transkrip:hover {
            background: #f59e0b;
            color: white;
        }

        /* KEY: Fixed aspect ratio container untuk image simetris */
        .transkrip-preview {
            position: relative;
            width: 100%;
            padding-top: 66.67%; /* 3:2 aspect ratio */
            border-radius: 8px;
            overflow: hidden;
            background: #f1f5f9;
            border: 2px solid white;
        }

        .transkrip-preview img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover; /* Crop to fill container */
            object-position: center;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .transkrip-preview img:hover {
            transform: scale(1.05);
        }

        /* Alternative style untuk contain (tampilkan full image) */
        .transkrip-preview.contain {
            padding-top: 0;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .transkrip-preview.contain img {
            position: static;
            width: 100%;
            height: 100%;
            object-fit: contain;
            max-height: 250px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin-top: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-item i {
            color: #fbbf24;
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .stat-item .value {
            display: block;
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a202c;
        }

        .stat-item .label {
            display: block;
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .mentor-actions {
            padding: 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 0.75rem;
            margin-top: auto; /* Push to bottom */
            flex-shrink: 0;
        }

        .btn-action {
            flex: 1;
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-action.approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-action.reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-action.view {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #64748b;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .modal-content h3 {
            margin-bottom: 1rem;
            color: #1a202c;
        }

        .modal-content p {
            color: #64748b;
            margin-bottom: 2rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-modal {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-modal.cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-modal.cancel:hover {
            background: #e2e8f0;
        }

        .btn-modal.confirm {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-modal.confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-modal.confirm.reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .btn-modal.confirm.reject:hover {
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
        }

        .image-modal.active {
            display: flex;
        }

        .image-modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }

        /* ========================================
           RESPONSIVE
           ======================================== */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .mentor-grid {
                grid-template-columns: 1fr;
            }

            .filter-tabs {
                flex-direction: column;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .transkrip-preview {
                padding-top: 75%; /* 4:3 on mobile */
            }
        }

        @media (min-width: 769px) and (max-width: 1200px) {
            .mentor-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1201px) {
            .mentor-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include 'admin-navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="bi bi-mortarboard-fill"></i>
                Kelola Mentor
            </h1>
            <p>Review dan verifikasi pendaftaran mentor baru</p>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">
                <i class="bi bi-hourglass-split"></i>
                Pending
                <?php if ($pendingCount > 0): ?>
                    <span class="tab-badge"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?filter=approved" class="filter-tab <?= $filter === 'approved' ? 'active' : '' ?>">
                <i class="bi bi-check-circle-fill"></i>
                Approved (<?= $approvedCount ?>)
            </a>
            <a href="?filter=rejected" class="filter-tab <?= $filter === 'rejected' ? 'active' : '' ?>">
                <i class="bi bi-x-circle-fill"></i>
                Rejected (<?= $rejectedCount ?>)
            </a>
            <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                Semua
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
        <div class="alert-custom alert-success">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-custom alert-error">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($filter === 'pending' && $pendingCount > 0): ?>
        <div class="alert-custom alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>Ada <strong><?= $pendingCount ?></strong> mentor menunggu approval. Silakan review dan setujui.</span>
        </div>
        <?php endif; ?>

        <!-- Mentor Grid -->
        <?php if (empty($mentors)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h3>Tidak ada data</h3>
                <p>
                    <?php 
                    if ($filter === 'pending') echo 'Belum ada mentor yang menunggu approval';
                    elseif ($filter === 'approved') echo 'Belum ada mentor yang disetujui';
                    elseif ($filter === 'rejected') echo 'Belum ada mentor yang ditolak';
                    else echo 'Belum ada mentor terdaftar';
                    ?>
                </p>
            </div>
        <?php else: ?>
            <div class="mentor-grid">
                <?php foreach ($mentors as $mentor): ?>
                    <?php 
                    $statusClass = '';
                    $statusLabel = '';
                    $statusIcon = '';
                    
                    switch ($mentor['is_approved']) {
                        case 0:
                            $statusClass = 'pending';
                            $statusLabel = 'Pending Approval';
                            $statusIcon = 'hourglass-split';
                            break;
                        case 1:
                            $statusClass = 'approved';
                            $statusLabel = 'Approved';
                            $statusIcon = 'check-circle-fill';
                            break;
                        case 2:
                            $statusClass = 'rejected';
                            $statusLabel = 'Rejected';
                            $statusIcon = 'x-circle-fill';
                            break;
                    }
                    
                    $totalSessions = $sessionCounts[$mentor['id']] ?? 0;
                    $avgRating = $mentor['review_count'] > 0 
                        ? number_format($mentor['total_rating'] / $mentor['review_count'], 1)
                        : '0.0';
                    ?>
                    <div class="mentor-card <?= $statusClass ?>">
                        <!-- Header -->
                        <div class="mentor-header">
                            <div class="mentor-profile">
                                <div class="mentor-avatar">
                                    <?= strtoupper(substr($mentor['name'] ?? '?', 0, 1)) ?>
                                </div>
                                <div class="mentor-info">
                                    <h3><?= htmlspecialchars($mentor['name'] ?? '-') ?></h3>
                                    <p class="email"><?= htmlspecialchars($mentor['email'] ?? '-') ?></p>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <i class="bi bi-<?= $statusIcon ?>"></i>
                                        <?= $statusLabel ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="mentor-body">
                            <div class="info-row">
                                <i class="bi bi-mortarboard"></i>
                                <span class="label">Program:</span>
                                <span class="value"><?= htmlspecialchars($mentor['program_studi'] ?? '-') ?></span>
                            </div>

                            <?php if (!empty($mentor['semester'])): ?>
                            <div class="info-row">
                                <i class="bi bi-book"></i>
                                <span class="label">Semester:</span>
                                <span class="value"><?= (int)$mentor['semester'] ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="info-row">
                                <i class="bi bi-gem"></i>
                                <span class="label">Rate:</span>
                                <span class="value"><?= number_format($mentor['hourly_rate'] ?? 0) ?> gems/jam</span>
                            </div>

                            <div class="info-row">
                                <i class="bi bi-calendar3"></i>
                                <span class="label">Bergabung:</span>
                                <span class="value">
                                    <?php
                                        $created = $mentor['created_at'] ?? null;
                                        echo $created ? date('d M Y', strtotime($created)) : '-';
                                    ?>
                                </span>
                            </div>

                            <!-- Stats (hanya untuk approved) -->
                            <?php if ($mentor['is_approved'] == 1): ?>
                            <div class="stats-row">
                                <div class="stat-item">
                                    <i class="bi bi-star-fill"></i>
                                    <span class="value"><?= $avgRating ?></span>
                                    <span class="label">Rating</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-chat-dots-fill"></i>
                                    <span class="value"><?= (int)$mentor['review_count'] ?></span>
                                    <span class="label">Reviews</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-calendar-check-fill"></i>
                                    <span class="value"><?= $totalSessions ?></span>
                                    <span class="label">Sessions</span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Expertise -->
                            <?php if (!empty($mentor['expertise'])): ?>
                            <div class="expertise-section">
                                <strong><i class="bi bi-lightbulb"></i> Keahlian:</strong>
                                <div class="expertise-tags">
                                    <?php
                                    $expertiseArr = json_decode($mentor['expertise'], true);
                                    if (!is_array($expertiseArr)) $expertiseArr = [];
                                    foreach ($expertiseArr as $exp):
                                    ?>
                                        <span class="expertise-tag"><?= htmlspecialchars((string)$exp) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Specialization -->
                            <?php if (!empty($mentor['specialization'])): ?>
                            <div class="bio-section">
                                <strong><i class="bi bi-briefcase"></i> Spesialisasi:</strong>
                                <p><?= htmlspecialchars($mentor['specialization']) ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Bio -->
                            <?php if (!empty($mentor['bio'])): ?>
                            <div class="bio-section">
                                <strong><i class="bi bi-person-lines-fill"></i> Bio:</strong>
                                <p><?= htmlspecialchars($mentor['bio']) ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Transkrip -->
                            <?php if (!empty($mentor['transkrip_path'])): ?>
                            <div class="transkrip-section">
                                <strong><i class="bi bi-file-earmark-text"></i> Transkrip Nilai:</strong>
                                <div class="transkrip-buttons">
                                    <a href="<?= url_path($mentor['transkrip_path']) ?>"
                                       target="_blank" class="btn-transkrip">
                                        <i class="bi bi-eye"></i>
                                        Lihat
                                    </a>
                                    <a href="<?= url_path($mentor['transkrip_path']) ?>"
                                       download class="btn-transkrip">
                                        <i class="bi bi-download"></i>
                                        Download
                                    </a>
                                </div>

                                <?php
                                $ext = pathinfo($mentor['transkrip_path'], PATHINFO_EXTENSION);
                                $isImage = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'], true);
                                ?>
                                <?php if ($isImage): ?>
                                <div class="transkrip-preview">
                                    <img src="<?= url_path($mentor['transkrip_path']) ?>"
                                         alt="Transkrip"
                                         onclick="openImageModal(this.src)"
                                         loading="lazy">
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="mentor-actions">
                            <?php if ($mentor['is_approved'] == 0): ?>
                                <button type="button" class="btn-action approve"
                                        onclick="showConfirmModal('approve', <?= (int)$mentor['id'] ?>, '<?= htmlspecialchars($mentor['name'] ?? '', ENT_QUOTES) ?>')">
                                    <i class="bi bi-check-lg"></i>
                                    Setujui
                                </button>
                                <button type="button" class="btn-action reject"
                                        onclick="showConfirmModal('reject', <?= (int)$mentor['id'] ?>, '<?= htmlspecialchars($mentor['name'] ?? '', ENT_QUOTES) ?>')">
                                    <i class="bi bi-x-lg"></i>
                                    Tolak
                                </button>
                            <?php elseif ($mentor['is_approved'] == 2): ?>
                                <button type="button" class="btn-action approve"
                                        onclick="showConfirmModal('approve', <?= (int)$mentor['id'] ?>, '<?= htmlspecialchars($mentor['name'] ?? '', ENT_QUOTES) ?>')">
                                    <i class="bi bi-arrow-repeat"></i>
                                    Re-approve
                                </button>
                            <?php else: ?>
                                <a href="<?= url_path('mentor-detail.php?id=' . $mentor['id']) ?>" 
                                   class="btn-action view">
                                    <i class="bi bi-eye"></i>
                                    Lihat Detail
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div class="image-modal" id="imageModal" onclick="closeImageModal()">
        <img id="modalImage" src="" alt="Preview">
    </div>

    <!-- Confirm Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-content">
            <div id="confirmIcon"></div>
            <h3 id="confirmTitle">Konfirmasi</h3>
            <p id="confirmMessage">Apakah Anda yakin?</p>

            <form method="POST" id="confirmForm">
                <input type="hidden" name="mentor_id" id="confirmMentorId">
                <input type="hidden" name="action" id="confirmAction">

                <div class="modal-actions">
                    <button type="button" class="btn-modal cancel" onclick="closeConfirmModal()">
                        Batal
                    </button>
                    <button type="submit" class="btn-modal confirm" id="confirmButton">
                        Konfirmasi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function openImageModal(src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        document.getElementById('imageModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function showConfirmModal(action, mentorId, mentorName) {
        const modal = document.getElementById('confirmModal');
        const icon = document.getElementById('confirmIcon');
        const title = document.getElementById('confirmTitle');
        const message = document.getElementById('confirmMessage');
        const confirmBtn = document.getElementById('confirmButton');

        document.getElementById('confirmMentorId').value = mentorId;
        document.getElementById('confirmAction').value = action;

        if (action === 'approve') {
            icon.innerHTML = '<i class="bi bi-check-circle" style="color: #10b981;"></i>';
            title.textContent = 'Setujui Mentor?';
            message.innerHTML = `Anda akan menyetujui <strong>${mentorName}</strong> sebagai mentor. Mentor akan aktif dan bisa menerima request.`;
            confirmBtn.textContent = 'Ya, Setujui';
            confirmBtn.className = 'btn-modal confirm';
        } else {
            icon.innerHTML = '<i class="bi bi-x-circle" style="color: #ef4444;"></i>';
            title.textContent = 'Tolak Pendaftaran?';
            message.innerHTML = `Anda akan menolak pendaftaran <strong>${mentorName}</strong>. Mentor tidak akan muncul di daftar public.`;
            confirmBtn.textContent = 'Ya, Tolak';
            confirmBtn.className = 'btn-modal confirm reject';
        }

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Auto-dismiss alert
    setTimeout(() => {
        document.querySelectorAll('.alert-custom').forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'all 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageModal();
            closeConfirmModal();
        }
    });

    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeConfirmModal();
    });
    </script>
</body>
</html>
