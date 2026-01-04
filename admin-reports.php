<?php
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

// Date filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Revenue Statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(amount) as total_revenue,
        AVG(amount) as avg_transaction,
        SUM(gems) as total_gems_sold
    FROM gem_transactions 
    WHERE transaction_status = 'settlement'
    AND DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$revenue_stats = $stmt->fetch();

// Revenue by Package
$stmt = $pdo->prepare("
    SELECT 
        package,
        COUNT(*) as count,
        SUM(amount) as revenue,
        SUM(gems) as gems_sold
    FROM gem_transactions 
    WHERE transaction_status = 'settlement'
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY package
    ORDER BY revenue DESC
");
$stmt->execute([$start_date, $end_date]);
$package_stats = $stmt->fetchAll();

// Daily Revenue Trend (Last 30 days)
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as transactions,
        SUM(amount) as revenue
    FROM gem_transactions 
    WHERE transaction_status = 'settlement'
    AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute();
$daily_revenue = $stmt->fetchAll();

// User Growth Statistics
$stmt = $pdo->prepare("
    SELECT 
        role,
        COUNT(*) as count
    FROM users
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY role
");
$stmt->execute([$start_date, $end_date]);
$user_growth = $stmt->fetchAll();

// Top Mentors by Sessions
$stmt = $pdo->prepare("
    SELECT 
        u.name,
        u.email,
        COUNT(DISTINCT s.id) as total_sessions,
        AVG(s.rating) as avg_rating
    FROM users u
    LEFT JOIN sessions s ON u.id = s.mentor_id AND s.status = 'completed'
    WHERE u.role = 'mentor' AND u.is_approved = 1
    AND DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_sessions DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_mentors = $stmt->fetchAll();

// Payment Methods Distribution
$stmt = $pdo->prepare("
    SELECT 
        payment_type,
        COUNT(*) as count,
        SUM(amount) as total
    FROM gem_transactions 
    WHERE transaction_status = 'settlement'
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_type
");
$stmt->execute([$start_date, $end_date]);
$payment_methods = $stmt->fetchAll();

// Prepare data for charts
$daily_dates = array_column($daily_revenue, 'date');
$daily_amounts = array_column($daily_revenue, 'revenue');
$package_names = array_column($package_stats, 'package');
$package_revenues = array_column($package_stats, 'revenue');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - JagoNugas Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 0;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #718096;
            margin: 0;
        }

        .filter-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .filter-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-filter {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-export {
            padding: 0.75rem 1.5rem;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }

        .stat-card.green {
            border-left-color: #10b981;
        }

        .stat-card.orange {
            border-left-color: #f59e0b;
        }

        .stat-card.purple {
            border-left-color: #8b5cf6;
        }

        .stat-card h4 {
            font-size: 0.85rem;
            color: #718096;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .stat-card .change {
            font-size: 0.85rem;
            color: #10b981;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .chart-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .chart-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
        }

        .chart-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .chart-body {
            padding: 2rem;
        }

        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-custom thead th {
            background: #f8f9fa;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: #4a5568;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-custom tbody td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #1a202c;
        }

        .table-custom tbody tr:hover {
            background: #f8f9fa;
        }

        .badge-custom {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-basic {
            background: #6c757d;
            color: white;
        }

        .badge-pro {
            background: #667eea;
            color: white;
        }

        .badge-plus {
            background: #ffc107;
            color: #1a202c;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .filter-form {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin-navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="bi bi-graph-up-arrow"></i> Reports & Analytics</h1>
            <p>Monitor performa bisnis dan aktivitas platform</p>
        </div>

        <!-- Filter -->
        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label><i class="bi bi-calendar-event"></i> Tanggal Mulai</label>
                    <input type="date" name="start_date" class="filter-input" value="<?= htmlspecialchars($start_date) ?>">
                </div>

                <div class="filter-group">
                    <label><i class="bi bi-calendar-check"></i> Tanggal Akhir</label>
                    <input type="date" name="end_date" class="filter-input" value="<?= htmlspecialchars($end_date) ?>">
                </div>

                <button type="submit" class="btn-filter">
                    <i class="bi bi-funnel"></i>
                    <span>Filter</span>
                </button>

                <button type="button" class="btn-export" onclick="exportReport()">
                    <i class="bi bi-download"></i>
                    <span>Export</span>
                </button>
            </form>
        </div>

        <!-- Revenue Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4><i class="bi bi-currency-dollar"></i> Total Revenue</h4>
                <div class="value">Rp <?= number_format($revenue_stats['total_revenue'] ?? 0, 0, ',', '.') ?></div>
                <div class="change">
                    <i class="bi bi-arrow-up-circle-fill"></i>
                    <span><?= date('d M', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></span>
                </div>
            </div>

            <div class="stat-card green">
                <h4><i class="bi bi-receipt"></i> Total Transaksi</h4>
                <div class="value"><?= number_format($revenue_stats['total_transactions'] ?? 0) ?></div>
                <div class="change">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Transactions completed</span>
                </div>
            </div>

            <div class="stat-card orange">
                <h4><i class="bi bi-gem"></i> Gems Terjual</h4>
                <div class="value"><?= number_format($revenue_stats['total_gems_sold'] ?? 0) ?></div>
                <div class="change">
                    <i class="bi bi-star-fill"></i>
                    <span>Total gems sold</span>
                </div>
            </div>

            <div class="stat-card purple">
                <h4><i class="bi bi-calculator"></i> Rata-rata Transaksi</h4>
                <div class="value">Rp <?= number_format($revenue_stats['avg_transaction'] ?? 0, 0, ',', '.') ?></div>
                <div class="change">
                    <i class="bi bi-graph-up"></i>
                    <span>Average per transaction</span>
                </div>
            </div>
        </div>

        <!-- Daily Revenue Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="bi bi-graph-up"></i> Revenue Trend (30 Hari Terakhir)</h3>
            </div>
            <div class="chart-body">
                <canvas id="revenueChart" height="80"></canvas>
            </div>
        </div>

        <!-- Package Performance & Payment Methods -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="bi bi-pie-chart"></i> Revenue by Package</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="packageChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="bi bi-credit-card"></i> Payment Methods</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="paymentChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Package Details Table -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="bi bi-table"></i> Detail Revenue per Package</h3>
            </div>
            <div class="chart-body">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Paket</th>
                            <th>Jumlah Transaksi</th>
                            <th>Total Revenue</th>
                            <th>Gems Terjual</th>
                            <th>Avg per Transaksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($package_stats)): ?>
                            <?php foreach ($package_stats as $pkg): ?>
                            <tr>
                                <td>
                                    <span class="badge-custom badge-<?= strtolower($pkg['package']) ?>">
                                        <?= ucfirst($pkg['package']) ?>
                                    </span>
                                </td>
                                <td><strong><?= number_format($pkg['count']) ?></strong> transaksi</td>
                                <td><strong>Rp <?= number_format($pkg['revenue'], 0, ',', '.') ?></strong></td>
                                <td><i class="bi bi-gem text-warning"></i> <?= number_format($pkg['gems_sold']) ?></td>
                                <td>Rp <?= number_format($pkg['revenue'] / $pkg['count'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #718096; padding: 2rem;">
                                    <i class="bi bi-inbox" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                                    Tidak ada data untuk periode ini
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Mentors -->
        <?php if (!empty($top_mentors)): ?>
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="bi bi-trophy"></i> Top 10 Mentor Aktif</h3>
            </div>
            <div class="chart-body">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Nama Mentor</th>
                            <th>Email</th>
                            <th>Total Sesi</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($top_mentors as $mentor): ?>
                        <tr>
                            <td>
                                <strong style="font-size: 1.2rem; color: <?= $rank <= 3 ? '#fbbf24' : '#718096' ?>;">
                                    <?php if ($rank === 1): ?>
                                        <i class="bi bi-trophy-fill"></i>
                                    <?php elseif ($rank === 2): ?>
                                        <i class="bi bi-award-fill"></i>
                                    <?php elseif ($rank === 3): ?>
                                        <i class="bi bi-star-fill"></i>
                                    <?php else: ?>
                                        <?= $rank ?>
                                    <?php endif; ?>
                                </strong>
                            </td>
                            <td><strong><?= htmlspecialchars($mentor['name']) ?></strong></td>
                            <td><?= htmlspecialchars($mentor['email']) ?></td>
                            <td><strong><?= number_format($mentor['total_sessions']) ?></strong> sesi</td>
                            <td>
                                <span style="color: #fbbf24;">
                                    <i class="bi bi-star-fill"></i>
                                    <?= number_format($mentor['avg_rating'] ?? 0, 1) ?>
                                </span>
                            </td>
                        </tr>
                        <?php $rank++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function($d) { return date('d M', strtotime($d)); }, $daily_dates)) ?>,
                datasets: [{
                    label: 'Revenue (Rp)',
                    data: <?= json_encode($daily_amounts) ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // Package Chart
        const packageCtx = document.getElementById('packageChart').getContext('2d');
        new Chart(packageCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map('ucfirst', $package_names)) ?>,
                datasets: [{
                    data: <?= json_encode($package_revenues) ?>,
                    backgroundColor: ['#6c757d', '#667eea', '#ffc107'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Payment Methods Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($payment_methods, 'payment_type')) ?>,
                datasets: [{
                    label: 'Total (Rp)',
                    data: <?= json_encode(array_column($payment_methods, 'total')) ?>,
                    backgroundColor: ['#667eea', '#10b981', '#f59e0b', '#ef4444'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // Export Report
        function exportReport() {
            const startDate = '<?= $start_date ?>';
            const endDate = '<?= $end_date ?>';
            alert('Export report dari ' + startDate + ' sampai ' + endDate + '\n\nFeature akan segera hadir!');
        }
    </script>
</body>
</html>
