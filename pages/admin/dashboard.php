<?php
// pages/admin/dashboard.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

// Cek login & role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_PATH . "/login");
    exit;
}

$name = $_SESSION['name'] ?? 'Admin';

// Dummy stats (nanti bisa diambil dari DB)
$totalUsers = 1250;
$totalMentors = 48;
$totalTransaksi = 856;
$pendapatanBulan = 12500000;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JagoNugas</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/style.css">
</head>
<body class="dashboard-page">
    <!-- Navbar Admin -->
    <header class="dash-navbar dash-navbar-admin">
        <div class="dash-container">
            <div class="dash-nav-inner">
                <div class="dash-logo">
                    <div class="dash-logo-mark admin">A</div>
                    <span class="dash-logo-text">JagoNugas <span class="role-badge admin">Admin</span></span>
                </div>
                
                <div class="dash-nav-right">
                    <nav class="dash-nav-links">
                        <a href="<?php echo BASE_PATH; ?>/admin/dashboard" class="active">Dashboard</a>
                        <a href="<?php echo BASE_PATH; ?>/admin/users">Users</a>
                        <a href="<?php echo BASE_PATH; ?>/admin/mentors">Mentors</a>
                        <a href="<?php echo BASE_PATH; ?>/admin/transactions">Transaksi</a>
                        <a href="<?php echo BASE_PATH; ?>/admin/settings">Settings</a>
                    </nav>
                    
                    <div class="dash-user-menu">
                        <div class="dash-avatar admin"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                        <div class="dash-user-info">
                            <span class="dash-user-name"><?php echo htmlspecialchars($name); ?></span>
                            <span class="dash-user-role">Administrator</span>
                        </div>
                        <div class="dash-dropdown">
                            <a href="<?php echo BASE_PATH; ?>/admin/profile">Profil</a>
                            <a href="<?php echo BASE_PATH; ?>/logout" class="logout">Keluar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="dash-container dash-main">
        <!-- Welcome Section -->
        <section class="dash-welcome admin">
            <div class="welcome-content">
                <h1>Admin Dashboard 🛡️</h1>
                <p>Kelola seluruh sistem JagoNugas dari sini</p>
            </div>
            <div class="welcome-action">
                <a href="<?php echo BASE_PATH; ?>/admin/reports" class="btn btn-admin">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    Lihat Laporan
                </a>
            </div>
        </section>

        <!-- Stats Grid -->
        <section class="stats-grid stats-grid-4">
            <div class="stat-card">
                <div class="stat-icon users">👥</div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo number_format($totalUsers); ?></span>
                    <span class="stat-label">Total Users</span>
                </div>
                <span class="stat-trend up">+12% ↑</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon mentors">🎓</div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo $totalMentors; ?></span>
                    <span class="stat-label">Total Mentors</span>
                </div>
                <span class="stat-trend up">+5 baru ↑</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon transactions">💳</div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo number_format($totalTransaksi); ?></span>
                    <span class="stat-label">Transaksi</span>
                </div>
                <span class="stat-trend up">+8% ↑</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon revenue">💰</div>
                <div class="stat-info">
                    <span class="stat-value">Rp <?php echo number_format($pendapatanBulan / 1000000, 1); ?>jt</span>
                    <span class="stat-label">Pendapatan Bulan Ini</span>
                </div>
                <span class="stat-trend up">+15% ↑</span>
            </div>
        </section>

        <!-- Content Grid -->
        <div class="dash-content-grid">
            <!-- User Terbaru -->
            <section class="dash-section">
                <div class="section-header">
                    <h2>👤 User Terbaru</h2>
                    <a href="<?php echo BASE_PATH; ?>/admin/users" class="section-link">Lihat Semua</a>
                </div>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Ahmad Rizky</td>
                                <td>ahmad@student.telu.ac.id</td>
                                <td><span class="badge-role student">Student</span></td>
                                <td>13 Des 2025</td>
                            </tr>
                            <tr>
                                <td>Siti Nurhaliza</td>
                                <td>siti@student.telu.ac.id</td>
                                <td><span class="badge-role student">Student</span></td>
                                <td>13 Des 2025</td>
                            </tr>
                            <tr>
                                <td>Budi Santoso</td>
                                <td>budi@telu.ac.id</td>
                                <td><span class="badge-role mentor">Mentor</span></td>
                                <td>12 Des 2025</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Transaksi Terbaru -->
            <section class="dash-section">
                <div class="section-header">
                    <h2>💳 Transaksi Terbaru</h2>
                    <a href="<?php echo BASE_PATH; ?>/admin/transactions" class="section-link">Lihat Semua</a>
                </div>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Paket</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>#TRX-001</td>
                                <td>Ahmad Rizky</td>
                                <td>Pro (Rp 25.000)</td>
                                <td><span class="badge-status success">Sukses</span></td>
                            </tr>
                            <tr>
                                <td>#TRX-002</td>
                                <td>Dewi Kartika</td>
                                <td>Basic (Rp 10.000)</td>
                                <td><span class="badge-status success">Sukses</span></td>
                            </tr>
                            <tr>
                                <td>#TRX-003</td>
                                <td>Rudi Hermawan</td>
                                <td>Plus (Rp 50.000)</td>
                                <td><span class="badge-status pending">Pending</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Quick Actions -->
        <section class="quick-actions">
            <h2>⚡ Aksi Cepat</h2>
            <div class="action-grid">
                <a href="<?php echo BASE_PATH; ?>/admin/users" class="action-card">
                    <span class="action-icon">👥</span>
                    <span class="action-label">Kelola Users</span>
                </a>
                <a href="<?php echo BASE_PATH; ?>/admin/mentors" class="action-card">
                    <span class="action-icon">🎓</span>
                    <span class="action-label">Kelola Mentors</span>
                </a>
                <a href="<?php echo BASE_PATH; ?>/admin/transactions" class="action-card">
                    <span class="action-icon">💳</span>
                    <span class="action-label">Kelola Transaksi</span>
                </a>
                <a href="<?php echo BASE_PATH; ?>/admin/forum" class="action-card">
                    <span class="action-icon">💬</span>
                    <span class="action-label">Moderasi Forum</span>
                </a>
                <a href="<?php echo BASE_PATH; ?>/admin/packages" class="action-card">
                    <span class="action-icon">📦</span>
                    <span class="action-label">Atur Paket Gem</span>
                </a>
                <a href="<?php echo BASE_PATH; ?>/admin/settings" class="action-card">
                    <span class="action-icon">⚙️</span>
                    <span class="action-label">Pengaturan</span>
                </a>
            </div>
        </section>
    </main>
</body>
</html>
