<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NotificationHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function url_path(string $path = ''): string
{
    $base = '';
    if (defined('BASE_PATH')) {
        $base = (string) constant('BASE_PATH');
    } elseif (defined('BASEPATH')) {
        $base = (string) constant('BASEPATH');
    }
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

// Cek login & role mentor
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'mentor') {
    header('Location: ' . url_path('mentor-login.php'));
    exit;
}

$mentor_id = (int)$_SESSION['user_id'];

// Koneksi DB
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    die('Database connection failed.');
}

// ===================== DATA MENTOR (NAMA, EMAIL, AVATAR) =====================
$stmt = $pdo->prepare("SELECT name, email, avatar FROM users WHERE id = ? AND role = 'mentor'");
$stmt->execute([$mentor_id]);
$mentorRow = $stmt->fetch(PDO::FETCH_ASSOC);

if ($mentorRow) {
    $name   = $mentorRow['name'];
    $email  = $mentorRow['email'];
    $avatar = $mentorRow['avatar']; // path relatif, misal uploads/avatars/xxx.jpg
} else {
    $name   = $_SESSION['name'] ?? 'Mentor';
    $email  = $_SESSION['email'] ?? '';
    $avatar = null;
}

// Avatar initial (kalau tidak ada foto)
$initial = 'M';
if (is_string($name) && $name !== '') {
    $initial = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($name, 0, 1));
}

// ===================== NOTIFIKASI =====================
$notifHelper      = new NotificationHelper($pdo);
$notifUnreadCount = $notifHelper->getUnreadCount($mentor_id);
$notifications    = $notifHelper->getLatest($mentor_id, 10);

// ===================== STATISTIK DASHBOARD =====================

// 1. Total sesi
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE mentor_id = ?");
$stmt->execute([$mentor_id]);
$totalSesi = (int)$stmt->fetchColumn();

// 2. Pendapatan (sesi completed)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(price), 0) FROM sessions WHERE mentor_id = ? AND status = 'completed'");
$stmt->execute([$mentor_id]);
$totalPendapatan = (int)$stmt->fetchColumn();

// 3. Rating mentor
$stmt = $pdo->prepare("SELECT total_rating, review_count FROM users WHERE id = ?");
$stmt->execute([$mentor_id]);
$mentorData = $stmt->fetch(PDO::FETCH_ASSOC);
if ($mentorData && $mentorData['review_count'] > 0) {
    $rating = round($mentorData['total_rating'] / $mentorData['review_count'], 1);
} else {
    $rating = 0;
}

// 4. Mahasiswa aktif
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM sessions WHERE mentor_id = ?");
$stmt->execute([$mentor_id]);
$siswaAktif = (int)$stmt->fetchColumn();

