<?php
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_PATH . "/login");
    exit;
}

$userId = $_SESSION['user_id'];
$loginTime = $_SESSION['login_time'] ?? time();

// Get user data lengkap
$currentUser = null;
$gemBalance = 0;
$name = 'User';

// Success message dari redirect
$successMsg = '';
if (isset($_GET['profile_updated'])) {
    $successMsg = 'Profil berhasil diperbarui!';
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    if ($currentUser) {
        $gemBalance = $currentUser['gems'] ?? 0;
        $name = $currentUser['name'] ?? 'User';
    }
} catch (Exception $e) {
    $gemBalance = $_SESSION['gems'] ?? 0;
    $name = $_SESSION['name'] ?? 'User';
}

// Hitung waktu aktif
$activeSeconds = time() - $loginTime;

function formatActiveTime($seconds) {
    $minutes = floor($seconds / 60);
    if ($minutes < 5) return '< 5 menit';
    $roundedMinutes = floor($minutes / 5) * 5;
    $hours = floor($roundedMinutes / 60);
    $mins = $roundedMinutes % 60;
    if ($hours > 0) {
        return $mins > 0 ? $hours . ' jam ' . $mins . ' menit' : $hours . ' jam';
    }
    return $roundedMinutes . ' menit';
}
$activeTimeFormatted = formatActiveTime($activeSeconds);

// Stats: Jawaban yang lo bantu (hitung per thread, bukan per reply)
$totalReplies = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT fr.thread_id) FROM forum_replies fr
        JOIN forum_threads ft ON fr.thread_id = ft.id
        WHERE fr.user_id = ? AND ft.user_id != ?
    ");
    $stmt->execute([$userId, $userId]);
    $totalReplies = $stmt->fetchColumn();
} catch (Exception $e) {}

// Stats: Gem yang lo dapet
$totalGemsEarned = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ft.gem_reward), 0) 
        FROM forum_replies fr 
        JOIN forum_threads ft ON fr.thread_id = ft.id 
        WHERE fr.user_id = ? AND fr.is_best_answer = 1
    ");
    $stmt->execute([$userId]);
    $totalGemsEarned = $stmt->fetchColumn();
} catch (Exception $e) {}

