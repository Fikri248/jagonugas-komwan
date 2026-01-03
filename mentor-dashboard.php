<?php
// mentor-dashboard.php - Dashboard Mentor (v5.4 - Fix Google Avatar)
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

$mentor_id = (int)$_SESSION['user_id'];

try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed.');
}

// ===================== HELPER AVATAR (GOOGLE/LOKAL) =====================
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($avatar, $base = '') {
        if (empty($avatar)) return '';
        if (filter_var($avatar, FILTER_VALIDATE_URL)) return $avatar;
        return $base . '/' . ltrim($avatar, '/');
    }
}

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

// ===================== DATA MENTOR =====================
$stmt = $pdo->prepare("SELECT name, email, avatar, total_rating, review_count FROM users WHERE id = ? AND role = 'mentor'");
$stmt->execute([$mentor_id]);
$mentorRow = $stmt->fetch(PDO::FETCH_ASSOC);

$name = $mentorRow['name'] ?? ($_SESSION['name'] ?? 'Mentor');
$email = $mentorRow['email'] ?? '';
$avatar = $mentorRow['avatar'] ?? null;

// v5.4: Generate avatar URL (support Google avatar)
$avatarUrl = get_avatar_url($avatar, $BASE);
$initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');

// ===================== STATISTIK DASHBOARD =====================

// 1. Total sesi
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentor_id = ?");
$stmt->execute([$mentor_id]);
$totalSesi = (int)$stmt->fetchColumn();

// 2. Total Gems dari SESI
$stmt = $pdo->prepare("SELECT COALESCE(SUM(price), 0) FROM sessions WHERE mentor_id = ? AND status IN ('completed', 'ongoing')");
$stmt->execute([$mentor_id]);
$gemsFromSessions = (int)$stmt->fetchColumn();

// 3. Total Gems dari FORUM
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(ft.gem_reward), 0) 
    FROM forum_replies fr
    JOIN forum_threads ft ON fr.thread_id = ft.id
    WHERE fr.user_id = ? AND fr.is_best_answer = 1
");
$stmt->execute([$mentor_id]);
$gemsFromForum = (int)$stmt->fetchColumn();

// *** TOTAL GEMS = SESI + FORUM (FINAL - JANGAN DIUBAH LAGI) ***
$totalGems = $gemsFromSessions + $gemsFromForum;

// 4. Rating mentor
$rating = 0;
if ($mentorRow && $mentorRow['review_count'] > 0) {
    $rating = round($mentorRow['total_rating'] / $mentorRow['review_count'], 1);
}

// 5. Mahasiswa aktif
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM sessions WHERE mentor_id = ?");
$stmt->execute([$mentor_id]);
$siswaAktif = (int)$stmt->fetchColumn();

// 6. Sesi pending
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentor_id = ? AND status = 'pending'");
$stmt->execute([$mentor_id]);
$pendingCount = (int)$stmt->fetchColumn();

