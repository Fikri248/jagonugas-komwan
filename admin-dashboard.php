<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ TRACK VISITOR - Auto tracking
if (file_exists(__DIR__ . '/track-visitor.php')) {
    require_once __DIR__ . '/track-visitor.php';
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

// ========================================
// REAL DATA FROM DATABASE
// ========================================

try {
    // ✅ Total Students (simple count)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $totalUsers = (int) $stmt->fetchColumn();
    
    // ✅ Total Mentors (simple count)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor'");
    $totalMentors = (int) $stmt->fetchColumn();
    
    // ✅ Total Transaksi SUCCESS (settlement only)
    $stmt = $pdo->query("SELECT COUNT(*) FROM gem_transactions WHERE transaction_status = 'settlement'");
    $totalTransaksi = (int) $stmt->fetchColumn();
    
    // ✅ Pendapatan Bulan Ini (settlement only, current month)
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) 
        FROM gem_transactions 
        WHERE transaction_status = 'settlement' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $pendapatanBulan = (int) $stmt->fetchColumn();
    
    // ✅ Visitor Stats (last 30 days) - FIXED: Ganti created_at jadi visited_at
    $visitorData = [];
    $visitorDates = [];
    $visitorCounts = [];
    
    // Check if visitor_logs table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'visitor_logs'")->fetchColumn();
    
    if ($tableCheck) {
        // ✅ FIX: Ganti 'created_at' jadi 'visited_at'
        $stmt = $pdo->query("
            SELECT 
                DATE(visited_at) as visit_date, 
                COUNT(DISTINCT session_id) as visitors
            FROM visitor_logs
            WHERE visited_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
            GROUP BY DATE(visited_at)
            ORDER BY visit_date ASC
        ");
        $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create array for all 30 days (fill empty dates with 0)
        $dataMap = [];
        foreach ($rawData as $row) {
            $dataMap[$row['visit_date']] = (int)$row['visitors'];
        }
        
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $visitorDates[] = date('d M', strtotime($date));
            $visitorCounts[] = $dataMap[$date] ?? 0;
        }
        
        $visitorData = $rawData;
    } else {
        // Table doesn't exist yet, fill with zeros
        for ($i = 29; $i >= 0; $i--) {
            $visitorDates[] = date('d M', strtotime("-$i days"));
            $visitorCounts[] = 0;
        }
    }
    
    // ✅ Latest Users (5 newest)
    $stmt = $pdo->query("
        SELECT id, name, email, role, created_at
        FROM users
        WHERE role IN ('student', 'mentor')
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $latestUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ Latest Transactions (5 newest)
    $stmt = $pdo->query("
        SELECT 
            gt.id,
            gt.order_id,
            u.name as user_name,
            gt.package,
            gt.amount,
            gt.transaction_status,
            gt.created_at
        FROM gem_transactions gt
        LEFT JOIN users u ON gt.user_id = u.id
        ORDER BY gt.created_at DESC
        LIMIT 5
    ");
    $latestTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ Calculate trends (compare with last week)
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM users 
        WHERE role = 'student' 
        AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
    ");
    $newUsersWeek = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM users 
        WHERE role = 'student' 
        AND created_at BETWEEN DATE_SUB(CURRENT_DATE(), INTERVAL 14 DAY) 
        AND DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
    ");
    $prevUsersWeek = (int) $stmt->fetchColumn();
    
    $userTrend = $prevUsersWeek > 0 
        ? round((($newUsersWeek - $prevUsersWeek) / $prevUsersWeek) * 100, 1)
        : ($newUsersWeek > 0 ? 100 : 0);
    
    // ✅ Additional stats
    $stmt = $pdo->query("SELECT COUNT(*) FROM sessions WHERE status = 'completed'");
    $totalSessions = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM forum_threads");
    $totalThreads = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT user_id) 
        FROM memberships 
        WHERE status = 'active' AND end_date >= NOW()
    ");
    $activeMembers = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mentor' AND is_approved = 0");
    $pendingMentors = (int) $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    
    // Set default values on error
    $totalUsers = 0;
    $totalMentors = 0;
    $totalTransaksi = 0;
    $pendapatanBulan = 0;
    $visitorData = [];
    $visitorDates = [];
    $visitorCounts = [];
    $latestUsers = [];
    $latestTransactions = [];
    $userTrend = 0;
    $totalSessions = 0;
    $totalThreads = 0;
    $activeMembers = 0;
    $pendingMentors = 0;
}

// Avatar initial aman untuk UTF-8
$initial = 'A';
if (is_string($name) && $name !== '') {
    $initial = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($name, 0, 1));
}

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatDate($date) {
    return date('d M Y', strtotime($date));
}

