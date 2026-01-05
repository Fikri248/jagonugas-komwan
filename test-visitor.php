<?php
// test-visitor.php - ULTRA MODERN DESIGN + IP FIX
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$BASE = BASE_PATH;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: {$BASE}/login.php");
    exit;
}

function url_path(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return BASE_PATH . ($path === '/' ? '' : $path);
}

try {
    // Total visits
    $stmt = $pdo->query("SELECT COUNT(*) FROM visitor_logs");
    $totalVisits = (int) $stmt->fetchColumn();

    // Today visits
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM visitor_logs 
        WHERE DATE(visited_at) = CURDATE()
    ");
    $todayVisits = (int) $stmt->fetchColumn();

    // Unique visitors today
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT session_id) FROM visitor_logs 
        WHERE DATE(visited_at) = CURDATE()
    ");
    $uniqueToday = (int) $stmt->fetchColumn();

    // Yesterday comparison
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM visitor_logs 
        WHERE DATE(visited_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ");
    $yesterdayVisits = (int) $stmt->fetchColumn();
    $growthPercent = $yesterdayVisits > 0 ? round((($todayVisits - $yesterdayVisits) / $yesterdayVisits) * 100, 1) : 0;

    // Top 10 pages
    $stmt = $pdo->query("
        SELECT 
            page_url, 
            COUNT(*) as visit_count 
        FROM visitor_logs 
        GROUP BY page_url 
        ORDER BY visit_count DESC 
        LIMIT 10
    ");
    $topPages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent visits
    $stmt = $pdo->query("
        SELECT 
            vl.*,
            u.name as user_name,
            u.role as user_role
        FROM visitor_logs vl
        LEFT JOIN users u ON vl.user_id = u.id
        ORDER BY vl.visited_at DESC
        LIMIT 20
    ");
    $recentVisits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hourly distribution (last 24h)
    $stmt = $pdo->query("
        SELECT 
            HOUR(visited_at) as hour, 
            COUNT(*) as count 
        FROM visitor_logs 
        WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY HOUR(visited_at)
        ORDER BY hour
    ");
    $hourlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $trackingStatus = 'active';
    $trackingMessage = 'Tracking Active & Working';
} catch (PDOException $e) {
    $trackingStatus = 'error';
    $trackingMessage = 'Error: ' . $e->getMessage();
    $totalVisits = 0;
    $todayVisits = 0;
    $uniqueToday = 0;
    $growthPercent = 0;
    $topPages = [];
    $recentVisits = [];
    $hourlyData = [];
}

// Handle clear logs
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    try {
        $pdo->exec("TRUNCATE TABLE visitor_logs");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $trackingMessage = 'Error clearing logs: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Visitor Analytics - JagoNugas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: pulse 8s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 48px;
            margin-bottom: 32px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header h1 {
            font-size: 3rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            font-weight: 800;
        }

        .header p {
            color: #64748b;
            font-size: 1.15rem;
            font-weight: 500;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            border-radius: 50px;
            font-weight: 600;
            margin-top: 20px;
            font-size: 0.95rem;
            animation: fadeIn 0.8s ease-out 0.3s both;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .status-badge.active {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #059669;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .status-badge.error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }

        /* Action Buttons */
        .action-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 32px;
            flex-wrap: wrap;
            animation: slideUp 0.6s ease-out 0.2s both;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .btn {
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.95);
            color: #667eea;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-back:hover {
            background: white;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-clear {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-clear:hover {
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }

        .btn-refresh {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-refresh:hover {
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: scaleIn 0.5s ease-out both;
            animation-delay: calc(var(--card-index) * 0.1s);
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .stat-card:nth-child(1) {
            --card-index: 0;
        }

        .stat-card:nth-child(2) {
            --card-index: 1;
        }

        .stat-card:nth-child(3) {
            --card-index: 2;
        }

        .stat-card:nth-child(4) {
            --card-index: 3;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .stat-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .stat-icon-wrapper.blue {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
        }

        .stat-icon-wrapper.green {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #059669;
        }

        .stat-icon-wrapper.yellow {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #d97706;
        }

        .stat-icon-wrapper.purple {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            color: #6366f1;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1;
            margin-top: 8px;
        }

        .stat-growth {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 12px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .stat-growth.up {
            background: #d1fae5;
            color: #059669;
        }

        .stat-growth.down {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Card */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeInUp 0.6s ease-out both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card h2 {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }

        .card h2 i {
            font-size: 1.8rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        thead {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        }

        thead th {
            padding: 16px;
            text-align: left;
            font-weight: 700;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        tbody td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            font-size: 0.95rem;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            transform: scale(1.01);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badge */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .badge.admin {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }

        .badge.mentor {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
        }

        .badge.student {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            color: #5b21b6;
        }

        .badge.guest {
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            color: #64748b;
        }

        /* ✅ NEW: Localhost Badge */
        .badge.localhost {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
        }

        code {
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 0.85rem;
            color: #667eea;
        }

        /* Progress Bar for Top Pages */
        .progress-bar {
            height: 8px;
            background: #f1f5f9;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        .error-box {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 2px solid #fca5a5;
            border-radius: 16px;
            padding: 24px;
            color: #dc2626;
            animation: shake 0.5s ease;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-10px);
            }

            75% {
                transform: translateX(10px);
            }
        }

        .error-box strong {
            display: block;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.4rem;
            margin-bottom: 12px;
            color: #64748b;
        }

        .empty-state p {
            font-size: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .header {
                padding: 32px 24px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .card {
                padding: 20px;
            }

            .action-bar {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            table {
                font-size: 0.85rem;
            }

            thead th,
            tbody td {
                padding: 12px 8px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="bi bi-graph-up-arrow"></i> Visitor Analytics</h1>
            <p>Monitor dan analisis pengunjung website secara real-time</p>
            <div class="status-badge <?php echo $trackingStatus; ?>">
                <i class="bi bi-<?php echo $trackingStatus === 'active' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?>"></i>
                <?php echo htmlspecialchars($trackingMessage); ?>
            </div>
        </div>

        <?php if ($trackingStatus === 'error'): ?>
            <div class="card">
                <div class="error-box">
                    <strong><i class="bi bi-exclamation-triangle-fill"></i> Error Detected</strong>
                    <?php echo htmlspecialchars($trackingMessage); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-bar">
            <a href="<?php echo htmlspecialchars(url_path('admin-dashboard.php')); ?>" class="btn btn-back">
                <i class="bi bi-house-door-fill"></i> Dashboard
            </a>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-refresh">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </a>
            <a href="?action=clear" class="btn btn-clear" onclick="return confirm('⚠️ Yakin ingin menghapus semua log?')">
                <i class="bi bi-trash3-fill"></i> Clear Logs
            </a>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Total Kunjungan</div>
                    <div class="stat-icon-wrapper blue">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($totalVisits); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Kunjungan Hari Ini</div>
                    <div class="stat-icon-wrapper green">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($todayVisits); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Pengunjung Online</div>
                    <div class="stat-icon-wrapper yellow">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($uniqueToday); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Rata Rata Kunjungan</div>
                    <div class="stat-icon-wrapper purple">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $uniqueToday > 0 ? number_format($todayVisits / $uniqueToday, 1) : '0'; ?></div>
            </div>
        </div>

        <!-- Top Pages -->
        <?php if (!empty($topPages)): ?>
            <div class="card" style="animation-delay: 0.3s;">
                <h2><i class="bi bi-bar-chart-fill"></i> Top 10 Most Visited Pages</h2>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Page URL</th>
                            <th style="text-align: center; width: 120px;">Visits</th>
                            <th style="width: 200px;">Popularity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $maxCount = !empty($topPages) ? $topPages[0]['visit_count'] : 1;
                        foreach ($topPages as $index => $page):
                            $percentage = ($page['visit_count'] / $maxCount) * 100;
                        ?>
                            <tr>
                                <td><strong><?php echo $index + 1; ?></strong></td>
                                <td><code><?php echo htmlspecialchars($page['page_url']); ?></code></td>
                                <td style="text-align: center;"><strong style="color: #667eea;"><?php echo number_format($page['visit_count']); ?></strong></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card" style="animation-delay: 0.3s;">
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>No Data Available</h3>
                    <p>Belum ada data pengunjung yang tercatat</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Visits -->
        <?php if (!empty($recentVisits)): ?>
            <div class="card" style="animation-delay: 0.4s;">
                <h2><i class="bi bi-clock-history"></i> Recent Visits (Last 20)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Page</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentVisits as $visit): ?>
                            <tr>
                                <td>
                                    <?php if ($visit['user_id']): ?>
                                        <strong><?php echo htmlspecialchars($visit['user_name']); ?></strong>
                                        <br>
                                        <span class="badge <?php echo htmlspecialchars($visit['user_role']); ?>">
                                            <?php echo ucfirst($visit['user_role']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge guest">Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo htmlspecialchars($visit['page_url']); ?></code></td>
                                <td>
                                    <?php
                                    // ✅ Display IP with localhost badge
                                    $ip = htmlspecialchars($visit['ip_address']);
                                    if ($ip === '127.0.0.1' || $ip === '::1'):
                                    ?>
                                        <span class="badge localhost">
                                            <i class="bi bi-laptop"></i> localhost
                                        </span>
                                    <?php else: ?>
                                        <?php echo $ip; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y, H:i:s', strtotime($visit['visited_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>