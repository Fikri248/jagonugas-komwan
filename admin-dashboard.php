<?php
require_once __DIR__ . '/config.php';

// Pastikan session aktif (kalau config.php belum start session)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper URL: pakai BASE_PATH (baru), fallback ke BASEPATH (lama).
 */
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

// Cek login & role admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . url_path('login.php'));
    exit;
}

$name = $_SESSION['name'] ?? 'Admin';

// Dummy stats (nanti bisa diambil dari DB)
$totalUsers = 1250;
$totalMentors = 48;
$totalTransaksi = 856;
$pendapatanBulan = 12500000;

// Avatar initial aman untuk UTF-8
$initial = 'A';
if (is_string($name) && $name !== '') {
    $initial = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($name, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JagoNugas</title>

    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="admin-dashboard-page">
    <!-- Navbar Admin -->
    <header class="admin-navbar">
        <div class="admin-navbar-inner">
            <div class="admin-navbar-left">
                <a href="<?php echo htmlspecialchars(url_path('admin-dashboard.php')); ?>" class="admin-logo">
                    <div class="admin-logo-mark">A</div>
                    <span class="admin-logo-text">JagoNugas</span>
                    <span class="admin-badge">Admin</span>
                </a>

                <nav class="admin-nav-links">
                    <a href="<?php echo htmlspecialchars(url_path('admin-dashboard.php')); ?>" class="active">Dashboard</a>
                    <a href="<?php echo htmlspecialchars(url_path('admin-users.php')); ?>">Users</a>
                    <a href="<?php echo htmlspecialchars(url_path('admin-mentors.php')); ?>">Mentors</a>
                    <a href="<?php echo htmlspecialchars(url_path('admin-transactions.php')); ?>">Transaksi</a>
                    <a href="<?php echo htmlspecialchars(url_path('admin-settings.php')); ?>">Settings</a>
                </nav>
            </div>

            <div class="admin-navbar-right">
                <div class="admin-user-menu">
                    <div class="admin-avatar"><?php echo htmlspecialchars($initial); ?></div>
                    <div class="admin-user-info">
                        <span class="admin-user-name"><?php echo htmlspecialchars($name); ?></span>
                        <span class="admin-user-role">Administrator</span>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                    <div class="admin-dropdown">
                        <a href="<?php echo htmlspecialchars(url_path('admin-profile.php')); ?>"><i class="bi bi-person"></i> Profil</a>
                        <a href="<?php echo htmlspecialchars(url_path('admin-settings.php')); ?>"><i class="bi bi-gear"></i> Pengaturan</a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo htmlspecialchars(url_path('logout.php')); ?>" class="logout"><i class="bi bi-box-arrow-right"></i> Keluar</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="admin-main">
        <!-- Welcome Section -->
        <section class="admin-welcome">
            <div class="welcome-content">
                <h1>Admin Dashboard <i class="bi bi-shield-check"></i></h1>
                <p>Kelola seluruh sistem JagoNugas dari sini</p>
            </div>
            <div class="welcome-action">
                <a href="<?php echo htmlspecialchars(url_path('admin-reports.php')); ?>" class="btn btn-admin-outline">
                    <i class="bi bi-file-earmark-text"></i>
                    Lihat Laporan
                </a>
            </div>
        </section>

        <!-- Stats Grid -->
        <section class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-icon blue">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value"><?php echo number_format($totalUsers); ?></span>
                    <span class="admin-stat-label">Total Users</span>
                </div>
                <span class="admin-stat-trend up"><i class="bi bi-arrow-up"></i> 12%</span>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-icon purple">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value"><?php echo (int) $totalMentors; ?></span>
                    <span class="admin-stat-label">Total Mentors</span>
                </div>
                <span class="admin-stat-trend up"><i class="bi bi-arrow-up"></i> 5 baru</span>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-icon green">
                    <i class="bi bi-credit-card-fill"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value"><?php echo number_format($totalTransaksi); ?></span>
                    <span class="admin-stat-label">Transaksi</span>
                </div>
                <span class="admin-stat-trend up"><i class="bi bi-arrow-up"></i> 8%</span>
            </div>

            <div class="admin-stat-card">
                <div class="admin-stat-icon yellow">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value">Rp <?php echo number_format($pendapatanBulan / 1000000, 1); ?>jt</span>
                    <span class="admin-stat-label">Pendapatan Bulan Ini</span>
                </div>
                <span class="admin-stat-trend up"><i class="bi bi-arrow-up"></i> 15%</span>
            </div>
        </section>

        <!-- Content Grid -->
        <div class="admin-content-grid">
            <!-- User Terbaru -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <h2><i class="bi bi-person-fill"></i> User Terbaru</h2>
                    <a href="<?php echo htmlspecialchars(url_path('admin-users.php')); ?>" class="section-link">Lihat Semua</a>
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
            <section class="admin-section">
                <div class="admin-section-header">
                    <h2><i class="bi bi-credit-card"></i> Transaksi Terbaru</h2>
                    <a href="<?php echo htmlspecialchars(url_path('admin-transactions.php')); ?>" class="section-link">Lihat Semua</a>
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

            <!-- Quick Actions -->
            <section class="admin-section admin-quick-actions">
                <div class="admin-section-header">
                    <h2><i class="bi bi-lightning-fill"></i> Aksi Cepat</h2>
                </div>
                <div class="admin-action-grid">
                    <a href="<?php echo htmlspecialchars(url_path('admin-users.php')); ?>" class="admin-action-card">
                        <div class="action-icon blue"><i class="bi bi-people-fill"></i></div>
                        <span>Kelola Users</span>
                    </a>
                    <a href="<?php echo htmlspecialchars(url_path('admin-mentors.php')); ?>" class="admin-action-card">
                        <div class="action-icon purple"><i class="bi bi-mortarboard-fill"></i></div>
                        <span>Kelola Mentors</span>
                    </a>
                    <a href="<?php echo htmlspecialchars(url_path('admin-transactions.php')); ?>" class="admin-action-card">
                        <div class="action-icon green"><i class="bi bi-credit-card-fill"></i></div>
                        <span>Transaksi</span>
                    </a>
                    <a href="<?php echo htmlspecialchars(url_path('admin-forum.php')); ?>" class="admin-action-card">
                        <div class="action-icon cyan"><i class="bi bi-chat-square-text-fill"></i></div>
                        <span>Forum</span>
                    </a>
                    <a href="<?php echo htmlspecialchars(url_path('admin-packages.php')); ?>" class="admin-action-card">
                        <div class="action-icon yellow"><i class="bi bi-box-fill"></i></div>
                        <span>Paket Gem</span>
                    </a>
                    <a href="<?php echo htmlspecialchars(url_path('admin-settings.php')); ?>" class="admin-action-card">
                        <div class="action-icon gray"><i class="bi bi-gear-fill"></i></div>
                        <span>Settings</span>
                    </a>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