function getPackageLabel($package) {
    $labels = [
        'basic' => 'Basic',
        'pro' => 'Pro',
        'plus' => 'Plus'
    ];
    return $labels[$package] ?? ucfirst($package);
}

function getStatusBadge($status) {
    $badges = [
        'settlement' => 'success',
        'pending' => 'pending',
        'expire' => 'danger',
        'cancel' => 'danger'
    ];
    return $badges[$status] ?? 'secondary';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="admin-dashboard-page">
    
    <?php include __DIR__ . '/admin-navbar.php'; ?>
    
    <main class="admin-main">
        <!-- Welcome Section -->
        <section class="admin-welcome">
            <div class="welcome-content">
                <h1>Admin Dashboard <i class="bi bi-shield-check"></i></h1>
                <p>Kelola seluruh sistem JagoNugas dari sini</p>
            </div>
            <div class="welcome-action">
                <?php if ($pendingMentors > 0): ?>
                <a href="<?php echo htmlspecialchars(url_path('admin-mentors.php?filter=pending')); ?>" class="btn btn-admin-warning" style="margin-right: 10px;">
                    <i class="bi bi-hourglass-split"></i>
                    <?php echo $pendingMentors; ?> Mentor Pending
                </a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars(url_path('test-visitor.php')); ?>" class="btn btn-admin-outline">
                    <i class="bi bi-graph-up"></i>
                    Test Analytics
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
                    <span class="admin-stat-label">Total Students</span>
                </div>
                <?php if ($userTrend != 0): ?>
                <span class="admin-stat-trend <?php echo $userTrend > 0 ? 'up' : 'down'; ?>">
                    <i class="bi bi-arrow-<?php echo $userTrend > 0 ? 'up' : 'down'; ?>"></i> 
                    <?php echo abs($userTrend); ?>%
                </span>
                <?php endif; ?>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon purple">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value"><?php echo number_format($totalMentors); ?></span>
                    <span class="admin-stat-label">Total Mentors</span>
                </div>
                <?php if ($pendingMentors > 0): ?>
                <span class="admin-stat-trend pending">
                    <i class="bi bi-hourglass-split"></i> 
                    <?php echo $pendingMentors; ?> pending
                </span>
                <?php endif; ?>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon green">
                    <i class="bi bi-credit-card-fill"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value"><?php echo number_format($totalTransaksi); ?></span>
                    <span class="admin-stat-label">Transaksi</span>
                </div>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon yellow">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value">
                        <?php echo $pendapatanBulan >= 1000000 
                            ? 'Rp ' . number_format($pendapatanBulan / 1000000, 1) . 'jt'
                            : formatRupiah($pendapatanBulan); ?>
                    </span>
                    <span class="admin-stat-label">Pendapatan Bulan Ini</span>
                </div>
            </div>
        </section>

        <!-- Additional Stats Row -->
        <section class="admin-stats-grid" style="margin-top: 16px;">
            <div class="admin-stat-card">
                <div class="admin-stat-icon cyan">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value"><?php echo number_format($totalSessions); ?></span>
                    <span class="admin-stat-label">Sesi Selesai</span>
                </div>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon pink">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value"><?php echo number_format($activeMembers); ?></span>
                    <span class="admin-stat-label">Member Aktif</span>
                </div>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon indigo">
                    <i class="bi bi-chat-square-text-fill"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value"><?php echo number_format($totalThreads); ?></span>
                    <span class="admin-stat-label">Forum Threads</span>
                </div>
            </div>
            
            <div class="admin-stat-card">
                <div class="admin-stat-icon orange">
                    <i class="bi bi-eye-fill"></i>
                </div>
                <div class="admin-stat-info">
                    <span class="admin-stat-value"><?php echo number_format(array_sum($visitorCounts)); ?></span>
                    <span class="admin-stat-label">Total Visitors (30d)</span>
                </div>
            </div>
        </section>
        
        <!-- Visitor Graph -->
        <section class="admin-section" style="margin: 32px 0;">
            <div class="admin-section-header">
                <h2><i class="bi bi-graph-up"></i> Grafik Pengunjung (30 Hari Terakhir)</h2>
                <span class="section-info">
                    <?php 
                    $totalVisits = array_sum($visitorCounts);
                    $avgVisits = $totalVisits > 0 ? round($totalVisits / 30, 1) : 0;
                    echo "Total: " . number_format($totalVisits) . " | Avg: " . $avgVisits . "/hari";
                    ?>
                </span>
            </div>
            <div class="admin-chart-container" style="padding: 24px; background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">
                <?php if (empty($visitorData) && array_sum($visitorCounts) == 0): ?>
                <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
                    <i class="bi bi-info-circle" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
                    <h3 style="font-size: 1.2rem; margin-bottom: 8px; color: #64748b;">Belum Ada Data Pengunjung</h3>
                    <p style="margin-bottom: 16px;">Pastikan tracking visitor sudah aktif di semua halaman.</p>
                    <a href="<?php echo htmlspecialchars(url_path('test-visitor.php')); ?>" 
                       style="display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 10px; font-weight: 600;">
                        <i class="bi bi-play-fill"></i> Test Visitor Tracking
                    </a>
                </div>
                <?php else: ?>
                <canvas id="visitorChart" style="max-height: 400px;"></canvas>
                <?php endif; ?>
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
                    <?php if (empty($latestUsers)): ?>
                        <div style="padding: 40px; text-align: center; color: #94a3b8;">
                            <i class="bi bi-inbox" style="font-size: 2rem; display: block; margin-bottom: 8px;"></i>
                            Belum ada user terdaftar
                        </div>
                    <?php else: ?>
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
                            <?php foreach ($latestUsers as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge-role <?php echo htmlspecialchars($user['role']); ?>">
                                        <i class="bi bi-<?php echo $user['role'] === 'student' ? 'mortarboard' : 'award'; ?>-fill"></i>
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Transaksi Terbaru -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <h2><i class="bi bi-credit-card"></i> Transaksi Terbaru</h2>
                    <a href="<?php echo htmlspecialchars(url_path('admin-transactions.php')); ?>" class="section-link">Lihat Semua</a>
                </div>
                <div class="admin-table-wrapper">
                    <?php if (empty($latestTransactions)): ?>
                        <div style="padding: 40px; text-align: center; color: #94a3b8;">
                            <i class="bi bi-inbox" style="font-size: 2rem; display: block; margin-bottom: 8px;"></i>
                            Belum ada transaksi
                        </div>
                    <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Paket</th>
                                <th>Nominal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestTransactions as $trx): ?>
                            <tr>
                                <td><code>#<?php echo htmlspecialchars(substr($trx['order_id'], -6)); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($trx['user_name'] ?? 'User Deleted'); ?></strong></td>
                                <td>
                                    <span class="badge-package <?php echo $trx['package']; ?>">
                                        <?php echo getPackageLabel($trx['package']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatRupiah($trx['amount']); ?></td>
                                <td>
                                    <span class="badge-status <?php echo getStatusBadge($trx['transaction_status']); ?>">
                                        <?php echo ucfirst($trx['transaction_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Quick Actions -->
            <section class="admin-section admin-quick-actions" style="grid-column: 1 / -1;">
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
                        <?php if ($pendingMentors > 0): ?>
                        <span class="badge-notification"><?php echo $pendingMentors; ?></span>
                        <?php endif; ?>
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
                    <a href="<?php echo htmlspecialchars(url_path('test-visitor.php')); ?>" class="admin-action-card">
                        <div class="action-icon orange"><i class="bi bi-graph-up"></i></div>
                        <span>Analytics</span>
                    </a>
                </div>
            </section>
        </div>
    </main>
    
    <script>
    const ctx = document.getElementById('visitorChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($visitorDates); ?>,
                datasets: [{
                    label: 'Pengunjung Unik',
                    data: <?php echo json_encode($visitorCounts); ?>,
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: 'rgb(102, 126, 234)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { 
                        display: true, 
                        position: 'top',
                        labels: {
                            font: { size: 13, weight: '600' },
                            padding: 16
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            precision: 0,
                            font: { size: 12 }
                        },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 12 }
                        }
                    }
                }
            }
        });
    }

    // Close dropdown on outside click
    document.querySelector('.admin-user-menu')?.addEventListener('click', function() {
        this.classList.toggle('active');
    });
    
    document.addEventListener('click', function(e) {
        const menu = document.querySelector('.admin-user-menu');
        if (menu && !menu.contains(e.target)) {
            menu.classList.remove('active');
        }
    });
    </script>
</body>
</html>
