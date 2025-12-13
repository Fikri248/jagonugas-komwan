<?php
// pages/mentor/dashboard.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

// Cek login & role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mentor') {
    header("Location: " . BASE_PATH . "/mentor/login");
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
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">
</head>
<body class="dashboard-page">
    <!-- Navbar Mentor -->
    <header class="dash-navbar dash-navbar-mentor">
        <div class="dash-container">
            <div class="dash-nav-inner">
                <div class="dash-logo">
                    <div class="dash-logo-mark mentor">M</div>
                    <span class="dash-logo-text">JagoNugas <span class="role-badge mentor">Mentor</span></span>
                </div>
                
                <div class="dash-nav-right">
                    <nav class="dash-nav-links">
                        <a href="<?php echo BASE_PATH; ?>/mentor/dashboard" class="active">Dashboard</a>
                        <a href="<?php echo BASE_PATH; ?>/mentor/bookings">Booking Saya</a>
                        <a href="<?php echo BASE_PATH; ?>/mentor/chat">Chat</a>
                    </nav>
                    
                    <div class="dash-user-menu">
                        <div class="dash-avatar mentor"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                        <div class="dash-user-info">
                            <span class="dash-user-name"><?php echo htmlspecialchars($name); ?></span>
                            <span class="dash-user-role">Mentor</span>
                        </div>
                        <div class="dash-dropdown">
                            <a href="<?php echo BASE_PATH; ?>/mentor/profile">Profil Saya</a>
                            <a href="<?php echo BASE_PATH; ?>/mentor/settings">Pengaturan</a>
                            <a href="<?php echo BASE_PATH; ?>/logout" class="logout">Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="dash-container dash-main">
        <!-- Welcome Section -->
        <section class="dash-welcome mentor">
            <div class="welcome-content">
                <h1>Halo, <?php echo htmlspecialchars($name); ?>! 👋</h1>
                <p>Siap membantu mahasiswa hari ini?</p>
            </div>
            <div class="welcome-action">
                <a href="<?php echo BASE_PATH; ?>/mentor/availability" class="btn btn-mentor">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Atur Jadwal
                </a>
            </div>
        </section>

        <!-- Stats Grid -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon mentor">📚</div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo $totalSesi; ?></span>
                    <span class="stat-label">Total Sesi</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon money">💰</div>
                <div class="stat-info">
                    <span class="stat-value">Rp <?php echo number_format($totalPendapatan, 0, ',', '.'); ?></span>
                    <span class="stat-label">Pendapatan</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon rating">⭐</div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo $rating; ?></span>
                    <span class="stat-label">Rating</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon students">👥</div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo $siswaAktif; ?></span>
                    <span class="stat-label">Siswa Aktif</span>
                </div>
            </div>
        </section>

        <!-- Content Grid -->
        <div class="dash-content-grid">
            <!-- Booking Terbaru -->
            <section class="dash-section">
                <div class="section-header">
                    <h2>📅 Booking Terbaru</h2>
                    <a href="<?php echo BASE_PATH; ?>/mentor/bookings" class="section-link">Lihat Semua</a>
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
            <section class="dash-section">
                <div class="section-header">
                    <h2>💬 Review Terbaru</h2>
                </div>
                <div class="review-list">
                    <div class="review-item">
                        <div class="review-header">
                            <span class="review-author">Ahmad Rizky</span>
                            <span class="review-rating">⭐ 5.0</span>
                        </div>
                        <p class="review-text">"Penjelasannya sangat jelas dan sabar banget. Recommended!"</p>
                    </div>
                    <div class="review-item">
                        <div class="review-header">
                            <span class="review-author">Dewi Kartika</span>
                            <span class="review-rating">⭐ 4.5</span>
                        </div>
                        <p class="review-text">"Membantu banget buat tugas praktikum. Makasih kak!"</p>
                    </div>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