// 7. Booking terbaru
$stmt = $pdo->prepare("
    SELECT s.*, u.name AS student_name, u.avatar AS student_avatar
    FROM sessions s
    JOIN users u ON s.student_id = u.id
    WHERE s.mentor_id = ?
    ORDER BY s.created_at DESC
    LIMIT 5
");
$stmt->execute([$mentor_id]);
$bookingTerbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Review terbaru
$stmt = $pdo->prepare("
    SELECT s.*, u.name AS student_name, u.avatar AS student_avatar
    FROM sessions s
    JOIN users u ON s.student_id = u.id
    WHERE s.mentor_id = ?
      AND s.status = 'completed'
      AND s.rating IS NOT NULL
    ORDER BY s.updated_at DESC
    LIMIT 3
");
$stmt->execute([$mentor_id]);
$reviewTerbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Stats Forum
$stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_replies WHERE user_id = ?");
$stmt->execute([$mentor_id]);
$forumAnswers = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_replies WHERE user_id = ? AND is_best_answer = 1");
$stmt->execute([$mentor_id]);
$bestAnswers = (int)$stmt->fetchColumn();

// Helper functions
function statusLabel($status) {
    $labels = [
        'pending'   => 'Menunggu',
        'ongoing'   => 'Berlangsung',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    return $labels[$status] ?? $status;
}

function relativeTime($dateString) {
    $now  = time();
    $time = strtotime($dateString);
    $diff = $now - $time;

    if ($diff < 60) return 'baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 172800) return 'Kemarin';
    return date('d M Y, H:i', $time);
}

// *** FORMAT UNTUK DISPLAY ***
$totalGemsFormatted = number_format($totalGems, 0, ',', '.');
$gemsSessionsFormatted = number_format($gemsFromSessions, 0, ',', '.');
$gemsForumFormatted = number_format($gemsFromForum, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mentor - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; min-height: 100vh; color: #1e293b; line-height: 1.6; }

        .dash-container { max-width: 1280px; margin: 0 auto; padding: 2rem 1.5rem; }

        .welcome-banner { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 20px; padding: 2rem 2.5rem; color: white; margin-bottom: 2rem; position: relative; overflow: hidden; }
        .welcome-banner::before { content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: rgba(255,255,255,0.08); border-radius: 50%; }
        .welcome-banner::after { content: ''; position: absolute; bottom: -30%; right: 5%; width: 200px; height: 200px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .welcome-content { position: relative; z-index: 1; display: flex; align-items: center; gap: 20px; }
        
        /* v5.4: Welcome Avatar Style */
        .welcome-avatar {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: 700;
            color: white;
            overflow: hidden;
            flex-shrink: 0;
            border: 3px solid rgba(255,255,255,0.3);
        }
        .welcome-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .welcome-text h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 12px; }
        .welcome-text h1 i { font-size: 1.5rem; }
        .welcome-text p { opacity: 0.9; font-size: 1rem; max-width: 500px; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0; transition: all 0.2s; text-align: center; display: flex; flex-direction: column; align-items: center; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
        .stat-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem; }
        .stat-icon.blue { background: #dbeafe; color: #2563eb; }
        .stat-icon.green { background: #d1fae5; color: #059669; }
        .stat-icon.yellow { background: #fef3c7; color: #d97706; }
        .stat-icon.purple { background: #ede9fe; color: #7c3aed; }
        .stat-value { font-size: 2.25rem; font-weight: 700; color: #0f172a; line-height: 1; margin-bottom: 0.25rem; }
        .stat-label { font-size: 0.9rem; color: #64748b; font-weight: 500; }
        .stat-breakdown { font-size: 0.75rem; color: #94a3b8; margin-top: 4px; }

        .main-grid { display: flex; flex-direction: column; gap: 1.5rem; }
        .two-col-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }

        .section-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }
        .section-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; }
        .section-header h2 { font-size: 1.05rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .section-header h2 i { color: #10b981; }
        .section-link { font-size: 0.85rem; color: #10b981; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 4px; }
        .section-link:hover { text-decoration: underline; }
        .section-body { padding: 1.25rem 1.5rem; }

        .booking-list { display: flex; flex-direction: column; }
        .booking-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem 0; border-bottom: 1px solid #f1f5f9; }
        .booking-item:last-child { border-bottom: none; }
        .booking-left { display: flex; align-items: center; gap: 12px; }
        .booking-avatar { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #10b981, #059669); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; flex-shrink: 0; overflow: hidden; }
        .booking-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .booking-info { display: flex; flex-direction: column; }
        .booking-name { font-weight: 600; color: #0f172a; font-size: 0.95rem; }
        .booking-meta { font-size: 0.8rem; color: #64748b; display: flex; align-items: center; gap: 8px; }
        .booking-right { display: flex; align-items: center; gap: 0.75rem; }
        .booking-status { padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
        .booking-status.pending { background: #fef3c7; color: #92400e; }
        .booking-status.ongoing { background: #dbeafe; color: #1e40af; }
        .booking-status.completed { background: #d1fae5; color: #065f46; }
        .booking-status.cancelled { background: #fee2e2; color: #991b1b; }
        .booking-actions { display: flex; gap: 6px; }
        .btn-action { padding: 8px 14px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-accept { background: #10b981; color: white; }
        .btn-accept:hover { background: #059669; }
        .btn-reject { background: #f1f5f9; color: #64748b; }
        .btn-reject:hover { background: #fee2e2; color: #ef4444; }

        .forum-card { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 16px; padding: 1.75rem; color: white; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; }
        .forum-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; }
        .forum-icon i { font-size: 1.75rem; color: white; }
        .forum-card h3 { font-size: 1.15rem; margin-bottom: 0.5rem; font-weight: 700; }
        .forum-card p { font-size: 0.9rem; opacity: 0.9; margin-bottom: 1.25rem; line-height: 1.5; }
        .forum-stats { display: flex; justify-content: center; gap: 2rem; margin-bottom: 1.25rem; }
        .forum-stat { text-align: center; }
        .forum-stat-value { font-size: 1.75rem; font-weight: 700; }
        .forum-stat-label { font-size: 0.8rem; opacity: 0.85; }
        .btn-forum { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 28px; background: white; color: #059669; border-radius: 10px; font-weight: 600; font-size: 0.9rem; text-decoration: none; transition: all 0.2s; }
        .btn-forum:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }

        .review-list { display: flex; flex-direction: column; gap: 1rem; }
        .review-item { padding: 1rem; background: #f8fafc; border-radius: 12px; }
        .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .review-author { font-weight: 600; color: #0f172a; font-size: 0.9rem; }
        .review-rating { display: flex; align-items: center; gap: 4px; color: #f59e0b; font-size: 0.9rem; font-weight: 600; }
        .review-text { font-size: 0.9rem; color: #475569; font-style: italic; line-height: 1.6; }

        .empty-state { text-align: center; padding: 2rem 1rem; color: #64748b; }
        .empty-state i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 0.75rem; display: block; }
        .empty-state h4 { font-size: 1rem; color: #475569; margin-bottom: 0.25rem; }
        .empty-state p { font-size: 0.85rem; }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .two-col-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .welcome-banner { padding: 1.5rem; }
            .welcome-content { flex-direction: column; text-align: center; }
            .welcome-text h1 { font-size: 1.4rem; justify-content: center; }
            .welcome-text p { text-align: center; }
            .booking-item { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .booking-right { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/mentor-navbar.php'; ?>

<div class="dash-container">
    
    <section class="welcome-banner">
        <div class="welcome-content">
            <!-- v5.4: Avatar dengan support Google -->
            <div class="welcome-avatar">
                <?php if ($avatarUrl): ?>
                    <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" referrerpolicy="no-referrer">
                <?php else: ?>
                    <?= htmlspecialchars($initial) ?>
                <?php endif; ?>
            </div>
            <div class="welcome-text">
                <h1><i class="bi bi-hand-wave"></i> Halo, <?= htmlspecialchars($name) ?>!</h1>
                <p>Siap membantu mahasiswa hari ini? Cek booking dan chat yang masuk dari menu navigasi.</p>
            </div>
        </div>
    </section>

    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-journal-check"></i></div>
            <div class="stat-value"><?= $totalSesi ?></div>
            <div class="stat-label">Total Sesi</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-gem"></i></div>
            <div class="stat-value"><?= $totalGemsFormatted ?></div>
            <div class="stat-label">Gems Didapat</div>
            <?php if ($gemsFromForum > 0): ?>
            <div class="stat-breakdown">
                <i class="bi bi-briefcase"></i> <?= $gemsSessionsFormatted ?> sesi
                &nbsp;•&nbsp;
                <i class="bi bi-chat-quote"></i> <?= $gemsForumFormatted ?> forum
            </div>
            <?php endif; ?>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="bi bi-star-fill"></i></div>
            <div class="stat-value"><?= $rating > 0 ? number_format($rating, 1) : '0.0' ?></div>
            <div class="stat-label">Rating</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value"><?= $siswaAktif ?></div>
            <div class="stat-label">Mahasiswa Aktif</div>
        </div>
    </section>

    <div class="main-grid">
        <div class="two-col-grid">
            <section class="section-card">
                <div class="section-header">
                    <h2><i class="bi bi-calendar3"></i> Booking Terbaru</h2>
                    <a href="<?= $BASE ?>/mentor-sessions.php" class="section-link">
                        Lihat Semua <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="section-body">
                    <?php if (empty($bookingTerbaru)): ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"></i>
                            <h4>Belum Ada Booking</h4>
                            <p>Booking dari mahasiswa akan muncul di sini.</p>
                        </div>
                    <?php else: ?>
                        <div class="booking-list">
                            <?php foreach ($bookingTerbaru as $booking): 
                                $studentInitial = strtoupper(substr($booking['student_name'], 0, 1));
                                $studentAvatar = get_avatar_url($booking['student_avatar'] ?? '', $BASE);
                            ?>
                            <div class="booking-item">
                                <div class="booking-left">
                                    <div class="booking-avatar">
                                        <?php if ($studentAvatar): ?>
                                            <img src="<?= htmlspecialchars($studentAvatar) ?>" alt="" referrerpolicy="no-referrer">
                                        <?php else: ?>
                                            <?= $studentInitial ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="booking-info">
                                        <span class="booking-name"><?= htmlspecialchars($booking['student_name']) ?></span>
                                        <span class="booking-meta">
                                            <i class="bi bi-clock"></i> <?= (int)$booking['duration'] ?> menit
                                            <span>•</span> <?= relativeTime($booking['created_at']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="booking-right">
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <div class="booking-actions">
                                            <form method="POST" action="<?= $BASE ?>/mentor-session-action.php" style="display:inline;">
                                                <input type="hidden" name="session_id" value="<?= $booking['id'] ?>">
                                                <input type="hidden" name="action" value="accept">
                                                <button type="submit" class="btn-action btn-accept">Terima</button>
                                            </form>
                                            <form method="POST" action="<?= $BASE ?>/mentor-session-action.php" style="display:inline;">
                                                <input type="hidden" name="session_id" value="<?= $booking['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn-action btn-reject">Tolak</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="booking-status <?= $booking['status'] ?>">
                                            <?= statusLabel($booking['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="forum-card">
                <div class="forum-icon">
                    <i class="bi bi-chat-square-quote-fill"></i>
                </div>
                <h3>Forum Diskusi</h3>
                <p>Bantu mahasiswa dengan menjawab pertanyaan di forum dan dapatkan gems!</p>
                <div class="forum-stats">
                    <div class="forum-stat">
                        <div class="forum-stat-value"><?= $forumAnswers ?></div>
                        <div class="forum-stat-label">Jawaban</div>
                    </div>
                    <div class="forum-stat">
                        <div class="forum-stat-value"><?= $bestAnswers ?></div>
                        <div class="forum-stat-label">Terbaik</div>
                    </div>
                    <div class="forum-stat">
                        <div class="forum-stat-value"><?= $gemsForumFormatted ?></div>
                        <div class="forum-stat-label">Gems Forum</div>
                    </div>
                </div>
                <a href="<?= $BASE ?>/mentor-forum.php" class="btn-forum">
                    <i class="bi bi-arrow-right-circle"></i> Kunjungi Forum
                </a>
            </div>
        </div>

        <section class="section-card">
            <div class="section-header">
                <h2><i class="bi bi-chat-quote"></i> Review Terbaru</h2>
            </div>
            <div class="section-body">
                <?php if (empty($reviewTerbaru)): ?>
                    <div class="empty-state">
                        <i class="bi bi-chat-square-heart"></i>
                        <h4>Belum Ada Review</h4>
                        <p>Review dari mahasiswa akan muncul di sini.</p>
                    </div>
                <?php else: ?>
                    <div class="review-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                        <?php foreach ($reviewTerbaru as $rev): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <span class="review-author"><?= htmlspecialchars($rev['student_name']) ?></span>
                                <span class="review-rating">
                                    <i class="bi bi-star-fill"></i>
                                    <?= number_format($rev['rating'], 1) ?>
                                </span>
                            </div>
                            <p class="review-text">
                                "<?= htmlspecialchars($rev['review'] ?: 'Tidak ada komentar.') ?>"
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

</body>
</html>
