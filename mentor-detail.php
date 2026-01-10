<?php
ob_start();

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

$mentorId = intval($_GET['id'] ?? 0);

if ($mentorId <= 0) {
    header("Location: " . url_path('admin-mentors.php'));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'mentor'");
    $stmt->execute([$mentorId]);
    $mentor = $stmt->fetch();

    if (!$mentor) {
        header("Location: " . url_path('admin-mentors.php'));
        exit;
    }

    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.id) as total_sessions,
            COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as completed_sessions,
            COUNT(DISTINCT CASE WHEN s.status = 'pending' THEN s.id END) as pending_sessions,
            COUNT(DISTINCT CASE WHEN s.status = 'ongoing' THEN s.id END) as ongoing_sessions,
            COUNT(DISTINCT CASE WHEN s.status = 'cancelled' THEN s.id END) as cancelled_sessions
        FROM sessions s
        WHERE s.mentor_id = ?
    ");
    $stmtStats->execute([$mentorId]);
    $stats = $stmtStats->fetch();

    // Ambil data siswa yang dibimbing mentor ini
    $stmtSessions = $pdo->prepare("
        SELECT s.*, u.name as student_name, u.email as student_email
        FROM sessions s
        LEFT JOIN users u ON s.student_id = u.id
        WHERE s.mentor_id = ?
        ORDER BY s.created_at DESC
        LIMIT 10
    ");
    $stmtSessions->execute([$mentorId]);
    $recentSessions = $stmtSessions->fetchAll();

    // Ambil review dari siswa
    $stmtReviews = $pdo->prepare("
        SELECT r.*, u.name as student_name, u.email as student_email
        FROM mentor_reviews r
        LEFT JOIN users u ON r.student_id = u.id
        WHERE r.mentor_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmtReviews->execute([$mentorId]);
    $reviews = $stmtReviews->fetchAll();

    $stmtRating = $pdo->prepare("
        SELECT 
            AVG(rating) as avg_rating,
            COUNT(*) as review_count
        FROM mentor_reviews
        WHERE mentor_id = ?
    ");
    $stmtRating->execute([$mentorId]);
    $ratingStats = $stmtRating->fetch();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$avgRating = $ratingStats['avg_rating'] ? number_format($ratingStats['avg_rating'], 1) : '0.0';
$reviewCount = $ratingStats['review_count'] ?? 0;

$statusClass = '';
$statusLabel = '';
$statusIcon = '';

switch ($mentor['is_verified']) {
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Mentor - <?= htmlspecialchars($mentor['name'] ?? 'Unknown') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; line-height: 1.6; }
        .main-content { padding: 2rem; min-height: 100vh; }
        .back-button { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: white; color: #667eea; border: 2px solid #667eea; border-radius: 10px; text-decoration: none; font-weight: 600; margin-bottom: 1.5rem; transition: all 0.3s ease; }
        .back-button:hover { background: #667eea; color: white; transform: translateX(-4px); }
        .profile-header { background: white; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 2rem; margin-bottom: 2rem; }
        .profile-top { display: flex; align-items: start; gap: 2rem; margin-bottom: 2rem; }
        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; font-weight: 700; flex-shrink: 0; border: 4px solid white; box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3); }
        .profile-info { flex: 1; }
        .profile-info h1 { font-size: 2rem; font-weight: 700; color: #1a202c; margin-bottom: 0.5rem; }
        .profile-info .email { color: #64748b; font-size: 1.1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .status-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 25px; font-size: 0.9rem; font-weight: 600; }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.approved { background: #d1fae5; color: #065f46; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 2rem; }
        .stat-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 1.5rem; border-radius: 12px; text-align: center; }
        .stat-card i { font-size: 2rem; margin-bottom: 0.5rem; }
        .stat-card.rating i { color: #fbbf24; }
        .stat-card.sessions i { color: #667eea; }
        .stat-card.reviews i { color: #10b981; }
        .stat-card.rate i { color: #ef4444; }
        .stat-card .value { display: block; font-size: 2rem; font-weight: 700; color: #1a202c; margin: 0.5rem 0; }
        .stat-card .label { color: #64748b; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-section { background: white; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 2rem; margin-bottom: 2rem; }
        .detail-section h2 { font-size: 1.5rem; font-weight: 700; color: #1a202c; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .info-item { padding: 1rem; background: #f8f9fa; border-radius: 10px; border-left: 3px solid #667eea; }
        .info-item .label { display: block; color: #64748b; font-size: 0.85rem; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-item .value { display: block; color: #1a202c; font-size: 1.1rem; font-weight: 600; }
        .expertise-tags { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
        .expertise-tag { padding: 0.5rem 1rem; background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #3730a3; border-radius: 25px; font-size: 0.9rem; font-weight: 600; }
        .bio-box { padding: 1.5rem; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #667eea; margin-top: 1rem; }
        .bio-box p { margin: 0; color: #4a5568; line-height: 1.8; }
        .transkrip-box { padding: 1.5rem; background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 10px; margin-top: 1rem; }
        .transkrip-buttons { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .btn-transkrip { flex: 1; padding: 0.75rem 1.5rem; background: white; color: #92400e; border: 2px solid #f59e0b; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.3s ease; }
        .btn-transkrip:hover { background: #f59e0b; color: white; }
        .transkrip-preview img { width: 100%; max-width: 600px; border-radius: 10px; border: 3px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.3s ease; }
        .transkrip-preview img:hover { transform: scale(1.02); }
        .sessions-table { width: 100%; margin-top: 1rem; border-collapse: collapse; }
        .sessions-table th { background: #f8f9fa; padding: 1rem; text-align: left; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; }
        .sessions-table td { padding: 1rem; border-bottom: 1px solid #e2e8f0; color: #1a202c; }
        .sessions-table tbody tr:hover { background: #f8f9fa; }
        .session-status { padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; display: inline-block; }
        .session-status.completed { background: #d1fae5; color: #065f46; }
        .session-status.pending { background: #fef3c7; color: #92400e; }
        .session-status.ongoing { background: #dbeafe; color: #1e40af; }
        .session-status.cancelled { background: #fee2e2; color: #991b1b; }
        .review-card { padding: 1.5rem; background: #f8f9fa; border-radius: 10px; margin-bottom: 1rem; border-left: 3px solid #fbbf24; }
        .review-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem; }
        .review-header strong { color: #1a202c; font-size: 1.05rem; }
        .review-rating { display: flex; gap: 0.25rem; color: #fbbf24; }
        .review-comment { color: #4a5568; line-height: 1.6; margin-bottom: 0.5rem; }
        .review-meta { display: flex; gap: 1rem; font-size: 0.85rem; color: #64748b; }
        .empty-state { text-align: center; padding: 3rem 2rem; color: #64748b; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; }
        .image-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.9); z-index: 10000; align-items: center; justify-content: center; cursor: zoom-out; }
        .image-modal.active { display: flex; }
        .image-modal img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.5); }
        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .profile-top { flex-direction: column; align-items: center; text-align: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .info-grid { grid-template-columns: 1fr; }
            .sessions-table { font-size: 0.85rem; }
            .sessions-table th, .sessions-table td { padding: 0.75rem 0.5rem; }
        }
    </style>
</head>
<body>
    <?php include 'admin-navbar.php'; ?>
    <div class="main-content">
        <a href="<?= url_path('admin-mentors.php') ?>" class="back-button">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Mentor
        </a>
        <div class="profile-header">
            <div class="profile-top">
                <div class="profile-avatar"><?= strtoupper(substr($mentor['name'] ?? '?', 0, 1)) ?></div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($mentor['name'] ?? 'Unknown') ?></h1>
                    <p class="email"><i class="bi bi-envelope"></i> <?= htmlspecialchars($mentor['email'] ?? '-') ?></p>
                    <span class="status-badge <?= $statusClass ?>"><i class="bi bi-<?= $statusIcon ?>"></i> <?= $statusLabel ?></span>
                </div>
            </div>
            <div class="stats-grid">
                <div class="stat-card rating"><i class="bi bi-star-fill"></i><span class="value"><?= $avgRating ?></span><span class="label">Rating</span></div>
                <div class="stat-card sessions"><i class="bi bi-calendar-check-fill"></i><span class="value"><?= $stats['completed_sessions'] ?? 0 ?></span><span class="label">Sesi Selesai</span></div>
                <div class="stat-card reviews"><i class="bi bi-chat-dots-fill"></i><span class="value"><?= $reviewCount ?></span><span class="label">Reviews</span></div>
                <div class="stat-card rate"><i class="bi bi-gem"></i><span class="value"><?= number_format($mentor['hourly_rate'] ?? 0) ?></span><span class="label">Gems/Jam</span></div>
            </div>
        </div>
        <div class="detail-section">
            <h2><i class="bi bi-person-circle"></i> Informasi Personal</h2>
            <div class="info-grid">
                <div class="info-item"><span class="label">Program Studi</span><span class="value"><?= htmlspecialchars($mentor['program_studi'] ?? '-') ?></span></div>
                <div class="info-item"><span class="label">Semester</span><span class="value"><?= $mentor['semester'] ? 'Semester ' . $mentor['semester'] : '-' ?></span></div>
                <div class="info-item"><span class="label">Tanggal Bergabung</span><span class="value"><?= $mentor['created_at'] ? date('d F Y', strtotime($mentor['created_at'])) : '-' ?></span></div>
                <div class="info-item"><span class="label">Terakhir Update</span><span class="value"><?= $mentor['updated_at'] ? date('d F Y, H:i', strtotime($mentor['updated_at'])) : '-' ?></span></div>
            </div>
            <?php if (!empty($mentor['expertise'])): ?>
            <div style="margin-top: 2rem;">
                <strong style="display: block; margin-bottom: 0.75rem; color: #4a5568;"><i class="bi bi-lightbulb"></i> Keahlian</strong>
                <div class="expertise-tags">
                    <?php $expertiseArr = json_decode($mentor['expertise'], true); if (!is_array($expertiseArr)) $expertiseArr = []; foreach ($expertiseArr as $exp): ?>
                    <span class="expertise-tag"><?= htmlspecialchars((string)$exp) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($mentor['specialization'])): ?>
            <div style="margin-top: 1.5rem;">
                <strong style="display: block; margin-bottom: 0.75rem; color: #4a5568;"><i class="bi bi-briefcase"></i> Spesialisasi</strong>
                <div class="bio-box"><p><?= nl2br(htmlspecialchars($mentor['specialization'])) ?></p></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($mentor['bio'])): ?>
            <div style="margin-top: 1.5rem;">
                <strong style="display: block; margin-bottom: 0.75rem; color: #4a5568;"><i class="bi bi-person-lines-fill"></i> Bio</strong>
                <div class="bio-box"><p><?= nl2br(htmlspecialchars($mentor['bio'])) ?></p></div>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($mentor['transkrip_path'])): ?>
        <div class="detail-section">
            <h2><i class="bi bi-file-earmark-text"></i> Transkrip Nilai</h2>
            <div class="transkrip-box">
                <div class="transkrip-buttons">
                    <a href="<?= url_path($mentor['transkrip_path']) ?>" target="_blank" class="btn-transkrip"><i class="bi bi-eye"></i> Lihat Transkrip</a>
                    <a href="<?= url_path($mentor['transkrip_path']) ?>" download class="btn-transkrip"><i class="bi bi-download"></i> Download</a>
                </div>
                <?php $ext = pathinfo($mentor['transkrip_path'], PATHINFO_EXTENSION); $isImage = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'], true); ?>
                <?php if ($isImage): ?>
                <div class="transkrip-preview"><img src="<?= url_path($mentor['transkrip_path']) ?>" alt="Transkrip" onclick="openImageModal(this.src)"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="detail-section">
            <h2><i class="bi bi-bar-chart"></i> Statistik Sesi</h2>
            <div class="info-grid">
                <div class="info-item"><span class="label">Total Sesi</span><span class="value"><?= $stats['total_sessions'] ?? 0 ?></span></div>
                <div class="info-item"><span class="label">Selesai</span><span class="value"><?= $stats['completed_sessions'] ?? 0 ?></span></div>
                <div class="info-item"><span class="label">Sedang Berlangsung</span><span class="value"><?= $stats['ongoing_sessions'] ?? 0 ?></span></div>
                <div class="info-item"><span class="label">Pending</span><span class="value"><?= $stats['pending_sessions'] ?? 0 ?></span></div>
                <div class="info-item"><span class="label">Dibatalkan</span><span class="value"><?= $stats['cancelled_sessions'] ?? 0 ?></span></div>
            </div>
        </div>
        <div class="detail-section">
            <h2><i class="bi bi-clock-history"></i> Riwayat Sesi dengan Siswa</h2>
            <?php if (empty($recentSessions)): ?>
            <div class="empty-state"><i class="bi bi-calendar-x"></i><p>Belum ada sesi mentoring dengan siswa</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="sessions-table">
                    <thead>
                        <tr><th>Siswa (Student)</th><th>Tanggal Mulai</th><th>Tanggal Selesai</th><th>Durasi</th><th>Harga</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSessions as $session): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($session['student_name'] ?? 'Unknown') ?></strong><br><small style="color: #64748b;"><?= htmlspecialchars($session['student_email'] ?? '') ?></small></td>
                            <td><?= $session['started_at'] ? date('d M Y, H:i', strtotime($session['started_at'])) : '-' ?></td>
                            <td><?= $session['ended_at'] ? date('d M Y, H:i', strtotime($session['ended_at'])) : '-' ?></td>
                            <td><?= $session['duration'] ?? '-' ?> jam</td>
                            <td>Rp <?= number_format($session['price'] ?? 0, 0, ',', '.') ?></td>
                            <td><span class="session-status <?= $session['status'] ?? 'pending' ?>"><?= ucfirst($session['status'] ?? 'pending') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <div class="detail-section">
            <h2><i class="bi bi-star"></i> Review dari Siswa</h2>
            <?php if (empty($reviews)): ?>
            <div class="empty-state"><i class="bi bi-chat"></i><p>Belum ada review dari siswa</p></div>
            <?php else: ?>
            <?php foreach ($reviews as $review): ?>
            <div class="review-card">
                <div class="review-header">
                    <strong><?= htmlspecialchars($review['student_name'] ?? 'Anonymous') ?></strong>
                    <div class="review-rating">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                        <i class="bi bi-star<?= $i < ($review['rating'] ?? 0) ? '-fill' : '' ?>"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php if (!empty($review['comment'])): ?>
                <p class="review-comment"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                <?php endif; ?>
                <div class="review-meta">
                    <span><i class="bi bi-calendar"></i> <?= $review['created_at'] ? date('d M Y', strtotime($review['created_at'])) : '-' ?></span>
                    <span><i class="bi bi-person"></i> Siswa: <?= htmlspecialchars($review['student_email'] ?? '-') ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="image-modal" id="imageModal" onclick="closeImageModal()"><img id="modalImage" src="" alt="Preview"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function openImageModal(src) { document.getElementById('modalImage').src = src; document.getElementById('imageModal').classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeImageModal() { document.getElementById('imageModal').classList.remove('active'); document.body.style.overflow = ''; }
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeImageModal(); } });
    </script>
</body>
</html>
