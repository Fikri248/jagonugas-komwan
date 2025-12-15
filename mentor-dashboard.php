<?php
require_once __DIR__ . '/config.php';

// Cek login & role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mentor') {
    header("Location: " . BASE_PATH . "/mentor-login.php");
    exit;
}

$name = $_SESSION['name'] ?? 'Mentor';
$email = $_SESSION['email'] ?? '';

// Dummy stats (nanti bisa diambil dari DB)
$totalSesi = 24;
$totalPendapatan = 180000;
$rating = 4.8;
$siswaAktif = 12;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mentor - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="mentor-dashboard-page">
    <!-- Navbar Mentor -->
    <header class="mentor-navbar">
        <div class="mentor-navbar-inner">
            <div class="mentor-navbar-left">
                <a href="<?php echo BASE_PATH; ?>/mentor-dashboard.php" class="mentor-logo">
                    <div class="mentor-logo-mark">M</div>
                    <span class="mentor-logo-text">JagoNugas</span>
                    <span class="mentor-badge">Mentor</span>
                </a>
                <nav class="mentor-nav-links">
                    <a href="<?php echo BASE_PATH; ?>/mentor-dashboard.php" class="active">Dashboard</a>
                    <a href="<?php echo BASE_PATH; ?>/mentor-bookings.php">Booking Saya</a>
                    <a href="<?php echo BASE_PATH; ?>/mentor-chat.php">Chat</a>
                </nav>
            </div>
            
            <div class="mentor-navbar-right">
                <!-- Notification Bell -->
                <div class="mentor-notif-wrapper">
                    <button class="mentor-notif-btn">
                        <i class="bi bi-bell"></i>
                        <span class="notif-badge">3</span>
                    </button>
                    <div class="mentor-notif-dropdown">
                        <div class="notif-header">
                            <h4>Notifikasi</h4>
                            <button class="btn-mark-read" title="Tandai semua dibaca">
                                <i class="bi bi-check2-all"></i>
                            </button>
                        </div>
                        <div class="notif-list">
                            <a href="#" class="notif-item unread">
                                <div class="notif-icon booking">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <div class="notif-content">
                                    <p><strong>Ahmad Rizky</strong> mengajukan booking baru</p>
                                    <span class="notif-time">5 menit yang lalu</span>
                                </div>
                            </a>
                            <a href="#" class="notif-item unread">
                                <div class="notif-icon review">
                                    <i class="bi bi-star"></i>
                                </div>
                                <div class="notif-content">
                                    <p><strong>Dewi Kartika</strong> memberikan review bintang 5</p>
                                    <span class="notif-time">1 jam yang lalu</span>
                                </div>
                            </a>
                            <a href="#" class="notif-item">
                                <div class="notif-icon chat">
                                    <i class="bi bi-chat-dots"></i>
                                </div>
                                <div class="notif-content">
                                    <p><strong>Budi Santoso</strong> mengirim pesan baru</p>
                                    <span class="notif-time">2 jam yang lalu</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- User Menu -->
                <div class="mentor-user-menu">
                    <div class="mentor-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                    <div class="mentor-user-info">
                        <span class="mentor-user-name"><?php echo htmlspecialchars($name); ?></span>
                        <span class="mentor-user-role">Mentor</span>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                    <div class="mentor-dropdown">
                        <a href="<?php echo BASE_PATH; ?>/mentor-profile.php"><i class="bi bi-person"></i> Profil Saya</a>
                        <a href="<?php echo BASE_PATH; ?>/mentor-settings.php"><i class="bi bi-gear"></i> Pengaturan</a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo BASE_PATH; ?>/logout.php" class="logout"><i class="bi bi-box-arrow-right"></i> Keluar</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="mentor-main">
        <!-- Welcome Section -->
        <section class="mentor-welcome">
            <div class="welcome-content">
                <h1>Halo, <?php echo htmlspecialchars($name); ?>! 👋</h1>
                <p>Siap membantu mahasiswa hari ini?</p>
            </div>
            <div class="welcome-action">
                <a href="<?php echo BASE_PATH; ?>/mentor-availability.php" class="btn btn-mentor-outline">
                    <i class="bi bi-calendar-check"></i>
                    Atur Jadwal
                </a>
            </div>
        </section>

        <!-- Stats Grid -->
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
                    <span class="mentor-stat-value">Rp <?php echo number_format($totalPendapatan, 0, ',', '.'); ?></span>
                    <span class="mentor-stat-label">Pendapatan</span>
                </div>
            </div>
            <div class="mentor-stat-card">
                <div class="mentor-stat-icon yellow">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div class="mentor-stat-info">
                    <span class="mentor-stat-value"><?php echo $rating; ?></span>
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

        <!-- Content Grid -->
        <div class="mentor-content-grid">
            <!-- Booking Terbaru -->
            <section class="mentor-section">
                <div class="mentor-section-header">
                    <h2><i class="bi bi-calendar3"></i> Booking Terbaru</h2>
                    <a href="<?php echo BASE_PATH; ?>/mentor-bookings.php" class="section-link">Lihat Semua</a>
                </div>
                <div class="booking-list">
                    <div class="booking-item pending">
                        <div class="booking-info">
                            <span class="booking-name">Ahmad Rizky</span>
                            <span class="booking-topic">Pemrograman Web - Tugas Besar</span>
                            <span class="booking-time">Hari ini, 14:00 - 15:30</span>
                        </div>
                        <div class="booking-actions">
                            <button class="btn-sm btn-accept">Terima</button>
                            <button class="btn-sm btn-reject">Tolak</button>
                        </div>
                    </div>
                    <div class="booking-item confirmed">
                        <div class="booking-info">
                            <span class="booking-name">Siti Nurhaliza</span>
                            <span class="booking-topic">Database - Praktikum</span>
                            <span class="booking-time">Besok, 10:00 - 10:30</span>
                        </div>
                        <span class="booking-status confirmed">Dikonfirmasi</span>
                    </div>
                    <div class="booking-item completed">
                        <div class="booking-info">
                            <span class="booking-name">Budi Santoso</span>
                            <span class="booking-topic">Algoritma - Tugas Biasa</span>
                            <span class="booking-time">Kemarin, 16:00</span>
                        </div>
                        <span class="booking-status completed">Selesai</span>
                    </div>
                </div>
            </section>

            <!-- Review Terbaru -->
            <section class="mentor-section">
                <div class="mentor-section-header">
                    <h2><i class="bi bi-chat-quote"></i> Review Terbaru</h2>
                </div>
                <div class="review-list">
                    <div class="review-item">
                        <div class="review-header">
                            <span class="review-author">Ahmad Rizky</span>
                            <span class="review-rating"><i class="bi bi-star-fill"></i> 5.0</span>
                        </div>
                        <p class="review-text">"Penjelasannya sangat jelas dan sabar banget. Recommended!"</p>
                    </div>
                    <div class="review-item">
                        <div class="review-header">
                            <span class="review-author">Dewi Kartika</span>
                            <span class="review-rating"><i class="bi bi-star-fill"></i> 4.5</span>
                        </div>
                        <p class="review-text">"Membantu banget buat tugas praktikum. Makasih kak!"</p>
                    </div>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
