<?php
// student-dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

// Pastikan login_time ada (buat hitung waktu aktif)
if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = time();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: " . $BASE . "/login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$loginTime = (int)($_SESSION['login_time'] ?? time());

// Koneksi PDO
$pdo = null;
try {
    $pdo = (new Database())->getConnection();
} catch (Throwable $e) {
    $pdo = null;
}

$currentUser = null;
$gemBalance = 0;
$name = 'User';

$successMsg = '';
if (isset($_GET['profile_updated'])) {
    $successMsg = 'Profil berhasil diperbarui!';
}
if (isset($_GET['rated'])) {
    $successMsg = 'Review berhasil dikirim! Terima kasih atas feedback Anda.';
}
if (isset($_GET['booking_success'])) {
    $successMsg = 'Booking mentor berhasil! Mentor akan segera menghubungi Anda.';
}

// Ambil data user (kalau PDO ada)
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

    if ($hours > 0) {
        return $mins > 0 ? $hours . ' jam ' . $mins . ' menit' : $hours . ' jam';
    }
    return $roundedMinutes . ' menit';
}
$activeTimeFormatted = formatActiveTime($activeSeconds);

function time_elapsed(string $datetime): string {
    $tz = new DateTimeZone('Asia/Jakarta');
    $now = new DateTime('now', $tz);

    try {
        $ago = new DateTime($datetime, $tz);
    } catch (Throwable $e) {
        return 'Baru saja';
    }

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
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT fr.thread_id)
            FROM forum_replies fr
            JOIN forum_threads ft ON fr.thread_id = ft.id
            WHERE fr.user_id = ? AND ft.user_id != ?
        ");
        $stmt->execute([$userId, $userId]);
        $totalReplies = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}

    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(ft.gem_reward), 0)
            FROM forum_replies fr
            JOIN forum_threads ft ON fr.thread_id = ft.id
            WHERE fr.user_id = ? AND fr.is_best_answer = 1
        ");
        $stmt->execute([$userId]);
        $totalGemsEarned = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}
}

// ===== MENTOR BOOKING STATS =====
$active_sessions = 0;
$pending_sessions = 0;
$completed_sessions = 0;
$need_rating = 0;

if ($pdo) {
    try {
        // Active sessions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM sessions 
            WHERE student_id = ? AND status = 'ongoing'
        ");
        $stmt->execute([$userId]);
        $active_sessions = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}

    try {
        // Pending sessions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM sessions 
            WHERE student_id = ? AND status = 'pending'
        ");
        $stmt->execute([$userId]);
        $pending_sessions = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}

    try {
        // Completed sessions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM sessions 
            WHERE student_id = ? AND status = 'completed'
        ");
        $stmt->execute([$userId]);
        $completed_sessions = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}

    try {
        // Sessions that need rating
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM sessions s
            LEFT JOIN mentor_reviews mr ON s.id = mr.session_id
            WHERE s.student_id = ? AND s.status = 'completed' AND mr.id IS NULL
        ");
        $stmt->execute([$userId]);
        $need_rating = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}
}

// Pertanyaan sendiri (ambil 1 terbaru)
$myQuestions = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT ft.*, u.name AS author_name, u.avatar AS author_avatar, fc.name AS category_name,
                   (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id) AS reply_count
            FROM forum_threads ft
            JOIN users u ON ft.user_id = u.id
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE ft.user_id = ?
            ORDER BY ft.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $myQuestions = $stmt->fetchAll();
    } catch (Throwable $e) {}
}

// Pertanyaan mahasiswa lain
$recentQuestionsLimit = !empty($myQuestions) ? 3 : 1;
$recentQuestionsLimit = max(1, min(10, (int)$recentQuestionsLimit));
$recentQuestions = [];