// 5. Booking terbaru
$stmt = $pdo->prepare("
    SELECT s.*, u.name AS student_name
    FROM sessions s
    JOIN users u ON s.student_id = u.id
    WHERE s.mentor_id = ?
    ORDER BY s.created_at DESC
    LIMIT 3
");
$stmt->execute([$mentor_id]);
$bookingTerbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Review terbaru
$stmt = $pdo->prepare("
    SELECT s.*, u.name AS student_name
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

// Helper status label
function statusLabel($status) {
    $labels = [
        'pending'   => 'Menunggu',
        'ongoing'   => 'Berlangsung',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    return $labels[$status] ?? $status;
}

// Helper waktu relatif
function relativeTime($dateString) {
    $now  = time();
    $time = strtotime($dateString);
    $diff = $now - $time;

    if ($diff < 60) {
        return 'baru saja';
    } elseif ($diff < 3600) {
        $min = floor($diff / 60);
        return $min . ' menit yang lalu';
    } elseif ($diff < 86400) {
        $hr = floor($diff / 3600);
        return $hr . ' jam yang lalu';
    } elseif ($diff < 172800) {
        return 'Kemarin';
    } else {
        return date('d M Y, H:i', $time);
    }
}

// Helper link tujuan notif
function notificationUrl($n) {
    if (!empty($n['related_type']) && !empty($n['related_id'])) {
        switch ($n['related_type']) {
            case 'session':
                return url_path('mentor-sessions.php') . '#session-' . $n['related_id'];
            case 'thread':
                return url_path('thread.php?id=' . $n['related_id']);
            default:
                return '#';
        }
    }
    return '#';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mentor - JagoNugas</title>

    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style-mentor-dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="mentor-dashboard-page">
<header class="mentor-navbar">
    <div class="mentor-navbar-inner">
        <div class="mentor-navbar-left">
            <a href="<?php echo htmlspecialchars(url_path('mentor-dashboard.php')); ?>" class="mentor-logo">
                <div class="mentor-logo-mark">M</div>
                <span class="mentor-logo-text">JagoNugas</span>
                <span class="mentor-badge">Mentor</span>
            </a>
            <nav class="mentor-nav-links">
                <a href="<?php echo htmlspecialchars(url_path('mentor-dashboard.php')); ?>" class="active">Dashboard</a>
                <a href="<?php echo htmlspecialchars(url_path('mentor-sessions.php')); ?>">Booking Saya</a>
                <a href="<?php echo htmlspecialchars(url_path('mentor-chat.php')); ?>">Chat</a>
            </nav>
        </div>

        <div class="mentor-navbar-right">
            <!-- NOTIFICATION BELL -->
            <div class="mentor-notif-wrapper">
                <button class="mentor-notif-btn" type="button">
                    <i class="bi bi-bell"></i>
                    <?php if ($notifUnreadCount > 0): ?>
                        <span class="notif-badge"><?php echo $notifUnreadCount; ?></span>
                    <?php endif; ?>
                </button>

                <div class="mentor-notif-dropdown">
                    <div class="notif-header">
                        <h4>Notifikasi</h4>
                        <?php if ($notifUnreadCount > 0): ?>
                            <form method="POST" action="<?php echo BASE_PATH; ?>/notifications-mark-read.php">
                                <button class="btn-mark-read" type="submit" title="Tandai semua dibaca">
                                    <i class="bi bi-check2-all"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="notif-list">
                        <?php if (empty($notifications)): ?>
                            <p class="notif-empty">Belum ada notifikasi.</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                                <?php
                                    $iconClass  = 'bi-bell';
                                    $extraClass = '';
                                    switch ($n['type']) {
                                        case 'booking_created':
                                        case 'booking_accepted':
                                        case 'booking_rejected':
                                        case 'booking_completed':
                                            $iconClass  = 'bi-calendar-check';
                                            $extraClass = 'booking';
                                            break;
                                        case 'gem_received':
                                        case 'gem_bonus':
                                            $iconClass  = 'bi-gem';
                                            $extraClass = 'gem';
                                            break;
                                        case 'new_reply':
                                        case 'reply_received':
                                            $iconClass  = 'bi-chat-dots';
                                            $extraClass = 'chat';
                                            break;
                                    }
                                ?>
                                <a href="<?php echo htmlspecialchars(notificationUrl($n)); ?>"
                                   class="notif-item <?php echo !$n['is_read'] ? 'unread' : ''; ?>">
                                    <div class="notif-icon <?php echo $extraClass; ?>">
                                        <i class="bi <?php echo $iconClass; ?>"></i>
                                    </div>
                                    <div class="notif-content">
                                        <p><strong><?php echo htmlspecialchars($n['title']); ?></strong></p>
                                        <p class="notif-message"><?php echo htmlspecialchars($n['message']); ?></p>
                                        <span class="notif-time"><?php echo relativeTime($n['created_at']); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- USER DROPDOWN -->
            <div class="mentor-user-menu">
                <div class="mentor-avatar">
                    <?php if (!empty($avatar)): ?>
                        <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($avatar); ?>"
                             alt="Avatar"
                             style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <?php echo htmlspecialchars($initial); ?>
                    <?php endif; ?>
                </div>
                <div class="mentor-user-info">
                    <span class="mentor-user-name"><?php echo htmlspecialchars($name); ?></span>
                    <span class="mentor-user-role">Mentor</span>
                </div>
                <i class="bi bi-chevron-down"></i>
                <div class="mentor-dropdown">
                    <a href="<?php echo htmlspecialchars(url_path('mentor-profile.php')); ?>"><i class="bi bi-person"></i> Profil Saya</a>
                    <a href="<?php echo htmlspecialchars(url_path('mentor-settings.php')); ?>"><i class="bi bi-gear"></i> Pengaturan</a>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo htmlspecialchars(url_path('logout.php')); ?>" class="logout"><i class="bi bi-box-arrow-right"></i> Keluar</a>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="mentor-main">
    <section class="mentor-welcome">
        <div class="welcome-content">
            <h1>Halo, <?php echo htmlspecialchars($name); ?>!</h1>
            <p>Siap membantu mahasiswa hari ini?</p>
        </div>
        <div class="welcome-action">
            <a href="<?php echo htmlspecialchars(url_path('mentor-availability.php')); ?>" class="btn btn-mentor-outline">
                <i class="bi bi-calendar-check"></i>
                Atur Jadwal
            </a>
        </div>
    </section>

    <!-- Stats -->
    <section class="mentor-stats-grid">
        <div class="mentor-stat-card">
            <div class="mentor-stat-icon blue">
                <i class="bi bi-journal-check"></i>
            </div>
            <div class="mentor-stat-info">
                <span class="mentor-stat-value"><?php echo $totalSesi; ?></span>
                <span class="mentor-stat-label">Total Sesi</span>
            </div>
        </div>

        <div class="mentor-stat-card">
            <div class="mentor-stat-icon green">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="mentor-stat-info">
                <span class="mentor-stat-value">Gems <?php echo number_format($totalPendapatan, 0, ',', '.'); ?></span>
                <span class="mentor-stat-label">Pendapatan</span>
            </div>
        </div>

        <div class="mentor-stat-card">
            <div class="mentor-stat-icon yellow">
                <i class="bi bi-star-fill"></i>
            </div>
            <div class="mentor-stat-info">
                <span class="mentor-stat-value"><?php echo $rating > 0 ? number_format($rating, 1) : '0.0'; ?></span>
                <span class="mentor-stat-label">Rating</span>
            </div>
        </div>

        <div class="mentor-stat-card">
            <div class="mentor-stat-icon purple">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="mentor-stat-info">
                <span class="mentor-stat-value"><?php echo $siswaAktif; ?></span>
                <span class="mentor-stat-label">Siswa Aktif</span>
            </div>
        </div>
    </section>

    <!-- Content -->
    <div class="mentor-content-grid">
        <!-- Booking terbaru -->
        <section class="mentor-section">
            <div class="mentor-section-header">
                <h2><i class="bi bi-calendar3"></i> Booking Terbaru</h2>
                <a href="<?php echo htmlspecialchars(url_path('mentor-sessions.php')); ?>" class="section-link">Lihat Semua</a>
            </div>

            <div class="booking-list">
                <?php if (empty($bookingTerbaru)): ?>
                    <p class="empty-text">Belum ada booking.</p>
                <?php else: ?>
                    <?php foreach ($bookingTerbaru as $booking): ?>
                        <div class="booking-item <?php echo $booking['status']; ?>">
                            <div class="booking-info">
                                <span class="booking-name"><?php echo htmlspecialchars($booking['student_name']); ?></span>
                                <span class="booking-topic">
                                    <?php echo htmlspecialchars($booking['notes'] ?: 'Konsultasi ' . $booking['duration'] . ' menit'); ?>
                                </span>
                                <span class="booking-time"><?php echo relativeTime($booking['created_at']); ?></span>
                            </div>

                            <?php if ($booking['status'] === 'pending'): ?>
                                <div class="booking-actions">
                                    <form method="POST" action="<?php echo BASE_PATH; ?>/mentor-session-action.php">
                                        <input type="hidden" name="session_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button class="btn-sm btn-accept" type="submit">Terima</button>
                                    </form>
                                    <form method="POST" action="<?php echo BASE_PATH; ?>/mentor-session-action.php">
                                        <input type="hidden" name="session_id" value="<?php echo $booking['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn-sm btn-reject" type="submit">Tolak</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="booking-status <?php echo $booking['status']; ?>">
                                    <?php echo statusLabel($booking['status']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Review terbaru -->
        <section class="mentor-section">
            <div class="mentor-section-header">
                <h2><i class="bi bi-chat-quote"></i> Review Terbaru</h2>
            </div>

            <div class="review-list">
                <?php if (empty($reviewTerbaru)): ?>
                    <p class="empty-text">Belum ada review.</p>
                <?php else: ?>
                    <?php foreach ($reviewTerbaru as $rev): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <span class="review-author"><?php echo htmlspecialchars($rev['student_name']); ?></span>
                                <span class="review-rating">
                                    <i class="bi bi-star-fill"></i>
                                    <?php echo number_format($rev['rating'], 1); ?>
                                </span>
                            </div>
                            <p class="review-text">
                                "<?php echo htmlspecialchars($rev['review'] ?: 'Tidak ada komentar.'); ?>"
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
</body>
</html>