// Ambil pertanyaan milik sendiri
$myQuestions = [];
try {
    $stmt = $pdo->prepare("
        SELECT ft.*, u.name as author_name, u.avatar as author_avatar, fc.name as category_name,
        (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id) as reply_count
        FROM forum_threads ft 
        JOIN users u ON ft.user_id = u.id 
        JOIN forum_categories fc ON ft.category_id = fc.id 
        WHERE ft.user_id = ?
        ORDER BY ft.created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $myQuestions = $stmt->fetchAll();
} catch (Exception $e) {}

// Ambil pertanyaan dari mahasiswa lain
// Jika ada myQuestions = LIMIT 3, jika tidak ada = LIMIT 1
$recentQuestionsLimit = !empty($myQuestions) ? 3 : 1;
$recentQuestions = [];
try {
    $stmt = $pdo->prepare("
        SELECT ft.*, u.name as author_name, u.avatar as author_avatar, fc.name as category_name,
        (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id) as reply_count
        FROM forum_threads ft 
        JOIN users u ON ft.user_id = u.id 
        JOIN forum_categories fc ON ft.category_id = fc.id 
        WHERE ft.user_id != ?
        ORDER BY ft.created_at DESC 
        LIMIT $recentQuestionsLimit
    ");
    $stmt->execute([$userId]);
    $recentQuestions = $stmt->fetchAll();
} catch (Exception $e) {}

// Ambil mentor populer
$popularMentors = [];
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
} catch (Exception $e) {}

// Helper function
function time_elapsed($datetime) {
    $tz = new DateTimeZone('Asia/Jakarta');
    $now = new DateTime('now', $tz);
    $ago = new DateTime($datetime, $tz);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . ' hari yang lalu';
    if ($diff->h > 0) return $diff->h . ' jam yang lalu';
    if ($diff->i > 0) return $diff->i . ' menit yang lalu';
    return 'Baru saja';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="dashboard-page">
    
    <?php include 'pages/partials/navbar.php'; ?>

    <!-- Main Content -->
    <div class="dash-container">
        <main class="dash-main">
            <!-- Success Message -->
            <?php if ($successMsg): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?php echo $successMsg; ?>
            </div>
            <?php endif; ?>

            <!-- Welcome Hero -->
            <section class="dash-hero">
                <div class="dash-hero-content">
                    <h1>Lagi Kesulitan?</h1>
                    <p>Tulis pertanyaan lo dan tunggu mentor atau mahasiswa lain bantu jawabnya.</p>
                    <a href="<?php echo BASE_PATH; ?>/forum/create" class="btn btn-light">
                        <i class="bi bi-pencil-square"></i>
                        Tanya Sekarang
                    </a>
                </div>
                <div class="dash-hero-stats">
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon blue">
                            <i class="bi bi-chat-heart"></i>
                        </div>
                        <div class="dash-stat-info">
                            <span class="dash-stat-value"><?php echo $totalReplies; ?></span>
                            <span class="dash-stat-label">Jawaban yang lo bantu</span>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon purple">
                            <i class="bi bi-gem"></i>
                        </div>
                        <div class="dash-stat-info">
                            <span class="dash-stat-value"><?php echo $totalGemsEarned; ?></span>
                            <span class="dash-stat-label">Gem yang lo dapet</span>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon green">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="dash-stat-info">
                            <span class="dash-stat-value"><?php echo $activeTimeFormatted; ?></span>
                            <span class="dash-stat-label">Waktu aktif</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Pertanyaan yang Lo Ajukan -->
            <?php if (!empty($myQuestions)): ?>
            <section class="dash-questions">
                <div class="dash-section-header">
                    <h2>Pertanyaan yang Lo Ajukan</h2>
                    <a href="<?php echo BASE_PATH; ?>/forum?filter=my" class="btn btn-text">Lihat Semua</a>
                </div>

                <div class="dash-questions-list">
                    <?php foreach ($myQuestions as $q): ?>
                        <article class="dash-question-card my-question <?php echo $q['is_solved'] ? 'solved' : ''; ?>">
                            <div class="dash-question-header">
                                <div class="dash-question-meta">
                                    <span class="category"><i class="bi bi-folder"></i> <?php echo htmlspecialchars($q['category_name']); ?></span>
                                    <span class="time"><i class="bi bi-clock"></i> <?php echo time_elapsed($q['created_at']); ?></span>
                                    <span class="replies"><i class="bi bi-chat-dots"></i> <?php echo $q['reply_count']; ?> jawaban</span>
                                </div>
                                <div class="dash-question-reward">
                                    <?php if ($q['is_solved']): ?>
                                        <span class="badge-solved"><i class="bi bi-check-circle-fill"></i> Terjawab</span>
                                    <?php else: ?>
                                        <span class="badge-waiting"><i class="bi bi-hourglass-split"></i> Menunggu Jawaban</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="<?php echo BASE_PATH; ?>/forum/thread/<?php echo $q['id']; ?>" class="dash-question-title-link">
                                <h3 class="dash-question-title"><?php echo htmlspecialchars($q['title']); ?></h3>
                            </a>
                            <p class="dash-question-excerpt"><?php echo htmlspecialchars(substr($q['content'], 0, 150)) . '...'; ?></p>
                            <div class="dash-question-footer">
                                <div class="dash-question-author"></div>
                                <a href="<?php echo BASE_PATH; ?>/forum/thread/<?php echo $q['id']; ?>" class="btn btn-outline btn-sm">
                                    <?php echo $q['reply_count'] > 0 ? 'Lihat Jawaban' : 'Lihat Detail'; ?>
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
                    <a href="<?php echo BASE_PATH; ?>/forum" class="dash-quick-item">
                        <i class="bi bi-chat-square-text"></i>
                        <span>Forum Diskusi</span>
                    </a>
                    <a href="<?php echo BASE_PATH; ?>/mentor" class="dash-quick-item">
                        <i class="bi bi-person-video3"></i>
                        <span>Cari Mentor</span>
                    </a>
                    <a href="<?php echo BASE_PATH; ?>/topup" class="dash-quick-item">
                        <i class="bi bi-gem"></i>
                        <span>Top Up Gem</span>
                    </a>
                    <a href="<?php echo BASE_PATH; ?>/chat-history" class="dash-quick-item">
                        <i class="bi bi-chat-left-text"></i>
                        <span>Histori Chat</span>
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
                        foreach ($dummyMentors as $mentor): ?>
                        <div class="dash-mentor-item">
                            <div class="dash-avatar sm"><?php echo strtoupper(substr($mentor['name'], 0, 1)); ?></div>
                            <div class="dash-mentor-info">
                                <span class="name"><?php echo $mentor['name']; ?></span>
                                <span class="expertise"><?php echo $mentor['expertise']; ?></span>
                            </div>
                            <div class="dash-mentor-rating">
                                <i class="bi bi-star-fill"></i> <?php echo $mentor['rating']; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($popularMentors as $mentor): ?>
                        <div class="dash-mentor-item">
                            <div class="dash-avatar sm">
                                <?php if (!empty($mentor['avatar'])): ?>
                                    <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($mentor['avatar']); ?>" alt="Avatar">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="dash-mentor-info">
                                <span class="name"><?php echo htmlspecialchars($mentor['name']); ?></span>
                                <span class="expertise"><?php echo htmlspecialchars($mentor['expertise']); ?></span>
                            </div>
                            <div class="dash-mentor-rating">
                                <i class="bi bi-star-fill"></i> <?php echo number_format($mentor['rating'], 1); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a href="<?php echo BASE_PATH; ?>/mentor" class="btn btn-outline btn-full btn-sm">Lihat Semua Mentor</a>
            </div>
        </aside>
    </div>

    <!-- Pertanyaan dari Mahasiswa Lain - FULL WIDTH (di luar grid) -->
    <?php if (!empty($myQuestions)): ?>
    <div class="dash-full-section">
        <section class="dash-questions">
            <div class="dash-section-header">
                <h2>Pertanyaan dari Mahasiswa Lain</h2>
                <a href="<?php echo BASE_PATH; ?>/forum" class="btn btn-text">Lihat Semua</a>
            </div>

            <div class="dash-questions-list grid-three">
                <?php if (empty($recentQuestions)): ?>
                    <div class="dash-empty-state">
                        <i class="bi bi-chat-square-text"></i>
                        <h3>Belum Ada Pertanyaan</h3>
                        <p>Belum ada pertanyaan dari mahasiswa lain. Cek lagi nanti!</p>
                        <a href="<?php echo BASE_PATH; ?>/forum" class="btn btn-primary">Lihat Forum</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentQuestions as $q): ?>
                        <article class="dash-question-card <?php echo $q['is_solved'] ? 'solved' : ''; ?>">
                            <div class="dash-question-header">
                                <div class="dash-question-meta">
                                    <span class="category"><i class="bi bi-folder"></i> <?php echo htmlspecialchars($q['category_name']); ?></span>
                                    <span class="time"><i class="bi bi-clock"></i> <?php echo time_elapsed($q['created_at']); ?></span>
                                    <span class="replies"><i class="bi bi-chat-dots"></i> <?php echo $q['reply_count']; ?> jawaban</span>
                                </div>
                                <div class="dash-question-reward">
                                    <?php if ($q['is_solved']): ?>
                                        <span class="badge-solved"><i class="bi bi-check-circle-fill"></i> Terjawab</span>
                                    <?php endif; ?>
                                    <span class="gem-reward">+<?php echo $q['gem_reward']; ?> gem</span>
                                </div>
                            </div>
                            <a href="<?php echo BASE_PATH; ?>/forum/thread/<?php echo $q['id']; ?>" class="dash-question-title-link">
                                <h3 class="dash-question-title"><?php echo htmlspecialchars($q['title']); ?></h3>
                            </a>
                            <p class="dash-question-excerpt"><?php echo htmlspecialchars(substr($q['content'], 0, 80)) . '...'; ?></p>
                            <div class="dash-question-footer">
                                <div class="dash-question-author">
                                    <div class="author-avatar">
                                        <?php if (!empty($q['author_avatar'])): ?>
                                            <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($q['author_avatar']); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($q['author_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($q['author_name']); ?></span>
                                </div>
                                <a href="<?php echo BASE_PATH; ?>/forum/thread/<?php echo $q['id']; ?>" class="btn btn-outline btn-sm">
                                    <?php echo $q['is_solved'] ? 'Lihat Jawaban' : 'Jawab'; ?>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <?php else: ?>
    <!-- Jika tidak ada myQuestions, tampilkan di dalam container biasa -->
    <div class="dash-container">
        <main class="dash-main" style="grid-column: 1 / -1;">
            <section class="dash-questions">
                <div class="dash-section-header">
                    <h2>Pertanyaan dari Mahasiswa Lain</h2>
                    <a href="<?php echo BASE_PATH; ?>/forum" class="btn btn-text">Lihat Semua</a>
                </div>

                <div class="dash-questions-list">
                    <?php if (empty($recentQuestions)): ?>
                        <div class="dash-empty-state">
                            <i class="bi bi-chat-square-text"></i>
                            <h3>Belum Ada Pertanyaan</h3>
                            <p>Belum ada pertanyaan dari mahasiswa lain. Cek lagi nanti!</p>
                            <a href="<?php echo BASE_PATH; ?>/forum" class="btn btn-primary">Lihat Forum</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentQuestions as $q): ?>
                            <article class="dash-question-card <?php echo $q['is_solved'] ? 'solved' : ''; ?>">
                                <div class="dash-question-header">
                                    <div class="dash-question-meta">
                                        <span class="category"><i class="bi bi-folder"></i> <?php echo htmlspecialchars($q['category_name']); ?></span>
                                        <span class="time"><i class="bi bi-clock"></i> <?php echo time_elapsed($q['created_at']); ?></span>
                                        <span class="replies"><i class="bi bi-chat-dots"></i> <?php echo $q['reply_count']; ?> jawaban</span>
                                    </div>
                                    <div class="dash-question-reward">
                                        <?php if ($q['is_solved']): ?>
                                            <span class="badge-solved"><i class="bi bi-check-circle-fill"></i> Terjawab</span>
                                        <?php endif; ?>
                                        <span class="gem-reward">+<?php echo $q['gem_reward']; ?> gem</span>
                                    </div>
                                </div>
                                <a href="<?php echo BASE_PATH; ?>/forum/thread/<?php echo $q['id']; ?>" class="dash-question-title-link">
                                    <h3 class="dash-question-title"><?php echo htmlspecialchars($q['title']); ?></h3>
                                </a>
                                <p class="dash-question-excerpt"><?php echo htmlspecialchars(substr($q['content'], 0, 150)) . '...'; ?></p>
                                <div class="dash-question-footer">
                                    <div class="dash-question-author">
                                        <div class="author-avatar">
                                            <?php if (!empty($q['author_avatar'])): ?>
                                                <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($q['author_avatar']); ?>" alt="Avatar">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($q['author_name'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($q['author_name']); ?></span>
                                    </div>
                                    <a href="<?php echo BASE_PATH; ?>/forum/thread/<?php echo $q['id']; ?>" class="btn btn-outline btn-sm">
                                        <?php echo $q['is_solved'] ? 'Lihat Jawaban' : 'Jawab'; ?>
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