if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT ft.*, u.name AS author_name, u.avatar AS author_avatar, fc.name AS category_name,
                   (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id) AS reply_count
            FROM forum_threads ft
            JOIN users u ON ft.user_id = u.id
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE ft.user_id != ?
            ORDER BY ft.created_at DESC
            LIMIT {$recentQuestionsLimit}
        ");
        $stmt->execute([$userId]);
        $recentQuestions = $stmt->fetchAll();
    } catch (Throwable $e) {}
}

// Mentor populer
$popularMentors = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT m.*, u.name, u.avatar
            FROM mentors m
            JOIN users u ON m.user_id = u.id
            WHERE m.is_verified = 1
            ORDER BY m.rating DESC
            LIMIT 3
        ");
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
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="dashboard-page">

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
                    <i class="bi bi-pencil-square"></i>
                    Tanya Sekarang
                </a>
            </div>

            <div class="dash-hero-stats">
                <div class="dash-stat-card">
                    <div class="dash-stat-icon blue"><i class="bi bi-chat-heart"></i></div>
                    <div class="dash-stat-info">
                        <span class="dash-stat-value"><?php echo (int)$totalReplies; ?></span>
                        <span class="dash-stat-label">Jawaban yang lo bantu</span>
                    </div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-icon purple"><i class="bi bi-gem"></i></div>
                    <div class="dash-stat-info">
                        <span class="dash-stat-value"><?php echo (int)$totalGemsEarned; ?></span>
                        <span class="dash-stat-label">Gem yang lo dapet</span>
                    </div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-icon green"><i class="bi bi-clock-history"></i></div>
                    <div class="dash-stat-info">
                        <span class="dash-stat-value"><?php echo htmlspecialchars($activeTimeFormatted); ?></span>
                        <span class="dash-stat-label">Waktu aktif</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== MENTOR BOOKING QUICK STATS ===== -->
        <section class="dash-quick-stats">
            <div class="quick-stat-card">
                <div class="stat-icon ongoing">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
                <div class="stat-content">
                    <h3>Sesi Berlangsung</h3>
                    <p class="stat-number"><?php echo $active_sessions; ?></p>
                    <a href="<?php echo htmlspecialchars($BASE); ?>/student-sessions.php?filter=ongoing" class="stat-link">
                        Lihat Detail <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="quick-stat-card">
                <div class="stat-icon pending">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="stat-content">
                    <h3>Menunggu Konfirmasi</h3>
                    <p class="stat-number"><?php echo $pending_sessions; ?></p>
                    <a href="<?php echo htmlspecialchars($BASE); ?>/student-sessions.php?filter=pending" class="stat-link">
                        Lihat Detail <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="quick-stat-card">
                <div class="stat-icon rating">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div class="stat-content">
                    <h3>Perlu Rating</h3>
                    <p class="stat-number"><?php echo $need_rating; ?></p>
                    <?php if ($need_rating > 0): ?>
                        <a href="<?php echo htmlspecialchars($BASE); ?>/student-sessions.php?filter=completed" class="stat-link highlight">
                            Beri Rating Sekarang <i class="bi bi-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="stat-link-muted">
                            <i class="bi bi-check-circle"></i> Semua sudah di-rating
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- My Questions Section -->
        <?php if (!empty($myQuestions)): ?>
        <section class="dash-questions">
            <div class="dash-section-header">
                <h2>Pertanyaan yang Lo Ajukan</h2>
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php?filter=my" class="btn btn-text">Lihat Semua</a>
            </div>

            <div class="dash-questions-list">
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

                        <p class="dash-question-excerpt">
                            <?php
                            $content = (string)($q['content'] ?? '');
                            echo htmlspecialchars(mb_substr($content, 0, 150)) . '...';
                            ?>
                        </p>

                        <div class="dash-question-footer">
                            <div class="dash-question-author"></div>
                            <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="btn btn-outline btn-sm">
                                <?php echo ((int)$q['reply_count'] > 0) ? 'Lihat Jawaban' : 'Lihat Detail'; ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- Sidebar -->
    <aside class="dash-sidebar">
        <div class="dash-sidebar-card">
            <h3>Menu Cepat</h3>
            <div class="dash-quick-menu">
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php" class="dash-quick-item">
                    <i class="bi bi-chat-square-text"></i><span>Forum Diskusi</span>
                </a>
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-mentor.php" class="dash-quick-item">
                    <i class="bi bi-person-video3"></i><span>Cari Mentor</span>
                </a>
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-sessions.php" class="dash-quick-item">
                    <i class="bi bi-calendar-check"></i><span>Sesi Mentor</span>
                </a>
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-topup.php" class="dash-quick-item">
                    <i class="bi bi-gem"></i><span>Top Up Gem</span>
                </a>
            </div>
        </div>

        <div class="dash-sidebar-card">
            <h3>Mentor Populer</h3>
            <div class="dash-mentor-list">
                <?php if (empty($popularMentors)): ?>
                    <?php
                    $dummyMentors = [
                        ['name' => 'Ahmad Wijaya', 'expertise' => 'Pemrograman', 'rating' => 4.9],
                        ['name' => 'Siti Rahma', 'expertise' => 'Database', 'rating' => 4.8],
                        ['name' => 'Budi Santoso', 'expertise' => 'Jaringan', 'rating' => 4.7],
                    ];
                    foreach ($dummyMentors as $mentor):
                    ?>
                    <div class="dash-mentor-item">
                        <div class="dash-avatar sm"><?php echo strtoupper(substr($mentor['name'], 0, 1)); ?></div>
                        <div class="dash-mentor-info">
                            <span class="name"><?php echo htmlspecialchars($mentor['name']); ?></span>
                            <span class="expertise"><?php echo htmlspecialchars($mentor['expertise']); ?></span>
                        </div>
                        <div class="dash-mentor-rating"><i class="bi bi-star-fill"></i> <?php echo htmlspecialchars((string)$mentor['rating']); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($popularMentors as $mentor): ?>
                    <div class="dash-mentor-item">
                        <div class="dash-avatar sm">
                            <?php if (!empty($mentor['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($BASE . '/' . (string)$mentor['avatar']); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo strtoupper(substr((string)$mentor['name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="dash-mentor-info">
                            <span class="name"><?php echo htmlspecialchars((string)$mentor['name']); ?></span>
                            <span class="expertise"><?php echo htmlspecialchars((string)($mentor['specialization'] ?? $mentor['expertise'] ?? '')); ?></span>
                        </div>
                        <div class="dash-mentor-rating"><i class="bi bi-star-fill"></i> <?php echo number_format((float)($mentor['rating'] ?? 0), 1); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <a href="<?php echo htmlspecialchars($BASE); ?>/student-mentor.php" class="btn btn-outline btn-full btn-sm">Lihat Semua Mentor</a>
        </div>
    </aside>
</div>

<?php
$showRecentFullWidth = !empty($myQuestions);
?>

<!-- Other Students Questions (Full Width) -->
<?php if ($showRecentFullWidth): ?>
<div class="dash-full-section">
    <section class="dash-questions">
        <div class="dash-section-header">
            <h2>Pertanyaan dari Mahasiswa Lain</h2>
            <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php" class="btn btn-text">Lihat Semua</a>
        </div>

        <div class="dash-questions-list grid-three">
            <?php if (empty($recentQuestions)): ?>
                <div class="dash-empty-state">
                    <i class="bi bi-chat-square-text"></i>
                    <h3>Belum Ada Pertanyaan</h3>
                    <p>Belum ada pertanyaan dari mahasiswa lain.</p>
                    <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php" class="btn btn-primary">Lihat Forum</a>
                </div>
            <?php else: ?>
                <?php foreach ($recentQuestions as $q): ?>
                    <article class="dash-question-card <?php echo !empty($q['is_solved']) ? 'solved' : ''; ?>">
                        <div class="dash-question-header">
                            <div class="dash-question-meta">
                                <span class="category"><i class="bi bi-folder"></i> <?php echo htmlspecialchars((string)$q['category_name']); ?></span>
                                <span class="time"><i class="bi bi-clock"></i> <?php echo htmlspecialchars(time_elapsed((string)$q['created_at'])); ?></span>
                            </div>
                            <div class="dash-question-reward">
                                <?php if (!empty($q['is_solved'])): ?>
                                    <span class="badge-solved"><i class="bi bi-check-circle-fill"></i> Terjawab</span>
                                <?php endif; ?>
                                <span class="gem-reward">+<?php echo (int)($q['gem_reward'] ?? 0); ?> gem</span>
                            </div>
                        </div>

                        <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="dash-question-title-link">
                            <h3 class="dash-question-title"><?php echo htmlspecialchars((string)$q['title']); ?></h3>
                        </a>

                        <p class="dash-question-excerpt">
                            <?php
                            $content = (string)($q['content'] ?? '');
                            echo htmlspecialchars(mb_substr($content, 0, 80)) . '...';
                            ?>
                        </p>

                        <div class="dash-question-footer">
                            <div class="dash-question-author">
                                <div class="author-avatar">
                                    <?php if (!empty($q['author_avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($BASE . '/' . (string)$q['author_avatar']); ?>" alt="Avatar">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr((string)$q['author_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <span><?php echo htmlspecialchars((string)$q['author_name']); ?></span>
                            </div>

                            <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="btn btn-outline btn-sm">
                                <?php echo !empty($q['is_solved']) ? 'Lihat Jawaban' : 'Jawab'; ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php else: ?>
<!-- Alternative layout when no my questions -->
<div class="dash-container">
    <main class="dash-main" style="grid-column: 1 / -1;">
        <section class="dash-questions">
            <div class="dash-section-header">
                <h2>Pertanyaan dari Mahasiswa Lain</h2>
                <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php" class="btn btn-text">Lihat Semua</a>
            </div>

            <div class="dash-questions-list">
                <?php if (empty($recentQuestions)): ?>
                    <div class="dash-empty-state">
                        <i class="bi bi-chat-square-text"></i>
                        <h3>Belum Ada Pertanyaan</h3>
                        <p>Belum ada pertanyaan dari mahasiswa lain.</p>
                        <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum.php" class="btn btn-primary">Lihat Forum</a>
                    </div>
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
                                    <?php if (!empty($q['is_solved'])): ?>
                                        <span class="badge-solved"><i class="bi bi-check-circle-fill"></i> Terjawab</span>
                                    <?php endif; ?>
                                    <span class="gem-reward">+<?php echo (int)($q['gem_reward'] ?? 0); ?> gem</span>
                                </div>
                            </div>

                            <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="dash-question-title-link">
                                <h3 class="dash-question-title"><?php echo htmlspecialchars((string)$q['title']); ?></h3>
                            </a>

                            <p class="dash-question-excerpt">
                                <?php
                                $content = (string)($q['content'] ?? '');
                                echo htmlspecialchars(mb_substr($content, 0, 150)) . '...';
                                ?>
                            </p>

                            <div class="dash-question-footer">
                                <div class="dash-question-author">
                                    <div class="author-avatar">
                                        <?php if (!empty($q['author_avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($BASE . '/' . (string)$q['author_avatar']); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr((string)$q['author_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <span><?php echo htmlspecialchars((string)$q['author_name']); ?></span>
                                </div>

                                <a href="<?php echo htmlspecialchars($BASE); ?>/student-forum-thread.php?id=<?php echo (int)$q['id']; ?>" class="btn btn-outline btn-sm">
                                    <?php echo !empty($q['is_solved']) ? 'Lihat Jawaban' : 'Jawab'; ?>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<?php endif; ?>

<script>
// Auto-hide success alert
const successAlert = document.querySelector('.alert-success');
if (successAlert) {
    setTimeout(() => {
        successAlert.style.opacity = '0';
        successAlert.style.transform = 'translateY(-10px)';
        setTimeout(() => successAlert.remove(), 300);
    }, 5000);
}
</script>

</body>
</html>
